<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Server-Enforced Application Lifecycle & State Machine (Phase 7)
 *
 * Strictly enforces:
 * - ATTENDEE: DRAFT -> SUBMITTED -> CONFIRMED; SUBMITTED/CONFIRMED -> CANCELLED
 * - VOLUNTEER / SLI:
 *     DRAFT -> SUBMITTED -> UNDER_REVIEW -> {APPROVED | REJECTED | WAITLISTED | ADDITIONAL_INFO_REQUIRED}
 *     ADDITIONAL_INFO_REQUIRED -> RESUBMITTED -> UNDER_REVIEW
 *     WAITLISTED -> APPROVED | REJECTED
 *     SUBMITTED / UNDER_REVIEW / APPROVED / WAITLISTED / ADDITIONAL_INFO_REQUIRED / RESUBMITTED -> WITHDRAWN | CANCELLED
 * - Immutable `registration_status_history` audit trail on every transition
 * - Phase 4 transactional email + `in_app_notifications` enqueue (never leaking internal admin notes)
 * - Strict decoupling: NEVER modifies `attendance_records` and NEVER issues `qr_credentials`
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notification-service.php';
if (file_exists(__DIR__ . '/auth.php')) {
    require_once __DIR__ . '/auth.php';
}

if (!class_exists('PfgInvalidTransitionException')) {
    class PfgInvalidTransitionException extends InvalidArgumentException
    {
    }
}

if (!function_exists('pfgResolveLifecycleActorType')) {
    function pfgResolveLifecycleActorType(array $actor): string
    {
        if (!empty($actor['actorType']) && is_string($actor['actorType'])) {
            $t = strtoupper(trim($actor['actorType']));
            if (in_array($t, ['PARTICIPANT', 'ADMIN', 'SUPERADMIN', 'SYSTEM'], true)) {
                return $t;
            }
        }
        $roles = isset($actor['roles']) && is_array($actor['roles']) ? $actor['roles'] : [];
        if (in_array('SUPERADMIN', $roles, true)) {
            return 'SUPERADMIN';
        }
        if (in_array('ADMIN', $roles, true)) {
            return 'ADMIN';
        }
        if (empty($actor['id'])) {
            return 'SYSTEM';
        }
        return 'PARTICIPANT';
    }
}

if (!function_exists('pfgGetAllowedStatusTransitions')) {
    /**
     * Returns the canonical list of valid target statuses for a given category, current status, and actor type.
     *
     * @param string $categoryCode ATTENDEE | VOLUNTEER | SLI (or legacy category code)
     * @param string $currentStatus Current registration status
     * @param string $actorType PARTICIPANT | ADMIN | SUPERADMIN | SYSTEM
     * @return string[]
     */
    function pfgGetAllowedStatusTransitions(string $categoryCode, string $currentStatus, string $actorType = 'ADMIN'): array
    {
        $cat = strtoupper(trim($categoryCode));
        $cur = strtoupper(trim($currentStatus));
        if ($cur === 'PENDING') {
            $cur = 'SUBMITTED';
        }
        $actor = strtoupper(trim($actorType));

        $isAdmin = in_array($actor, ['ADMIN', 'SUPERADMIN'], true);
        $isSystem = ($actor === 'SYSTEM');
        $isParticipant = ($actor === 'PARTICIPANT');

        // Review-gated categories: VOLUNTEER and SLI
        // Unified authoritative state machine (HIGH-02):
        // - ADMIN/SUPERADMIN may fast-track from SUBMITTED or RESUBMITTED directly to a decision
        //   (an implicit UNDER_REVIEW history record is inserted cleanly).
        // - WITHDRAWN is reserved for PARTICIPANT self-service; ADMIN uses CANCELLED.
        if (in_array($cat, ['VOLUNTEER', 'SLI'], true)) {
            $decisions = ['APPROVED', 'REJECTED', 'WAITLISTED', 'ADDITIONAL_INFO_REQUIRED'];
            return match ($cur) {
                'DRAFT' => $isAdmin
                    ? ['SUBMITTED', 'CANCELLED']
                    : ['SUBMITTED', 'WITHDRAWN'],
                'SUBMITTED' => $isAdmin
                    ? array_merge(['UNDER_REVIEW'], $decisions, ['CANCELLED'])
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : ['UNDER_REVIEW']),
                'UNDER_REVIEW' => $isAdmin
                    ? array_merge($decisions, ['CANCELLED'])
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : []),
                'ADDITIONAL_INFO_REQUIRED' => $isParticipant
                    ? ['RESUBMITTED', 'WITHDRAWN', 'CANCELLED']
                    : ($isAdmin ? ['UNDER_REVIEW', 'CANCELLED'] : []),
                'RESUBMITTED' => $isAdmin
                    ? array_merge(['UNDER_REVIEW'], $decisions, ['CANCELLED'])
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : []),
                'WAITLISTED' => $isAdmin
                    ? ['APPROVED', 'REJECTED', 'CANCELLED']
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : []),
                'APPROVED' => $isAdmin
                    ? ['CANCELLED']
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : []),
                // Historical rows confirmed under legacy single-step flow
                'CONFIRMED' => $isAdmin
                    ? ['CANCELLED']
                    : ($isParticipant ? ['WITHDRAWN', 'CANCELLED'] : []),
                default => [],
            };
        }

        // Instant-confirmation category: ATTENDEE (and non-approval legacy categories)
        return match ($cur) {
            'DRAFT' => ($isAdmin || $isSystem)
                ? ['SUBMITTED', 'CONFIRMED', 'CANCELLED']
                : ['SUBMITTED', 'CANCELLED'],
            'SUBMITTED' => ($isAdmin || $isSystem)
                ? ['CONFIRMED', 'CANCELLED']
                : ['CANCELLED'],
            'CONFIRMED' => ['CANCELLED'],
            default     => [],
        };
    }
}

