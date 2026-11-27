<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Branded WCAG 2.2 AA Email Template Registry & Renderer (Phase 4J–4K)
 *
 * Design & Accessibility Guarantees (remediates FND-42):
 * - Semantic HTML5 (`<!DOCTYPE html><html lang="en">`) with `role="presentation"` email layout tables.
 * - High-contrast Purple Festival Gujarat palette meeting WCAG 2.2 AA/AAA:
 *     Header bg `#2B144D` / text `#FFFFFF` (14.1:1)
 *     Body bg `#FFFFFF` / text `#1E1B26` (16.8:1)
 *     Callout bg `#F7F4FC` / muted text `#4A3F63` (8.9:1)
 *     Primary CTA `#5B2A86` / text `#FFFFFF` (10.2:1)
 * - Fully readable with external images disabled (zero image-only critical info).
 * - Zero participant QR entry pass images, zero api.qrserver.com URLs (Decision D-01).
 * - Strict UTF-8 HTML escaping (`ENT_QUOTES | ENT_SUBSTITUTE`) preserving Gujarati & Hindi scripts.
 * - Prohibited-field guard blocks passwords, MFA secrets, disability diagnoses, medical records,
 *   emergency contact phone numbers, and internal admin notes from ever entering email output.
 * - Complete 29-template catalog (Account 1–7, Attendee 8–10, Volunteer 11–17, SLI 18–24,
 *   Accessibility & Operational 25–29), each paired with a clean plain-text multipart alternative.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('pfgGetProhibitedEmailVariablePatterns')) {
    function pfgGetProhibitedEmailVariablePatterns(): array
    {
        return [
            '/password/i',
            '/mfa_?secret/i',
            '/otpauth/i',
            '/recovery_?code/i',
            '/raw_?token/i',
            '/disability_?(diagnosis|self_?description|categories|category_?codes)/i',
            '/medical_?(note|record|history)/i',
            '/internal_?admin_?note/i',
            '/emergency_?contact/i',
            '/api_?key/i',
            '/token_?hash/i',
        ];
    }
}

if (!function_exists('pfgStripProhibitedEmailVariables')) {
    /**
     * Recursively removes prohibited sensitive keys from email template variables (Phase 4N).
     * Note: Ephemeral one-time action URLs (`actionUrl`) are handled explicitly via encrypted ephemeral
     * delivery for verification/recovery templates and never persisted in `notification_queue.payload`.
     */
    function pfgStripProhibitedEmailVariables(array $vars): array
    {
        $patterns = pfgGetProhibitedEmailVariablePatterns();
        $clean = [];
        foreach ($vars as $key => $val) {
            $kStr = is_string($key) ? $key : '';
            $blocked = false;
            if ($kStr !== '') {
                foreach ($patterns as $pat) {
                    if (preg_match($pat, $kStr) === 1) {
                        $blocked = true;
                        break;
                    }
                }
            }
            if ($blocked) {
                continue;
            }
            if (is_array($val)) {
                $clean[$key] = pfgStripProhibitedEmailVariables($val);
            } else {
                $clean[$key] = $val;
            }
        }
        return $clean;
    }
}

