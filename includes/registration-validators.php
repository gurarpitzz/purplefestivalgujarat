<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Strict Server-Side Registration Validators & Domain Constants (Phase 6)
 *
 * Enforces:
 * - Strictly 3 Primary Categories (ATTENDEE, VOLUNTEER, SLI) — CAD-02
 * - Configurable multi-category rule (D-09, default 1 active registration per primary category per account)
 * - Cross-category shared accessibility validation without requiring a disability diagnosis (CAD-07)
 * - Category-specific strict validation (FND-31): age bounds, MSU affiliation enum, 5 exact volunteer roles,
 *   4 SLI qualification tiers, 4 SLI experience bands, explicit Gujarat residency & public-stage comfort,
 *   safe URL validation, phone validation, max lengths, and required consents.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/registration-id.php';

if (!defined('PFG_ALLOW_MULTI_CATEGORY')) {
    define('PFG_ALLOW_MULTI_CATEGORY', true);
}

if (!defined('PFG_PRIMARY_CATEGORIES')) {
    define('PFG_PRIMARY_CATEGORIES', ['ATTENDEE', 'VOLUNTEER', 'SLI']);
}

if (!defined('PFG_VALID_DAYS')) {
    define('PFG_VALID_DAYS', ['DAY_1', 'DAY_2', 'DAY_3']);
}

if (!defined('PFG_SHIFT_SLOTS')) {
    define('PFG_SHIFT_SLOTS', [
        'DAY_1_SHIFT_1' => ['day' => 'DAY_1', 'date' => '2027-01-21', 'dayLabel' => 'Day 1 — Thu, 21 Jan 2027', 'shift' => 'SHIFT_1', 'shiftLabel' => 'Shift 1 — Morning (08:00–14:30 IST)'],
        'DAY_1_SHIFT_2' => ['day' => 'DAY_1', 'date' => '2027-01-21', 'dayLabel' => 'Day 1 — Thu, 21 Jan 2027', 'shift' => 'SHIFT_2', 'shiftLabel' => 'Shift 2 — Afternoon & Evening (14:00–20:30 IST)'],
        'DAY_2_SHIFT_1' => ['day' => 'DAY_2', 'date' => '2027-01-22', 'dayLabel' => 'Day 2 — Fri, 22 Jan 2027', 'shift' => 'SHIFT_1', 'shiftLabel' => 'Shift 1 — Morning (08:00–14:30 IST)'],
        'DAY_2_SHIFT_2' => ['day' => 'DAY_2', 'date' => '2027-01-22', 'dayLabel' => 'Day 2 — Fri, 22 Jan 2027', 'shift' => 'SHIFT_2', 'shiftLabel' => 'Shift 2 — Afternoon & Evening (14:00–20:30 IST)'],
        'DAY_3_SHIFT_1' => ['day' => 'DAY_3', 'date' => '2027-01-23', 'dayLabel' => 'Day 3 — Sat, 23 Jan 2027', 'shift' => 'SHIFT_1', 'shiftLabel' => 'Shift 1 — Morning (08:00–14:30 IST)'],
        'DAY_3_SHIFT_2' => ['day' => 'DAY_3', 'date' => '2027-01-23', 'dayLabel' => 'Day 3 — Sat, 23 Jan 2027', 'shift' => 'SHIFT_2', 'shiftLabel' => 'Shift 2 — Afternoon & Evening (14:00–20:30 IST)'],
    ]);
}

if (!defined('PFG_ATTENDEE_AGE_GROUPS')) {
    define('PFG_ATTENDEE_AGE_GROUPS', [
        'UNDER_18' => 'Under 18',
        '18_35'    => '18–35',
        '36_59'    => '36–59',
        '60_PLUS'  => '60 and above',
    ]);
}

if (!defined('PFG_GENDER_OPTIONS')) {
    define('PFG_GENDER_OPTIONS', [
        'Female',
        'Male',
        'Non-binary',
        'Other',
        'Prefer not to say',
    ]);
}

if (!defined('PFG_ATTENDEE_SELF_ID_TAGS')) {
    define('PFG_ATTENDEE_SELF_ID_TAGS', [
        'Parent / Family Member / Caregiver',
        'Student',
        'Teacher / Special Educator',
        'Professional (Therapist, Social Worker, Doctor, Lawyer, Researcher, Corporate)',
        'Athlete / Coach',
        'Representative of an Organisation, NGO, or DPO',
        'Government Official',
        'Media',
        'Ally / General Public',
    ]);
}

if (!defined('PFG_ATTENDEE_TRACKS')) {
    define('PFG_ATTENDEE_TRACKS', [
        'Opening ceremony and cultural programme',
        'Purple March, led by Paralympic medallists',
        'We Care Film Festival screenings',
        'Panel discussions and sessions',
        'Para sports — as a spectator',
        'Exhibition / stalls (Purple Fair)',
        'Closing ceremony and cultural programme',
    ]);
}

if (!defined('PFG_PARA_SPORTS_EVENTS')) {
    define('PFG_PARA_SPORTS_EVENTS', [
        'Athletics',
        'Wheelchair Basketball',
        'Blind Cricket',
        'Deaf Sports',
        'Special Olympics Events',
        'Para Badminton',
        'Boccia',
        'Other',
    ]);
}

if (!defined('PFG_TRAVEL_MODES')) {
    define('PFG_TRAVEL_MODES', ['Train', 'Bus', 'Flight', 'Own vehicle']);
}

if (!defined('PFG_FOOD_PREFERENCES')) {
    define('PFG_FOOD_PREFERENCES', ['Vegetarian', 'Jain', 'Vegan', 'Non-Vegetarian']);
}

if (!defined('PFG_GUARDIAN_RELATIONSHIPS')) {
    define('PFG_GUARDIAN_RELATIONSHIPS', [
        'Parent',
        'Legal Guardian',
        'School Representative',
        'Organisation Representative',
        'Caregiver',
        'Other',
    ]);
}

if (!defined('PFG_EMERGENCY_RELATIONSHIPS')) {
    define('PFG_EMERGENCY_RELATIONSHIPS', [
        'Parent',
        'Spouse/Partner',
        'Sibling',
        'Relative',
        'Friend',
        'Colleague',
        'Caregiver',
        'Other',
    ]);
}

if (!defined('PFG_VOLUNTEER_MSU_AFFILIATIONS')) {
    define('PFG_VOLUNTEER_MSU_AFFILIATIONS', [
        'CURRENT_STUDENT' => 'Yes, currently enrolled at MSU Baroda',
        'ALUMNI'          => 'Yes, MSU Baroda Alumni',
        'EXTERNAL'        => 'No (External / Community / Other Institution)',
    ]);
}

if (!defined('PFG_VOLUNTEER_PRIMARY_ROLES')) {
    define('PFG_VOLUNTEER_PRIMARY_ROLES', [
        'Stage Anchoring / Hosting',
        'Event Logistics & Venue Management',
        'Guest Registration & Hospitality',
        'Accessibility Assistance / Peer Guide',
        'Technical / Media Support',
    ]);
}

if (!defined('PFG_VOLUNTEER_LANGUAGES')) {
    define('PFG_VOLUNTEER_LANGUAGES', [
        'Gujarati',
        'Hindi',
        'English',
        'Indian Sign Language (ISL)',
        'Marathi',
        'Other',
    ]);
}

