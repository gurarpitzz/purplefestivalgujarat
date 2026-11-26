<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Shared helpers for the Administration APIs (Phases 8, 11 & 12).
 *
 * Pure helper functions only (no side effects on include). Requires config.php + auth.php to be loaded first.
 * Includes a LOCAL registration lifecycle fallback (`pfgAdmin*`) that mirrors the canonical Phase 7 state
 * machine so the admin APIs stay correct whether or not `includes/application-lifecycle.php` has been merged.
 */

if (!function_exists('pfgAdminTableExists')) {
    function pfgAdminTableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
            $st->execute([':t' => $table]);
            return $cache[$table] = ((int)$st->fetchColumn() > 0);
        } catch (Throwable $e) {
            return $cache[$table] = false;
        }
    }
}

if (!function_exists('pfgAdminColumnExists')) {
    function pfgAdminColumnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
            $st->execute([':t' => $table, ':c' => $column]);
            return $cache[$key] = ((int)$st->fetchColumn() > 0);
        } catch (Throwable $e) {
            return $cache[$key] = false;
        }
    }
}

if (!function_exists('pfgAdminEditionId')) {
    /**
     * Resolves the active festival edition id (slug `gujarat-2027`, falling back to the newest active edition).
     */
    function pfgAdminEditionId(PDO $pdo): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $st = $pdo->prepare('SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1');
        $st->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $id = $st->fetchColumn();
        if (!$id) {
            $id = $pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1')->fetchColumn();
        }
        if (!$id) {
            throw new RuntimeException('No festival edition is configured.');
        }
        return $cached = (string)$id;
    }
}

if (!function_exists('pfgAdminPageParams')) {
    /**
     * Returns [page, pageSize, offset] from the query string with safe clamping.
     */
    function pfgAdminPageParams(int $defaultSize = 25, int $maxSize = 200): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = (int)($_GET['pageSize'] ?? $defaultSize);
        $size = min($maxSize, max(1, $size > 0 ? $size : $defaultSize));
        return [$page, $size, ($page - 1) * $size];
    }
}

if (!function_exists('pfgAdminPaginationMeta')) {
    function pfgAdminPaginationMeta(int $page, int $pageSize, int $total): array
    {
        return [
            'page'       => $page,
            'pageSize'   => $pageSize,
            'total'      => $total,
            'totalPages' => max(1, (int)ceil($total / max(1, $pageSize))),
        ];
    }
}

if (!function_exists('pfgAdminCanManageProgramme')) {
    function pfgAdminCanManageProgramme(array $user): bool
    {
        return hasPermission($user, PERMISSIONS['CMS_MANAGE']) || hasPermission($user, PERMISSIONS['PROGRAMME_MANAGE']);
    }
}

if (!function_exists('pfgAdminCanManageCommunications')) {
    function pfgAdminCanManageCommunications(array $user): bool
    {
        return hasPermission($user, PERMISSIONS['CMS_MANAGE']) || hasPermission($user, PERMISSIONS['COMMUNICATIONS_MANAGE']);
    }
}

if (!function_exists('pfgAdminRequireProgrammeAccess')) {
    function pfgAdminRequireProgrammeAccess(array $user): void
    {
        if (pfgAdminCanManageProgramme($user)) {
            return;
        }
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'rbac',
            'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
            'metadata'     => ['requiredPermission' => 'cms.manage|programme.manage', 'actualRoles' => $user['roles'] ?? []],
        ]);
        jsonResponse(['error' => 'Permission cms.manage or programme.manage is required.'], 403);
    }
}

if (!function_exists('pfgAdminRequireCommunicationsAccess')) {
    function pfgAdminRequireCommunicationsAccess(array $user): void
    {
        if (pfgAdminCanManageCommunications($user)) {
            return;
        }
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'rbac',
            'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
            'metadata'     => ['requiredPermission' => 'cms.manage|communications.manage', 'actualRoles' => $user['roles'] ?? []],
        ]);
        jsonResponse(['error' => 'Permission cms.manage or communications.manage is required.'], 403);
    }
}

if (!function_exists('pfgAdminIsUuid')) {
    function pfgAdminIsUuid(?string $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }
}

if (!function_exists('pfgAdminValidDate')) {
    function pfgAdminValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}

if (!function_exists('pfgAdminValidTime')) {
    function pfgAdminValidTime(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}

if (!function_exists('pfgAdminCleanText')) {
    /**
     * Trims, strips control characters and enforces a maximum multibyte length. Returns null for empty values.
     */
    function pfgAdminCleanText($value, int $maxLen = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string)$value);
        $s = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        if ($s === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, $maxLen, 'UTF-8');
        } else {
            $s = substr($s, 0, $maxLen);
        }
        return $s;
    }
}

if (!function_exists('pfgAdminStringList')) {
    /**
     * Normalises an input array (or comma/newline separated string) into a de-duplicated list of short strings.
     */
    function pfgAdminStringList($value, int $maxItems = 40, int $maxLen = 160): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                continue;
            }
            $clean = pfgAdminCleanText((string)$item, $maxLen);
            if ($clean !== null && !in_array($clean, $out, true)) {
                $out[] = $clean;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return $out;
    }
}

// ============================================================================
// REGISTRATION LIFECYCLE (LOCAL FALLBACK MIRRORING includes/application-lifecycle.php — Phase 7 state machine)
// ============================================================================