if (!function_exists('pfgRecordRegistrationStatusHistory')) {
    /**
     * Appends an immutable lifecycle event row to `registration_status_history`.
     */
    function pfgRecordRegistrationStatusHistory(
        PDO $pdo,
        string $registrationId,
        ?string $previousStatus,
        string $newStatus,
        ?string $actorUserId,
        ?string $actorRole,
        ?string $reason = null,
        ?string $publicMessage = null
    ): string {
        $id = uuid4();
        $stmt = $pdo->prepare(
            'INSERT INTO registration_status_history (
                id, registration_id, previous_status, new_status, actor_user_id, actor_role, reason, public_message, created_at
             ) VALUES (
                :id, :rid, :prev, :next, :actor_id, :actor_role, :reason, :pub_msg, :now
             )'
        );
        $stmt->execute([
            ':id'         => $id,
            ':rid'        => $registrationId,
            ':prev'       => $previousStatus !== null && trim($previousStatus) !== '' ? strtoupper(trim($previousStatus)) : null,
            ':next'       => strtoupper(trim($newStatus)),
            ':actor_id'   => $actorUserId !== null && trim($actorUserId) !== '' ? trim($actorUserId) : null,
            ':actor_role' => $actorRole !== null && trim($actorRole) !== '' ? strtoupper(trim($actorRole)) : null,
            ':reason'     => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ':pub_msg'    => $publicMessage !== null && trim($publicMessage) !== '' ? trim($publicMessage) : null,
            ':now'        => nowSql(),
        ]);
        return $id;
    }
}

