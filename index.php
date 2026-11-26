<?php
declare(strict_types=1);

if (is_file(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}
if (is_file(__DIR__ . '/includes/database.php')) {
    require_once __DIR__ . '/includes/database.php';
}

$FALLBACK_DISABILITIES = defined('RPWD_21_DISABILITIES') && is_array(RPWD_21_DISABILITIES)
    ? RPWD_21_DISABILITIES
    : [
        [
            'code' => 'BLINDNESS',
            'name' => 'Blindness',
            'rpwdGroup' => 'Visual Impairment',
            'description' => 'Total absence of sight, or visual acuity not exceeding 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 10 degrees.',
            'accommodationExamples' => ['Braille schedules and signage', 'Screen-reader-compatible digital materials', 'Sighted guides and tactile floor pathways', 'Audio description during stage performances'],
        ],
        [
            'code' => 'LOW_VISION',
            'name' => 'Low Vision',
            'rpwdGroup' => 'Visual Impairment',
            'description' => 'Visual acuity not exceeding 6/18 or less than 20/60 up to 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 40 degrees up to 10 degrees.',
            'accommodationExamples' => ['Large-print schedules and high-contrast signage', 'Front-row seating near screens', 'Adjustable text size and high-contrast digital access'],
        ],
        [
            'code' => 'LEPROSY_CURED',
            'name' => 'Leprosy Cured Persons',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'Persons cured of leprosy who continue to experience loss of sensation in hands or feet, paresis in the eye and eyelid, or manifest deformity that limits daily participation.',
            'accommodationExamples' => ['Ergonomic seating and minimal-grip utensils', 'Accessible washrooms and step-free routes', 'Dignified, stigma-free assistance desks'],
        ],
        [
            'code' => 'HEARING_IMPAIRMENT',
            'name' => 'Hearing Impairment (Deaf and Hard of Hearing)',
            'rpwdGroup' => 'Hearing Impairment',
            'description' => 'Includes Deaf persons (70 dB or greater loss in speech frequencies in both ears) and Hard of Hearing persons (60 dB to 70 dB loss in speech frequencies in both ears).',
            'accommodationExamples' => ['Qualified Indian Sign Language (ISL) interpretation', 'Live captioning (CART) on venue screens', 'Visual alerts and well-lit front seating for lip-reading'],
        ],
        [
            'code' => 'LOCOMOTOR_DISABILITY',
            'name' => 'Locomotor Disability',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'Inability to execute distinctive activities associated with movement of self and objects resulting from affliction of musculoskeletal or nervous system.',
            'accommodationExamples' => ['Step-free ramps (1:12 gradient) and elevators', 'Wheelchair-accessible seating and charging bays', 'Accessible restrooms and battery-operated venue shuttles'],
        ],
        [
            'code' => 'DWARFISM',
            'name' => 'Dwarfism',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A medical or genetic condition resulting in an adult height of 4 feet 10 inches (147 centimeters) or less.',
            'accommodationExamples' => ['Lowered registration and refreshment counters', 'Accessible microphones and seating options', 'Step-free access across all stages and pavilions'],
        ],
        [
            'code' => 'INTELLECTUAL_DISABILITY',
            'name' => 'Intellectual Disability',
            'rpwdGroup' => 'Intellectual Disability',
            'description' => 'A condition characterized by significant limitation both in intellectual functioning (reasoning, learning, problem solving) and in adaptive behavior covering everyday social and practical skills.',
            'accommodationExamples' => ['Easy-read plain-language schedules with pictograms', 'Trained orientation volunteers and buddy support', 'Predictable signage and calm rest areas'],
        ],
        [
            'code' => 'MENTAL_ILLNESS',
            'name' => 'Mental Illness',
            'rpwdGroup' => 'Mental Behavior',
            'description' => 'A substantial disorder of thinking, mood, perception, orientation, or memory that grossly impairs judgment, behavior, capacity to recognize reality, or ability to meet the ordinary demands of life.',
            'accommodationExamples' => ['Quiet sensory-friendly sanctuary spaces', 'Flexible breaks without losing seating', 'Trained psychosocial first-aid volunteers'],
        ],
        [
            'code' => 'AUTISM_SPECTRUM_DISORDER',
            'name' => 'Autism Spectrum Disorder',
            'rpwdGroup' => 'Intellectual / Neurodevelopmental',
            'description' => 'A neuro-developmental condition typically appearing in the first three years of life that significantly affects verbal and non-verbal communication, social interaction, and sensory processing.',
            'accommodationExamples' => ['Low-stimulation sensory sanctuary rooms', 'Noise-dampening headphones and visual schedules', 'Advance venue walkthroughs and reduced-queue entry'],
        ],
        [
            'code' => 'CEREBRAL_PALSY',
            'name' => 'Cerebral Palsy',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A group of non-progressive neurological conditions affecting body movements and muscle coordination caused by damage to one or more specific areas of the brain.',
            'accommodationExamples' => ['Wide wheelchair pathways and supportive seating', 'AAC (Augmentative & Alternative Communication) patience', 'Accessible restrooms with caregiver space'],
        ],
        [
            'code' => 'MUSCULAR_DYSTROPHY',
            'name' => 'Muscular Dystrophy',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A group of hereditary genetic muscle diseases that weaken the muscles that move the human body, characterized by progressive skeletal muscle weakness.',
            'accommodationExamples' => ['Power wheelchair charging bays', 'Short-distance accessible drop-off points', 'Climate-controlled rest zones'],
        ],
        [
            'code' => 'CHRONIC_NEUROLOGICAL',
            'name' => 'Chronic Neurological Conditions',
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'Conditions originating in the central or peripheral nervous system (such as epilepsy, stroke aftermath, or neurodegenerative conditions) requiring ongoing management.',
            'accommodationExamples' => ['Seizure-safe lighting (no flashing strobes)', 'Priority shaded seating and hydration stations', 'On-site medical emergency response desk'],
        ],
        [
            'code' => 'SPECIFIC_LEARNING_DISABILITIES',
            'name' => 'Specific Learning Disabilities',
            'rpwdGroup' => 'Intellectual / Neurodevelopmental',
            'description' => 'A heterogeneous group of conditions wherein there is a deficit in processing language, spoken or written, manifesting as difficulty in comprehending, reading, writing, spelling, or mathematical calculation.',
            'accommodationExamples' => ['Readable sans-serif typography and uncluttered layouts', 'Audio and visual alternatives to dense text', 'Untimed, save-and-resume registration workflows'],
        ],
        [
            'code' => 'MULTIPLE_SCLEROSIS',
            'name' => 'Multiple Sclerosis',
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'An inflammatory, nervous system disease in which the myelin sheaths around the axons of nerve cells of the brain and spinal cord are damaged, affecting communication between brain and body.',
            'accommodationExamples' => ['Cooling and shaded rest areas to prevent heat fatigue', 'Mobility assistance and priority seating', 'Accessible restrooms near every hall'],
        ],
        [
            'code' => 'SPEECH_AND_LANGUAGE',
            'name' => 'Speech and Language Disability',
            'rpwdGroup' => 'Speech & Language',
            'description' => 'A permanent disability arising out of conditions such as laryngectomy or aphasia affecting one or more components of speech and language.',
            'accommodationExamples' => ['Text, email, and digital chat support at help desks', 'Extended speaking time and AAC-friendly microphones', 'Written Q&A options during panel discussions'],
        ],
        [
            'code' => 'THALASSEMIA',
            'name' => 'Thalassemia',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'A group of inherited disorders characterized by reduced or absent amounts of hemoglobin.',
            'accommodationExamples' => ['Priority seating to reduce standing fatigue', 'Hydration and dietary accommodation support', 'Quick access to medical assistance desks'],
        ],
        [
            'code' => 'HEMOPHILIA',
            'name' => 'Hemophilia',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'An inheritable disease, usually affecting males, characterized by impairment of the normal clotting ability of blood.',
            'accommodationExamples' => ['Safe, uncrowded pathways and priority entry lanes', 'On-site medical room awareness and rapid first aid', 'Accessible seating near aisles'],
        ],
        [
            'code' => 'SICKLE_CELL_DISEASE',
            'name' => 'Sickle Cell Disease',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'A hemolytic disorder characterized by chronic anemia, painful events, and various complications due to associated tissue and organ damage.',
            'accommodationExamples' => ['Shaded rest lounges and continuous hydration stations', 'Minimal walking distances with shuttle support', 'Medical assistance readiness'],
        ],
        [
            'code' => 'MULTIPLE_DISABILITIES',
            'name' => 'Multiple Disabilities (including Deafblindness)',
            'rpwdGroup' => 'Multiple Disabilities',
            'description' => 'Two or more of the specified disabilities occurring together, including deafblindness — a combination of hearing and visual impairments causing severe communication and developmental needs.',
            'accommodationExamples' => ['Tactile sign language interpreters and intervener support', 'Combined physical, sensory, and communication accommodations', 'Dedicated companion / escort seating'],
        ],
        [
            'code' => 'ACID_ATTACK_VICTIM',
            'name' => 'Acid Attack Victim',
            'rpwdGroup' => 'Locomotor / Physical',
            'description' => 'A person disfigured due to violent assaults by throwing of acid or similar corrosive substance.',
            'accommodationExamples' => ['Climate-controlled, sun-protected spaces', 'Visual or mobility accommodations as individually requested', 'Respectful, privacy-preserving check-in and support'],
        ],
        [
            'code' => 'PARKINSONS_DISEASE',
            'name' => "Parkinson's Disease",
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'A progressive disease of the nervous system marked by tremor, muscular rigidity, and slow, imprecise movement.',
            'accommodationExamples' => ['Large touch targets and keyboard-accessible controls', 'Seated check-in desks and handrails along walkways', 'Untimed forms and mobility assistance'],
        ],
    ];

$FALLBACK_PROGRAMME = [
    [
        'id' => 'prog-1',
        'dayNumber' => 1,
        'timeSlot' => '08:30 – 09:30 IST',
        'title' => 'Participant Check-in, Accessible Welcome & Registration Desk',
        'category' => 'Registration',
        'venueName' => 'Sama Indoor Stadium & MSU Baroda',
        'description' => 'Registration ID (PFG-2027-NNNNNN) verification at the Vadodara Registration Desk, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.',
    ],
    [
        'id' => 'prog-2',
        'dayNumber' => 1,
        'timeSlot' => '09:30 – 11:00 IST',
        'title' => 'Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity',
        'category' => 'Plenary',
        'venueName' => 'Main Auditorium',
        'description' => "Opening keynote addresses celebrating Gujarat's inaugural Purple Festival edition and India's inclusive future under Viksit Bharat@2047.",
    ],
    [
        'id' => 'prog-3',
        'dayNumber' => 1,
        'timeSlot' => '11:30 – 13:00 IST',
        'title' => 'Panel 1: Universal Design & Accessible Infrastructure for 2047',
        'category' => 'Panel Discussion',
        'venueName' => 'Hall A',
        'description' => 'Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.',
    ],
    [
        'id' => 'prog-4',
        'dayNumber' => 1,
        'timeSlot' => '14:00 – 16:30 IST',
        'title' => 'Para Sports Championships — Opening Heats (25 Adaptive Disciplines)',
        'category' => 'Inclusive Sports',
        'venueName' => 'Sama Indoor Stadium',
        'description' => 'Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.',
    ],
    [
        'id' => 'prog-5',
        'dayNumber' => 1,
        'timeSlot' => '14:30 – 17:00 IST',
        'title' => 'Assistive Technology & Inclusive Innovation Expo',
        'category' => 'Exhibition',
        'venueName' => 'Innovation Pavilion',
        'description' => 'Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.',
    ],
    [
        'id' => 'prog-6',
        'dayNumber' => 1,
        'timeSlot' => '18:30 – 20:30 IST',
        'title' => 'Evening Cultural Showcase: Disability Arts & Inclusive Theatre',
        'category' => 'Cultural Performance',
        'venueName' => 'Open-Air Stage',
        'description' => "Performances by artists with and without disabilities celebrating Gujarat's folk heritage, contemporary dance, and sign-language music.",
    ],
];

$FALLBACK_PARTNERS = [
    [
        'partnerId' => 'p-1',
        'category' => 'Organisers',
        'orgName' => 'PRAANGAN, Vadodara',
        'shortName' => 'PRAANGAN',
        'logoUrl' => '/assets/logos/praangan.jpg',
        'logoAltText' => 'PRAANGAN, Vadodara logo',
        'description' => 'A centre for children with special needs.',
    ],
    [
        'partnerId' => 'p-2',
        'category' => 'Organisers',
        'orgName' => 'Brotherhood',
        'shortName' => 'Brotherhood',
        'logoUrl' => '/assets/logos/brotherhood-transparent.png',
        'logoAltText' => 'Brotherhood official logo',
        'description' => 'Rights and inclusion of persons with disabilities.',
    ],
    [
        'partnerId' => 'p-3',
        'category' => 'Organisers',
        'orgName' => 'Global Inclusive Education Network',
        'shortName' => 'GIEN',
        'logoUrl' => '/assets/logos/gien.jpg',
        'logoAltText' => 'Global Inclusive Education Network (GIEN) official logo',
        'description' => 'Making education inclusive for every child.',
    ],
    [
        'partnerId' => 'p-4',
        'category' => 'Venue Partners',
        'orgName' => 'Faculty of Social Work, MSU Baroda',
        'shortName' => 'MSU Baroda (FSW)',
        'logoUrl' => '/assets/logos/msu-baroda.png',
        'logoAltText' => 'The Maharaja Sayajirao University of Baroda crest',
        'description' => 'Venue partner hosting academic sessions and inclusive dialogues.',
    ],
    [
        'partnerId' => 'p-5',
        'category' => 'Venue Partners',
        'orgName' => 'Department of Physical Education, MSU Baroda',
        'shortName' => 'MSU Baroda (DPE)',
        'logoUrl' => '/assets/logos/msu-baroda.png',
        'logoAltText' => 'The Maharaja Sayajirao University of Baroda crest',
        'description' => 'Venue partner coordinating adaptive sports and Para Sports.',
    ],
    [
        'partnerId' => 'p-6',
        'category' => 'Venue Partners',
        'orgName' => 'GMERS Medical College and Hospital, Gotri',
        'shortName' => 'GMERS Gotri',
        'logoUrl' => '/assets/logos/gmers-gotri.png',
        'logoAltText' => 'GMERS Medical College and Hospital, Gotri crest',
        'description' => 'Venue and medical readiness partner.',
    ],
    [
        'partnerId' => 'p-7',
        'category' => 'Knowledge Partners',
        'orgName' => 'State Commissioner for Persons with Disabilities, Goa',
        'shortName' => 'SCPD Goa',
        'logoUrl' => '/assets/logos/scpd-goa.png',
        'logoAltText' => 'State Commission for Persons with Disabilities - Goa logo',
        'description' => 'Knowledge Partner supporting Purple Fest exchange.',
    ],
    [
        'partnerId' => 'p-8',
        'category' => 'Knowledge Partners',
        'orgName' => 'Department for Empowerment of Persons with Disabilities, Goa',
        'shortName' => 'DEPwD Goa',
        'logoUrl' => '/assets/logos/depwd-goa.png',
        'logoAltText' => 'Department for Empowerment of Persons with Disabilities, Goa logo',
        'description' => 'Knowledge Partner advancing accessibility practices.',
    ],
    [
        'partnerId' => 'p-9',
        'category' => 'Academic Partner',
        'orgName' => 'CDSA, Tata Institute of Social Sciences, Mumbai',
        'shortName' => 'CDSA-TISS',
        'logoUrl' => '/assets/logos/cdsa-tiss.jpg',
        'logoAltText' => 'CDSA, Tata Institute of Social Sciences, Mumbai logo',
        'description' => 'Academic Partner advancing disability studies and social work research.',
    ],
    [
        'partnerId' => 'p-10',
        'category' => 'Legal Partner',
        'orgName' => 'Gujarat National Law University, Gandhinagar',
        'shortName' => 'GNLU Gandhinagar',
        'logoUrl' => '/assets/logos/gnlu.png',
        'logoAltText' => 'Gujarat National Law University, Gandhinagar logo',
        'description' => 'Legal Partner supporting disability rights and RPwD Act 2016 awareness.',
    ],
    [
        'partnerId' => 'p-11',
        'category' => 'Education Partner',
        'orgName' => 'AYJNIHH, Mumbai',
        'shortName' => 'AYJNISHD(D)',
        'logoUrl' => '/assets/logos/ayjnihh.png',
        'logoAltText' => 'AYJNIHH Mumbai official logo',
        'description' => 'Education Partner supporting speech, hearing, and ISL inclusion.',
    ],
];

$FALLBACK_FAQS = [
    [
        'id' => 'faq-1',
        'category' => 'Registration',
        'question' => 'Do I need to disclose a disability to attend Purple Festival Gujarat 2027?',
        'answer' => 'No. Disability disclosure is completely optional, no diagnosis is required to request accommodations, and disclosure never restricts which category you may apply for. Persons with disabilities can register across any of our three registration categories: Attendee, Volunteer, or Sign Language Interpreter (SLI).',
    ],
    [
        'id' => 'faq-2',
        'category' => 'Accessibility',
        'question' => 'How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?',
        'answer' => 'You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Sensitive disability disclosures and accommodation free-text details are encrypted at rest and visible only to authorized accessibility coordinators.',
    ],
    [
        'id' => 'faq-3',
        'category' => 'Registration & Check-in',
        'question' => 'How do registration and check-in work at the Vadodara festival venues?',
        'answer' => 'Register in one of three categories — Attendee, Volunteer, or Sign Language Interpreter (SLI) — to receive your official Registration ID (for example, PFG-2027-000100). Attendee registrations are confirmed directly, while Volunteer and SLI applications undergo organizer review. On festival days, present your Registration ID or registered name at the manual Vadodara Registration Desk for verification and attendance check-in.',
    ],
    [
        'id' => 'faq-4',
        'category' => 'Volunteers & SLI',
        'question' => 'Does submitting a Volunteer or SLI application mean automatic approval?',
        'answer' => 'No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your official confirmation, Registration ID status update, and duty schedule in your Participant Dashboard.',
    ],
];

$FALLBACK_GALLERY = [
    [
        'id' => 'g-1',
        'url' => '/assets/gallery/venue-01.jpg',
        'altText' => 'Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports',
        'caption' => 'Adaptive Athletics & Outdoor Arena',
    ],
    [
        'id' => 'g-2',
        'url' => '/assets/gallery/venue-05.jpg',
        'altText' => 'Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027',
        'caption' => 'Indoor Court & Para-Sports Pavilion',
    ],
    [
        'id' => 'g-3',
        'url' => '/assets/gallery/venue-10.jpg',
        'altText' => 'Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda',
        'caption' => 'University Sports Field & Lawn',
    ],
    [
        'id' => 'g-4',
        'url' => '/assets/gallery/venue-15.jpg',
        'altText' => 'Accessible campus walkway and entrance corridor at Vadodara festival venue',
        'caption' => 'Campus Access Corridor',
    ],
    [
        'id' => 'g-5',
        'url' => '/assets/gallery/venue-20.jpg',
        'altText' => 'Sports complex seating and shaded pavilion area for spectators and para-athletes',
        'caption' => 'Shaded Spectator & Athlete Pavilion',
    ],
    [
        'id' => 'g-6',
        'url' => '/assets/gallery/venue-25.jpg',
        'altText' => 'Campus assembly area and shaded courtyard for workshops and community exhibitions',
        'caption' => 'Community Courtyard & Exhibition Grounds',
    ],
];

$FESTIVAL_HIGHLIGHTS = [
    [
        'title' => 'Cultural Performances',
        'category' => 'Stage & Music',
        'description' => "Inclusive folk heritage, contemporary dance, sign-language music ensembles, and audio-described stage showcases celebrating Gujarat's cultural spirit.",
        'icon' => 'music',
    ],
    [
        'title' => 'Disability Arts & Cinema',
        'category' => 'Creative Expression',
        'description' => 'Curated inclusive film screenings with closed captions and audio description, fine-arts pavilions, and tactile exhibitions by artists with disabilities.',
        'icon' => 'palette',
    ],
    [
        'title' => 'Inclusive Education',
        'category' => 'NEP 2020 & UDL',
        'description' => 'Dialogues with educators, researchers, and students on Universal Design for Learning, teacher preparedness, and accessible school & university ecosystems.',
        'icon' => 'graduation-cap',
    ],
    [
        'title' => 'Assistive Technology Expo',
        'category' => 'Innovation Pavilion',
        'description' => 'Hands-on demonstrations of screen readers, Braille displays, AAC communication tools, adaptive mobility solutions, and Indian assistive startups.',
        'icon' => 'cpu',
    ],
    [
        'title' => 'Para Sports Championships',
        'category' => '25 Adaptive Disciplines',
        'description' => 'Unified athletic events, wheelchair basketball, boccia, blind cricket, and inclusive youth sports hosted at Sama Indoor Stadium and university grounds.',
        'icon' => 'trophy',
    ],
    [
        'title' => 'Workshops & Policy Panels',
        'category' => 'Rights & Livelihoods',
        'description' => 'Action-oriented sessions on the RPwD Act 2016, barrier-free urban infrastructure, employer inclusion conclaves, and digital accessibility compliance.',
        'icon' => 'message-square',
    ],
];

$sectionsByKey = [];
$publishedPartners = [];
$programme = [];
$disabilityList = [];
$faqList = [];
$galleryItems = [];
$activeAnnouncements = [];
$dbPartnerTotalCount = null;
$dbProgrammeTotalCount = null;

$resolveSafeUrl = static function (?string $url, bool $allowRelative = false): ?string {
    if ($url === null || trim($url) === '') {
        return null;
    }
    if (function_exists('pfgValidateSafeUrl')) {
        $checked = pfgValidateSafeUrl($url, $allowRelative);
        return ($checked !== null && $checked !== '') ? $checked : null;
    }
    $trimmed = trim($url);
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)) {
        return null;
    }
    if ($allowRelative && str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
        return $trimmed;
    }
    if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string)parse_url($trimmed, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $trimmed : null;
};