if (!function_exists('pfgGetEmailTemplateCatalog')) {
    /**
     * Authoritative 29-template inventory (Section 14 / Phase 4K).
     * Clearly classifies trigger status so future-phase templates are NEVER falsely reported as active.
     */
    function pfgGetEmailTemplateCatalog(): array
    {
        return [
            // === ACCOUNT TEMPLATES (1–7) ===
            'ACCOUNT_VERIFICATION' => [
                'number'         => 1,
                'group'          => 'Account',
                'recipient'      => 'Participant / New Account Holder',
                'triggerEvent'   => 'account.verification_requested',
                'triggerStatus'  => 'FUTURE_PHASE_5',
                'requiredVars'   => ['fullName', 'actionUrl'],
                'optionalVars'   => ['expiresInText'],
                'ephemeralVars'  => ['actionUrl'],
                'subject'        => 'Verify your email address — Purple Festival Gujarat 2027',
                'heading'        => 'Verify Your Email Address',
                'preheader'      => 'Complete email verification to activate your Purple Festival Gujarat 2027 account.',
                'ctaLabel'       => 'Verify Email Address',
                'bodyParagraphs' => [
                    'Thank you for creating an account for Purple Festival Gujarat 2027. Please verify your email address using the button below to activate your account.',
                    'This verification link is single-use and expires in {{expiresInText}}. If you did not request this account, you may safely ignore this message.',
                ],
                'restrictions'   => 'No raw password; single-use actionUrl delivered ephemerally and wiped post-handoff',
            ],
            'EMAIL_VERIFICATION_RESEND' => [
                'number'         => 2,
                'group'          => 'Account',
                'recipient'      => 'Participant',
                'triggerEvent'   => 'account.verification_resent',
                'triggerStatus'  => 'FUTURE_PHASE_5',
                'requiredVars'   => ['fullName', 'actionUrl'],
                'optionalVars'   => ['expiresInText'],
                'ephemeralVars'  => ['actionUrl'],
                'subject'        => 'New verification link — Purple Festival Gujarat 2027',
                'heading'        => 'Your New Email Verification Link',
                'preheader'      => 'A fresh single-use verification link has been issued for your account.',
                'ctaLabel'       => 'Verify Email Address',
                'bodyParagraphs' => [
                    'As requested, we have generated a fresh verification link for your Purple Festival Gujarat 2027 account. Any earlier verification links are no longer valid.',
                    'Please complete verification within {{expiresInText}}.',
                ],
                'restrictions'   => 'Single-use actionUrl delivered ephemerally and wiped post-handoff',
            ],
            'ACCOUNT_WELCOME_ACTIVATED' => [
                'number'         => 3,
                'group'          => 'Account',
                'recipient'      => 'Verified Participant',
                'triggerEvent'   => 'account.activated',
                'triggerStatus'  => 'FUTURE_PHASE_5',
                'requiredVars'   => ['fullName'],
                'optionalVars'   => ['dashboardUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Welcome to Purple Festival Gujarat 2027 — Account Activated',
                'heading'        => 'Your Account Is Active',
                'preheader'      => 'Your email is verified. You can now register as an Attendee, SLI, or Volunteer.',
                'ctaLabel'       => 'Open Participant Portal',
                'bodyParagraphs' => [
                    'Welcome to Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat). Your account is now verified and active.',
                    'You can now sign in to submit your Attendee registration, Volunteer application, or Sign Language Interpreter (SLI) application and manage any accessibility accommodations.',
                ],
                'restrictions'   => 'No credentials or sensitive personal disclosures',
            ],
            'PASSWORD_RECOVERY' => [
                'number'         => 4,
                'group'          => 'Account',
                'recipient'      => 'Account Holder (Staff or Participant)',
                'triggerEvent'   => 'auth.password_recovery_requested',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'actionUrl'],
                'optionalVars'   => ['expiresInText'],
                'ephemeralVars'  => ['actionUrl'],
                'subject'        => 'Reset your password — Purple Festival Gujarat 2027',
                'heading'        => 'Password Reset Request',
                'preheader'      => 'Use this single-use link to choose a new password for your account.',
                'ctaLabel'       => 'Reset Your Password',
                'bodyParagraphs' => [
                    'We received a request to reset the password for your Purple Festival Gujarat 2027 account. Use the secure button below within {{expiresInText}} to choose a new password.',
                    'If you did not request a password reset, no changes have been made to your account and you may safely ignore this email.',
                ],
                'restrictions'   => 'Single-use actionUrl encrypted ephemerally in queue and wiped immediately upon handoff; never stored in logs',
            ],
            'PASSWORD_RESET_CONFIRMATION' => [
                'number'         => 5,
                'group'          => 'Account',
                'recipient'      => 'Authorized Staff Member',
                'triggerEvent'   => 'auth.password_reset_completed',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName'],
                'optionalVars'   => ['changedAtIst', 'staffLoginUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Your staff password has been changed — Purple Festival Gujarat 2027',
                'heading'        => 'Password Updated Successfully',
                'preheader'      => 'Your staff password was changed and previous sessions were signed out.',
                'ctaLabel'       => 'Sign In to Staff Portal',
                'bodyParagraphs' => [
                    'This is a confirmation that your Purple Festival Gujarat 2027 staff account password was updated on {{changedAtIst}}. All previous active sessions have been signed out for your security.',
                    'If you did not make this change, contact the Festival Secretariat immediately using the contact details below.',
                ],
                'restrictions'   => 'No password values, hashes, or session tokens',
            ],
            'EMAIL_CHANGE_VERIFICATION' => [
                'number'         => 6,
                'group'          => 'Account',
                'recipient'      => 'Account Holder (New Email)',
                'triggerEvent'   => 'account.email_change_requested',
                'triggerStatus'  => 'FUTURE_PHASE_5',
                'requiredVars'   => ['fullName', 'actionUrl'],
                'optionalVars'   => ['expiresInText'],
                'ephemeralVars'  => ['actionUrl'],
                'subject'        => 'Confirm your new email address — Purple Festival Gujarat 2027',
                'heading'        => 'Confirm Email Address Change',
                'preheader'      => 'Confirm this email address to finish updating your account contact email.',
                'ctaLabel'       => 'Confirm New Email Address',
                'bodyParagraphs' => [
                    'A request was made to update the email address on your Purple Festival Gujarat 2027 account to this address. Confirm the change within {{expiresInText}} using the button below.',
                    'If you did not request this update, ignore this message and your current email address will remain unchanged.',
                ],
                'restrictions'   => 'Single-use actionUrl encrypted ephemerally and wiped post-handoff',
            ],
            'SECURITY_ALERT' => [
                'number'         => 7,
                'group'          => 'Account',
                'recipient'      => 'Account Holder (Staff or Participant)',
                'triggerEvent'   => 'auth.security_notification',
                'triggerStatus'  => 'FUTURE_PHASE_5',
                'requiredVars'   => ['fullName', 'securityEventSummary'],
                'optionalVars'   => ['occurredAtIst', 'dashboardUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Security notice for your Purple Festival Gujarat 2027 account',
                'heading'        => 'Account Security Notification',
                'preheader'      => 'An important security action occurred on your Purple Festival Gujarat 2027 account.',
                'ctaLabel'       => 'Review Account Security',
                'bodyParagraphs' => [
                    'We are notifying you of an important security update on your account: {{securityEventSummary}}.',
                    'Time recorded: {{occurredAtIst}}. If you did not authorize this activity, contact the Festival Secretariat immediately.',
                ],
                'restrictions'   => 'Never include MFA secrets, recovery codes, or raw session tokens',
            ],

            // === ATTENDEE TEMPLATES (8–10) ===
            'ATTENDEE_REGISTRATION_CONFIRMED' => [
                'number'         => 8,
                'group'          => 'Attendee',
                'recipient'      => 'General Attendee',
                'triggerEvent'   => 'attendee.registration_confirmed',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status', 'attendingDaysText', 'programmeUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Registration Confirmed ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your Festival Registration Is Confirmed',
                'preheader'      => 'Your official Registration ID is {{registrationCode}} for Purple Festival Gujarat 2027.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Your registration for Purple Festival Gujarat 2027 is confirmed under {{categoryName}}. Please save your official Registration ID shown below.',
                    'At the venue in Vadodara (21–23 January 2027), simply quote your Registration ID or registered name at the Registration Desk for quick check-in. No website account or QR pass is required.',
                ],
                'restrictions'   => 'No QR pass images or external QR URLs (D-01); no disability diagnoses or medical notes; no participant dashboard links',
            ],
            'ATTENDEE_REGISTRATION_UPDATED' => [
                'number'         => 9,
                'group'          => 'Attendee',
                'recipient'      => 'General Attendee',
                'triggerEvent'   => 'attendee.registration_updated',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status', 'updateSummary', 'programmeUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Registration Updated ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your Registration Details Were Updated',
                'preheader'      => 'Your registration {{registrationCode}} has been updated.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Your registration details for {{registrationCode}} ({{categoryName}}) have been updated by the Festival Secretariat.',
                    '{{updateSummary}} If you have any questions, reply to this email or contact purplefestivalgujarat@gmail.com quoting your Registration ID.',
                ],
                'restrictions'   => 'No disability diagnoses or sensitive emergency phone numbers',
            ],
            'ATTENDEE_REGISTRATION_CANCELLED' => [
                'number'         => 10,
                'group'          => 'Attendee',
                'recipient'      => 'General Attendee',
                'triggerEvent'   => 'attendee.registration_cancelled',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'reasonSummary'],
                'ephemeralVars'  => [],
                'subject'        => 'Registration Cancelled ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Registration Cancellation Confirmed',
                'preheader'      => 'Your registration {{registrationCode}} has been marked cancelled.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Your registration {{registrationCode}} ({{categoryName}}) for Purple Festival Gujarat 2027 has been cancelled.',
                    '{{reasonSummary}} If this was unintended or if you need assistance, please contact the Festival Secretariat quoting your Registration ID.',
                ],
                'restrictions'   => 'No internal administrative review notes',
            ],

            // === VOLUNTEER TEMPLATES (11–17) ===
            'VOLUNTEER_APPLICATION_RECEIVED' => [
                'number'         => 11,
                'group'          => 'Volunteer',
                'recipient'      => 'Volunteer Applicant',
                'triggerEvent'   => 'volunteer.application_received',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status'],
                'ephemeralVars'  => [],
                'subject'        => 'Volunteer Application Received ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Volunteer Application Received — Pending Review',
                'preheader'      => 'We received your Volunteer application {{registrationCode}}. Review is in progress.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for applying to volunteer at Purple Festival Gujarat 2027. Your application reference ID is {{registrationCode}} and its current status is SUBMITTED (pending coordinator review).',
                    'Please note that submitting an application does not confirm approval or role assignment. Our volunteer coordinators will review your application and send the decision and any assignment instructions to this email address.',
                ],
                'restrictions'   => 'Must clearly state pending review (never imply confirmed eligibility); no QR pass; no dashboard links',
            ],
            'VOLUNTEER_APPLICATION_APPROVED' => [
                'number'         => 12,
                'group'          => 'Volunteer',
                'recipient'      => 'Approved Volunteer',
                'triggerEvent'   => 'volunteer.application_approved',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status', 'dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Volunteer Application Approved ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your Volunteer Application Has Been Approved',
                'preheader'      => 'Congratulations! Your Volunteer application {{registrationCode}} is approved.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Congratulations! Your Volunteer application ({{registrationCode}}) for Purple Festival Gujarat 2027 has been approved by our festival coordinators.',
                    'Please quote your official Registration ID ({{registrationCode}}) at the Registration Desk in Vadodara for check-in. Your assigned venue, shift, and reporting instructions are listed below or will be sent via email.',
                ],
                'restrictions'   => 'No QR pass images (D-01); no internal review notes; no dashboard links',
            ],
            'VOLUNTEER_APPLICATION_REJECTED' => [
                'number'         => 13,
                'group'          => 'Volunteer',
                'recipient'      => 'Volunteer Applicant',
                'triggerEvent'   => 'volunteer.application_rejected',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'publicGuidance'],
                'ephemeralVars'  => [],
                'subject'        => 'Update on Your Volunteer Application ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Volunteer Application Decision',
                'preheader'      => 'An update is available on your Volunteer application {{registrationCode}}.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for your interest in volunteering for Purple Festival Gujarat 2027 (Reference: {{registrationCode}}). After careful review, we are unable to offer a volunteer placement for this edition.',
                    '{{publicGuidance}} You are warmly welcome to join the festival as a General Attendee.',
                ],
                'restrictions'   => 'Never leak internal reviewer notes; courteous public guidance only',
            ],
            'VOLUNTEER_APPLICATION_WAITLISTED' => [
                'number'         => 14,
                'group'          => 'Volunteer',
                'recipient'      => 'Volunteer Applicant',
                'triggerEvent'   => 'volunteer.application_waitlisted',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'publicGuidance'],
                'ephemeralVars'  => [],
                'subject'        => 'Volunteer Application Waitlisted ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your Volunteer Application Is on the Waitlist',
                'preheader'      => 'Your Volunteer application {{registrationCode}} has been placed on our waitlist.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for applying as a Volunteer for Purple Festival Gujarat 2027 (Reference: {{registrationCode}}). Due to high demand, your application has been placed on our waitlist.',
                    '{{publicGuidance}} We will notify you by email if a volunteer placement opens up.',
                ],
                'restrictions'   => 'Must not imply confirmed placement; no internal notes',
            ],
            'VOLUNTEER_INFO_REQUESTED' => [
                'number'         => 15,
                'group'          => 'Volunteer',
                'recipient'      => 'Volunteer Applicant',
                'triggerEvent'   => 'volunteer.info_requested',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'requestedDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Additional Information Needed ({{registrationCode}}) — Volunteer Application',
                'heading'        => 'Additional Information Requested',
                'preheader'      => 'Our coordinators need a quick clarification for your Volunteer application {{registrationCode}}.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Our volunteer review team is evaluating your application ({{registrationCode}}) and needs additional information before making a decision.',
                    '{{requestedDetails}} Please reply directly to this email or write to purplefestivalgujarat@gmail.com quoting Registration ID {{registrationCode}}.',
                ],
                'restrictions'   => 'Only coordinator-approved participant-facing message; never expose internal notes',
            ],
            'VOLUNTEER_ASSIGNMENT_CREATED' => [
                'number'         => 16,
                'group'          => 'Volunteer',
                'recipient'      => 'Approved Volunteer',
                'triggerEvent'   => 'volunteer.assignment_created',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Your Volunteer Duty Schedule ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Volunteer Duty Assignment Issued',
                'preheader'      => 'Your venue, department, and shift details are ready for {{registrationCode}}.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Your volunteer duty assignment for Purple Festival Gujarat 2027 ({{registrationCode}}) has been scheduled. Please review your assigned venue, shift, and reporting details below.',
                    'Quote your Registration ID at the Registration Desk when reporting for duty. If you have any questions, contact purplefestivalgujarat@gmail.com.',
                ],
                'restrictions'   => 'No internal coordinator comments or personal data of other volunteers',
            ],
            'VOLUNTEER_ASSIGNMENT_UPDATED' => [
                'number'         => 17,
                'group'          => 'Volunteer',
                'recipient'      => 'Approved Volunteer',
                'triggerEvent'   => 'volunteer.assignment_updated',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Updated Volunteer Duty Schedule ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Volunteer Duty Assignment Updated',
                'preheader'      => 'Please review your updated venue or shift assignment for {{registrationCode}}.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'There has been an update to your volunteer duty assignment ({{registrationCode}}) for Purple Festival Gujarat 2027. Please review the latest details below.',
                    'If you have any scheduling conflict, contact the Festival Secretariat right away quoting your Registration ID.',
                ],
                'restrictions'   => 'No internal coordinator comments',
            ],

            // === SLI TEMPLATES (18–24) ===
            'SLI_APPLICATION_RECEIVED' => [
                'number'         => 18,
                'group'          => 'SLI',
                'recipient'      => 'SLI Applicant',
                'triggerEvent'   => 'sli.application_received',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status'],
                'ephemeralVars'  => [],
                'subject'        => 'SLI Application Received ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Sign Language Interpreter Application Received',
                'preheader'      => 'We received your SLI application {{registrationCode}}. Coordinator verification is pending.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for applying as an Indian Sign Language Interpreter (SLI) for Purple Festival Gujarat 2027. Your reference ID is {{registrationCode}} and your current status is SUBMITTED (pending verification).',
                    'Submitting an application does not confirm approval or session assignment. Our accessibility and SLI coordinators will review your application and notify you at this email address once a decision is made.',
                ],
                'restrictions'   => 'Must clearly state pending verification; no CRR or private credential data echoed unnecessarily; no dashboard links',
            ],
            'SLI_APPLICATION_APPROVED' => [
                'number'         => 19,
                'group'          => 'SLI',
                'recipient'      => 'Approved SLI',
                'triggerEvent'   => 'sli.application_approved',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'status', 'dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'SLI Application Approved ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your SLI Application Has Been Approved',
                'preheader'      => 'Congratulations! Your Sign Language Interpreter application {{registrationCode}} is approved.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Congratulations! Your Indian Sign Language Interpreter application ({{registrationCode}}) for Purple Festival Gujarat 2027 has been approved.',
                    'Please quote your official Registration ID ({{registrationCode}}) at the Registration Desk in Vadodara. Any assigned sessions, venue details, and reporting instructions are shown below or will be sent via email.',
                ],
                'restrictions'   => 'No QR pass images (D-01); no internal verification notes; no dashboard links',
            ],
            'SLI_APPLICATION_REJECTED' => [
                'number'         => 20,
                'group'          => 'SLI',
                'recipient'      => 'SLI Applicant',
                'triggerEvent'   => 'sli.application_rejected',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'publicGuidance'],
                'ephemeralVars'  => [],
                'subject'        => 'Update on Your SLI Application ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'SLI Application Decision',
                'preheader'      => 'An update is available regarding your SLI application {{registrationCode}}.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for applying to serve as an Indian Sign Language Interpreter for Purple Festival Gujarat 2027 (Reference: {{registrationCode}}). After review, we are unable to confirm an interpreter assignment for this edition.',
                    '{{publicGuidance}} You are warmly welcome to attend the festival as a General Attendee.',
                ],
                'restrictions'   => 'No internal verification notes',
            ],
            'SLI_APPLICATION_WAITLISTED' => [
                'number'         => 21,
                'group'          => 'SLI',
                'recipient'      => 'SLI Applicant',
                'triggerEvent'   => 'sli.application_waitlisted',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'publicGuidance'],
                'ephemeralVars'  => [],
                'subject'        => 'SLI Application Waitlisted ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Your SLI Application Is on the Waitlist',
                'preheader'      => 'Your SLI application {{registrationCode}} has been placed on our interpreter waitlist.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Thank you for applying as an Indian Sign Language Interpreter for Purple Festival Gujarat 2027 (Reference: {{registrationCode}}). Your application is currently on our waitlist.',
                    '{{publicGuidance}} Our SLI coordinators will reach out by email if additional interpretation sessions become available.',
                ],
                'restrictions'   => 'Must not imply confirmed assignment',
            ],
            'SLI_INFO_REQUESTED' => [
                'number'         => 22,
                'group'          => 'SLI',
                'recipient'      => 'SLI Applicant',
                'triggerEvent'   => 'sli.info_requested',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'requestedDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Additional Information Needed ({{registrationCode}}) — SLI Application',
                'heading'        => 'Additional Information Requested',
                'preheader'      => 'Our SLI team needs additional information for your application {{registrationCode}}.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    'Our Sign Language Interpreter review coordinators need a clarification to complete verification of your application ({{registrationCode}}).',
                    '{{requestedDetails}} Please reply directly to this email or contact purplefestivalgujarat@gmail.com quoting Registration ID {{registrationCode}}.',
                ],
                'restrictions'   => 'Participant-facing message only; no internal verification notes',
            ],
            'SLI_ASSIGNMENT_CREATED' => [
                'number'         => 23,
                'group'          => 'SLI',
                'recipient'      => 'Approved SLI',
                'triggerEvent'   => 'sli.assignment_created',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Your Interpreter Session Schedule ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Interpreter Assignment Issued',
                'preheader'      => 'Your assigned venue and interpretation sessions are ready for {{registrationCode}}.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Your Indian Sign Language interpretation assignment ({{registrationCode}}) for Purple Festival Gujarat 2027 has been scheduled. Please review your assigned sessions, venue, and duty times below.',
                    'Quote your Registration ID at the Registration Desk upon arrival. Contact purplefestivalgujarat@gmail.com if you need any assistance.',
                ],
                'restrictions'   => 'No personal details of other interpreters or internal notes',
            ],
            'SLI_ASSIGNMENT_UPDATED' => [
                'number'         => 24,
                'group'          => 'SLI',
                'recipient'      => 'Approved SLI',
                'triggerEvent'   => 'sli.assignment_updated',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['dutyDetails'],
                'ephemeralVars'  => [],
                'subject'        => 'Updated Interpreter Schedule ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Interpreter Assignment Updated',
                'preheader'      => 'Please review your updated interpretation sessions for {{registrationCode}}.',
                'ctaLabel'       => 'View Festival Programme',
                'bodyParagraphs' => [
                    'Your Indian Sign Language interpretation schedule ({{registrationCode}}) has been updated. Please check the revised venue, sessions, and duty times below.',
                    'Please notify the Festival Secretariat quoting your Registration ID if you have any availability concerns.',
                ],
                'restrictions'   => 'No internal notes',
            ],

            // === ACCESSIBILITY & OPERATIONAL TEMPLATES (25–29) ===
            'ACCESSIBILITY_REQUEST_RECEIVED' => [
                'number'         => 25,
                'group'          => 'Accessibility & Operational',
                'recipient'      => 'Participant',
                'triggerEvent'   => 'accessibility.request_received',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['accommodationCount'],
                'ephemeralVars'  => [],
                'subject'        => 'Accessibility Support Request Received ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Accessibility Support Request Received',
                'preheader'      => 'Our accessibility coordination team has received your support request for {{registrationCode}}.',
                'ctaLabel'       => 'Accessibility Guide',
                'bodyParagraphs' => [
                    'We have received your accessibility accommodation request associated with Registration ID {{registrationCode}}. To protect your privacy, specific personal disclosures are not repeated in this email.',
                    'Our authorized accessibility coordinators will review your request and send updates to this email address once arrangements are confirmed. For urgent accessibility assistance, email purplefestivalgujarat@gmail.com quoting {{registrationCode}}.',
                ],
                'restrictions'   => 'STRICT PRIVACY: Never repeat disability diagnoses, medical details, or raw free-text disclosures in email body',
            ],
            'ACCESSIBILITY_ARRANGEMENT_UPDATED' => [
                'number'         => 26,
                'group'          => 'Accessibility & Operational',
                'recipient'      => 'Participant',
                'triggerEvent'   => 'accessibility.arrangement_updated',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'registrationCode', 'arrangementStatus'],
                'optionalVars'   => [],
                'ephemeralVars'  => [],
                'subject'        => 'Accessibility Arrangement Update ({{registrationCode}}) — Purple Festival Gujarat 2027',
                'heading'        => 'Accessibility Arrangement Status Updated',
                'preheader'      => 'Your accessibility request for {{registrationCode}} is now {{arrangementStatus}}.',
                'ctaLabel'       => 'Accessibility Guide',
                'bodyParagraphs' => [
                    'Your accessibility support request for Registration ID {{registrationCode}} has been updated to status: {{arrangementStatus}}.',
                    'For your privacy, sensitive personal details are not included in plain email. Please reply to this email or contact our accessibility desk at purplefestivalgujarat@gmail.com quoting Registration ID {{registrationCode}} if you have any questions.',
                ],
                'restrictions'   => 'STRICT PRIVACY: Never repeat disability details or raw coordinator medical notes in email',
            ],
            'FESTIVAL_IMPORTANT_INFO' => [
                'number'         => 27,
                'group'          => 'Accessibility & Operational',
                'recipient'      => 'Registered Participants',
                'triggerEvent'   => 'festival.important_information',
                'triggerStatus'  => 'CONNECTED',
                'requiredVars'   => ['fullName', 'announcementTitle', 'announcementBody'],
                'optionalVars'   => ['registrationCode'],
                'ephemeralVars'  => [],
                'subject'        => '{{announcementTitle}} — Purple Festival Gujarat 2027',
                'heading'        => '{{announcementTitle}}',
                'preheader'      => 'Important festival information for Purple Festival Gujarat 2027.',
                'ctaLabel'       => 'Visit Festival Website',
                'bodyParagraphs' => [
                    '{{announcementBody}}',
                ],
                'restrictions'   => 'Approved operational announcements only; never broadcast individual PII',
            ],
            'PROGRAMME_CHANGE_NOTICE' => [
                'number'         => 28,
                'group'          => 'Accessibility & Operational',
                'recipient'      => 'Registered Participants / Assigned Staff',
                'triggerEvent'   => 'festival.programme_changed',
                'triggerStatus'  => 'FUTURE_PHASE_12',
                'requiredVars'   => ['fullName', 'changeSummary'],
                'optionalVars'   => ['registrationCode', 'programmeUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Programme Update — Purple Festival Gujarat 2027',
                'heading'        => 'Festival Programme Update',
                'preheader'      => 'A schedule or venue update has been published for Purple Festival Gujarat 2027.',
                'ctaLabel'       => 'View Latest Programme',
                'bodyParagraphs' => [
                    'Please note an update to the Purple Festival Gujarat 2027 programme:',
                    '{{changeSummary}}',
                ],
                'restrictions'   => 'Distinguish confirmed vs tentative venues per CMS policy',
            ],
            'FESTIVAL_REMINDER' => [
                'number'         => 29,
                'group'          => 'Accessibility & Operational',
                'recipient'      => 'Confirmed Participants',
                'triggerEvent'   => 'festival.event_reminder',
                'triggerStatus'  => 'FUTURE_PHASE_13',
                'requiredVars'   => ['fullName', 'registrationCode'],
                'optionalVars'   => ['categoryName', 'reminderDetails', 'dashboardUrl'],
                'ephemeralVars'  => [],
                'subject'        => 'Reminder: Purple Festival Gujarat 2027 Starts Soon ({{registrationCode}})',
                'heading'        => 'We Look Forward to Welcoming You in Vadodara',
                'preheader'      => 'Keep your Registration ID {{registrationCode}} handy for the Registration Desk.',
                'ctaLabel'       => 'View Festival Details',
                'bodyParagraphs' => [
                    'Purple Festival Gujarat 2027 takes place on 21–23 January 2027 in Vadodara, Gujarat. Please keep your Registration ID ({{registrationCode}}) handy to share at the Registration Desk upon arrival.',
                    '{{reminderDetails}}',
                ],
                'restrictions'   => 'No QR pass instructions (D-01); accessible venue guidance only',
            ],
        ];
    }
}