if (!function_exists('pfgAdminRegistrationTrack')) {
    /**
     * Maps a registration category code to its lifecycle track: ATTENDEE (instant-confirm) or REVIEW (Volunteer / SLI).
     * Historical LEGACY categories (DELEGATE / ALLY / ACCESSIBLE ...) follow the attendee track.
     */
    function pfgAdminRegistrationTrack(string $categoryCode): string
    {
        return in_array(strtoupper($categoryCode), ['VOLUNTEER', 'SLI'], true) ? 'REVIEW' : 'ATTENDEE';
    }
}

if (!function_exists('pfgAdminNormalizeStatus')) {
    /**
     * Normalises historical status vocabulary onto the canonical machine (PENDING -> SUBMITTED).
     */
    function pfgAdminNormalizeStatus(string $status): string
    {
        $s = strtoupper(trim($status));
        return $s === 'PENDING' ? 'SUBMITTED' : $s;
    }
}

if (!function_exists('pfgAdminAllowedTransitions')) {
    /**
     * Statuses an ADMIN / SUPERADMIN may move a registration to from its current status.
     * Unified with `includes/application-lifecycle.php` (HIGH-02) so there is zero transition divergence.
     * Participant-driven steps (`ADDITIONAL_INFO_REQUIRED -> RESUBMITTED`, `-> WITHDRAWN`) belong strictly to PARTICIPANT.
     * From SUBMITTED / RESUBMITTED, reviewers may decide directly; the server records the implicit UNDER_REVIEW step in history.
     */
    function pfgAdminAllowedTransitions(string $categoryCode, string $currentStatus): array
    {
        if (!function_exists('pfgGetAllowedStatusTransitions') && file_exists(__DIR__ . '/application-lifecycle.php')) {
            require_once __DIR__ . '/application-lifecycle.php';
        }
        if (function_exists('pfgGetAllowedStatusTransitions')) {
            return pfgGetAllowedStatusTransitions($categoryCode, $currentStatus, 'ADMIN');
        }

        $current = pfgAdminNormalizeStatus($currentStatus);
        if (pfgAdminRegistrationTrack($categoryCode) === 'ATTENDEE') {
            $map = [
                'DRAFT'     => ['SUBMITTED', 'CONFIRMED', 'CANCELLED'],
                'SUBMITTED' => ['CONFIRMED', 'CANCELLED'],
                'CONFIRMED' => ['CANCELLED'],
            ];
        } else {
            $decisions = ['APPROVED', 'REJECTED', 'WAITLISTED', 'ADDITIONAL_INFO_REQUIRED'];
            $map = [
                'DRAFT'                    => ['SUBMITTED', 'CANCELLED'],
                'SUBMITTED'                => array_merge(['UNDER_REVIEW'], $decisions, ['CANCELLED']),
                'RESUBMITTED'              => array_merge(['UNDER_REVIEW'], $decisions, ['CANCELLED']),
                'UNDER_REVIEW'             => array_merge($decisions, ['CANCELLED']),
                'ADDITIONAL_INFO_REQUIRED' => ['UNDER_REVIEW', 'CANCELLED'],
                'WAITLISTED'               => ['APPROVED', 'REJECTED', 'CANCELLED'],
                'APPROVED'                 => ['CANCELLED'],
                // Historical rows that were confirmed under the old single-step flow.
                'CONFIRMED'                => ['CANCELLED'],
            ];
        }
        return $map[$current] ?? [];
    }
}

if (!function_exists('pfgAdminIsApprovedStatus')) {
    function pfgAdminIsApprovedStatus(string $status): bool
    {
        return in_array(pfgAdminNormalizeStatus($status), ['APPROVED', 'CONFIRMED'], true);
    }
}

if (!function_exists('pfgAdminRecordStatusHistory')) {
    /**
     * Appends an immutable row to `registration_status_history` (no-op if the Phase 6/7 table is not migrated yet).
     */
    function pfgAdminRecordStatusHistory(PDO $pdo, string $registrationId, ?string $previous, string $new, ?string $actorUserId, ?string $actorRole, ?string $reason = null, ?string $publicMessage = null, ?string $createdAt = null): void
    {
        if (!pfgAdminTableExists($pdo, 'registration_status_history')) {
            return;
        }
        $st = $pdo->prepare('
            INSERT INTO registration_status_history (id, registration_id, previous_status, new_status, actor_user_id, actor_role, reason, public_message, created_at)
            VALUES (:id, :rid, :prev, :new, :actor, :role, :reason, :pub, :now)
        ');
        $st->execute([
            ':id'     => uuid4(),
            ':rid'    => $registrationId,
            ':prev'   => $previous,
            ':new'    => $new,
            ':actor'  => $actorUserId,
            ':role'   => $actorRole,
            ':reason' => $reason,
            ':pub'    => $publicMessage,
            ':now'    => $createdAt ?? nowSql(),
        ]);
    }
}

if (!function_exists('pfgAdminPrimaryRole')) {
    function pfgAdminPrimaryRole(array $user): string
    {
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        foreach (['SUPERADMIN', 'ADMIN', 'RGST'] as $r) {
            if (in_array($r, $roles, true)) {
                return $r;
            }
        }
        return (string)($roles[0] ?? 'ADMIN');
    }
}
