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
        'description' => 'QR pass verification, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.',
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
        'category' => 'Organizers',
        'orgName' => 'Brotherhood',
        'shortName' => 'Brotherhood',
        'logoUrl' => '/assets/logos/brotherhood-transparent.png',
        'logoAltText' => 'Brotherhood official logo',
        'description' => 'Co-organiser advancing disability inclusion, film festivals, and cultural participation across India.',
    ],
    [
        'partnerId' => 'p-2',
        'category' => 'Organizers',
        'orgName' => 'Global Inclusive Education Network (GIEN)',
        'shortName' => 'GIEN',
        'logoUrl' => '/assets/logos/gien.jpg',
        'logoAltText' => 'Global Inclusive Education Network (GIEN) official logo',
        'description' => 'Co-organiser driving inclusive education policy, teacher training, and universal design for learning.',
    ],
    [
        'partnerId' => 'p-3',
        'category' => 'Venue & Academic Partners',
        'orgName' => 'The Maharaja Sayajirao University of Baroda',
        'shortName' => 'MSU Baroda',
        'logoUrl' => null,
        'logoAltText' => 'The Maharaja Sayajirao University of Baroda',
        'description' => 'Committed academic and venue host via Faculty of Social Work and Department of Physical Education.',
    ],
];

$FALLBACK_FAQS = [
    [
        'id' => 'faq-1',
        'category' => 'Registration',
        'question' => 'Do I need to disclose a disability to attend Purple Festival Gujarat 2027?',
        'answer' => 'No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Delegates, Volunteers, or SLI Professionals.',
    ],
    [
        'id' => 'faq-2',
        'category' => 'Accessibility',
        'question' => 'How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?',
        'answer' => 'You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.',
    ],
    [
        'id' => 'faq-3',
        'category' => 'QR Pass & Check-in',
        'question' => 'How will I receive my digital QR entry pass?',
        'answer' => 'Once your registration is confirmed (or your Volunteer/SLI application is approved), your unique digital QR pass is issued automatically in your Participant Dashboard and can be downloaded as an accessible PDF.',
    ],
    [
        'id' => 'faq-4',
        'category' => 'Volunteers & SLI',
        'question' => 'Does submitting a Volunteer or SLI application mean automatic approval?',
        'answer' => 'No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your role assignment and official digital credential.',
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

if (function_exists('getDb')) {
    try {
        $pdo = getDb();

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

        // 2. Published Partners
        $stmtPartners = $pdo->query("
            SELECT p.id AS partner_id, p.category, p.display_order,
                   o.name AS org_name, o.short_name, o.logo_url, o.logo_alt_text, o.description, o.website_url
            FROM partners p
            INNER JOIN organizations o ON p.organization_id = o.id
            WHERE p.status = 'PUBLISHED'
            ORDER BY p.display_order ASC
        ");
        if ($stmtPartners) {
            foreach ($stmtPartners->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $publishedPartners[] = [
                    'partnerId' => (string)($row['partner_id'] ?? ''),
                    'category' => (string)($row['category'] ?? ''),
                    'orgName' => (string)($row['org_name'] ?? ''),
                    'shortName' => $row['short_name'] ?? null,
                    'logoUrl' => $row['logo_url'] ?? null,
                    'logoAltText' => $row['logo_alt_text'] ?? null,
                    'description' => $row['description'] ?? null,
                    'websiteUrl' => $row['website_url'] ?? null,
                ];
            }
        }

        // 3. Programme Preview (6 items)
        $stmtProg = $pdo->query("
            SELECT id, day_number, time_slot, title, description, category, venue_name, display_order
            FROM programme_items
            WHERE status = 'PUBLISHED'
            ORDER BY day_number ASC, display_order ASC
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
                $galleryItems[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'url' => (string)($row['url'] ?? ''),
                    'altText' => (string)($row['alt_text'] ?? ''),
                    'caption' => !empty($row['source_attribution']) ? (string)$row['source_attribution'] : 'Purple Festival Gujarat 2027 Venue',
                ];
            }
        }
    } catch (\Throwable $e) {
        // Fallback arrays will be used seamlessly
    }
}

if (empty($publishedPartners)) {
    $publishedPartners = $FALLBACK_PARTNERS;
}
if (empty($programme)) {
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
                'name' => 'The Maharaja Sayajirao University of Baroda & Vadodara Sports Venues',
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
    . '  <link rel="stylesheet" href="/assets/css/disabilities-section.css?v=1" />';

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
  <!-- ============================================================
       1. AUTHENTIC LAVENDER-PERIWINKLE HERO (With Gujarat Architectural Line-Art Background)
       ============================================================ -->
  <section
    aria-labelledby="hero-heading"
    class="relative flex min-h-[calc(100svh-76px)] flex-col items-center justify-between overflow-hidden bg-[#F2E9FE] px-6 pb-8 pt-10 text-center text-[#202D59] sm:px-10 md:pt-14"
  >
    <?php include __DIR__ . '/includes/hero-background.php'; ?>

    <div class="relative z-10 mx-auto flex w-full max-w-[1040px] flex-1 flex-col items-center justify-center">
      <!-- Monumental Reference-Matched Headline (~104px desktop, DM Sans Bold) -->
      <h1
        id="hero-heading"
        class="text-[clamp(3.1rem,8vw,6.5rem)] font-bold leading-[1.03] tracking-[-0.03em] text-[#202D59]"
      >
        Inclusion, celebrated.
      </h1>

      <!-- Reference-Matched Large Subtitle (~30px desktop, regular weight) -->
      <p class="mt-5 max-w-[920px] text-[clamp(1.2rem,2.2vw,1.95rem)] font-normal leading-[1.35] tracking-[-0.01em] text-[#202D59]">
        <?= htmlspecialchars(
            !empty($heroSection['body'])
                ? (string)$heroSection['body']
                : "India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016).",
            ENT_QUOTES,
            'UTF-8'
        ) ?>
      </p>

      <!-- Single Prominent Centered #A459FF Apply Now CTA (exact reference scale) -->
      <div class="mt-10 flex items-center justify-center">
        <a
          href="/apply"
          class="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-9 py-3.5 text-[20px] font-semibold tracking-[-0.01em] text-white transition-colors hover:bg-[#8D3BF2]"
        >
          Apply Now
        </a>
      </div>

      <!-- Clean Static Flat Authorized Purple Festival Emblem (NO unwanted 3D animation) -->
      <div class="mt-10 flex flex-col items-center">
        <?= renderPurpleFestEmblemSvg('h-28 w-auto text-[#202D59]/85 sm:h-32') ?>
        <p class="mt-2.5 text-sm font-medium tracking-[0.08em] uppercase text-[#202D59]/75">
          Purple Festival Gujarat 2027 &middot; Vadodara, Gujarat
        </p>
      </div>
    </div>

    <!-- Bottom-Left Info Pill (matching reference corner badge) -->
    <div class="relative z-10 mt-6 flex w-full max-w-[1440px] justify-start">
      <div class="inline-flex items-center gap-2.5 bg-white px-5 py-3 text-[15px] font-medium text-[#202D59] shadow-xs">
        <span>Purple Festival Gujarat</span>
        <span aria-hidden="true" class="text-[#099F9F]">▼</span>
        <span class="font-bold text-[#202D59]">21–23 Jan 2027</span>
      </div>
    </div>
  </section>

  <!-- ============================================================
       2. ABOUT THE FESTIVAL & KEY BROCHURE STATS
       ============================================================ -->
  <section
    id="about"
    aria-labelledby="about-heading"
    class="border-b border-[#202D59]/10 bg-white py-20 sm:py-24"
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
       3. VIKSIT BHARAT@2047
       ============================================================ -->
  <section
    aria-labelledby="viksit-heading"
    class="bg-[#202D59] py-20 text-white sm:py-24"
    data-surface="dark"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="grid gap-10 lg:grid-cols-12 lg:items-center">
        <div class="space-y-5 lg:col-span-8">
          <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#DDDDF9]">
            <?= htmlspecialchars(
                !empty($viksitSection['subtitle'])
                    ? (string)$viksitSection['subtitle']
                    : "Aligned with India's Vision for 2047",
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>
          <h2
            id="viksit-heading"
            class="text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl"
          >
            <?= htmlspecialchars(
                !empty($viksitSection['title'])
                    ? (string)$viksitSection['title']
                    : 'A Viksit Bharat Must Be an Inclusive Bharat.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </h2>
          <p class="max-w-3xl text-base leading-relaxed text-[#DDDDF9] sm:text-lg">
            <?= htmlspecialchars(
                !empty($viksitSection['body'])
                    ? (string)$viksitSection['body']
                    : "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.",
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>
          <p class="text-xs text-[#DDDDF9]/75">
            <?= htmlspecialchars(
                !empty($viksitData['disclaimer'])
                    ? (string)$viksitData['disclaimer']
                    : 'Purple Festival Gujarat 2027 contributes to the wider national vision for inclusive development under Viksit Bharat@2047.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:col-span-4 lg:grid-cols-1">
          <?php
          $viksitPillars = [
              'Inclusive Education & NEP 2020',
              'Assistive & Digital Public Innovation',
              'Dignified Livelihoods & Equal Opportunity',
              'Barrier-Free Cultural & Civic Participation',
          ];
          foreach ($viksitPillars as $pillar):
          ?>
            <div class="flex items-center gap-3 rounded-lg border border-white/15 bg-white/10 px-4 py-3.5 text-sm font-semibold text-white">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-[#A459FF]" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="m9 12 2 2 4-4" />
              </svg>
              <span><?= htmlspecialchars($pillar, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
       4. FESTIVAL HIGHLIGHTS
       ============================================================ -->
  <section
    id="highlights"
    aria-labelledby="highlights-heading"
    class="border-b border-[#202D59]/10 bg-[#EFEFEF]/65 py-20 sm:py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="max-w-2xl space-y-3">
        <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
          The Festival Experience
        </p>
        <h2
          id="highlights-heading"
          class="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
        >
          Six Pillars of Celebration
        </h2>
      </div>

      <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($FESTIVAL_HIGHLIGHTS as $item): ?>
          <article class="flex flex-col rounded-xl border border-[#202D59]/10 bg-white p-7 shadow-xs transition-shadow hover:shadow-md">
            <div class="flex items-center justify-between">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#DDDDF9] text-[#202D59]">
                <?= renderHighlightIconSvg($item['icon']) ?>
              </span>
              <span class="text-xs font-bold tracking-wider uppercase text-[#099F9F]">
                <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <h3 class="mt-5 text-xl font-bold text-[#202D59]">
              <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <p class="mt-2.5 text-sm leading-relaxed text-[#46537C]">
              <?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

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
      'initialExpandedCount' => 0,
  ];
  require __DIR__ . '/includes/disabilities-section.php';
  ?>

  <!-- ============================================================
       6. PROGRAMME PREVIEW
       ============================================================ -->
  <section
    aria-labelledby="programme-preview-heading"
    class="border-b border-[#202D59]/10 bg-[#DDDDF9]/50 py-20 sm:py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
        <div class="space-y-3">
          <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
            21–23 January 2027 &middot; Vadodara
          </p>
          <h2
            id="programme-preview-heading"
            class="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
          >
            Programme Highlights
          </h2>
        </div>
        <a
          href="/programme"
          class="btn-primary-pfg touch-target inline-flex items-center gap-2 bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]"
        >
          <span>Explore Full 3-Day Schedule</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
          </svg>
        </a>
      </div>

      <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($programme as $item): ?>
          <article class="flex flex-col justify-between rounded-xl border border-[#202D59]/12 bg-white p-6 shadow-xs">
            <div>
              <div class="flex items-center justify-between gap-2 text-xs font-bold uppercase tracking-wider">
                <span class="rounded bg-[#DDDDF9] px-2.5 py-1 text-[#202D59]">
                  Day <?= (int)$item['dayNumber'] ?>
                </span>
                <span class="text-[#099F9F]">
                  <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <h3 class="mt-4 text-lg font-bold text-[#202D59]">
                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
              </h3>
              <?php if (!empty($item['description'])): ?>
                <p class="mt-2 text-sm text-[#46537C]">
                  <?= htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php endif; ?>
            </div>
            <div class="mt-5 space-y-1.5 border-t border-[#202D59]/10 pt-4 text-xs font-medium text-[#46537C]">
              <p class="flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#A459FF]" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                <span><?= htmlspecialchars($item['timeSlot'], ENT_QUOTES, 'UTF-8') ?></span>
              </p>
              <?php if (!empty($item['venueName'])): ?>
                <p class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                    <circle cx="12" cy="10" r="3" />
                  </svg>
                  <span><?= htmlspecialchars((string)$item['venueName'], ENT_QUOTES, 'UTF-8') ?></span>
                </p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================================================
       7. PARTNERS & ORGANISERS (Consent-Verified Published Only)
       ============================================================ -->
  <section
    id="partners"
    aria-labelledby="partners-heading"
    class="border-b border-[#202D59]/10 bg-white py-20 sm:py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="max-w-2xl space-y-3">
        <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
          Confirmed Collaborations
        </p>
        <h2
          id="partners-heading"
          class="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
        >
          Organisers &amp; Partners
        </h2>
        <p class="text-sm text-[#46537C]">
          Only organisations with verified publication consent are displayed publicly; other
          invited institutions remain in CMS draft until confirmed.
        </p>
      </div>

      <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($publishedPartners as $partner): ?>
          <article class="flex flex-col justify-between rounded-xl border border-[#202D59]/12 bg-[#EFEFEF]/45 p-6">
            <div>
              <span class="inline-block rounded bg-[#DDDDF9] px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-[#202D59]">
                <?= htmlspecialchars($partner['category'], ENT_QUOTES, 'UTF-8') ?>
              </span>
              <div class="mt-4 flex h-20 items-center justify-start rounded-lg bg-white p-3">
                <?php if (!empty($partner['logoUrl'])): ?>
                  <img
                    src="<?= htmlspecialchars((string)$partner['logoUrl'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars(!empty($partner['logoAltText']) ? (string)$partner['logoAltText'] : $partner['orgName'] . ' logo', ENT_QUOTES, 'UTF-8') ?>"
                    width="180"
                    height="56"
                    loading="lazy"
                    decoding="async"
                    class="max-h-14 w-auto object-contain"
                  />
                <?php else: ?>
                  <span class="text-base font-extrabold text-[#202D59]">
                    <?= htmlspecialchars(!empty($partner['shortName']) ? (string)$partner['shortName'] : $partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>
              </div>
              <h3 class="mt-4 text-lg font-bold text-[#202D59]">
                <?= htmlspecialchars($partner['orgName'], ENT_QUOTES, 'UTF-8') ?>
              </h3>
              <?php if (!empty($partner['description'])): ?>
                <p class="mt-2 text-sm text-[#46537C]">
                  <?= htmlspecialchars((string)$partner['description'], ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================================================
       8. VENUE & FESTIVAL GALLERY
       ============================================================ -->
  <section
    id="gallery"
    aria-labelledby="gallery-heading"
    class="border-b border-[#202D59]/10 bg-[#EFEFEF]/65 py-20 sm:py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="max-w-2xl space-y-3">
        <p class="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
          Vadodara Venues
        </p>
        <h2
          id="gallery-heading"
          class="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
        >
          Festival Grounds &amp; Facilities
        </h2>
      </div>

      <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($galleryItems as $img): ?>
          <figure class="overflow-hidden rounded-xl border border-[#202D59]/12 bg-white shadow-xs">
            <div class="aspect-[16/10] w-full overflow-hidden bg-[#DDDDF9]">
              <img
                src="<?= htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($img['altText'], ENT_QUOTES, 'UTF-8') ?>"
                width="800"
                height="500"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover"
              />
            </div>
            <figcaption class="px-4 py-3 text-xs font-semibold text-[#46537C]">
              <?= htmlspecialchars($img['caption'], ENT_QUOTES, 'UTF-8') ?>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================================================
       9. FAQs & CONTACT
       ============================================================ -->
  <section
    id="contact"
    aria-labelledby="faq-contact-heading"
    class="bg-white py-20 sm:py-24"
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
            <a
              href="/login"
              class="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
            >
              Login
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  "use strict";

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