if (!defined('PFG_SLI_QUALIFICATION_TIERS')) {
    define('PFG_SLI_QUALIFICATION_TIERS', [
        'CERTIFIED_ISL'         => 'Certified Indian Sign Language (ISL) Interpreter (e.g., ISLRTC / RCI recognized)',
        'COMMUNITY_DEAF_SIGNER' => 'Professional Practitioner / Deaf Interpreter / Fluent Community Signer',
        'STUDENT_TRAINEE'       => 'Student / Trainee Interpreter',
        'OTHER'                 => 'Other',
    ]);
}

if (!defined('PFG_SLI_EXPERIENCE_BANDS')) {
    define('PFG_SLI_EXPERIENCE_BANDS', [
        'LT_1'   => 'Less than 1 year',
        '1_TO_3' => '1 – 3 years',
        '3_TO_5' => '3 – 5 years',
        '5_PLUS' => '5+ years',
    ]);
}

if (!defined('PFG_SLI_EXPERTISE_AREAS')) {
    define('PFG_SLI_EXPERTISE_AREAS', [
        'Plenary & Keynote Sessions',
        'Panel Discussions & Policy',
        'Workshops & Education',
        'Cultural & Stage Performances',
        'Tactile / Deafblind Interpretation',
        'Para-Sports & Community Events',
    ]);
}

if (!defined('PFG_SLI_WORKING_PREFERENCES')) {
    define('PFG_SLI_WORKING_PREFERENCES', [
        'ISL ↔ Gujarati',
        'ISL ↔ Hindi',
        'ISL ↔ English',
        'Team Interpreting (20–30 min rotations)',
        'Platform / Stage Interpreting',
    ]);
}

if (!defined('PFG_INDIAN_STATES')) {
    define('PFG_INDIAN_STATES', [
        'Gujarat',
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chhattisgarh',
        'Goa',
        'Haryana',
        'Himachal Pradesh',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal',
        'Andaman and Nicobar Islands',
        'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi',
        'Jammu and Kashmir',
        'Ladakh',
        'Lakshadweep',
        'Puducherry',
        'Outside India',
    ]);
}

if (!defined('PFG_CANONICAL_ACCOMMODATION_CODES')) {
    define('PFG_CANONICAL_ACCOMMODATION_CODES', [
        'ISL_INTERPRETER',
        'TACTILE_INTERPRETATION',
        'CAPTIONING',
        'LIVE_CAPTIONS',
        'AUDIO_DESCRIPTION',
        'BRAILLE_SCHEDULE',
        'LARGE_PRINT',
        'EASY_READ_MATERIAL',
        'SCREEN_READER_DIGITAL',
        'WHEELCHAIR_SEATING',
        'WHEELCHAIR_LOAN',
        'WHEELCHAIR_CHARGING',
        'MOBILITY_ASSISTANCE',
        'ACCESSIBLE_STATION_TRANSPORT',
        'SIGHTED_GUIDE',
        'SENSORY_SANCTUARY',
        'ONSITE_MEDICAL_SUPPORT',
        'DIETARY',
        'DIETARY_ACCESS_SUPPORT',
        'OTHER',
    ]);
}

if (!function_exists('pfgEncryptSensitiveValue')) {
    function pfgEncryptSensitiveValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        return pfgEncryptValue($trimmed, 'data');
    }
}

if (!function_exists('pfgDecryptSensitiveValue')) {
    function pfgDecryptSensitiveValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return pfgDecryptValue($value, 'data');
    }
}

if (!function_exists('pfgDecryptDraftPayload')) {
    function pfgDecryptDraftPayload(array $payload): array
    {
        foreach (['disabilitySelfDescription', 'accommodationFreeText', 'escortName', 'dietaryRequirements'] as $k) {
            if (isset($payload[$k]) && is_string($payload[$k]) && $payload[$k] !== '') {
                $payload[$k] = (string)(pfgDecryptSensitiveValue($payload[$k]) ?? '');
            }
        }
        if (isset($payload['attendeeDetails']) && is_array($payload['attendeeDetails'])) {
            if (
                isset($payload['attendeeDetails']['dietaryRequirements'])
                && is_string($payload['attendeeDetails']['dietaryRequirements'])
                && $payload['attendeeDetails']['dietaryRequirements'] !== ''
            ) {
                $payload['attendeeDetails']['dietaryRequirements'] = (string)(pfgDecryptSensitiveValue($payload['attendeeDetails']['dietaryRequirements']) ?? '');
            }
        }
        return $payload;
    }
}

if (!function_exists('pfgAllocateRegistrationCode')) {
    function pfgAllocateRegistrationCode(PDO $pdo, ?string $editionId = null): string
    {
        return allocateNextRegistrationCode($pdo, $editionId);
    }
}

if (!function_exists('pfgIsMultiCategoryAllowed')) {
    /**
     * Reads configurable D-09 multi-category policy from `system_settings` (fallback PFG_ALLOW_MULTI_CATEGORY).
     */
    function pfgIsMultiCategoryAllowed(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'allow_multi_category_registration' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null && trim((string)$val) !== '') {
                $parsed = filter_var(trim((string)$val), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        } catch (Throwable $e) {
            // Fall through to default constant
        }
        return (bool)PFG_ALLOW_MULTI_CATEGORY;
    }
}

if (!function_exists('pfgIsValidPhone')) {
    function pfgIsValidPhone(string $phone): bool
    {
        $trimmed = trim($phone);
        if (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $trimmed)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        $digitLen = strlen($digits);
        return $digitLen >= 7 && $digitLen <= 15;
    }
}

if (!function_exists('pfgParseExplicitBoolean')) {
    /**
     * Parses an explicit boolean ('yes'/'no', true/false, 1/0). Returns null if missing/unrecognized.
     */
    function pfgParseExplicitBoolean($val): ?bool
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val)) {
            if ($val === 1) {
                return true;
            }
            if ($val === 0) {
                return false;
            }
            return null;
        }
        if (is_string($val)) {
            $norm = strtolower(trim($val));
            if (in_array($norm, ['true', '1', 'yes', 'y'], true)) {
                return true;
            }
            if (in_array($norm, ['false', '0', 'no', 'n'], true)) {
                return false;
            }
        }
        return null;
    }
}