if (!function_exists('pfgResolveTemplateCode')) {
    /**
     * Maps legacy template aliases cleanly to canonical Phase 4 template codes.
     */
    function pfgResolveTemplateCode(string $code): string
    {
        $upper = strtoupper(trim($code));
        $aliases = [
            'REGISTRATION_CONFIRMED' => 'ATTENDEE_REGISTRATION_CONFIRMED',
            'APPLICATION_SUBMITTED'  => 'VOLUNTEER_APPLICATION_RECEIVED',
            'APPLICATION_APPROVED'   => 'VOLUNTEER_APPLICATION_APPROVED',
            'PASSWORD_RESET'         => 'PASSWORD_RECOVERY',
        ];
        return $aliases[$upper] ?? $upper;
    }
}

if (!function_exists('pfgInterpolatePlain')) {
    function pfgInterpolatePlain(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', static function (array $m) use ($vars): string {
            $key = $m[1];
            if (!array_key_exists($key, $vars) || $vars[$key] === null || is_array($vars[$key])) {
                return '';
            }
            return trim((string)$vars[$key]);
        }, $text) ?? $text;
    }
}

if (!function_exists('pfgRenderEmailTemplate')) {
    /**
     * Validates required variables, strips prohibited sensitive fields, and renders a WCAG 2.2 AA
     * responsive HTML + plain-text multipart email from the centralized 29-template catalog.
     *
     * @throws InvalidArgumentException when template code is unknown or required variables are missing.
     */
    function pfgRenderEmailTemplate(string $templateCode, array $rawVariables = [], array $ephemeralVariables = []): array
    {
        $catalog = pfgGetEmailTemplateCatalog();
        $resolvedCode = pfgResolveTemplateCode($templateCode);

        if (!isset($catalog[$resolvedCode])) {
            throw new InvalidArgumentException("Unknown email template code: {$templateCode}");
        }

        $tpl = $catalog[$resolvedCode];
        $safeVars = pfgStripProhibitedEmailVariables($rawVariables);

        // Merge allowed ephemeral vars (e.g. single-use actionUrl) in memory only
        foreach ($tpl['ephemeralVars'] ?? [] as $eKey) {
            if (isset($ephemeralVariables[$eKey]) && is_string($ephemeralVariables[$eKey]) && trim($ephemeralVariables[$eKey]) !== '') {
                $safeVars[$eKey] = trim($ephemeralVariables[$eKey]);
            }
        }

        // Sensible defaults for optional variables (CAD-17: ZERO /dashboard or participant /login links)
        $baseSiteUrl = rtrim((string)APP_URL, '/');
        $defaults = [
            'expiresInText'    => '1 hour',
            'categoryName'     => 'Festival Participant',
            'status'           => 'CONFIRMED',
            'changedAtIst'     => formatIstTimestamp(),
            'occurredAtIst'    => formatIstTimestamp(),
            'staffLoginUrl'    => $baseSiteUrl . '/staff/login',
            'programmeUrl'     => $baseSiteUrl . '/programme',
            'accessibilityUrl' => $baseSiteUrl . '/accessibility',
            'websiteUrl'       => $baseSiteUrl . '/',
            'updateSummary'    => 'Your latest registration details have been updated.',
            'reasonSummary'    => '',
            'publicGuidance'   => '',
            'requestedDetails' => 'Please reply to this email or write to purplefestivalgujarat@gmail.com quoting your Registration ID.',
            'reminderDetails'  => 'Visit our website for the latest accessibility guide, venue directions, and 3-day schedule.',
        ];
        $vars = array_merge($defaults, array_filter($safeVars, static fn($v): bool => $v !== null && $v !== ''));

        // Validate required variables
        $missing = [];
        foreach ($tpl['requiredVars'] as $reqVar) {
            if (!isset($vars[$reqVar]) || trim((string)$vars[$reqVar]) === '') {
                $missing[] = $reqVar;
            }
        }
        if (!empty($missing)) {
            throw new InvalidArgumentException(
                sprintf('Template %s is missing required variable(s): %s', $resolvedCode, implode(', ', $missing))
            );
        }

        // Validate CTA action URL safely (reject javascript:/data:/CRLF injection; NEVER emit /dashboard for participants)
        $ctaUrl = '';
        if (!empty($vars['actionUrl']) && is_string($vars['actionUrl'])) {
            $validated = pfgValidateSafeUrl($vars['actionUrl'], false);
            if ($validated === null) {
                throw new InvalidArgumentException("Template {$resolvedCode} received an unsafe actionUrl.");
            }
            $ctaUrl = $validated;
        } elseif (in_array($resolvedCode, ['PASSWORD_RESET_CONFIRMATION', 'SECURITY_ALERT'], true)) {
            $ctaUrl = pfgValidateSafeUrl((string)$vars['staffLoginUrl'], false) ?? ($baseSiteUrl . '/staff/login');
        } elseif (in_array($resolvedCode, ['ACCESSIBILITY_REQUEST_RECEIVED', 'ACCESSIBILITY_ARRANGEMENT_UPDATED'], true)) {
            $ctaUrl = pfgValidateSafeUrl((string)$vars['accessibilityUrl'], false) ?? ($baseSiteUrl . '/accessibility');
        } elseif (in_array($resolvedCode, ['ATTENDEE_REGISTRATION_CONFIRMED', 'PROGRAMME_CHANGE_NOTICE', 'VOLUNTEER_APPLICATION_APPROVED', 'VOLUNTEER_ASSIGNMENT_CREATED', 'VOLUNTEER_ASSIGNMENT_UPDATED', 'SLI_APPLICATION_APPROVED', 'SLI_ASSIGNMENT_CREATED', 'SLI_ASSIGNMENT_UPDATED'], true)) {
            $ctaUrl = pfgValidateSafeUrl((string)$vars['programmeUrl'], false) ?? ($baseSiteUrl . '/programme');
        } else {
            $ctaUrl = pfgValidateSafeUrl((string)$vars['websiteUrl'], false) ?? ($baseSiteUrl . '/');
        }

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $subject = pfgInterpolatePlain($tpl['subject'], $vars);
        $heading = pfgInterpolatePlain($tpl['heading'], $vars);
        $preheader = pfgInterpolatePlain($tpl['preheader'], $vars);
        $fullName = trim((string)$vars['fullName']);
        $regCode = isset($vars['registrationCode']) ? trim((string)$vars['registrationCode']) : '';
        $categoryName = isset($vars['categoryName']) ? trim((string)$vars['categoryName']) : '';
        $statusDisplay = isset($vars['status']) ? trim((string)$vars['status']) : '';
        $supportEmail = defined('NOTIFICATION_SUPPORT_EMAIL') ? (string)NOTIFICATION_SUPPORT_EMAIL : 'purplefestivalgujarat@gmail.com';
        $festivalName = defined('FESTIVAL_NAME') ? (string)FESTIVAL_NAME : 'Purple Festival Gujarat 2027';
        $festivalDates = defined('FESTIVAL_DATES_DISPLAY') ? (string)FESTIVAL_DATES_DISPLAY : '21–23 January 2027';
        $festivalLoc = defined('FESTIVAL_LOCATION_DISPLAY') ? (string)FESTIVAL_LOCATION_DISPLAY : 'Vadodara, Gujarat';

        // Build body paragraphs (HTML-escaped + plain text)
        $bodyHtmlBlocks = [];
        $bodyTextBlocks = [];
        foreach ($tpl['bodyParagraphs'] as $rawPara) {
            $rendered = trim(pfgInterpolatePlain($rawPara, $vars));
            if ($rendered === '') {
                continue;
            }
            $bodyHtmlBlocks[] = '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:#1E1B26;">' . nl2br($esc($rendered)) . '</p>';
            $bodyTextBlocks[] = $rendered;
        }

        // Registration ID summary card (when applicable)
        $regCardHtml = '';
        $regCardText = '';
        if ($regCode !== '') {
            $metaRows = '<tr>'
                . '<td style="padding:6px 0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#4A3F63;">Official Registration ID</td>'
                . '<td style="padding:6px 0;font-family:\'Courier New\',Courier,monospace;font-size:18px;font-weight:700;color:#2B144D;text-align:right;">' . $esc($regCode) . '</td>'
                . '</tr>';
            $regCardText .= "\nOfficial Registration ID: {$regCode}";

            if ($categoryName !== '') {
                $metaRows .= '<tr>'
                    . '<td style="padding:4px 0;font-size:14px;color:#4A3F63;">Category</td>'
                    . '<td style="padding:4px 0;font-size:14px;font-weight:600;color:#1E1B26;text-align:right;">' . $esc($categoryName) . '</td>'
                    . '</tr>';
                $regCardText .= "\nCategory: {$categoryName}";
            }
            if ($statusDisplay !== '' && !in_array($tpl['group'], ['Account'], true)) {
                $metaRows .= '<tr>'
                    . '<td style="padding:4px 0;font-size:14px;color:#4A3F63;">Current Status</td>'
                    . '<td style="padding:4px 0;font-size:14px;font-weight:700;color:#2B144D;text-align:right;">' . $esc($statusDisplay) . '</td>'
                    . '</tr>';
                $regCardText .= "\nCurrent Status: {$statusDisplay}";
            }

            $regCardHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background-color:#F7F4FC;border:2px solid #D8CBEF;border-radius:10px;">'
                . '<tr><td style="padding:16px 20px;">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $metaRows . '</table>'
                . '</td></tr></table>';
        }

        // Optional structured duty/assignment schedule block
        $dutyHtml = '';
        $dutyText = '';
        if (isset($vars['dutyDetails']) && is_array($vars['dutyDetails'])) {
            $dutyRows = '';
            foreach ($vars['dutyDetails'] as $dLabel => $dVal) {
                if ($dVal === null || $dVal === '' || $dVal === []) {
                    continue;
                }
                $dValStr = is_array($dVal) ? implode(', ', array_map('strval', $dVal)) : (string)$dVal;
                $dutyRows .= '<tr>'
                    . '<td style="padding:6px 8px;border-bottom:1px solid #E5DCF5;font-size:14px;color:#4A3F63;width:38%;">' . $esc((string)$dLabel) . '</td>'
                    . '<td style="padding:6px 8px;border-bottom:1px solid #E5DCF5;font-size:14px;font-weight:600;color:#1E1B26;">' . $esc($dValStr) . '</td>'
                    . '</tr>';
                $dutyText .= "\n- {$dLabel}: {$dValStr}";
            }
            if ($dutyRows !== '') {
                $dutyHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background-color:#FFFFFF;border:1px solid #D8CBEF;border-radius:10px;">'
                    . '<tr><td style="padding:16px 20px;">'
                    . '<h2 style="margin:0 0 10px 0;font-size:16px;line-height:1.3;color:#2B144D;">Assigned Venue &amp; Duty Schedule</h2>'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $dutyRows . '</table>'
                    . '</td></tr></table>';
                $dutyText = "\n\nAssigned Venue & Duty Schedule:" . $dutyText;
            }
        }

        // Accessible high-contrast CTA button (10.2:1 contrast ratio) + plain URL fallback
        $ctaHtml = '';
        $ctaText = '';
        if ($ctaUrl !== '') {
            $ctaLabel = (string)($tpl['ctaLabel'] ?? 'Open Portal');
            $ctaHtml = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 16px 0;">'
                . '<tr><td align="left" bgcolor="#5B2A86" style="border-radius:8px;">'
                . '<a href="' . $esc($ctaUrl) . '" style="display:inline-block;padding:14px 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">'
                . $esc($ctaLabel) . '</a>'
                . '</td></tr></table>'
                . '<p style="margin:0 0 16px 0;font-size:13px;line-height:1.5;color:#4A3F63;word-break:break-all;">'
                . 'If the button does not work, copy and paste this link into your browser:<br>'
                . '<a href="' . $esc($ctaUrl) . '" style="color:#4A1D75;text-decoration:underline;">' . $esc($ctaUrl) . '</a>'
                . '</p>';
            $ctaText = "\n\n{$ctaLabel}: {$ctaUrl}";
        }

        $html = '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $esc($subject) . '</title>'
            . '</head>'
            . '<body style="margin:0;padding:0;background-color:#F4F0FA;font-family:Arial,Helvetica,sans-serif;color:#1E1B26;">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $esc($preheader) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F0FA;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#FFFFFF;border:1px solid #D8CBEF;border-radius:14px;overflow:hidden;">'
            // Branded Header (14.1:1 contrast; readable without images)
            . '<tr><td style="background-color:#2B144D;padding:24px 28px;color:#FFFFFF;">'
            . '<p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#EADCF8;">'
            . $esc($festivalDates . ' · ' . $festivalLoc)
            . '</p>'
            . '<h1 style="margin:8px 0 0 0;font-size:22px;line-height:1.3;font-weight:700;color:#FFFFFF;">'
            . $esc($festivalName)
            . '</h1>'
            . '</td></tr>'
            // Body Content
            . '<tr><td style="padding:28px;">'
            . '<h2 style="margin:0 0 16px 0;font-size:20px;line-height:1.35;color:#2B144D;">' . $esc($heading) . '</h2>'
            . '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:#1E1B26;">Dear <strong>' . $esc($fullName) . '</strong>,</p>'
            . implode("\n", $bodyHtmlBlocks)
            . $regCardHtml
            . $dutyHtml
            . $ctaHtml
            . '</td></tr>'
            // Accessible Footer
            . '<tr><td style="background-color:#F7F4FC;padding:20px 28px;border-top:1px solid #E5DCF5;">'
            . '<p style="margin:0 0 8px 0;font-size:13px;line-height:1.5;color:#4A3F63;">'
            . 'Need accessibility or registration assistance? Contact the Festival Secretariat at '
            . '<a href="mailto:' . $esc($supportEmail) . '" style="color:#4A1D75;font-weight:700;text-decoration:underline;">' . $esc($supportEmail) . '</a>.'
            . '</p>'
            . '<p style="margin:0;font-size:12px;line-height:1.5;color:#4A3F63;">'
            . $esc($festivalName . ' · ' . $festivalDates . ' · ' . $festivalLoc)
            . '</p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';

        $text = "{$festivalName} ({$festivalDates} · {$festivalLoc})\n"
            . str_repeat('=', 56) . "\n\n"
            . "{$heading}\n\n"
            . "Dear {$fullName},\n\n"
            . implode("\n\n", $bodyTextBlocks)
            . $regCardText
            . $dutyText
            . $ctaText
            . "\n\n---\n"
            . "Festival Secretariat & Accessibility Support: {$supportEmail}\n";

        return [
            'templateCode' => $resolvedCode,
            'eventType'    => $tpl['triggerEvent'],
            'subject'      => $subject,
            'html'         => $html,
            'text'         => $text,
        ];
    }
}