if (!function_exists('pfgGetTransitionNotificationSpec')) {
    /**
     * Resolves the Phase 4 notification template and safe participant-facing copy for a status transition.
     * Internal admin `reason` is NEVER included in email variables.
     */
    function pfgGetTransitionNotificationSpec(
        array $registration,
        string $targetStatus,
        ?string $publicMessage = null
    ): ?array {
        $cat = strtoupper(trim((string)($registration['category_code'] ?? $registration['categoryCode'] ?? 'ATTENDEE')));
        $regCode = (string)($registration['registration_code'] ?? $registration['registrationCode'] ?? '');
        $fullName = (string)($registration['full_name'] ?? $registration['fullName'] ?? 'Participant');
        $catName = (string)($registration['category_name'] ?? $registration['categoryName'] ?? match ($cat) {
            'VOLUNTEER' => 'Festival Volunteer',
            'SLI'       => 'Sign Language Interpreter (SLI)',
            default     => 'General Attendee',
        });
        $safePub = $publicMessage !== null ? trim($publicMessage) : '';

        if ($cat === 'VOLUNTEER') {
            return match ($targetStatus) {
                'APPROVED' => [
                    'templateCode' => 'VOLUNTEER_APPLICATION_APPROVED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'status'           => 'APPROVED',
                        'dutyDetails'      => $safePub !== '' ? $safePub : 'Your venue, shift, and reporting instructions will be sent to you via email.',
                    ],
                    'inAppTitle'   => "Volunteer Application Approved ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Congratulations! Your Volunteer application ({$regCode}) has been approved.",
                ],
                'REJECTED' => [
                    'templateCode' => 'VOLUNTEER_APPLICATION_REJECTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'publicGuidance'   => $safePub,
                    ],
                    'inAppTitle'   => "Volunteer Application Decision ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "An update has been posted on your Volunteer application ({$regCode}).",
                ],
                'WAITLISTED' => [
                    'templateCode' => 'VOLUNTEER_APPLICATION_WAITLISTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'publicGuidance'   => $safePub,
                    ],
                    'inAppTitle'   => "Volunteer Application Waitlisted ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Your Volunteer application ({$regCode}) has been placed on our waitlist.",
                ],
                'ADDITIONAL_INFO_REQUIRED' => [
                    'templateCode' => 'VOLUNTEER_INFO_REQUESTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'requestedDetails' => $safePub !== '' ? $safePub : 'Please reply to this email or write to purplefestivalgujarat@gmail.com quoting your Registration ID to provide the requested clarification.',
                    ],
                    'inAppTitle'   => "Additional Information Needed ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Coordinators requested additional information for your Volunteer application ({$regCode}).",
                ],
                'WITHDRAWN', 'CANCELLED' => [
                    'templateCode' => 'ATTENDEE_REGISTRATION_CANCELLED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'reasonSummary'    => $safePub !== '' ? $safePub : ($targetStatus === 'WITHDRAWN' ? 'You withdrew your volunteer application.' : 'This application has been cancelled.'),
                    ],
                    'inAppTitle'   => ($targetStatus === 'WITHDRAWN' ? 'Application Withdrawn' : 'Application Cancelled') . " ({$regCode})",
                    'inAppMessage' => "Your {$catName} application ({$regCode}) is now marked {$targetStatus}.",
                ],
                default => null,
            };
        }

        if ($cat === 'SLI') {
            return match ($targetStatus) {
                'APPROVED' => [
                    'templateCode' => 'SLI_APPLICATION_APPROVED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'status'           => 'APPROVED',
                        'dutyDetails'      => $safePub !== '' ? $safePub : 'Your assigned venue, interpretation sessions, and reporting instructions will be sent to you via email.',
                    ],
                    'inAppTitle'   => "SLI Application Approved ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Congratulations! Your Sign Language Interpreter application ({$regCode}) has been approved.",
                ],
                'REJECTED' => [
                    'templateCode' => 'SLI_APPLICATION_REJECTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'publicGuidance'   => $safePub,
                    ],
                    'inAppTitle'   => "SLI Application Decision ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "An update has been posted on your SLI application ({$regCode}).",
                ],
                'WAITLISTED' => [
                    'templateCode' => 'SLI_APPLICATION_WAITLISTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'publicGuidance'   => $safePub,
                    ],
                    'inAppTitle'   => "SLI Application Waitlisted ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Your SLI application ({$regCode}) has been placed on our interpreter waitlist.",
                ],
                'ADDITIONAL_INFO_REQUIRED' => [
                    'templateCode' => 'SLI_INFO_REQUESTED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'requestedDetails' => $safePub !== '' ? $safePub : 'Please reply to this email or write to purplefestivalgujarat@gmail.com quoting your Registration ID to provide the requested clarification.',
                    ],
                    'inAppTitle'   => "Additional Information Needed ({$regCode})",
                    'inAppMessage' => $safePub !== '' ? $safePub : "Coordinators requested additional information for your SLI application ({$regCode}).",
                ],
                'WITHDRAWN', 'CANCELLED' => [
                    'templateCode' => 'ATTENDEE_REGISTRATION_CANCELLED',
                    'variables'    => [
                        'fullName'         => $fullName,
                        'registrationCode' => $regCode,
                        'categoryName'     => $catName,
                        'reasonSummary'    => $safePub !== '' ? $safePub : ($targetStatus === 'WITHDRAWN' ? 'You withdrew your SLI application.' : 'This application has been cancelled.'),
                    ],
                    'inAppTitle'   => ($targetStatus === 'WITHDRAWN' ? 'Application Withdrawn' : 'Application Cancelled') . " ({$regCode})",
                    'inAppMessage' => "Your {$catName} application ({$regCode}) is now marked {$targetStatus}.",
                ],
                default => null,
            };
        }

        // ATTENDEE / other categories
        return match ($targetStatus) {
            'CONFIRMED' => [
                'templateCode' => 'ATTENDEE_REGISTRATION_CONFIRMED',
                'variables'    => [
                    'fullName'         => $fullName,
                    'registrationCode' => $regCode,
                    'categoryName'     => $catName,
                    'status'           => 'CONFIRMED',
                ],
                'inAppTitle'   => "Registration Confirmed ({$regCode})",
                'inAppMessage' => "Your {$catName} registration ({$regCode}) is confirmed.",
            ],
            'CANCELLED', 'WITHDRAWN' => [
                'templateCode' => 'ATTENDEE_REGISTRATION_CANCELLED',
                'variables'    => [
                    'fullName'         => $fullName,
                    'registrationCode' => $regCode,
                    'categoryName'     => $catName,
                    'reasonSummary'    => $safePub !== '' ? $safePub : 'Your registration has been cancelled.',
                ],
                'inAppTitle'   => "Registration Cancelled ({$regCode})",
                'inAppMessage' => "Your {$catName} registration ({$regCode}) has been cancelled.",
            ],
            default => null,
        };
    }
}