if (!function_exists('pfgNormalizeStringList')) {
    function pfgNormalizeStringList($value): array
    {
        if (is_string($value)) {
            $decoded = jsonDecode($value, null);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $s = trim((string)$item);
            if ($s !== '' && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }
}

if (!function_exists('pfgDeriveAttendingDaysFromShifts')) {
    function pfgDeriveAttendingDaysFromShifts(array $shifts): array
    {
        $days = [];
        foreach ($shifts as $slot) {
            $code = strtoupper(trim((string)$slot));
            if (isset(PFG_SHIFT_SLOTS[$code])) {
                $day = PFG_SHIFT_SLOTS[$code]['day'];
                if (!in_array($day, $days, true)) {
                    $days[] = $day;
                }
            }
        }
        usort($days, static function (string $a, string $b): int {
            return array_search($a, PFG_VALID_DAYS, true) <=> array_search($b, PFG_VALID_DAYS, true);
        });
        return $days;
    }
}

if (!function_exists('pfgNormalizeAgeGroup')) {
    function pfgNormalizeAgeGroup(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        $u = strtoupper(str_replace(['–', '—', '-', ' '], '_', $t));
        $map = [
            'UNDER_18'      => 'UNDER_18',
            '18_35'         => '18_35',
            '18_TO_35'      => '18_35',
            '36_59'         => '36_59',
            '36_TO_59'      => '36_59',
            '60_PLUS'       => '60_PLUS',
            '60_AND_ABOVE'  => '60_PLUS',
            '60+'           => '60_PLUS',
        ];
        return $map[$u] ?? null;
    }
}

if (!function_exists('pfgNormalizeMsuAffiliation')) {
    function pfgNormalizeMsuAffiliation(string $raw): ?string
    {
        $u = strtoupper(trim($raw));
        $map = [
            'CURRENT_STUDENT'     => 'CURRENT_STUDENT',
            'MSU_CURRENT_STUDENT' => 'CURRENT_STUDENT',
            'ALUMNI'              => 'ALUMNI',
            'MSU_ALUMNI'          => 'ALUMNI',
            'EXTERNAL'            => 'EXTERNAL',
            'NO'                  => 'EXTERNAL',
        ];
        return $map[$u] ?? null;
    }
}

if (!function_exists('pfgNormalizeVolunteerRole')) {
    function pfgNormalizeVolunteerRole(string $raw): ?string
    {
        $t = trim($raw);
        if (in_array($t, PFG_VOLUNTEER_PRIMARY_ROLES, true)) {
            return $t;
        }
        $codeMap = [
            'STAGE_ANCHORING'                => 'Stage Anchoring / Hosting',
            'EVENT_LOGISTICS'                => 'Event Logistics & Venue Management',
            'GUEST_REGISTRATION_HOSPITALITY' => 'Guest Registration & Hospitality',
            'ACCESSIBILITY_PEER_GUIDE'       => 'Accessibility Assistance / Peer Guide',
            'TECHNICAL_MEDIA'                => 'Technical / Media Support',
        ];
        $u = strtoupper($t);
        return $codeMap[$u] ?? null;
    }
}

if (!function_exists('pfgNormalizeSliQualificationTier')) {
    function pfgNormalizeSliQualificationTier(string $raw): ?string
    {
        $u = strtoupper(trim($raw));
        $map = [
            'CERTIFIED_ISL'               => 'CERTIFIED_ISL',
            'CERTIFIED_ISL_INTERPRETER'   => 'CERTIFIED_ISL',
            'COMMUNITY_DEAF_SIGNER'       => 'COMMUNITY_DEAF_SIGNER',
            'PRACTITIONER_DEAF_COMMUNITY' => 'COMMUNITY_DEAF_SIGNER',
            'STUDENT_TRAINEE'             => 'STUDENT_TRAINEE',
            'OTHER'                       => 'OTHER',
        ];
        return $map[$u] ?? null;
    }
}

if (!function_exists('pfgNormalizeSliExperienceBand')) {
    function pfgNormalizeSliExperienceBand(string $raw): ?string
    {
        $u = strtoupper(trim($raw));
        $map = [
            'LT_1'         => 'LT_1',
            'LT_1_YEAR'    => 'LT_1',
            '1_TO_3'       => '1_TO_3',
            '1_3_YEARS'    => '1_TO_3',
            '3_TO_5'       => '3_TO_5',
            '3_5_YEARS'    => '3_TO_5',
            '5_PLUS'       => '5_PLUS',
            '5_PLUS_YEARS' => '5_PLUS',
        ];
        return $map[$u] ?? null;
    }
}

if (!function_exists('pfgLoadValidDisabilityMap')) {
    function pfgLoadValidDisabilityMap(PDO $pdo): array
    {
        $map = [];
        try {
            $stmt = $pdo->query('SELECT id, code FROM disability_categories');
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $code = strtoupper(trim((string)$row['code']));
                    if ($code !== '') {
                        $map[$code] = (string)$row['id'];
                    }
                }
            }
        } catch (Throwable $e) {
        }
        if (empty($map) && defined('RPWD_21_DISABILITIES')) {
            foreach (RPWD_21_DISABILITIES as $d) {
                $code = strtoupper((string)$d['code']);
                $map[$code] = $code;
            }
        }
        return $map;
    }
}

if (!function_exists('pfgLoadValidAccommodationCodes')) {
    function pfgLoadValidAccommodationCodes(PDO $pdo): array
    {
        $codes = PFG_CANONICAL_ACCOMMODATION_CODES;
        try {
            $stmt = $pdo->query('SELECT code FROM accommodation_types WHERE is_active = 1');
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) {
                    $c = strtoupper(trim((string)$code));
                    if ($c !== '' && !in_array($c, $codes, true)) {
                        $codes[] = $c;
                    }
                }
            }
        } catch (Throwable $e) {
        }
        return $codes;
    }
}