if (function_exists('getDb')) {
    try {
        $pdo = getDb();

        // 0. Active Published Announcements (within published_at..expires_at window — FND-33)
        try {
            $nowTs = function_exists('nowSql') ? nowSql() : date('Y-m-d H:i:s');
            $stmtAnn = $pdo->prepare("
                SELECT id, title, message, severity, published_at, expires_at
                FROM announcements
                WHERE status = 'PUBLISHED'
                  AND (published_at IS NULL OR published_at <= :now1)
                  AND (expires_at IS NULL OR expires_at >= :now2)
                ORDER BY published_at DESC, created_at DESC
                LIMIT 3
            ");
            $stmtAnn->execute([':now1' => $nowTs, ':now2' => $nowTs]);
            foreach ($stmtAnn->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $annTitle = trim((string)($row['title'] ?? ''));
                $annMsg = trim((string)($row['message'] ?? ''));
                if ($annTitle === '' && $annMsg === '') {
                    continue;
                }
                $activeAnnouncements[] = [
                    'id'          => (string)($row['id'] ?? ''),
                    'title'       => $annTitle,
                    'message'     => $annMsg,
                    'severity'    => strtoupper(trim((string)($row['severity'] ?? 'INFO'))),
                    'publishedAt' => (string)($row['published_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // Announcements table unavailable or empty
        }

        // 1. CMS Sections for 'home'
        $stmtSections = $pdo->query("
            SELECT s.id, s.section_key, s.title, s.subtitle, s.body, s.data, s.display_order
            FROM cms_sections s
            INNER JOIN cms_pages p ON s.page_id = p.id
            WHERE p.slug = 'home' AND s.status = 'PUBLISHED'
            ORDER BY s.display_order ASC
        ");
        if ($stmtSections) {
            foreach ($stmtSections->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dataVal = $row['data'] ?? null;
                if (is_string($dataVal) && $dataVal !== '') {
                    $decoded = json_decode($dataVal, true);
                    $dataVal = is_array($decoded) ? $decoded : null;
                }
                $row['data'] = $dataVal;
                $sectionsByKey[$row['section_key']] = $row;
            }
        }

        // 2. Published & Consent-Verified Partners (§36.2, BLK-03)
        $cntPartStmt = $pdo->query('SELECT COUNT(*) FROM partners');
        if ($cntPartStmt !== false) {
            $dbPartnerTotalCount = (int)$cntPartStmt->fetchColumn();
        }
        $stmtPartners = $pdo->query("
            SELECT p.id AS partner_id, p.category, p.display_order,
                   o.name AS org_name, o.short_name, o.logo_url, o.logo_alt_text, o.description, o.website_url
            FROM partners p
            INNER JOIN organizations o ON p.organization_id = o.id
            WHERE p.status = 'PUBLISHED'
              AND p.consent_verified = 1
            ORDER BY p.display_order ASC
        ");
        if ($stmtPartners) {
            foreach ($stmtPartners->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $publishedPartners[] = [
                    'partnerId' => (string)($row['partner_id'] ?? ''),
                    'category' => (string)($row['category'] ?? ''),
                    'orgName' => (string)($row['org_name'] ?? ''),
                    'shortName' => $row['short_name'] ?? null,
                    'logoUrl' => $resolveSafeUrl(isset($row['logo_url']) ? (string)$row['logo_url'] : null, true),
                    'logoAltText' => $row['logo_alt_text'] ?? null,
                    'description' => $row['description'] ?? null,
                    'websiteUrl' => $resolveSafeUrl(isset($row['website_url']) ? (string)$row['website_url'] : null, false),
                ];
            }
        }

        // 3. Programme Preview (6 published/confirmed items only — never DRAFT/CANCELLED)
        $cntProgStmt = $pdo->query('SELECT COUNT(*) FROM programme_items');
        if ($cntProgStmt !== false) {
            $dbProgrammeTotalCount = (int)$cntProgStmt->fetchColumn();
        }
        $stmtProg = $pdo->query("
            SELECT pi.id, pi.day_number, pi.time_slot, pi.title, pi.description, pi.category, pi.venue_name, pi.display_order
            FROM programme_items pi
            LEFT JOIN festival_sessions fs ON pi.session_id = fs.id
            WHERE pi.status IN ('PUBLISHED', 'CONFIRMED')
              AND (pi.session_id IS NULL OR fs.status IN ('PUBLISHED', 'CONFIRMED'))
            ORDER BY pi.day_number ASC, pi.display_order ASC
            LIMIT 6
        ");
        if ($stmtProg) {
            foreach ($stmtProg->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $programme[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'dayNumber' => (int)($row['day_number'] ?? 1),
                    'timeSlot' => (string)($row['time_slot'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'description' => $row['description'] ?? null,
                    'category' => (string)($row['category'] ?? ''),
                    'venueName' => $row['venue_name'] ?? null,
                ];
            }
        }

        // 4. 21 RPwD Disabilities
        $stmtDis = $pdo->query("
            SELECT id, code, name, rpwd_group, description, accommodation_examples, display_order
            FROM disability_categories
            ORDER BY display_order ASC
        ");
        if ($stmtDis) {
            foreach ($stmtDis->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $examples = $row['accommodation_examples'] ?? [];
                if (is_string($examples) && $examples !== '') {
                    $decoded = json_decode($examples, true);
                    $examples = is_array($decoded) ? $decoded : [];
                }
                $disabilityList[] = [
                    'id' => (string)($row['id'] ?? $row['code']),
                    'code' => (string)($row['code'] ?? ''),
                    'name' => (string)($row['name'] ?? ''),
                    'rpwdGroup' => (string)($row['rpwd_group'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                    'accommodationExamples' => is_array($examples) ? $examples : [],
                    'displayOrder' => (int)($row['display_order'] ?? 0),
                ];
            }
        }

        // 5. FAQs
        $stmtFaq = $pdo->query("
            SELECT id, category, question, answer
            FROM faq_items
            WHERE status = 'PUBLISHED'
            ORDER BY display_order ASC
        ");
        if ($stmtFaq) {
            foreach ($stmtFaq->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $faqList[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'category' => (string)($row['category'] ?? 'General'),
                    'question' => (string)($row['question'] ?? ''),
                    'answer' => (string)($row['answer'] ?? ''),
                ];
            }
        }

        // 6. Gallery
        $stmtGal = $pdo->query("
            SELECT id, url, alt_text, source_attribution
            FROM media_assets
            WHERE category = 'GALLERY'
            LIMIT 6
        ");
        if ($stmtGal) {
            foreach ($stmtGal->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $safeGalUrl = $resolveSafeUrl(isset($row['url']) ? (string)$row['url'] : null, true);
                if ($safeGalUrl === null) {
                    continue;
                }
                $galleryItems[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'url' => $safeGalUrl,
                    'altText' => (string)($row['alt_text'] ?? ''),
                    'caption' => !empty($row['source_attribution']) ? (string)$row['source_attribution'] : 'Purple Festival Gujarat 2027 Venue',
                ];
            }
        }
    } catch (\Throwable $e) {
        // Fallback arrays will be used seamlessly
    }
}

// Only fall back to static partner seed when DB is unavailable or partners table is empty.
// If DB has partner records and none are consent_verified + PUBLISHED, NEVER publish unconfirmed partners.
if (empty($publishedPartners) && ($dbPartnerTotalCount === null || $dbPartnerTotalCount === 0)) {
    $publishedPartners = $FALLBACK_PARTNERS;
}
if (empty($programme) && ($dbProgrammeTotalCount === null || $dbProgrammeTotalCount === 0)) {
    $programme = $FALLBACK_PROGRAMME;
}
if (empty($disabilityList)) {
    foreach ($FALLBACK_DISABILITIES as $idx => $d) {
        $disabilityList[] = [
            'id' => $d['code'],
            'code' => $d['code'],
            'name' => $d['name'],
            'rpwdGroup' => $d['rpwdGroup'] ?? $d['rpwd_group'] ?? 'Specified Disability',
            'description' => $d['description'],
            'accommodationExamples' => $d['accommodationExamples'] ?? $d['accommodation_examples'] ?? [],
            'displayOrder' => $idx + 1,
        ];
    }
}
if (empty($faqList)) {
    $faqList = $FALLBACK_FAQS;
}
if (empty($galleryItems)) {
    $galleryItems = $FALLBACK_GALLERY;
}

$heroSection = $sectionsByKey['hero'] ?? null;
$aboutSection = $sectionsByKey['about'] ?? null;
$viksitSection = $sectionsByKey['viksit-bharat'] ?? null;
$contactSection = $sectionsByKey['contact'] ?? null;

$heroData = is_array($heroSection['data'] ?? null) ? $heroSection['data'] : [];
$heroTitle = !empty($heroSection['title']) && trim((string)$heroSection['title']) !== ''
    ? trim((string)$heroSection['title'])
    : 'Inclusion, celebrated.';
$heroBody = !empty($heroSection['body']) && trim((string)$heroSection['body']) !== ''
    ? trim((string)$heroSection['body'])
    : "India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016).";
$heroCtaLabel = !empty($heroData['primaryCtaLabel']) && trim((string)$heroData['primaryCtaLabel']) !== ''
    ? trim((string)$heroData['primaryCtaLabel'])
    : 'Apply Now';
$rawHeroCtaHref = (string)($heroData['primaryCtaHref'] ?? '');
$heroCtaHref = $resolveSafeUrl($rawHeroCtaHref, true) ?? '/apply';
$heroWebsiteUrl = $resolveSafeUrl(
    isset($heroData['website_url']) ? (string)$heroData['website_url'] : (isset($heroData['websiteUrl']) ? (string)$heroData['websiteUrl'] : null),
    false
);
$heroDatesLabel = !empty($heroData['dates']) && trim((string)$heroData['dates']) !== ''
    ? trim((string)$heroData['dates'])
    : '21–23 Jan 2027';
$heroLocationLabel = !empty($heroData['location']) && trim((string)$heroData['location']) !== ''
    ? trim((string)$heroData['location'])
    : 'Vadodara, Gujarat';

$aboutData = is_array($aboutSection['data'] ?? null) ? $aboutSection['data'] : [];
$stats = !empty($aboutData['stats']) && is_array($aboutData['stats'])
    ? $aboutData['stats']
    : [
        ['label' => 'Expected Attendees', 'value' => '5,000+'],
        ['label' => 'Benchmark Disabilities', 'value' => '21'],
        ['label' => 'Para Sports Disciplines', 'value' => '25'],
        ['label' => 'Days of Celebration', 'value' => '3'],
    ];

$viksitData = is_array($viksitSection['data'] ?? null) ? $viksitSection['data'] : [];
$contactData = is_array($contactSection['data'] ?? null) ? $contactSection['data'] : [];
$contactEmail = !empty($contactData['email']) ? (string)$contactData['email'] : 'purplefestivalvadodara@gmail.com';
$contactPhones = !empty($contactData['phones']) && is_array($contactData['phones'])
    ? $contactData['phones']
    : [
        ['name' => 'Radha Tandon', 'number' => '+91 99743 83920'],
        ['name' => 'Satish Kapoor', 'number' => '+91 92117 60202'],
    ];

// Extract unique RPwD groups for filter buttons
$rpwdGroups = ['All'];
foreach ($disabilityList as $dItem) {
    if (!in_array($dItem['rpwdGroup'], $rpwdGroups, true)) {
        $rpwdGroups[] = $dItem['rpwdGroup'];
    }
}

$siteUrl = defined('APP_URL') ? rtrim((string)APP_URL, '/') : 'https://purplefestivalgujarat.com';
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Festival',
            '@id' => $siteUrl . '/#festival',
            'name' => 'Purple Festival Gujarat 2027',
            'alternateName' => 'PurpleFest Gujarat 2027',
            'description' => "India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities under the RPwD Act 2016.",
            'startDate' => '2027-01-21T09:00:00+05:30',
            'endDate' => '2027-01-23T21:00:00+05:30',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'isAccessibleForFree' => true,
            'inLanguage' => ['en-IN', 'gu', 'hi'],
            'image' => [$siteUrl . '/assets/logos/purple-festival.jpg'],
            'location' => [
                '@type' => 'Place',
                'name' => 'Sama Indoor Stadium & Participating Vadodara Venues',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Vadodara',
                    'addressRegion' => 'Gujarat',
                    'postalCode' => '390002',
                    'addressCountry' => 'IN',
                ],
            ],
            'organizer' => [
                ['@type' => 'Organization', 'name' => 'Brotherhood', 'url' => $siteUrl],
                ['@type' => 'Organization', 'name' => 'Global Inclusive Education Network (GIEN)', 'url' => $siteUrl],
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $siteUrl . '/apply',
                'price' => '0',
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
                'validFrom' => '2026-11-01T00:00:00+05:30',
            ],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => $siteUrl . '/#faq',
            'mainEntity' => array_map(static function (array $f): array {
                return [
                    '@type' => 'Question',
                    'name' => $f['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $f['answer'],
                    ],
                ];
            }, $faqList),
        ],
    ],
];

$extraHeadHtml = (isset($extraHeadHtml) ? $extraHeadHtml . "\n" : '')
    . '  <link rel="stylesheet" href="/assets/css/viksit-section.css?v=1" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/disabilities-section.css?v=2" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/partners-section.css?v=2" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/home-whats-on.css?v=1" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/home-programme-highlights.css?v=1" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/home-festival-grounds.css?v=2" />';

require_once __DIR__ . '/includes/header.php';

function renderHighlightIconSvg(string $iconName): string
{
    switch ($iconName) {
        case 'music':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
        case 'palette':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>';
        case 'graduation-cap':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>';
        case 'cpu':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/></svg>';
        case 'trophy':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>';
        default:
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    }
}
?>

<!-- ============================================================
     INTRODUCTORY PRELOADER (Controlled by /assets/js/preloader.js)
     ============================================================ -->
<div id="pfg-preloader-status" role="status" aria-live="polite" class="sr-only"></div>

<div
  id="pfg-preloader"
  role="region"
  aria-label="Purple Festival Gujarat 2027 Introductory Animation"
  class="fixed left-0 top-0 z-[1001] hidden h-full w-full items-center justify-center overflow-hidden text-[#202D59]"
>
  <!-- Exact #DDDDF9 Lavender-Periwinkle Curtain Backdrop -->
  <span
    id="pfg-preloader-backdrop"
    aria-hidden="true"
    class="fixed left-0 top-0 -z-10 h-screen w-full bg-[#DDDDF9]"
  ></span>

  <!-- Subtle, Accessible Skip Intro Button -->
  <button
    id="pfg-preloader-skip"
    type="button"
    class="touch-target fixed bottom-6 right-6 z-[1002] inline-flex items-center gap-2 rounded border-2 border-[#202D59]/30 bg-[#DDDDF9]/90 px-4 py-2 text-sm font-semibold text-[#202D59] transition-colors hover:border-[#202D59] hover:bg-[#202D59] hover:text-white"
  >
    <span>Skip Intro</span>
    <span aria-hidden="true" class="text-xs opacity-70">ESC</span>
  </button>

  <!-- Animated SVG Logo Container — Exact original #202D59 monochrome ink -->
  <div
    id="pfg-preloader-wrap"
    aria-hidden="true"
    class="relative flex w-full items-center justify-center opacity-0"
  >
    <div class="relative flex w-full items-center justify-center overflow-visible">
      <svg
        id="pfg-preloader-svg"
        width="900"
        height="320"
        viewBox="0 0 900 320"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        style="width: min(88vw, 860px); height: auto; overflow: visible;"
        aria-hidden="true"
        focusable="false"
      >
        <!-- 1. Left horizontal lead-in line -->
        <path
          data-draw="left-line"
          d="M -280 156 L 312 156 C 338 156 350 110 360.15 118.4"
          fill="none"
          stroke="#202D59"
          stroke-width="6.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />

        <!-- 2. Core Logo Group -->
        <g data-logo-core="true">
          <!-- Continuous 3-figure calligraphic emblem ribbon -->
          <path
            data-draw="emblem-ribbon"
            d="M 360.15 118.4 C 350.74 157.88 368.27 174.29 374.54 171.88 C 380.81 169.47 369.56 135.42 374.54 133.58 C 379.58 131.78 395.88 170.65 402.09 168.24 C 408.36 165.78 399.63 115.38 366.42 95.33 C 333.21 75.9 318.2 97.12 326.94 109.89 C 336.96 123.27 363.28 107.48 377.06 83.18 C 397.72 46.72 435.3 46.72 460.33 84.97 C 477.24 111.12 470.97 171.88 462.24 174.29 C 453.44 176.75 445.94 127.53 439.67 127.53 C 433.4 127.53 425.89 176.75 418.39 174.29 C 410.88 171.88 408.36 111.12 429.03 84.97 C 454.06 52.77 497.91 54.62 524.84 83.18 C 539.85 100.2 564.94 117.17 573.68 105.07 C 581.18 94.1 564.94 77.07 535.48 95.33 C 506.08 113.53 493.54 163.98 499.81 168.24 C 506.08 171.88 522.32 131.78 527.36 133.58 C 532.35 135.42 521.09 169.47 527.36 171.88 C 533.64 174.29 548.03 157.88 539.85 118.4"
            fill="none"
            stroke="#202D59"
            stroke-width="6.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />

          <!-- 3 Hearts above the 3 figures — exact #202D59 -->
          <path
            data-heart="1"
            d="M 356.96 43.64 C 349.4 34.96 335.12 36.92 336.24 48.12 C 337.36 58.2 353.04 65.2 364.8 68.84 C 369.28 58.2 371.52 41.96 362.56 36.08 C 355.84 31.6 354.72 40.28 356.96 43.64 Z"
            fill="#202D59"
          />
          <path
            data-heart="2"
            d="M 447.68 26.84 C 442.08 14.52 428.08 14.52 428.08 26.84 C 428.08 38.04 442.08 45.88 447.68 52.88 C 453.28 45.88 467.28 38.04 467.28 26.84 C 467.28 14.52 453.28 14.52 447.68 26.84 Z"
            fill="#202D59"
          />
          <path
            data-heart="3"
            d="M 536.72 43.64 C 540.08 40.28 538.96 31.6 532.24 36.08 C 523.28 41.96 525.52 58.2 530 68.84 C 541.76 65.2 557.44 58.2 558.56 48.12 C 559.68 36.92 545.4 34.96 536.72 43.64 Z"
            fill="#202D59"
          />

          <!-- Rebranded Gujarat 2027 Wordmark — exact #202D59 geometric sans & scale -->
          <g>
            <text
              data-draw-text="title"
              x="450"
              y="228"
              text-anchor="middle"
              fill="#202D59"
              stroke="#202D59"
              stroke-width="1.2"
              font-family="var(--font-dm-sans), Arial, sans-serif"
              font-weight="700"
              font-size="50"
              letter-spacing="1.2"
            >PURPLE FESTIVAL</text>
            <text
              data-draw-text="subtitle"
              x="450"
              y="274"
              text-anchor="middle"
              fill="#202D59"
              stroke="#202D59"
              stroke-width="0.9"
              font-family="var(--font-dm-sans), Arial, sans-serif"
              font-weight="500"
              font-size="36"
              letter-spacing="0.5"
            >Gujarat 2027</text>
          </g>
        </g>

        <!-- 3. Right horizontal exit line -->
        <path
          data-draw="right-line"
          d="M 539.85 118.4 C 550 110 562 156 588 156 L 1180 156"
          fill="none"
          stroke="#202D59"
          stroke-width="6.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>
  </div>
</div>

<main id="main-content" tabindex="-1" class="focus:outline-none">
  <?php if (!empty($activeAnnouncements)): ?>
    <!-- Active Published Announcements Banner (FND-33) -->
    <div
      role="region"
      aria-label="Festival Announcements"
      class="border-b border-[#202D59]/15 bg-[#202D59] text-white"
      data-surface="dark"
    >
      <div class="mx-auto max-w-[1440px] divide-y divide-white/15 px-5 sm:px-10">
        <?php foreach ($activeAnnouncements as $ann):
            $sev = $ann['severity'];
            $isUrgent = in_array($sev, ['URGENT', 'CRITICAL', 'ALERT', 'ERROR'], true);
            $isWarn = in_array($sev, ['WARNING', 'IMPORTANT'], true);
            $badgeClass = $isUrgent
                ? 'bg-red-500 text-white'
                : ($isWarn ? 'bg-[#D5B77B] text-[#17151C]' : 'bg-[#A459FF] text-white');
            $badgeText = $isUrgent ? 'Alert' : ($isWarn ? 'Notice' : 'Update');
            $annDomId = 'pfg-announcement-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $ann['id']);
        ?>
          <div
            id="<?= htmlspecialchars($annDomId, ENT_QUOTES, 'UTF-8') ?>"
            data-pfg-announcement="<?= htmlspecialchars($ann['id'], ENT_QUOTES, 'UTF-8') ?>"
            class="flex items-center justify-between gap-4 py-2.5 text-sm"
          >
            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
              <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold tracking-wider uppercase <?= $badgeClass ?>">
                <?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?>
              </span>
              <?php if ($ann['title'] !== ''): ?>
                <strong class="font-bold text-white"><?= htmlspecialchars($ann['title'], ENT_QUOTES, 'UTF-8') ?>:</strong>
              <?php endif; ?>
              <?php if ($ann['message'] !== ''): ?>
                <span class="text-[#E8DFF0]"><?= htmlspecialchars($ann['message'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
            <button
              type="button"
              data-dismiss-announcement="<?= htmlspecialchars($ann['id'], ENT_QUOTES, 'UTF-8') ?>"
              aria-label="Dismiss announcement: <?= htmlspecialchars($ann['title'] !== '' ? $ann['title'] : 'Festival notice', ENT_QUOTES, 'UTF-8') ?>"
              class="touch-target inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/25 text-white/90 transition-colors hover:border-white hover:bg-white/10 hover:text-white"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
              </svg>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ============================================================
       1. AUTHENTIC LAVENDER-PERIWINKLE HERO (With Gujarat Architectural Line-Art Background)
       ============================================================ -->
  <section
    aria-labelledby="hero-heading"
    class="pfg-hero-section relative flex flex-col items-center justify-between overflow-hidden bg-[#F2E9FE] px-6 text-center text-[#202D59] sm:px-10"
  >
    <?php include __DIR__ . '/includes/hero-background.php'; ?>

    <div class="pfg-hero-inner relative z-10 mx-auto flex flex-1 flex-col items-center justify-center">
      <!-- Viewport-Proportioned Headline (DM Sans Bold, balanced across laptop & desktop screens) -->
      <h1
        id="hero-heading"
        class="pfg-hero-title font-bold text-[#202D59]"
      >
        <?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?>
      </h1>

      <!-- Balanced Hero Subtitle -->
      <p class="pfg-hero-subtitle font-normal text-[#202D59]">
        <?= htmlspecialchars($heroBody, ENT_QUOTES, 'UTF-8') ?>
      </p>

      <!-- Single Prominent Centered #A459FF Apply Now CTA -->
      <div class="pfg-hero-cta-wrap flex flex-wrap items-center justify-center gap-4">
        <a
          href="<?= htmlspecialchars($heroCtaHref, ENT_QUOTES, 'UTF-8') ?>"
          class="btn-primary-pfg pfg-hero-cta-btn touch-target inline-flex items-center justify-center bg-[#A459FF] font-semibold tracking-[-0.01em] text-white transition-colors hover:bg-[#8D3BF2]"
        >
          <?= htmlspecialchars($heroCtaLabel, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php if ($heroWebsiteUrl !== null && $heroWebsiteUrl !== $heroCtaHref): ?>
          <a
            href="<?= htmlspecialchars($heroWebsiteUrl, ENT_QUOTES, 'UTF-8') ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="pfg-hero-cta-secondary touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-white/75 font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
          >
            Official Info
            <span class="sr-only"> (opens in a new tab)</span>
          </a>
        <?php endif; ?>
      </div>

      <!-- Clean Static Flat Authorized Purple Festival Emblem (full uncut above-the-fold fit) -->
      <div class="pfg-hero-emblem-wrap flex flex-col items-center">
        <?= renderPurpleFestEmblemSvg('pfg-hero-emblem-svg text-[#202D59]/85') ?>
        <p class="pfg-hero-emblem-caption font-medium uppercase text-[#202D59]/75">
          Purple Festival Gujarat 2027 &middot; <?= htmlspecialchars($heroLocationLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>

    <!-- Bottom-Left Info Pill (matching reference corner badge) -->
    <div class="pfg-hero-badge-wrap relative z-10 flex w-full max-w-[1440px] justify-start">
      <div class="pfg-hero-badge inline-flex items-center gap-2.5 bg-white font-medium text-[#202D59] shadow-xs">
        <span>Purple Festival Gujarat</span>
        <span aria-hidden="true" class="text-[#099F9F]">▼</span>
        <span class="font-bold text-[#202D59]"><?= htmlspecialchars($heroDatesLabel, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
  </section>

  <!-- ============================================================
       2. ABOUT THE FESTIVAL & KEY BROCHURE STATS
       ============================================================ -->
  <section
    id="about"
    aria-labelledby="about-heading"
    class="border-b border-[#202D59]/10 pfg-ivory-surface py-20 sm:py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="grid gap-12 lg:grid-cols-12 lg:items-start">
        <div class="space-y-6 lg:col-span-7">
          <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
            <?= htmlspecialchars(
                !empty($aboutSection['subtitle'])
                    ? (string)$aboutSection['subtitle']
                    : 'First Edition in Gujarat — Vadodara, January 2027',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>
          <h2
            id="about-heading"
            class="text-3xl font-extrabold leading-tight tracking-tight text-[#202D59] sm:text-5xl"
          >
            <?= htmlspecialchars(
                !empty($aboutSection['title'])
                    ? (string)$aboutSection['title']
                    : 'Inclusion Is Not Charity. It Is Culture, Innovation, and Right.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </h2>
          <p class="text-base leading-relaxed text-[#46537C] sm:text-lg">
            <?= htmlspecialchars(
                !empty($aboutSection['body'])
                    ? (string)$aboutSection['body']
                    : 'Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>
          <p class="text-base leading-relaxed text-[#46537C]">
            Built around dignity, representation, and universal design, the festival elevates
            persons with disabilities as leaders, athletes, creators, and decision-makers —
            never as objects of sympathy.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:col-span-5">
          <?php foreach ($stats as $st): ?>
            <div class="rounded-xl border border-[#202D59]/12 bg-[#DDDDF9]/55 p-6">
              <p class="text-3xl font-extrabold text-[#202D59] sm:text-4xl">
                <?= htmlspecialchars((string)($st['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </p>
              <p class="mt-2 text-sm font-semibold text-[#46537C]">
                <?= htmlspecialchars((string)($st['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
       3. VIKSIT BHARAT@2047 (Royal Purple Illustrated Vision Showcase)
       ============================================================ -->
  <?php
  $viksitEyebrow = !empty($viksitSection['subtitle'])
      ? (string)$viksitSection['subtitle']
      : "Aligned with India's Vision for 2047";
  $viksitTitle = !empty($viksitSection['title'])
      ? (string)$viksitSection['title']
      : 'A Viksit Bharat Must Be an Inclusive Bharat.';
  $viksitBody = !empty($viksitSection['body'])
      ? (string)$viksitSection['body']
      : "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.";
  $viksitDisclaimer = !empty($viksitData['disclaimer'])
      ? (string)$viksitData['disclaimer']
      : 'Purple Festival Gujarat 2027 contributes to the wider national vision for inclusive development under Viksit Bharat@2047.';

  $viksitPillarItems = [
      [
          'label' => 'Inclusive Education & NEP 2020',
          'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>',
      ],
      [
          'label' => 'Assistive & Digital Public Innovation',
          'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="16" height="11" x="4" y="4" rx="2"/><path d="M2 19h20"/><path d="M9 9.5h6"/></svg>',
      ],
      [
          'label' => 'Dignified Livelihoods & Equal Opportunity',
          'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>',
      ],
      [
          'label' => 'Barrier-Free Cultural & Civic Participation',
          'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a3 3 0 0 1 0 5.74"/></svg>',
      ],
  ];
  ?>
  <section
    aria-labelledby="viksit-heading"
    class="pfg-viksit-section"
    data-surface="dark"
  >
    <!-- Subtle Ashoka Chakra Watermark behind Portrait -->
    <svg
      aria-hidden="true"
      viewBox="0 0 200 200"
      style="position:absolute;left:18px;top:36px;width:clamp(190px,18vw,290px);height:auto;z-index:1;opacity:0.15;pointer-events:none;color:#ffffff;"
    >
      <circle cx="100" cy="100" r="88" fill="none" stroke="currentColor" stroke-width="8" />
      <circle cx="100" cy="100" r="18" fill="currentColor" />
      <g stroke="currentColor" stroke-width="3">
        <?php for ($sp = 0; $sp < 24; $sp++): $ang = $sp * 15; ?>
          <line x1="100" y1="82" x2="100" y2="16" transform="rotate(<?= $ang ?> 100 100)" />
        <?php endfor; ?>
      </g>
    </svg>

    <!-- Decorative Top-Right Mandala & Bottom Gujarat Skyline -->
    <img
      src="/assets/illustrations/viksit-mandala-tr.png"
      alt=""
      aria-hidden="true"
      loading="lazy"
      decoding="async"
      class="pfg-viksit-mandala"
    />
    <img
      src="/assets/illustrations/viksit-skyline-bottom.png"
      alt=""
      aria-hidden="true"
      loading="lazy"
      decoding="async"
      class="pfg-viksit-skyline"
    />

    <!-- Main Inner Layout -->
    <div class="pfg-viksit-inner">
      <div class="pfg-viksit-copy">
        <p class="pfg-viksit-eyebrow">
          <?= htmlspecialchars($viksitEyebrow, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="pfg-viksit-bar" aria-hidden="true"></div>
        <h2 id="viksit-heading" class="pfg-viksit-title">
          <?= htmlspecialchars($viksitTitle, ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="pfg-viksit-body">
          <?= htmlspecialchars($viksitBody, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="pfg-viksit-disclaimer">
          <?= htmlspecialchars($viksitDisclaimer, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <div class="pfg-viksit-visual-right">
        <div class="pfg-viksit-arc-wrap">
          <svg
            class="pfg-viksit-arc-svg"
            viewBox="0 0 42 280"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path d="M 34 18 Q 10 140 34 262" fill="none" stroke="rgba(255,255,255,0.68)" stroke-width="2" />
            <circle cx="30" cy="28" r="5" fill="#ffffff" />
            <circle cx="22" cy="100" r="5" fill="#ffffff" />
            <circle cx="22" cy="180" r="5" fill="#ffffff" />
            <circle cx="30" cy="252" r="5" fill="#ffffff" />
          </svg>
          <?php foreach ($viksitPillarItems as $vPillar): ?>
            <div class="pfg-viksit-pill">
              <span class="pfg-viksit-pill-icon" aria-hidden="true">
                <?= $vPillar['icon'] ?>
              </span>
              <span class="pfg-viksit-pill-label">
                <?= htmlspecialchars($vPillar['label'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="pfg-viksit-emblem" aria-label="Viksit Bharat @2047 Emblem">
          <svg
            class="pfg-viksit-emblem-svg"
            viewBox="0 0 110 72"
            fill="none"
            aria-hidden="true"
          >
            <!-- Tricolour Flag -->
            <line x1="55" y1="30" x2="55" y2="4" stroke="#3B1A7A" stroke-width="2.2" stroke-linecap="round" />
            <rect x="56" y="5" width="22" height="5" fill="#FF9933" rx="0.5" />
            <rect x="56" y="10" width="22" height="5" fill="#FFFFFF" />
            <rect x="56" y="15" width="22" height="5" fill="#138808" rx="0.5" />
            <circle cx="67" cy="12.5" r="2" fill="none" stroke="#1D3A8A" stroke-width="0.9" />
            <!-- Parliament Dome & Colonade -->
            <path d="M12 44 C28 24 82 24 98 44 Z" fill="#5D35B0" opacity="0.24" />
            <path d="M16 44 C32 27 78 27 94 44" stroke="#3B1A7A" stroke-width="2.2" stroke-linecap="round" />
            <rect x="10" y="44" width="90" height="4" rx="1.5" fill="#3B1A7A" />
            <g stroke="#3B1A7A" stroke-width="2.6" stroke-linecap="round">
              <line x1="18" y1="49" x2="18" y2="63" />
              <line x1="28" y1="49" x2="28" y2="63" />
              <line x1="38" y1="49" x2="38" y2="63" />
              <line x1="48" y1="49" x2="48" y2="63" />
              <line x1="58" y1="49" x2="58" y2="63" />
              <line x1="68" y1="49" x2="68" y2="63" />
              <line x1="78" y1="49" x2="78" y2="63" />
              <line x1="88" y1="49" x2="88" y2="63" />
            </g>
            <rect x="6" y="64" width="98" height="4" rx="2" fill="#3B1A7A" />
          </svg>
          <p class="pfg-viksit-emblem-title">Viksit Bharat</p>
          <p class="pfg-viksit-emblem-year">@2047</p>
        </div>
      </div>
    </div>

    <!-- Hon'ble PM Portrait + Tricolour Ribbon -->
    <img
      src="/assets/illustrations/viksit-pm-portrait.png"
      alt="Hon'ble Prime Minister Narendra Modi — Viksit Bharat@2047"
      loading="lazy"
      decoding="async"
      class="pfg-viksit-portrait"
    />

    <!-- Flowing Bottom Purple Waves -->
    <svg
      class="pfg-viksit-bottom-waves"
      viewBox="0 0 1600 120"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id="pfgViksitWaveBack" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#8A4BF5" stop-opacity="0.72" />
          <stop offset="50%" stop-color="#A16BFF" stop-opacity="0.62" />
          <stop offset="100%" stop-color="#7635DF" stop-opacity="0.78" />
        </linearGradient>
        <linearGradient id="pfgViksitWaveFront" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#6B2AD6" />
          <stop offset="48%" stop-color="#8848F2" />
          <stop offset="100%" stop-color="#6225CA" />
        </linearGradient>
      </defs>
      <path d="M0,44 C260,12 520,74 860,48 C1190,22 1410,66 1600,32 L1600,120 L0,120 Z" fill="url(#pfgViksitWaveBack)" />
      <path d="M0,74 C310,36 620,98 980,68 C1260,44 1460,72 1600,46 L1600,120 L0,120 Z" fill="url(#pfgViksitWaveFront)" />
      <path d="M0,74 C310,36 620,98 980,68 C1260,44 1460,72 1600,46" fill="none" stroke="rgba(255,255,255,0.45)" stroke-width="2" />
    </svg>
  </section>

  <!-- ============================================================
       4. FESTIVAL HIGHLIGHTS (What's On Editorial Band)
       ============================================================ -->
  <?php require __DIR__ . '/includes/home-sections/whats-on.php'; ?>

  <!-- ============================================================
       5. UNDERSTANDING THE 21 DISABILITIES (RPwD Act, 2016)
       ============================================================ -->
  <?php
  $disabilitiesSectionConfig = [
      'headingId' => 'disabilities-heading',
      'eyebrow' => 'Rights of Persons with Disabilities Act, 2016',
      'title' => 'Understanding the 21 Specified Disabilities',
      'subtitle' => "Every individual's access needs are unique. These categories are an educational guide — never a restrictive gate for festival participation.",
      'showGuideCta' => true,
      'guideCtaHref' => '/accessibility#rpwd-disabilities',
      'showExplorer' => false,
      'initialExpandedCount' => 0,
  ];
  require __DIR__ . '/includes/disabilities-section.php';
  ?>

  <!-- ============================================================
       6. PROGRAMME PREVIEW (Programme Highlights Editorial Band)
       ============================================================ -->
  <?php require __DIR__ . '/includes/home-sections/programme-highlights.php'; ?>

  <!-- ============================================================
       7. PARTNERS & ORGANISERS (Compact Dark Purple #5B2A86 Showcase)
       ============================================================ -->
  <?php
  $organisersList = [];
  $ourPartnersList = [];

  foreach ($publishedPartners as $partnerItem) {
      $catRaw = strtolower(trim((string)($partnerItem['category'] ?? '')));
      $orgRaw = strtolower(trim((string)($partnerItem['orgName'] ?? '')));

      if (str_contains($catRaw, 'organis') || str_contains($catRaw, 'organiz')) {
          $organisersList[] = $partnerItem;
          continue;
      }

      $roleLabel = (string)($partnerItem['category'] ?? 'Partner');
      if (str_contains($catRaw, 'venue')) {
          $roleLabel = 'Venue Partner';
      } elseif (str_contains($catRaw, 'knowledge')) {
          $roleLabel = 'Knowledge Partner';
      } elseif (str_contains($catRaw, 'academic') || str_contains($orgRaw, 'tiss')) {
          $roleLabel = 'Academic Partner';
      } elseif (str_contains($catRaw, 'legal') || str_contains($orgRaw, 'gnlu')) {
          $roleLabel = 'Legal Partner';
      } elseif (str_contains($catRaw, 'education') || str_contains($orgRaw, 'ayjnihh')) {
          $roleLabel = 'Education Partner';
      }

      $partnerItem['roleLabel'] = $roleLabel;
      $ourPartnersList[] = $partnerItem;
  }
  ?>
  <section
    id="partners"
    aria-labelledby="partners-heading"
    class="pfg-partners-section"
  >
    <h2 id="partners-heading" class="sr-only">
      Organisers and Partners
    </h2>
    <div class="pfg-partners-inner">
      <?php if (!empty($organisersList)): ?>
        <div class="pfg-partners-group">
          <h3 class="pfg-partners-group-title">Organisers</h3>
          <div class="pfg-partners-row--organisers">
            <?php foreach ($organisersList as $partner):
                $logoUrl = $resolveSafeUrl(isset($partner['logoUrl']) ? (string)$partner['logoUrl'] : null, true) ?? '';
                $partnerWebsite = $resolveSafeUrl(isset($partner['websiteUrl']) ? (string)$partner['websiteUrl'] : null, false);
            ?>
              <article class="pfg-partner-item">
                <div class="pfg-partner-tile">
                  <?php if ($logoUrl !== ''): ?>
                    <img
                      src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                      alt="<?= htmlspecialchars(!empty($partner['logoAltText']) ? (string)$partner['logoAltText'] : (string)$partner['orgName'] . ' logo', ENT_QUOTES, 'UTF-8') ?>"
                      loading="lazy"
                      decoding="async"
                    />
                  <?php else: ?>
                    <span class="pfg-partner-fallback">
                      <?= htmlspecialchars(!empty($partner['shortName']) ? (string)$partner['shortName'] : (string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                </div>
                <p class="pfg-partner-name">
                  <?php if ($partnerWebsite !== null): ?>
                    <a
                      href="<?= htmlspecialchars($partnerWebsite, ENT_QUOTES, 'UTF-8') ?>"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="underline-offset-4 hover:underline"
                    >
                      <?= htmlspecialchars((string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                      <span class="sr-only"> (opens in a new tab)</span>
                    </a>
                  <?php else: ?>
                    <?= htmlspecialchars((string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </p>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($organisersList) && !empty($ourPartnersList)): ?>
        <hr class="pfg-partners-divider" aria-hidden="true" />
      <?php endif; ?>

      <?php if (!empty($ourPartnersList)): ?>
        <div class="pfg-partners-group">
          <h3 class="pfg-partners-group-title">Our Partners</h3>
          <div class="pfg-partners-grid">
            <?php foreach ($ourPartnersList as $partner):
                $logoUrl = $resolveSafeUrl(isset($partner['logoUrl']) ? (string)$partner['logoUrl'] : null, true) ?? '';
                $partnerWebsite = $resolveSafeUrl(isset($partner['websiteUrl']) ? (string)$partner['websiteUrl'] : null, false);
            ?>
              <article class="pfg-partner-item">
                <div class="pfg-partner-tile">
                  <?php if ($logoUrl !== ''): ?>
                    <img
                      src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                      alt="<?= htmlspecialchars(!empty($partner['logoAltText']) ? (string)$partner['logoAltText'] : (string)$partner['orgName'] . ' logo', ENT_QUOTES, 'UTF-8') ?>"
                      loading="lazy"
                      decoding="async"
                    />
                  <?php else: ?>
                    <span class="pfg-partner-fallback">
                      <?= htmlspecialchars(!empty($partner['shortName']) ? (string)$partner['shortName'] : (string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($partner['roleLabel'])): ?>
                  <p class="pfg-partner-role">
                    <?= htmlspecialchars((string)$partner['roleLabel'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                <?php endif; ?>
                <p class="pfg-partner-name">
                  <?php if ($partnerWebsite !== null): ?>
                    <a
                      href="<?= htmlspecialchars($partnerWebsite, ENT_QUOTES, 'UTF-8') ?>"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="underline-offset-4 hover:underline"
                    >
                      <?= htmlspecialchars((string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                      <span class="sr-only"> (opens in a new tab)</span>
                    </a>
                  <?php else: ?>
                    <?= htmlspecialchars((string)$partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </p>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================================================
       8. VENUE & FESTIVAL GALLERY (Festival Grounds Editorial Band)
       ============================================================ -->
  <?php require __DIR__ . '/includes/home-sections/festival-grounds.php'; ?>

  <!-- ============================================================
       9. FAQs & CONTACT
       ============================================================ -->
  <section
    id="contact"
    aria-labelledby="faq-contact-heading"
    class="pfg-ivory-surface py-20 sm:py-24"
  >
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-12">
      <div class="space-y-6 lg:col-span-7">
        <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
          Questions &amp; Guidance
        </p>
        <h2
          id="faq-contact-heading"
          class="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-4xl"
        >
          Frequently Asked Questions
        </h2>

        <!-- HomeFaqAccordion -->
        <div class="space-y-3" data-faq-accordion="true">
          <?php foreach ($faqList as $fIdx => $faq):
              $isOpen = $fIdx === 0;
              $faqPanelId = 'faq-panel-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $faq['id']);
          ?>
            <div class="overflow-hidden rounded-xl border border-[#202D59]/15 bg-white">
              <button
                type="button"
                data-faq-toggle="<?= htmlspecialchars($faqPanelId, ENT_QUOTES, 'UTF-8') ?>"
                aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                aria-controls="<?= htmlspecialchars($faqPanelId, ENT_QUOTES, 'UTF-8') ?>"
                class="touch-target flex w-full items-center justify-between gap-4 px-6 py-4 text-left"
              >
                <div>
                  <span class="mb-1 block text-xs font-bold tracking-wider uppercase text-[#A459FF]">
                    <?= htmlspecialchars($faq['category'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="text-base font-bold text-[#202D59] sm:text-lg">
                    <?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <svg data-faq-chevron="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-[#202D59] transition-transform <?= $isOpen ? 'rotate-180' : '' ?>" aria-hidden="true">
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </button>
              <div
                id="<?= htmlspecialchars($faqPanelId, ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $isOpen ? '' : 'hidden ' ?>border-t border-[#202D59]/10 px-6 pb-5 pt-4 text-sm leading-relaxed text-[#46537C]"
              >
                <?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lg:col-span-5">
        <div class="rounded-2xl border border-[#202D59]/12 bg-[#DDDDF9] p-8 text-[#202D59]">
          <div class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#202D59]">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#099F9F]" aria-hidden="true">
              <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
              <path d="m9 12 2 2 4-4" />
            </svg>
            <span>Festival Secretariat</span>
          </div>
          <h3 class="mt-4 text-2xl font-extrabold text-[#202D59]">
            <?= htmlspecialchars(
                !empty($contactSection['title'])
                    ? (string)$contactSection['title']
                    : 'Connect with the Secretariat',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </h3>
          <p class="mt-2 text-sm text-[#202D59]/85">
            <?= htmlspecialchars(
                !empty($contactSection['body'])
                    ? (string)$contactSection['body']
                    : 'Reach our team for accessibility accommodations, institutional delegations, volunteer coordination, or media inquiries.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>

          <div class="mt-6 space-y-4 text-sm">
            <a
              href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"
              class="flex items-center gap-3 rounded-lg bg-white px-4 py-3 font-semibold text-[#202D59] hover:bg-[#202D59] hover:text-white"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#A459FF]" aria-hidden="true">
                <rect width="20" height="16" x="2" y="4" rx="2" />
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
              </svg>
              <span class="break-all"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <?php foreach ($contactPhones as $p):
                $phoneNum = (string)($p['number'] ?? '');
                $phoneClean = preg_replace('/\s+/', '', $phoneNum);
            ?>
              <a
                href="tel:<?= htmlspecialchars($phoneClean, ENT_QUOTES, 'UTF-8') ?>"
                class="flex items-center justify-between rounded-lg bg-white px-4 py-3 font-semibold text-[#202D59] hover:bg-[#202D59] hover:text-white"
              >
                <span class="flex items-center gap-2.5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#099F9F]" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                  </svg>
                  <span><?= htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <span><?= htmlspecialchars($phoneNum, ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 flex flex-wrap gap-3">
            <a
              href="/apply"
              class="btn-primary-pfg touch-target inline-flex flex-1 items-center justify-center bg-[#A459FF] px-5 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]"
            >
              Apply Now
            </a>
            <?php if (($pfgNavState ?? 'GUEST') === 'ADMIN'): ?>
              <a
                href="/admin"
                class="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
              >
                Admin Portal
              </a>
            <?php elseif (($pfgNavState ?? 'GUEST') === 'RGST'): ?>
              <a
                href="/rgst"
                class="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
              >
                Registration Desk
              </a>
            <?php elseif (($pfgNavState ?? 'GUEST') === 'PARTICIPANT'): ?>
              <a
                href="/dashboard"
                class="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
              >
                Dashboard
              </a>
            <?php else: ?>
              <a
                href="/login"
                class="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
              >
                Login
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  "use strict";

  // Dismissible Published Announcements Banner (persisted per browser session)
  var dismissedKey = "pfg27_dismissed_announcements";
  var dismissed = [];
  try {
    var stored = window.sessionStorage.getItem(dismissedKey);
    if (stored) {
      var parsed = JSON.parse(stored);
      if (Array.isArray(parsed)) dismissed = parsed;
    }
  } catch (e) {}

  function syncAnnouncementRegionVisibility() {
    var rows = document.querySelectorAll("[data-pfg-announcement]");
    var visible = 0;
    for (var i = 0; i < rows.length; i++) {
      if (!rows[i].classList.contains("hidden")) visible++;
    }
    var region = rows.length > 0 ? rows[0].closest('[role="region"]') : null;
    if (region && visible === 0) {
      region.classList.add("hidden");
    }
  }

  var annRows = document.querySelectorAll("[data-pfg-announcement]");
  for (var a = 0; a < annRows.length; a++) {
    var aid = annRows[a].getAttribute("data-pfg-announcement");
    if (aid && dismissed.indexOf(aid) !== -1) {
      annRows[a].classList.add("hidden");
    }
  }
  syncAnnouncementRegionVisibility();

  var dismissBtns = document.querySelectorAll("[data-dismiss-announcement]");
  for (var d = 0; d < dismissBtns.length; d++) {
    dismissBtns[d].addEventListener("click", function () {
      var id = this.getAttribute("data-dismiss-announcement");
      var row = this.closest("[data-pfg-announcement]");
      if (row) row.classList.add("hidden");
      if (id && dismissed.indexOf(id) === -1) {
        dismissed.push(id);
        try {
          window.sessionStorage.setItem(dismissedKey, JSON.stringify(dismissed));
        } catch (e) {}
      }
      syncAnnouncementRegionVisibility();
    });
  }

  // Home FAQ Accordion
  var faqBtns = document.querySelectorAll("[data-faq-toggle]");
  for (var f = 0; f < faqBtns.length; f++) {
    faqBtns[f].addEventListener("click", function () {
      var targetId = this.getAttribute("data-faq-toggle");
      var isCurrentlyOpen = this.getAttribute("aria-expanded") === "true";

      for (var j = 0; j < faqBtns.length; j++) {
        var btn = faqBtns[j];
        var pid = btn.getAttribute("data-faq-toggle");
        var pEl = pid ? document.getElementById(pid) : null;
        var chev = btn.querySelector("[data-faq-chevron]");
        var shouldOpen = !isCurrentlyOpen && pid === targetId;

        btn.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
        if (pEl) {
          if (shouldOpen) {
            pEl.classList.remove("hidden");
          } else {
            pEl.classList.add("hidden");
          }
        }
        if (chev) {
          if (shouldOpen) {
            chev.classList.add("rotate-180");
          } else {
            chev.classList.remove("rotate-180");
          }
        }
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