if (!function_exists('pfgTransitionRegistrationStatus')) {
    /**
     * Atomically transitions a registration through the server-enforced state machine.
     *
     * Guarantees:
     * - Rejects invalid transitions by throwing `PfgInvalidTransitionException` (HTTP 400).
     * - Writes immutable `registration_status_history` row + `audit_logs`.
     * - Enqueues matching Phase 4 transactional email + `in_app_notifications` inside the same transaction.
     * - NEVER mutates `attendance_records` and NEVER creates `qr_credentials`.
     * - Flags admin audit when an already-`ASSIGNED` participant withdraws/cancels (never silently deletes duty rosters).
     *
     * @throws PfgInvalidTransitionException on invalid transition (code 400)
     */
    function pfgTransitionRegistrationStatus(
        PDO $pdo,
        array $registration,
        string $targetStatus,
        array $actor,
        ?string $reason = null,
        ?string $publicMessage = null
    ): array {
        $regId = (string)($registration['id'] ?? '');
        $regCode = (string)($registration['registration_code'] ?? '');
        $categoryCode = strtoupper(trim((string)($registration['category_code'] ?? 'ATTENDEE')));
        $currentStatus = strtoupper(trim((string)($registration['status'] ?? 'SUBMITTED')));
        if ($currentStatus === 'PENDING') {
            $currentStatus = 'SUBMITTED';
        }
        $nextStatus = strtoupper(trim($targetStatus));
        $actorType = pfgResolveLifecycleActorType($actor);
        $actorUserId = !empty($actor['id']) ? (string)$actor['id'] : null;

        if ($regId === '') {
            throw new PfgInvalidTransitionException('Registration record is missing an identifier.', 400);
        }

        if ($currentStatus === $nextStatus) {
            throw new PfgInvalidTransitionException("Registration {$regCode} is already in status {$nextStatus}.", 400);
        }

        $allowed = pfgGetAllowedStatusTransitions($categoryCode, $currentStatus, $actorType);
        if (!in_array($nextStatus, $allowed, true)) {
            $allowedStr = empty($allowed) ? 'none (terminal state)' : implode(', ', $allowed);
            throw new PfgInvalidTransitionException(
                "Invalid status transition for {$categoryCode}: cannot move from {$currentStatus} to {$nextStatus}. Allowed transitions: {$allowedStr}.",
                400
            );
        }

        $isAdmin = in_array($actorType, ['ADMIN', 'SUPERADMIN'], true);
        $cleanReason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
        $cleanPublicMessage = $publicMessage !== null && trim($publicMessage) !== '' ? trim($publicMessage) : null;

        $assignmentStatus = strtoupper(trim((string)($registration['assignment_status'] ?? 'UNASSIGNED')));
        $assignedConflictFlagged = ($assignmentStatus === 'ASSIGNED' && in_array($nextStatus, ['WITHDRAWN', 'CANCELLED'], true));

        $startedTx = !$pdo->inTransaction();
        if ($startedTx) {
            $pdo->beginTransaction();
        }

        $historyId = '';
        $notificationQueueId = null;

        try {
            $now = nowSql();
            $setClauses = ['status = :new_status', 'updated_at = :now'];
            $params = [
                ':new_status' => $nextStatus,
                ':now'        => $now,
                ':id'         => $regId,
            ];

            if ($isAdmin) {
                $setClauses[] = 'reviewer_id = :reviewer_id';
                $setClauses[] = 'reviewed_at = :reviewed_at';
                $params[':reviewer_id'] = $actorUserId;
                $params[':reviewed_at'] = $now;

                if ($cleanReason !== null) {
                    $setClauses[] = 'review_notes = :review_notes';
                    $params[':review_notes'] = $cleanReason;
                }
                if ($cleanPublicMessage !== null) {
                    $setClauses[] = 'public_review_note = :public_review_note';
                    $params[':public_review_note'] = $cleanPublicMessage;
                }
            }

            $sql = 'UPDATE registrations SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
            $upd = $pdo->prepare($sql);
            $upd->execute($params);

            // Fast-track decision straight from SUBMITTED / RESUBMITTED: record the implicit UNDER_REVIEW step cleanly.
            $fastTracked = $isAdmin
                && in_array($categoryCode, ['VOLUNTEER', 'SLI'], true)
                && in_array($currentStatus, ['SUBMITTED', 'RESUBMITTED'], true)
                && in_array($nextStatus, ['APPROVED', 'REJECTED', 'WAITLISTED', 'ADDITIONAL_INFO_REQUIRED'], true);
            if ($fastTracked) {
                pfgRecordRegistrationStatusHistory(
                    $pdo,
                    $regId,
                    $currentStatus,
                    'UNDER_REVIEW',
                    $actorUserId,
                    $actorType,
                    'Implicit review step (decision recorded directly from ' . $currentStatus . ').',
                    null
                );
            }

            $historyId = pfgRecordRegistrationStatusHistory(
                $pdo,
                $regId,
                $fastTracked ? 'UNDER_REVIEW' : $currentStatus,
                $nextStatus,
                $actorUserId,
                $actorType,
                $cleanReason,
                $cleanPublicMessage
            );

            // Enqueue Phase 4 notification + in-app alert inside the SAME transaction
            $email = strtolower(trim((string)($registration['email'] ?? '')));
            $recipientUserId = !empty($registration['user_id']) ? (string)$registration['user_id'] : null;
            $notifSpec = pfgGetTransitionNotificationSpec($registration, $nextStatus, $cleanPublicMessage);

            if ($notifSpec !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $qRes = pfgEnqueueNotification($pdo, [
                    'to'              => $email,
                    'templateCode'    => $notifSpec['templateCode'],
                    'variables'       => $notifSpec['variables'],
                    'recipientUserId' => $recipientUserId,
                    'entityType'      => 'registration',
                    'entityId'        => $regId,
                    'idempotencyKey'  => 'lifecycle:' . $regId . ':' . $nextStatus . ':' . $historyId,
                    'createInApp'     => false,
                ]);
                $notificationQueueId = (string)($qRes['queueId'] ?? '');
            }

            if ($startedTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Best-effort post-commit dispatch in local file-sink mode
        if ($startedTx && $notificationQueueId !== null && $notificationQueueId !== '' && defined('MAIL_DRIVER') && MAIL_DRIVER === 'file') {
            try {
                pfgDispatchQueuedIdNow($pdo, $notificationQueueId);
            } catch (Throwable $mailErr) {
                error_log('[ApplicationLifecycle] local file-sink dispatch warning: ' . $mailErr->getMessage());
            }
        }

        // Audit logs (outside tx or safe post-commit)
        if (function_exists('logAudit')) {
            logAudit($pdo, [
                'actorUserId'  => $actorUserId,
                'action'       => 'REGISTRATION_STATUS_TRANSITIONED',
                'resourceType' => 'registration',
                'resourceId'   => $regId,
                'metadata'     => [
                    'registrationCode'        => $regCode,
                    'categoryCode'            => $categoryCode,
                    'previousStatus'          => $currentStatus,
                    'newStatus'               => $nextStatus,
                    'actorType'               => $actorType,
                    'historyId'               => $historyId,
                    'assignedConflictFlagged' => $assignedConflictFlagged,
                ],
            ]);

            if ($assignedConflictFlagged) {
                logAudit($pdo, [
                    'actorUserId'  => $actorUserId,
                    'action'       => 'ASSIGNED_PARTICIPANT_WITHDRAWN_ALERT',
                    'resourceType' => 'duty_assignment',
                    'resourceId'   => $regId,
                    'metadata'     => [
                        'registrationCode' => $regCode,
                        'categoryCode'     => $categoryCode,
                        'newStatus'        => $nextStatus,
                        'assignmentStatus' => $assignmentStatus,
                        'notice'           => 'Participant already had ASSIGNED duty roster; coordinator reassignment required (duty rows preserved for audit).',
                    ],
                ]);
            }
        }

        return [
            'registrationId'          => $regId,
            'registrationCode'        => $regCode,
            'categoryCode'            => $categoryCode,
            'previousStatus'          => $currentStatus,
            'newStatus'               => $nextStatus,
            'historyId'               => $historyId,
            'notificationQueueId'     => $notificationQueueId,
            'assignedConflictFlagged' => $assignedConflictFlagged,
        ];
    }
}