if (!function_exists('pfgValidateAccessibilityPayload')) {
    /**
     * Validates Shared Accessibility & Accommodations fields (CAD-07).
     * Strictly allows requesting accommodations WITHOUT disclosing any disability diagnosis.
     */
    function pfgValidateAccessibilityPayload(PDO $pdo, array $body): array
    {
        $errors = [];

        $rawPwd = strtoupper(trim((string)($body['isPersonWithDisability'] ?? $body['is_person_with_disability'] ?? 'PREFER_NOT_TO_SAY')));
        if ($rawPwd === '') {
            $rawPwd = 'PREFER_NOT_TO_SAY';
        }
        if (!in_array($rawPwd, ['YES', 'NO', 'PREFER_NOT_TO_SAY'], true)) {
            $errors['isPersonWithDisability'] = 'Please choose a valid disability disclosure option (Yes, No, or Prefer not to say).';
            $rawPwd = 'PREFER_NOT_TO_SAY';
        }

        $disabilityCodes = pfgNormalizeStringList($body['disabilityCategoryCodes'] ?? $body['disability_category_codes'] ?? []);
        $disabilitySelfDescription = trim((string)($body['disabilitySelfDescription'] ?? $body['disability_self_description'] ?? ''));

        $validDisMap = pfgLoadValidDisabilityMap($pdo);
        $resolvedDisabilities = [];

        if ($rawPwd === 'YES') {
            foreach ($disabilityCodes as $rawCode) {
                $code = strtoupper($rawCode);
                if (!isset($validDisMap[$code])) {
                    $errors['disabilityCategoryCodes'] = "Unknown disability category code: {$rawCode}.";
                    break;
                }
                $resolvedDisabilities[$code] = $validDisMap[$code];
            }
            if (mb_strlen($disabilitySelfDescription) > 1000) {
                $errors['disabilitySelfDescription'] = 'Disability self-description must not exceed 1,000 characters.';
            }
        } else {
            // Privacy guarantee: zero disability disclosure rows persisted if user did not choose YES
            $disabilityCodes = [];
            $disabilitySelfDescription = '';
            $resolvedDisabilities = [];
        }

        // Accommodations are ALWAYS allowed regardless of disability disclosure answer (CAD-07)
        $rawAccommTypes = pfgNormalizeStringList($body['accommodationTypes'] ?? $body['accommodation_types'] ?? []);
        $validAccommCodes = pfgLoadValidAccommodationCodes($pdo);
        $cleanAccommTypes = [];
        foreach ($rawAccommTypes as $aCode) {
            $upper = strtoupper($aCode);
            if (!in_array($upper, $validAccommCodes, true)) {
                $errors['accommodationTypes'] = "Unknown accommodation type: {$aCode}.";
                break;
            }
            if (!in_array($upper, $cleanAccommTypes, true)) {
                $cleanAccommTypes[] = $upper;
            }
        }

        $accommFreeText = trim((string)($body['accommodationFreeText'] ?? $body['accommodation_free_text'] ?? ''));
        if (mb_strlen($accommFreeText) > 1000) {
            $errors['accommodationFreeText'] = 'Accessibility support notes must not exceed 1,000 characters.';
        }

        $escortAccompanying = filter_var(
            $body['escortAccompanying'] ?? $body['escort_accompanying'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $escortName = trim((string)($body['escortName'] ?? $body['escort_name'] ?? ''));
        if (!$escortAccompanying) {
            $escortName = '';
        } elseif ($escortName !== '' && mb_strlen($escortName) > 150) {
            $errors['escortName'] = 'Personal assistant / escort name must not exceed 150 characters.';
        }

        return [
            'errors' => $errors,
            'data'   => [
                'isPersonWithDisability'    => $rawPwd,
                'disabilityCategoryCodes'   => array_keys($resolvedDisabilities),
                'resolvedDisabilityMap'     => $resolvedDisabilities,
                'disabilitySelfDescription' => $disabilitySelfDescription,
                'accommodationTypes'        => $cleanAccommTypes,
                'accommodationFreeText'     => $accommFreeText,
                'escortAccompanying'        => $escortAccompanying,
                'escortName'                => $escortName,
            ],
        ];
    }
}

if (!function_exists('pfgValidateEmergencyContact')) {
    function pfgValidateEmergencyContact(array $body, bool $required, bool $relationshipRequired = false): array
    {
        $errors = [];
        $name = trim((string)($body['emergencyContactName'] ?? $body['emergency_contact_name'] ?? ''));
        $rel = trim((string)($body['emergencyContactRelationship'] ?? $body['emergency_contact_relationship'] ?? ''));
        $phone = trim((string)($body['emergencyContactPhone'] ?? $body['emergency_contact_phone'] ?? ''));

        $anyProvided = ($name !== '' || $rel !== '' || $phone !== '');

        if ($required || $anyProvided) {
            if ($name === '' && $required) {
                $errors['emergencyContactName'] = 'Emergency contact name is required.';
            } elseif ($name !== '' && (mb_strlen($name) < 2 || mb_strlen($name) > 150)) {
                $errors['emergencyContactName'] = 'Emergency contact name must be between 2 and 150 characters.';
            }

            if ($rel === '' && $relationshipRequired) {
                $errors['emergencyContactRelationship'] = 'Emergency contact relationship is required.';
            } elseif ($rel !== '' && mb_strlen($rel) > 80) {
                $errors['emergencyContactRelationship'] = 'Emergency contact relationship must not exceed 80 characters.';
            }

            if ($phone === '' && $required) {
                $errors['emergencyContactPhone'] = 'Emergency contact phone number is required.';
            } elseif ($phone !== '' && !pfgIsValidPhone($phone)) {
                $errors['emergencyContactPhone'] = 'Please enter a valid emergency contact phone number (7–15 digits).';
            }
        }

        return [
            'errors' => $errors,
            'data'   => [
                'emergencyContactName'         => $name !== '' ? $name : null,
                'emergencyContactRelationship' => $rel !== '' ? $rel : null,
                'emergencyContactPhone'        => $phone !== '' ? $phone : null,
            ],
        ];
    }
}

if (!function_exists('pfgValidateConsents')) {
    function pfgValidateConsents(array $body, bool $isUpdate = false): array
    {
        $errors = [];

        $zeroTol = filter_var(
            $body['consentZeroTolerance'] ?? $body['consentCodeOfConduct'] ?? $body['consent_code_of_conduct'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $dpdp = filter_var(
            $body['consentDataProcessing'] ?? $body['consentDataProcessingDpdp'] ?? $body['consent_data_processing'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $accuracy = filter_var(
            $body['consentAccuracy'] ?? $body['consentAccuracyDeclaration'] ?? $body['consent_accuracy'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $media = filter_var(
            $body['consentMediaPhotography'] ?? $body['consentPhotographyMedia'] ?? $body['consent_media_photography'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $futureUpdates = filter_var(
            $body['consentFutureUpdates'] ?? $body['consent_future_updates'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$isUpdate) {
            if (!$zeroTol) {
                $errors['consentZeroTolerance'] = 'You must agree to the Code of Conduct and zero-tolerance policy towards harassment.';
            }
            if (!$dpdp) {
                $errors['consentDataProcessing'] = 'You must consent to data processing under the DPDP Act 2023.';
            }
            if (!$accuracy) {
                $errors['consentAccuracy'] = 'You must confirm the Information Accuracy declaration.';
            }
        }

        return [
            'errors' => $errors,
            'data'   => [
                'consentZeroTolerance'    => $zeroTol,
                'consentDataProcessing'   => $dpdp,
                'consentAccuracy'         => $accuracy,
                'consentMediaPhotography' => $media,
                'consentFutureUpdates'    => $futureUpdates,
            ],
        ];
    }
}

if (!function_exists('pfgValidateRegistrationPayload')) {
    /**
     * Master server-side validator for all 3 primary categories (ATTENDEE, VOLUNTEER, SLI).
     *
     * @return array{valid: bool, errors: array<string,string>, error: ?string, data: array}
     */
    function pfgValidateRegistrationPayload(
        PDO $pdo,
        string $categoryCode,
        array $body,
        ?array $accountProfile = null,
        bool $isUpdate = false
    ): array {
        $cat = strtoupper(trim($categoryCode));
        $errors = [];

        if (!in_array($cat, PFG_PRIMARY_CATEGORIES, true)) {
            return [
                'valid'  => false,
                'errors' => ['categoryCode' => 'Only the three primary categories (ATTENDEE, VOLUNTEER, SLI) are open for registration.'],
                'error'  => 'Only the three primary categories (ATTENDEE, VOLUNTEER, SLI) are open for registration.',
                'data'   => [],
            ];
        }

        $profile = is_array($accountProfile) ? $accountProfile : [];

        // Accountless public registration: applicant enters personal details directly in the form.
        $fullName = trim((string)($body['fullName'] ?? $body['full_name'] ?? $profile['full_name'] ?? $profile['fullName'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? $profile['email'] ?? '')));
        $phone = trim((string)($body['phone'] ?? $profile['phone'] ?? ''));
        $country = trim((string)($body['country'] ?? $profile['country'] ?? 'India'));
        if ($country === '') {
            $country = 'India';
        }
        $cityDistrict = trim((string)($body['cityDistrict'] ?? $body['city_district'] ?? $profile['city_district'] ?? ''));
        $state = trim((string)($body['state'] ?? $profile['state'] ?? ''));
        $preferredLanguage = trim((string)($body['preferredLanguage'] ?? $body['preferred_language'] ?? $profile['preferred_language'] ?? ''));

        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 200) {
            $errors['fullName'] = 'Full name is required (2–200 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($phone === '' || !pfgIsValidPhone($phone)) {
            $errors['phone'] = 'Please enter a valid phone number (7–15 digits).';
        }
        if (mb_strlen($country) < 2 || mb_strlen($country) > 100) {
            $errors['country'] = 'Country must be between 2 and 100 characters.';
        }
        if ($cityDistrict !== '' && mb_strlen($cityDistrict) > 120) {
            $errors['cityDistrict'] = 'City / District must not exceed 120 characters.';
        }
        if ($state !== '' && mb_strlen($state) > 100) {
            $errors['state'] = 'State must not exceed 100 characters.';
        }
        if ($preferredLanguage !== '' && mb_strlen($preferredLanguage) > 32) {
            $errors['preferredLanguage'] = 'Preferred language must not exceed 32 characters.';
        }

        $organizationName = trim((string)($body['organizationName'] ?? $body['organization_name'] ?? ''));
        $designation = trim((string)($body['designation'] ?? ''));
        if (mb_strlen($organizationName) > 200) {
            $errors['organizationName'] = 'Organisation / Institution name must not exceed 200 characters.';
        }
        if (mb_strlen($designation) > 150) {
            $errors['designation'] = 'Designation / Course must not exceed 150 characters.';
        }

        // Shared Accessibility validation (CAD-07)
        $accessRes = pfgValidateAccessibilityPayload($pdo, $body);
        $errors = array_merge($errors, $accessRes['errors']);

        // Shared Consents validation
        $consentRes = pfgValidateConsents($body, $isUpdate);
        $errors = array_merge($errors, $consentRes['errors']);

        $attendingDays = [];
        $interestedTracks = [];
        $occupation = null;
        $categoryData = [];
        $emergencyRequired = false;
        $emergencyRelRequired = false;

        // ====================================================================
        // CATEGORY 1: ATTENDEE
        // ====================================================================
        if ($cat === 'ATTENDEE') {
            $ad = isset($body['attendeeDetails']) && is_array($body['attendeeDetails']) ? $body['attendeeDetails'] : $body;

            $rawAgeGroup = (string)($ad['ageGroup'] ?? $ad['age_group'] ?? '');
            $ageGroup = pfgNormalizeAgeGroup($rawAgeGroup);
            if ($ageGroup === null) {
                $errors['ageGroup'] = 'Please select your age group.';
            }

            $gender = trim((string)($ad['gender'] ?? ''));
            if ($gender !== '' && !in_array($gender, PFG_GENDER_OPTIONS, true)) {
                $errors['gender'] = 'Please select a valid gender option.';
            }

            $registeringOnBehalf = filter_var($ad['registeringOnBehalf'] ?? $ad['registering_on_behalf'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $needsGuardian = ($ageGroup === 'UNDER_18' || $registeringOnBehalf);

            $guardianName = trim((string)($ad['guardianName'] ?? $ad['guardian_name'] ?? ''));
            $guardianRel = trim((string)($ad['guardianRelationship'] ?? $ad['guardian_relationship'] ?? ''));
            $guardianPhone = trim((string)($ad['guardianPhone'] ?? $ad['guardian_phone'] ?? ''));

            if ($needsGuardian) {
                if (mb_strlen($guardianName) < 2 || mb_strlen($guardianName) > 150) {
                    $errors['guardianName'] = 'Parent / Legal Guardian full name is required (2–150 characters).';
                }
                if ($guardianRel === '' || !in_array($guardianRel, PFG_GUARDIAN_RELATIONSHIPS, true)) {
                    $errors['guardianRelationship'] = 'Please select the guardian relationship.';
                }
                if ($guardianPhone !== '' && !pfgIsValidPhone($guardianPhone)) {
                    $errors['guardianPhone'] = 'Please enter a valid guardian phone number.';
                }
            } else {
                $guardianName = '';
                $guardianRel = '';
                $guardianPhone = '';
            }

            $selfIdTags = pfgNormalizeStringList($ad['selfIdentificationTags'] ?? $ad['self_identification_tags'] ?? []);
            if (empty($selfIdTags)) {
                $errors['selfIdentificationTags'] = 'Please select at least one option under "I am attending as".';
            } else {
                foreach ($selfIdTags as $tag) {
                    if (!in_array($tag, PFG_ATTENDEE_SELF_ID_TAGS, true)) {
                        $errors['selfIdentificationTags'] = "Invalid self-identification option: {$tag}.";
                        break;
                    }
                }
            }
            $occupation = !empty($selfIdTags) ? mb_substr($selfIdTags[0], 0, 120) : 'Attendee';

            $rawDays = pfgNormalizeStringList($body['attendingDays'] ?? $body['attending_days'] ?? []);
            if (empty($rawDays)) {
                $errors['attendingDays'] = 'Please select at least one festival day you plan to attend.';
            } else {
                foreach ($rawDays as $d) {
                    $du = strtoupper($d);
                    if (!in_array($du, PFG_VALID_DAYS, true)) {
                        $errors['attendingDays'] = "Invalid festival day selected: {$d}.";
                        break;
                    }
                    if (!in_array($du, $attendingDays, true)) {
                        $attendingDays[] = $du;
                    }
                }
            }

            $rawTracks = pfgNormalizeStringList($body['interestedTracks'] ?? $body['interested_tracks'] ?? []);
            foreach ($rawTracks as $trk) {
                if (!in_array($trk, PFG_ATTENDEE_TRACKS, true)) {
                    $errors['interestedTracks'] = "Invalid programme track selected: {$trk}.";
                    break;
                }
                $interestedTracks[] = $trk;
            }

            $paraSportsAthlete = filter_var($ad['paraSportsAthlete'] ?? $ad['para_sports_athlete'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $paraSportsEvents = $paraSportsAthlete
                ? pfgNormalizeStringList($ad['paraSportsEvents'] ?? $ad['para_sports_events'] ?? [])
                : [];
            $paraSportsOther = $paraSportsAthlete
                ? trim((string)($ad['paraSportsOther'] ?? $ad['para_sports_other'] ?? ''))
                : '';
            if ($paraSportsAthlete) {
                foreach ($paraSportsEvents as $ev) {
                    if (!in_array($ev, PFG_PARA_SPORTS_EVENTS, true)) {
                        $errors['paraSportsEvents'] = "Invalid Para-Sports event: {$ev}.";
                        break;
                    }
                }
                if (mb_strlen($paraSportsOther) > 120) {
                    $errors['paraSportsOther'] = 'Other Para-Sports specification must not exceed 120 characters.';
                }
            }

            $outstation = filter_var($ad['outstationTravelRequired'] ?? $ad['outstation_travel_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $arrivalAt = $outstation ? trim((string)($ad['arrivalAt'] ?? $ad['arrival_at'] ?? '')) : '';
            $departureAt = $outstation ? trim((string)($ad['departureAt'] ?? $ad['departure_at'] ?? '')) : '';
            $travelMode = $outstation ? trim((string)($ad['travelMode'] ?? $ad['travel_mode'] ?? '')) : '';
            $lodgingNeeded = $outstation && filter_var($ad['lodgingNeeded'] ?? $ad['lodging_needed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $accessibleRoomNeeded = $lodgingNeeded && filter_var($ad['accessibleRoomNeeded'] ?? $ad['accessible_room_needed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $accompanyingRaw = $outstation ? ($ad['accompanyingCount'] ?? $ad['accompanying_count'] ?? null) : null;
            $accompanyingCount = null;

            if ($outstation) {
                if (mb_strlen($arrivalAt) > 40) {
                    $errors['arrivalAt'] = 'Arrival date/time must not exceed 40 characters.';
                }
                if (mb_strlen($departureAt) > 40) {
                    $errors['departureAt'] = 'Departure date/time must not exceed 40 characters.';
                }
                if ($arrivalAt !== '' && $departureAt !== '') {
                    $arrTs = strtotime($arrivalAt);
                    $depTs = strtotime($departureAt);
                    if ($arrTs !== false && $depTs !== false && $depTs < $arrTs) {
                        $errors['departureAt'] = 'Departure date/time cannot be earlier than arrival date/time.';
                    }
                }
                if ($travelMode !== '' && !in_array($travelMode, PFG_TRAVEL_MODES, true)) {
                    $errors['travelMode'] = 'Please select a valid mode of travel.';
                }
                if ($accompanyingRaw !== null && $accompanyingRaw !== '') {
                    if (!is_numeric($accompanyingRaw) || (int)$accompanyingRaw < 0 || (int)$accompanyingRaw > 10) {
                        $errors['accompanyingCount'] = 'Number of accompanying travellers must be between 0 and 10.';
                    } else {
                        $accompanyingCount = (int)$accompanyingRaw;
                    }
                }
            }

            $foodPreference = trim((string)($ad['foodPreference'] ?? $ad['food_preference'] ?? ''));
            if ($foodPreference !== '' && !in_array($foodPreference, PFG_FOOD_PREFERENCES, true)) {
                $errors['foodPreference'] = 'Please select a valid food preference.';
            }

            $dietaryRequirements = trim((string)($ad['dietaryRequirements'] ?? $ad['dietary_requirements'] ?? ''));
            if (mb_strlen($dietaryRequirements) > 300) {
                $errors['dietaryRequirements'] = 'Dietary needs or allergies must not exceed 300 characters.';
            }

            // Emergency contact required for Under-18 Attendees, optional for adult Attendees
            $emergencyRequired = ($ageGroup === 'UNDER_18');
            $emergencyRelRequired = false;

            $categoryData = [
                'ageGroup'                 => $ageGroup,
                'gender'                   => $gender !== '' ? $gender : null,
                'registeringOnBehalf'      => $registeringOnBehalf,
                'selfIdentificationTags'   => $selfIdTags,
                'guardianName'             => $guardianName !== '' ? $guardianName : null,
                'guardianRelationship'     => $guardianRel !== '' ? $guardianRel : null,
                'guardianPhone'            => $guardianPhone !== '' ? $guardianPhone : null,
                'paraSportsAthlete'        => $paraSportsAthlete,
                'paraSportsEvents'         => $paraSportsEvents,
                'paraSportsOther'          => $paraSportsOther !== '' ? $paraSportsOther : null,
                'outstationTravelRequired' => $outstation,
                'arrivalAt'                => $arrivalAt !== '' ? $arrivalAt : null,
                'departureAt'              => $departureAt !== '' ? $departureAt : null,
                'travelMode'               => $travelMode !== '' ? $travelMode : null,
                'lodgingNeeded'            => $lodgingNeeded,
                'accessibleRoomNeeded'     => $accessibleRoomNeeded,
                'accompanyingCount'        => $accompanyingCount,
                'foodPreference'           => $foodPreference !== '' ? $foodPreference : null,
                'dietaryRequirements'      => $dietaryRequirements !== '' ? $dietaryRequirements : null,
            ];
        }

        // ====================================================================
        // CATEGORY 2: VOLUNTEER
        // ====================================================================
        if ($cat === 'VOLUNTEER') {
            $vd = isset($body['volunteerDetails']) && is_array($body['volunteerDetails']) ? $body['volunteerDetails'] : $body;

            $rawAge = $vd['age'] ?? null;
            $age = null;
            if ($rawAge === null || $rawAge === '' || !is_numeric($rawAge) || (int)$rawAge != (float)$rawAge) {
                $errors['age'] = 'Please enter your age in whole years (minimum 16 years).';
            } else {
                $age = (int)$rawAge;
                if ($age < 16 || $age > 85) {
                    $errors['age'] = 'Volunteers must be between 16 and 85 years of age.';
                }
            }

            $gender = trim((string)($vd['gender'] ?? ''));
            if ($gender !== '' && !in_array($gender, PFG_GENDER_OPTIONS, true)) {
                $errors['gender'] = 'Please select a valid gender option.';
            }

            $rawMsu = (string)($vd['msuAffiliation'] ?? $vd['msu_affiliation'] ?? $vd['msuAffiliationStatus'] ?? $vd['msu_affiliation_status'] ?? '');
            $msuAffiliation = pfgNormalizeMsuAffiliation($rawMsu);
            if ($msuAffiliation === null) {
                $errors['msuAffiliation'] = 'Please indicate your MSU Baroda affiliation (Current Student, Alumni, or External).';
            }

            $msuFaculty = trim((string)($vd['msuFacultyDepartment'] ?? $vd['msu_faculty_department'] ?? ''));
            $instName = trim((string)($vd['institutionName'] ?? $vd['institution_name'] ?? $organizationName));
            if (in_array($msuAffiliation, ['CURRENT_STUDENT', 'ALUMNI'], true)) {
                if (mb_strlen($msuFaculty) < 2 || mb_strlen($msuFaculty) > 150) {
                    $errors['msuFacultyDepartment'] = 'Please enter your MSU Baroda Faculty / Department name (2–150 characters).';
                }
                if ($instName === '') {
                    $instName = 'The Maharaja Sayajirao University of Baroda';
                }
            } else {
                $msuFaculty = '';
                if (mb_strlen($instName) > 200) {
                    $errors['institutionName'] = 'Institution / NSS / NCC / Organisation name must not exceed 200 characters.';
                }
            }

            // CRR number strictly optional on Volunteer (D-14)
            $crrNumber = trim((string)($vd['crrNumber'] ?? $vd['crr_number'] ?? ''));
            if ($crrNumber !== '' && !preg_match('/^[A-Za-z0-9\/\-]{3,40}$/', $crrNumber)) {
                $errors['crrNumber'] = 'CRR number must be 3–40 characters using letters, numbers, hyphens, or slashes.';
            }

            $rawPrimaryRole = (string)($vd['primaryRole'] ?? $vd['primary_role'] ?? '');
            $primaryRole = pfgNormalizeVolunteerRole($rawPrimaryRole);
            if ($primaryRole === null) {
                $errors['primaryRole'] = 'Please select one of the 5 primary volunteer roles.';
            }

            $rawSecondaryRoles = pfgNormalizeStringList($vd['secondaryRoles'] ?? $vd['preferredRoles'] ?? $vd['preferred_roles'] ?? []);
            $allowedSecondary = array_merge(PFG_VOLUNTEER_PRIMARY_ROLES, ['Para-Sports Support']);
            $secondaryRoles = [];
            foreach ($rawSecondaryRoles as $sr) {
                $normSr = pfgNormalizeVolunteerRole($sr) ?? $sr;
                if (!in_array($normSr, $allowedSecondary, true)) {
                    $errors['secondaryRoles'] = "Invalid secondary volunteer role: {$sr}.";
                    break;
                }
                if ($normSr !== $primaryRole && !in_array($normSr, $secondaryRoles, true)) {
                    $secondaryRoles[] = $normSr;
                }
            }

            $anchoringExp = trim((string)($vd['anchoringExperience'] ?? $vd['anchoring_experience'] ?? ''));
            if ($primaryRole === 'Stage Anchoring / Hosting') {
                if (mb_strlen($anchoringExp) < 10 || mb_strlen($anchoringExp) > 1000) {
                    $errors['anchoringExperience'] = 'Please describe your public speaking or stage hosting experience (10–1,000 characters).';
                }
            } elseif (mb_strlen($anchoringExp) > 1000) {
                $errors['anchoringExperience'] = 'Stage hosting experience must not exceed 1,000 characters.';
            }

            $relevantExp = trim((string)($vd['relevantExperience'] ?? $vd['relevant_experience'] ?? ''));
            if (mb_strlen($relevantExp) > 1000) {
                $errors['relevantExperience'] = 'Relevant volunteering experience must not exceed 1,000 characters.';
            }

            $languagesSpoken = pfgNormalizeStringList($vd['languagesSpoken'] ?? $vd['languages_spoken'] ?? []);
            if (empty($languagesSpoken)) {
                $errors['languagesSpoken'] = 'Please select at least one language you speak.';
            } else {
                foreach ($languagesSpoken as $lang) {
                    if (!in_array($lang, PFG_VOLUNTEER_LANGUAGES, true)) {
                        $errors['languagesSpoken'] = "Invalid language option: {$lang}.";
                        break;
                    }
                }
            }

            $languagesOther = trim((string)($vd['languagesOther'] ?? $vd['languages_other'] ?? ''));
            if (in_array('Other', $languagesSpoken, true)) {
                if ($languagesOther !== '' && (mb_strlen($languagesOther) < 2 || mb_strlen($languagesOther) > 80)) {
                    $errors['languagesOther'] = 'Other language specification must be between 2 and 80 characters.';
                }
            } else {
                $languagesOther = '';
            }

            $rawShifts = pfgNormalizeStringList($vd['availabilityShifts'] ?? $vd['availability_shifts'] ?? $body['availabilityShifts'] ?? []);
            if (empty($rawShifts)) {
                $errors['availabilityShifts'] = 'Please select at least one festival day and shift slot.';
            } else {
                $cleanShifts = [];
                foreach ($rawShifts as $slot) {
                    $su = strtoupper($slot);
                    if (!isset(PFG_SHIFT_SLOTS[$su])) {
                        $errors['availabilityShifts'] = "Invalid availability shift slot: {$slot}.";
                        break;
                    }
                    if (!in_array($su, $cleanShifts, true)) {
                        $cleanShifts[] = $su;
                    }
                }
                $rawShifts = $cleanShifts;
            }
            $attendingDays = pfgDeriveAttendingDaysFromShifts($rawShifts);

            $occupation = $msuAffiliation === 'CURRENT_STUDENT' ? 'MSU Student Volunteer' : ($msuAffiliation === 'ALUMNI' ? 'MSU Alumni Volunteer' : 'Community Volunteer');

            $emergencyRequired = true;
            $emergencyRelRequired = true;

            $allRoles = $primaryRole !== null ? array_values(array_unique(array_merge([$primaryRole], $secondaryRoles))) : $secondaryRoles;

            $categoryData = [
                'age'                  => $age,
                'gender'               => $gender !== '' ? $gender : null,
                'msuAffiliationStatus' => $msuAffiliation,
                'isMsuStudentOrAlumni' => in_array($msuAffiliation, ['CURRENT_STUDENT', 'ALUMNI'], true),
                'msuFacultyDepartment' => $msuFaculty !== '' ? $msuFaculty : null,
                'institutionName'      => $instName !== '' ? $instName : null,
                'crrNumber'            => $crrNumber !== '' ? $crrNumber : null,
                'primaryRole'          => $primaryRole,
                'preferredRoles'       => $allRoles,
                'anchoringExperience'  => $anchoringExp !== '' ? $anchoringExp : null,
                'relevantExperience'   => $relevantExp !== '' ? $relevantExp : null,
                'languagesSpoken'      => $languagesSpoken,
                'languagesOther'       => $languagesOther !== '' ? $languagesOther : null,
                'availabilityShifts'   => $rawShifts,
                'availabilityDays'     => $attendingDays,
            ];
        }

        // ====================================================================
        // CATEGORY 3: SIGN LANGUAGE INTERPRETER (SLI)
        // ====================================================================
        if ($cat === 'SLI') {
            $sd = isset($body['sliDetails']) && is_array($body['sliDetails']) ? $body['sliDetails'] : $body;

            $rawAge = $sd['age'] ?? null;
            $age = null;
            if ($rawAge === null || $rawAge === '' || !is_numeric($rawAge) || (int)$rawAge != (float)$rawAge) {
                $errors['age'] = 'Please enter your age in whole years (minimum 18 years).';
            } else {
                $age = (int)$rawAge;
                if ($age < 18 || $age > 85) {
                    $errors['age'] = 'Sign Language Interpreter applicants must be between 18 and 85 years of age.';
                }
            }

            // Explicit Gujarat residency context (D-12) — NEVER default silently to true!
            $sliCity = trim((string)($sd['cityDistrict'] ?? $sd['city_district'] ?? $cityDistrict));
            $sliState = trim((string)($sd['state'] ?? $state));
            if (mb_strlen($sliCity) < 2 || mb_strlen($sliCity) > 120) {
                $errors['cityDistrict'] = 'Please enter your current city / district (2–120 characters).';
            } else {
                $cityDistrict = $sliCity;
            }
            if (mb_strlen($sliState) < 2 || mb_strlen($sliState) > 100) {
                $errors['state'] = 'Please select your current state / union territory.';
            } else {
                $state = $sliState;
            }

            $basedInGujarat = pfgParseExplicitBoolean($sd['basedInGujarat'] ?? $sd['based_in_gujarat'] ?? null);
            if ($basedInGujarat === null) {
                $errors['basedInGujarat'] = 'Please explicitly indicate whether you are currently based in Gujarat (Yes or No).';
            }

            $profAffiliation = trim((string)($sd['professionalAffiliation'] ?? $sd['professional_affiliation'] ?? $organizationName));
            if (mb_strlen($profAffiliation) > 200) {
                $errors['professionalAffiliation'] = 'Professional affiliation must not exceed 200 characters.';
            }

            $rawQualCat = (string)($sd['qualificationCategory'] ?? $sd['qualification_category'] ?? '');
            $qualCat = pfgNormalizeSliQualificationTier($rawQualCat);
            if ($qualCat === null) {
                $errors['qualificationCategory'] = 'Please select your Sign Language qualification category.';
            }

            $qualDetail = trim((string)($sd['qualifications'] ?? $sd['qualificationDetail'] ?? ''));
            if ($qualCat === 'OTHER') {
                if (mb_strlen($qualDetail) < 2 || mb_strlen($qualDetail) > 200) {
                    $errors['qualifications'] = 'Please specify your qualification or background (2–200 characters).';
                }
            } elseif (mb_strlen($qualDetail) > 200) {
                $errors['qualifications'] = 'Qualification details must not exceed 200 characters.';
            }

            // CRR number optional across all tiers (D-13 — never hard-block Deaf interpreters/trainees)
            $crrNumber = trim((string)($sd['crrNumber'] ?? $sd['crr_number'] ?? ''));
            if ($crrNumber !== '' && !preg_match('/^[A-Za-z0-9\/\-]{3,40}$/', $crrNumber)) {
                $errors['crrNumber'] = 'RCI CRR number must be 3–40 characters using letters, numbers, hyphens, or slashes.';
            }

            $rawExpBand = (string)($sd['experienceBand'] ?? $sd['experience_band'] ?? '');
            $expBand = pfgNormalizeSliExperienceBand($rawExpBand);
            if ($expBand === null) {
                $errors['experienceBand'] = 'Please select your years of interpretation experience.';
            }

            $trainingInstitute = trim((string)($sd['trainingInstitute'] ?? $sd['training_institute'] ?? ''));
            if (mb_strlen($trainingInstitute) > 200) {
                $errors['trainingInstitute'] = 'Training institute name must not exceed 200 characters.';
            }

            $rawVideoUrl = trim((string)($sd['videoDemoUrl'] ?? $sd['video_demo_url'] ?? ''));
            $videoDemoUrl = null;
            if ($rawVideoUrl !== '') {
                if (strlen($rawVideoUrl) > 500) {
                    $errors['videoDemoUrl'] = 'Video demonstration URL must not exceed 500 characters.';
                } else {
                    $safeUrl = pfgValidateSafeUrl($rawVideoUrl, false);
                    if ($safeUrl === null) {
                        $errors['videoDemoUrl'] = 'Please enter a valid http:// or https:// video demonstration URL.';
                    } else {
                        $videoDemoUrl = $safeUrl;
                    }
                }
            }

            $comfortStage = pfgParseExplicitBoolean($sd['comfortablePublicStage'] ?? $sd['comfortable_public_stage'] ?? null);
            if ($comfortStage === null) {
                $errors['comfortablePublicStage'] = 'Please indicate whether you are comfortable interpreting for cultural performances, panels, and official ceremonies (Yes or No).';
            }

            $rawShifts = pfgNormalizeStringList($sd['availabilityShifts'] ?? $sd['availability_shifts'] ?? $body['availabilityShifts'] ?? []);
            if (empty($rawShifts)) {
                $errors['availabilityShifts'] = 'Please select at least one festival day and shift slot.';
            } else {
                $cleanShifts = [];
                foreach ($rawShifts as $slot) {
                    $su = strtoupper($slot);
                    if (!isset(PFG_SHIFT_SLOTS[$su])) {
                        $errors['availabilityShifts'] = "Invalid availability shift slot: {$slot}.";
                        break;
                    }
                    if (!in_array($su, $cleanShifts, true)) {
                        $cleanShifts[] = $su;
                    }
                }
                $rawShifts = $cleanShifts;
            }
            $attendingDays = pfgDeriveAttendingDaysFromShifts($rawShifts);

            $areasOfExpertise = pfgNormalizeStringList($sd['areasOfExpertise'] ?? $sd['areas_of_expertise'] ?? []);
            foreach ($areasOfExpertise as $area) {
                if (!in_array($area, PFG_SLI_EXPERTISE_AREAS, true)) {
                    $errors['areasOfExpertise'] = "Invalid interpreting expertise area: {$area}.";
                    break;
                }
            }

            $interpPrefs = pfgNormalizeStringList($sd['interpretationPreferences'] ?? $sd['interpretation_preferences'] ?? []);
            foreach ($interpPrefs as $pref) {
                if (!in_array($pref, PFG_SLI_WORKING_PREFERENCES, true)) {
                    $errors['interpretationPreferences'] = "Invalid language pair / working preference: {$pref}.";
                    break;
                }
            }

            $honorariumNotes = trim((string)($sd['honorariumOrTravelNotes'] ?? $sd['honorarium_or_travel_notes'] ?? ''));
            if (mb_strlen($honorariumNotes) > 500) {
                $errors['honorariumOrTravelNotes'] = 'Honorarium / travel support notes must not exceed 500 characters.';
            }

            $occupation = 'Sign Language Interpreter';
            $emergencyRequired = true;
            $emergencyRelRequired = true;

            $categoryData = [
                'age'                       => $age,
                'cityState'                 => trim($cityDistrict . ($state !== '' ? ', ' . $state : '')),
                'basedInGujarat'            => (bool)$basedInGujarat,
                'outstationReviewTag'       => ($basedInGujarat === false),
                'professionalAffiliation'   => $profAffiliation !== '' ? $profAffiliation : null,
                'qualificationCategory'     => $qualCat,
                'certificationLevel'        => $qualCat !== null ? (PFG_SLI_QUALIFICATION_TIERS[$qualCat] ?? $qualCat) : null,
                'qualifications'            => $qualDetail !== '' ? $qualDetail : null,
                'crrNumber'                 => $crrNumber !== '' ? $crrNumber : null,
                'experienceBand'            => $expBand,
                'trainingInstitute'         => $trainingInstitute !== '' ? $trainingInstitute : null,
                'videoDemoUrl'              => $videoDemoUrl,
                'comfortablePublicStage'    => (bool)$comfortStage,
                'availabilityShifts'        => $rawShifts,
                'availabilityDays'          => $attendingDays,
                'areasOfExpertise'          => $areasOfExpertise,
                'interpretationPreferences' => $interpPrefs,
                'honorariumOrTravelNotes'   => $honorariumNotes !== '' ? $honorariumNotes : null,
            ];
        }

        // Validate Emergency Contact (check top-level body, fallback to nested details if sent there)
        $ecInput = [
            'emergencyContactName'         => $body['emergencyContactName'] ?? $body['emergency_contact_name'] ?? ($body['volunteerDetails']['emergencyContactName'] ?? $body['sliDetails']['emergencyContactName'] ?? $body['attendeeDetails']['emergencyContactName'] ?? ''),
            'emergencyContactRelationship' => $body['emergencyContactRelationship'] ?? $body['emergency_contact_relationship'] ?? ($body['volunteerDetails']['emergencyContactRelationship'] ?? $body['sliDetails']['emergencyContactRelationship'] ?? $body['attendeeDetails']['emergencyContactRelationship'] ?? ''),
            'emergencyContactPhone'        => $body['emergencyContactPhone'] ?? $body['emergency_contact_phone'] ?? ($body['volunteerDetails']['emergencyContactPhone'] ?? $body['sliDetails']['emergencyContactPhone'] ?? $body['attendeeDetails']['emergencyContactPhone'] ?? ''),
        ];
        $ecRes = pfgValidateEmergencyContact($ecInput, $emergencyRequired, $emergencyRelRequired);
        $errors = array_merge($errors, $ecRes['errors']);

        if (!empty($errors)) {
            $firstError = (string)reset($errors);
            return [
                'valid'  => false,
                'errors' => $errors,
                'error'  => $firstError,
                'data'   => [],
            ];
        }

        return [
            'valid'  => true,
            'errors' => [],
            'error'  => null,
            'data'   => [
                'categoryCode'     => $cat,
                'fullName'         => $fullName,
                'email'            => $email,
                'phone'            => $phone,
                'country'           => $country,
                'preferredLanguage' => $preferredLanguage !== '' ? $preferredLanguage : null,
                'cityDistrict'      => $cityDistrict !== '' ? $cityDistrict : null,
                'state'            => $state !== '' ? $state : null,
                'occupation'       => $occupation,
                'organizationName' => $organizationName !== '' ? $organizationName : null,
                'designation'      => $designation !== '' ? $designation : null,
                'attendingDays'    => $attendingDays,
                'interestedTracks' => $interestedTracks,
                'accessibility'    => $accessRes['data'],
                'emergencyContact' => $ecRes['data'],
                'consents'         => $consentRes['data'],
                'categoryData'     => $categoryData,
            ],
        ];
    }
}
