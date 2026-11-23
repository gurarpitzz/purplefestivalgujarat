<?php
declare(strict_types=1);

if (is_file(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}
if (is_file(__DIR__ . '/includes/database.php')) {
    require_once __DIR__ . '/includes/database.php';
}

$pageTitle = 'Accessibility Guide & 21 RPwD Disabilities';
$pageDescription = "Complete venue accessibility guide, available accommodation services, and all 21 specified disabilities under India's RPwD Act 2016 for Purple Festival Gujarat 2027.";

$FALLBACK_DISABILITIES = defined('RPWD_21_DISABILITIES') && is_array(RPWD_21_DISABILITIES)
    ? RPWD_21_DISABILITIES
    : [
        ['code' => 'BLINDNESS', 'name' => 'Blindness', 'rpwdGroup' => 'Visual Impairment', 'description' => 'Total absence of sight, or visual acuity not exceeding 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 10 degrees.', 'accommodationExamples' => ['Braille schedules and signage', 'Screen-reader-compatible digital materials', 'Sighted guides and tactile floor pathways', 'Audio description during stage performances']],
        ['code' => 'LOW_VISION', 'name' => 'Low Vision', 'rpwdGroup' => 'Visual Impairment', 'description' => 'Visual acuity not exceeding 6/18 or less than 20/60 up to 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 40 degrees up to 10 degrees.', 'accommodationExamples' => ['Large-print schedules and high-contrast signage', 'Front-row seating near screens', 'Adjustable text size and high-contrast digital access']],
        ['code' => 'LEPROSY_CURED', 'name' => 'Leprosy Cured Persons', 'rpwdGroup' => 'Locomotor Disability', 'description' => 'Persons cured of leprosy who continue to experience loss of sensation in hands or feet, paresis in the eye and eyelid, or manifest deformity that limits daily participation.', 'accommodationExamples' => ['Ergonomic seating and minimal-grip utensils', 'Accessible washrooms and step-free routes', 'Dignified, stigma-free assistance desks']],
        ['code' => 'HEARING_IMPAIRMENT', 'name' => 'Hearing Impairment (Deaf and Hard of Hearing)', 'rpwdGroup' => 'Hearing Impairment', 'description' => 'Includes Deaf persons (70 dB or greater loss in speech frequencies in both ears) and Hard of Hearing persons (60 dB to 70 dB loss in speech frequencies in both ears).', 'accommodationExamples' => ['Qualified Indian Sign Language (ISL) interpretation', 'Live captioning (CART) on venue screens', 'Visual alerts and well-lit front seating for lip-reading']],
        ['code' => 'LOCOMOTOR_DISABILITY', 'name' => 'Locomotor Disability', 'rpwdGroup' => 'Locomotor Disability', 'description' => 'Inability to execute distinctive activities associated with movement of self and objects resulting from affliction of musculoskeletal or nervous system.', 'accommodationExamples' => ['Step-free ramps (1:12 gradient) and elevators', 'Wheelchair-accessible seating and charging bays', 'Accessible restrooms and battery-operated venue shuttles']],
        ['code' => 'DWARFISM', 'name' => 'Dwarfism', 'rpwdGroup' => 'Locomotor Disability', 'description' => 'A medical or genetic condition resulting in an adult height of 4 feet 10 inches (147 centimeters) or less.', 'accommodationExamples' => ['Lowered registration and refreshment counters', 'Accessible microphones and seating options', 'Step-free access across all stages and pavilions']],
        ['code' => 'INTELLECTUAL_DISABILITY', 'name' => 'Intellectual Disability', 'rpwdGroup' => 'Intellectual Disability', 'description' => 'A condition characterized by significant limitation both in intellectual functioning (reasoning, learning, problem solving) and in adaptive behavior covering everyday social and practical skills.', 'accommodationExamples' => ['Easy-read plain-language schedules with pictograms', 'Trained orientation volunteers and buddy support', 'Predictable signage and calm rest areas']],
        ['code' => 'MENTAL_ILLNESS', 'name' => 'Mental Illness', 'rpwdGroup' => 'Mental Behavior', 'description' => 'A substantial disorder of thinking, mood, perception, orientation, or memory that grossly impairs judgment, behavior, capacity to recognize reality, or ability to meet the ordinary demands of life.', 'accommodationExamples' => ['Quiet sensory-friendly sanctuary spaces', 'Flexible breaks without losing seating', 'Trained psychosocial first-aid volunteers']],
        ['code' => 'AUTISM_SPECTRUM_DISORDER', 'name' => 'Autism Spectrum Disorder', 'rpwdGroup' => 'Intellectual / Neurodevelopmental', 'description' => 'A neuro-developmental condition typically appearing in the first three years of life that significantly affects verbal and non-verbal communication, social interaction, and sensory processing.', 'accommodationExamples' => ['Low-stimulation sensory sanctuary rooms', 'Noise-dampening headphones and visual schedules', 'Advance venue walkthroughs and reduced-queue entry']],
        ['code' => 'CEREBRAL_PALSY', 'name' => 'Cerebral Palsy', 'rpwdGroup' => 'Locomotor Disability', 'description' => 'A group of non-progressive neurological conditions affecting body movements and muscle coordination caused by damage to one or more specific areas of the brain.', 'accommodationExamples' => ['Wide wheelchair pathways and supportive seating', 'AAC (Augmentative & Alternative Communication) patience', 'Accessible restrooms with caregiver space']],
        ['code' => 'MUSCULAR_DYSTROPHY', 'name' => 'Muscular Dystrophy', 'rpwdGroup' => 'Locomotor Disability', 'description' => 'A group of hereditary genetic muscle diseases that weaken the muscles that move the human body, characterized by progressive skeletal muscle weakness.', 'accommodationExamples' => ['Power wheelchair charging bays', 'Short-distance accessible drop-off points', 'Climate-controlled rest zones']],
        ['code' => 'CHRONIC_NEUROLOGICAL', 'name' => 'Chronic Neurological Conditions', 'rpwdGroup' => 'Chronic Neurological', 'description' => 'Conditions originating in the central or peripheral nervous system (such as epilepsy, stroke aftermath, or neurodegenerative conditions) requiring ongoing management.', 'accommodationExamples' => ['Seizure-safe lighting (no flashing strobes)', 'Priority shaded seating and hydration stations', 'On-site medical emergency response desk']],
        ['code' => 'SPECIFIC_LEARNING_DISABILITIES', 'name' => 'Specific Learning Disabilities', 'rpwdGroup' => 'Intellectual / Neurodevelopmental', 'description' => 'A heterogeneous group of conditions wherein there is a deficit in processing language, spoken or written, manifesting as difficulty in comprehending, reading, writing, spelling, or mathematical calculation.', 'accommodationExamples' => ['Readable sans-serif typography and uncluttered layouts', 'Audio and visual alternatives to dense text', 'Untimed, save-and-resume registration workflows']],
        ['code' => 'MULTIPLE_SCLEROSIS', 'name' => 'Multiple Sclerosis', 'rpwdGroup' => 'Chronic Neurological', 'description' => 'An inflammatory, nervous system disease in which the myelin sheaths around the axons of nerve cells of the brain and spinal cord are damaged, affecting communication between brain and body.', 'accommodationExamples' => ['Cooling and shaded rest areas to prevent heat fatigue', 'Mobility assistance and priority seating', 'Accessible restrooms near every hall']],
        ['code' => 'SPEECH_AND_LANGUAGE', 'name' => 'Speech and Language Disability', 'rpwdGroup' => 'Speech & Language', 'description' => 'A permanent disability arising out of conditions such as laryngectomy or aphasia affecting one or more components of speech and language.', 'accommodationExamples' => ['Text, email, and digital chat support at help desks', 'Extended speaking time and AAC-friendly microphones', 'Written Q&A options during panel discussions']],
        ['code' => 'THALASSEMIA', 'name' => 'Thalassemia', 'rpwdGroup' => 'Blood Disorder', 'description' => 'A group of inherited disorders characterized by reduced or absent amounts of hemoglobin.', 'accommodationExamples' => ['Priority seating to reduce standing fatigue', 'Hydration and dietary accommodation support', 'Quick access to medical assistance desks']],
        ['code' => 'HEMOPHILIA', 'name' => 'Hemophilia', 'rpwdGroup' => 'Blood Disorder', 'description' => 'An inheritable disease, usually affecting males, characterized by impairment of the normal clotting ability of blood.', 'accommodationExamples' => ['Safe, uncrowded pathways and priority entry lanes', 'On-site medical room awareness and rapid first aid', 'Accessible seating near aisles']],
        ['code' => 'SICKLE_CELL_DISEASE', 'name' => 'Sickle Cell Disease', 'rpwdGroup' => 'Blood Disorder', 'description' => 'A hemolytic disorder characterized by chronic anemia, painful events, and various complications due to associated tissue and organ damage.', 'accommodationExamples' => ['Shaded rest lounges and continuous hydration stations', 'Minimal walking distances with shuttle support', 'Medical assistance readiness']],
        ['code' => 'MULTIPLE_DISABILITIES', 'name' => 'Multiple Disabilities (including Deafblindness)', 'rpwdGroup' => 'Multiple Disabilities', 'description' => 'Two or more of the specified disabilities occurring together, including deafblindness — a combination of hearing and visual impairments causing severe communication and developmental needs.', 'accommodationExamples' => ['Tactile sign language interpreters and intervener support', 'Combined physical, sensory, and communication accommodations', 'Dedicated companion / escort seating']],
        ['code' => 'ACID_ATTACK_VICTIM', 'name' => 'Acid Attack Victim', 'rpwdGroup' => 'Locomotor / Physical', 'description' => 'A person disfigured due to violent assaults by throwing of acid or similar corrosive substance.', 'accommodationExamples' => ['Climate-controlled, sun-protected spaces', 'Visual or mobility accommodations as individually requested', 'Respectful, privacy-preserving check-in and support']],
        ['code' => 'PARKINSONS_DISEASE', 'name' => "Parkinson's Disease", 'rpwdGroup' => 'Chronic Neurological', 'description' => 'A progressive disease of the nervous system marked by tremor, muscular rigidity, and slow, imprecise movement.', 'accommodationExamples' => ['Large touch targets and keyboard-accessible controls', 'Seated check-in desks and handrails along walkways', 'Untimed forms and mobility assistance']],
    ];

$FALLBACK_ACCOMMODATIONS = defined('ACCOMMODATION_TYPES_SEED') && is_array(ACCOMMODATION_TYPES_SEED)
    ? ACCOMMODATION_TYPES_SEED
    : [
        ['code' => 'ISL_INTERPRETER', 'label' => 'Indian Sign Language (ISL) Interpreter', 'description' => null],
        ['code' => 'TACTILE_INTERPRETATION', 'label' => 'Tactile Interpretation (Deafblind support)', 'description' => null],
        ['code' => 'BRAILLE_SCHEDULE', 'label' => 'Braille Schedule & Programme', 'description' => null],
        ['code' => 'LARGE_PRINT', 'label' => 'Large-Print Material', 'description' => null],
        ['code' => 'SCREEN_READER_DIGITAL', 'label' => 'Screen-Reader-Compatible Digital Material', 'description' => null],
        ['code' => 'WHEELCHAIR_SEATING', 'label' => 'Wheelchair-Accessible Seating', 'description' => null],
        ['code' => 'WHEELCHAIR_CHARGING', 'label' => 'Wheelchair Charging Bay', 'description' => null],
        ['code' => 'SIGHTED_GUIDE', 'label' => 'Sighted Guide', 'description' => null],
        ['code' => 'SENSORY_SANCTUARY', 'label' => 'Sensory Sanctuary Access', 'description' => null],
        ['code' => 'CAPTIONING', 'label' => 'Live Captioning (CART)', 'description' => null],
        ['code' => 'MOBILITY_ASSISTANCE', 'label' => 'Mobility Assistance / Venue Shuttle', 'description' => null],
        ['code' => 'DIETARY', 'label' => 'Dietary Accommodation', 'description' => null],
        ['code' => 'OTHER', 'label' => 'Other Accessibility Requirement', 'description' => null],
    ];

$FALLBACK_VENUES = [
    [
        'id' => 'v-1',
        'name' => 'Sama Indoor Stadium',
        'address' => 'Sama, Vadodara, Gujarat',
        'confirmationStatus' => 'CONFIRMED',
        'accessibilityFeatures' => ['Step-free entry', 'Wheelchair-accessible courts', 'Accessible restrooms', 'Para-sports ready'],
    ],
    [
        'id' => 'v-2',
        'name' => 'The Maharaja Sayajirao University of Baroda (FSW & Dept. of Physical Education)',
        'address' => 'Pratapgunj, Vadodara, Gujarat',
        'confirmationStatus' => 'COMMITTED',
        'accessibilityFeatures' => ['Campus access audit in progress', 'Accessible seminar halls', 'Sports pavilion'],
    ],
    [
        'id' => 'v-3',
        'name' => 'GMERS Medical College, Gotri',
        'address' => 'Gotri, Vadodara, Gujarat',
        'confirmationStatus' => 'PENDING',
        'accessibilityFeatures' => ['Pending final venue confirmation'],
    ],
];

$disabilityList = [];
$accommodationList = [];
$venues = [];

if (function_exists('getDb')) {
    try {
        $pdo = getDb();

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

        $stmtAcc = $pdo->query("
            SELECT code, label, description
            FROM accommodation_types
            WHERE is_active = 1
            ORDER BY display_order ASC
        ");
        if ($stmtAcc) {
            foreach ($stmtAcc->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $accommodationList[] = [
                    'code' => (string)($row['code'] ?? ''),
                    'label' => (string)($row['label'] ?? ''),
                    'description' => $row['description'] ?? null,
                ];
            }
        }

        $stmtVen = $pdo->query("
            SELECT id, name, address, confirmation_status, accessibility_features
            FROM venues
        ");
        if ($stmtVen) {
            foreach ($stmtVen->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $features = $row['accessibility_features'] ?? [];
                if (is_string($features) && $features !== '') {
                    $decoded = json_decode($features, true);
                    $features = is_array($decoded) ? $decoded : [];
                }
                $venues[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'name' => (string)($row['name'] ?? ''),
                    'address' => $row['address'] ?? null,
                    'confirmationStatus' => (string)($row['confirmation_status'] ?? 'PENDING'),
                    'accessibilityFeatures' => is_array($features) ? $features : [],
                ];
            }
        }
    } catch (\Throwable $e) {
        // Fallback arrays used
    }
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
if (empty($accommodationList)) {
    foreach ($FALLBACK_ACCOMMODATIONS as $a) {
        $accommodationList[] = [
            'code' => $a['code'],
            'label' => $a['label'],
            'description' => $a['description'] ?? null,
        ];
    }
}
if (empty($venues)) {
    $venues = $FALLBACK_VENUES;
}

$rpwdGroups = ['All'];
foreach ($disabilityList as $dItem) {
    if (!in_array($dItem['rpwdGroup'], $rpwdGroups, true)) {
        $rpwdGroups[] = $dItem['rpwdGroup'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content" tabindex="-1" class="min-h-screen bg-[#F6F1F8] focus:outline-none">
  <!-- Hero -->
  <section
    aria-labelledby="accessibility-page-title"
    class="editorial-hero-bg border-b border-[#383242] bg-[#17151C] py-16 text-[#F9F7FC] sm:py-20"
    data-surface="dark"
  >
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.22em] uppercase text-[#D5B77B]">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
          <circle cx="16" cy="4" r="1" />
          <path d="m18 19 1-7-6 1" />
          <path d="m5 8 3-3 5.5 3-2.36 3.5" />
          <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
          <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
        </svg>
        Barrier-Free Festival Architecture
      </p>
      <h1
        id="accessibility-page-title"
        class="font-display mt-4 text-4xl font-bold text-[#F9F7FC] sm:text-5xl"
      >
        Accessibility &amp; Reasonable Accommodations
      </h1>
      <p class="mt-4 max-w-3xl text-base leading-relaxed text-[#E8DFF0] sm:text-lg">
        Purple Festival Gujarat 2027 is engineered for dignified, independent participation
        across physical, sensory, intellectual, neurodevelopmental, psychosocial, and
        communication access needs.
      </p>

      <div class="mt-8 flex flex-wrap gap-3">
        <a
          href="/apply"
          class="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
        >
          <span>Register &amp; Request Accommodations</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
          </svg>
        </a>
        <a
          href="/dashboard"
          class="touch-target inline-flex items-center gap-2 rounded-full border border-[#B6A1C8]/50 bg-[#25212B] px-6 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
          </svg>
          <span>Update Request in Dashboard</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Available Festival Accommodation Services -->
  <section aria-labelledby="services-heading" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="max-w-3xl space-y-2">
      <p class="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
        On-Site &amp; Digital Support
      </p>
      <h2 id="services-heading" class="font-display text-3xl font-bold text-[#211B29]">
        Requestable Accommodation Services
      </h2>
      <p class="text-sm leading-relaxed text-[#4B4257]">
        Select any of the following accommodations during registration or update your
        preferences anytime in your Participant Dashboard. Your disclosure and support
        requests are strictly confidential and accessible only to authorized accessibility
        coordinators.
      </p>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($accommodationList as $item): ?>
        <div class="surface-card flex items-start gap-3 rounded-2xl border border-[#59436D]/20 bg-white p-5 shadow-xs">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-5 w-5 shrink-0 text-[#19734B]" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <path d="m9 12 2 2 4-4" />
          </svg>
          <div>
            <h3 class="text-sm font-bold text-[#211B29]">
              <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <?php if (!empty($item['description'])): ?>
              <p class="mt-1 text-xs text-[#4B4257]">
                <?= htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Venue Accessibility Status -->
  <?php if (!empty($venues)): ?>
    <section
      aria-labelledby="venues-heading"
      class="border-y border-[#59436D]/15 bg-white py-16"
    >
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl space-y-2">
          <p class="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
            Vadodara Venues
          </p>
          <h2 id="venues-heading" class="font-display text-3xl font-bold text-[#211B29]">
            Venue Accessibility &amp; Confirmation Status
          </h2>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-3">
          <?php foreach ($venues as $v): ?>
            <article class="surface-card flex flex-col justify-between rounded-2xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-6">
              <div>
                <span class="inline-block rounded-full bg-[#E8DFF0] px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#432F56]">
                  Status: <?= htmlspecialchars($v['confirmationStatus'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <h3 class="font-display mt-3 text-lg font-bold text-[#211B29]">
                  <?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <?php if (!empty($v['address'])): ?>
                  <p class="mt-1 flex items-center gap-1.5 text-xs text-[#4B4257]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0 text-[#59436D]" aria-hidden="true">
                      <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    <span><?= htmlspecialchars((string)$v['address'], ENT_QUOTES, 'UTF-8') ?></span>
                  </p>
                <?php endif; ?>
                <?php if (!empty($v['accessibilityFeatures'])): ?>
                  <ul class="mt-4 space-y-1.5 text-xs text-[#211B29]">
                    <?php foreach ($v['accessibilityFeatures'] as $feat): ?>
                      <li class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#19734B]" aria-hidden="true">
                          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                          <path d="m9 12 2 2 4-4" />
                        </svg>
                        <span><?= htmlspecialchars((string)$feat, ENT_QUOTES, 'UTF-8') ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- All 21 Specified Disabilities under RPwD Act 2016 -->
  <section
    aria-labelledby="rpwd-heading"
    class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
  >
    <div class="mb-8 max-w-3xl space-y-2">
      <p class="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
        Rights of Persons with Disabilities (RPwD) Act, 2016
      </p>
      <h2 id="rpwd-heading" class="font-display text-3xl font-bold text-[#211B29]">
        All 21 Benchmark Disabilities &amp; Tailored Supports
      </h2>
      <p class="text-sm text-[#4B4257]">
        Disability disclosure is always voluntary at Purple Festival Gujarat 2027. Persons
        with disabilities are welcome in every role — attendee, delegate, speaker, artist,
        athlete, volunteer, or organizer.
      </p>
    </div>

    <div class="space-y-6" data-disabilities-explorer="true">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="relative w-full lg:max-w-sm">
          <label for="disability-search" class="sr-only">
            Search the 21 specified disabilities or accommodations
          </label>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#202D59]" aria-hidden="true">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <input
            id="disability-search"
            type="search"
            placeholder="Search disabilities or accommodations..."
            class="touch-target w-full rounded-lg border border-[#202D59]/25 bg-white pl-10 pr-4 text-sm text-[#202D59] placeholder:text-[#46537C] focus:border-[#A459FF]"
          />
        </div>

        <div
          role="group"
          aria-label="Filter disabilities by RPwD category"
          class="flex flex-wrap gap-2"
        >
          <?php foreach ($rpwdGroups as $gIdx => $grp): ?>
            <button
              type="button"
              data-rpwd-group-btn="<?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>"
              aria-pressed="<?= $gIdx === 0 ? 'true' : 'false' ?>"
              class="touch-target rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors <?= $gIdx === 0 ? 'border-[#202D59] bg-[#202D59] text-white' : 'border-[#202D59]/20 bg-white text-[#202D59] hover:border-[#A459FF] hover:bg-[#DDDDF9]' ?>"
            >
              <?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <p id="disability-count-status" class="text-xs font-medium text-[#46537C]" aria-live="polite">
        Showing <?= count($disabilityList) ?> of <?= count($disabilityList) ?> specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.
      </p>

      <div id="disability-empty-state" class="surface-card hidden rounded-2xl border border-[#59436D]/20 bg-white p-8 text-center">
        <p class="text-sm font-medium text-[#211B29]">
          No disabilities match your search filter. Try clearing your search filter.
        </p>
      </div>

      <div id="disability-grid" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($disabilityList as $dIdx => $dItem):
            $isOpen = $dIdx < 3; // initialExpandedCount = 3 matching src/app/accessibility/page.tsx
            $panelId = 'disability-panel-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $dItem['code']);
            $searchBlob = strtolower(
                $dItem['name'] . ' ' .
                $dItem['rpwdGroup'] . ' ' .
                $dItem['description'] . ' ' .
                implode(' ', $dItem['accommodationExamples'])
            );
        ?>
          <article
            data-disability-card="true"
            data-rpwd-group="<?= htmlspecialchars($dItem['rpwdGroup'], ENT_QUOTES, 'UTF-8') ?>"
            data-search-text="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
            class="surface-card flex flex-col rounded-2xl border border-[#59436D]/20 bg-white shadow-xs transition-shadow hover:shadow-md"
          >
            <button
              type="button"
              data-disability-toggle="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
              aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              class="touch-target flex w-full items-start justify-between gap-3 rounded-2xl p-5 text-left"
            >
              <div class="space-y-1.5">
                <div class="flex items-center gap-2">
                  <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md bg-[#E8DFF0] px-1.5 text-xs font-bold text-[#432F56]">
                    <?= str_pad((string)$dItem['displayOrder'], 2, '0', STR_PAD_LEFT) ?>
                  </span>
                  <span class="text-[11px] font-semibold tracking-wider uppercase text-[#59436D]">
                    <?= htmlspecialchars($dItem['rpwdGroup'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <h3 class="font-display text-base font-semibold leading-snug text-[#211B29]">
                  <?= htmlspecialchars($dItem['name'], ENT_QUOTES, 'UTF-8') ?>
                </h3>
              </div>
              <svg data-chevron="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-1 h-5 w-5 shrink-0 text-[#59436D] transition-transform <?= $isOpen ? 'rotate-180' : '' ?>" aria-hidden="true">
                <path d="m6 9 6 6 6-6" />
              </svg>
            </button>

            <div
              id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              class="<?= $isOpen ? '' : 'hidden ' ?>border-t border-[#59436D]/15 px-5 pb-5 pt-4 text-sm text-[#211B29]"
            >
              <p class="leading-relaxed text-[#4B4257]">
                <?= htmlspecialchars($dItem['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
              <?php if (!empty($dItem['accommodationExamples'])): ?>
                <div class="mt-4 rounded-xl bg-[#F6F1F8] p-3.5">
                  <p class="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wide uppercase text-[#432F56]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                      <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                      <path d="m9 12 2 2 4-4" />
                    </svg>
                    Festival Accommodations
                  </p>
                  <ul class="space-y-1.5 text-xs text-[#211B29]">
                    <?php foreach ($dItem['accommodationExamples'] as $ex): ?>
                      <li class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#19734B]" aria-hidden="true">
                          <circle cx="12" cy="12" r="10" />
                          <path d="m9 12 2 2 4-4" />
                        </svg>
                        <span><?= htmlspecialchars((string)$ex, ENT_QUOTES, 'UTF-8') ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Bottom CTA -->
  <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
    <div
      class="surface-card rounded-3xl border border-[#383242] bg-[#17151C] p-8 text-[#F9F7FC] sm:p-10"
      data-surface="dark"
    >
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-2">
          <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] uppercase text-[#D5B77B]">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
            </svg>
            Dedicated Accessibility Desk
          </p>
          <h2 class="font-display text-2xl font-bold text-[#F9F7FC] sm:text-3xl">
            Need Custom Support Before You Arrive?
          </h2>
          <p class="max-w-2xl text-sm text-[#D7CCE2]">
            Submit your accommodation needs during registration, manage them in your
            Participant Dashboard, or write directly to
            <a
              href="mailto:purplefestivalvadodara@gmail.com"
              class="font-semibold text-[#D5B77B] underline underline-offset-4"
            >
              purplefestivalvadodara@gmail.com
            </a>.
          </p>
        </div>
        <div class="flex flex-wrap gap-3">
          <a
            href="/apply"
            class="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
          >
            <span>Apply Now</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
              <path d="M5 12h14" />
              <path d="m12 5 7 7-7 7" />
            </svg>
          </a>
          <a
            href="/legal/accessibility-statement"
            class="touch-target inline-flex items-center gap-2 rounded-full border border-[#4D445C] bg-[#25212B] px-5 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
              <path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
            </svg>
            <span>WCAG 2.2 Statement</span>
          </a>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  "use strict";

  var selectedGroup = "All";
  var searchInput = document.getElementById("disability-search");
  var groupBtns = document.querySelectorAll("[data-rpwd-group-btn]");
  var cards = document.querySelectorAll("[data-disability-card]");
  var countStatus = document.getElementById("disability-count-status");
  var emptyState = document.getElementById("disability-empty-state");
  var gridEl = document.getElementById("disability-grid");

  function filterDisabilities() {
    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";
    var visibleCount = 0;

    for (var i = 0; i < cards.length; i++) {
      var card = cards[i];
      var grp = card.getAttribute("data-rpwd-group") || "";
      var text = card.getAttribute("data-search-text") || "";
      var matchesGroup = selectedGroup === "All" || grp === selectedGroup;
      var matchesQuery = !q || text.indexOf(q) !== -1;

      if (matchesGroup && matchesQuery) {
        card.classList.remove("hidden");
        card.classList.add("flex");
        visibleCount++;
      } else {
        card.classList.remove("flex");
        card.classList.add("hidden");
      }
    }

    if (countStatus) {
      countStatus.textContent =
        "Showing " +
        visibleCount +
        " of " +
        cards.length +
        " specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.";
    }

    if (emptyState && gridEl) {
      if (visibleCount === 0) {
        emptyState.classList.remove("hidden");
        gridEl.classList.add("hidden");
      } else {
        emptyState.classList.add("hidden");
        gridEl.classList.remove("hidden");
      }
    }
  }

  for (var g = 0; g < groupBtns.length; g++) {
    groupBtns[g].addEventListener("click", function () {
      selectedGroup = this.getAttribute("data-rpwd-group-btn") || "All";
      for (var k = 0; k < groupBtns.length; k++) {
        var btn = groupBtns[k];
        var active = btn.getAttribute("data-rpwd-group-btn") === selectedGroup;
        btn.setAttribute("aria-pressed", active ? "true" : "false");
        btn.className =
          "touch-target rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors " +
          (active
            ? "border-[#202D59] bg-[#202D59] text-white"
            : "border-[#202D59]/20 bg-white text-[#202D59] hover:border-[#A459FF] hover:bg-[#DDDDF9]");
      }
      filterDisabilities();
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", filterDisabilities);
  }

  var toggleBtns = document.querySelectorAll("[data-disability-toggle]");
  for (var t = 0; t < toggleBtns.length; t++) {
    toggleBtns[t].addEventListener("click", function () {
      var panelId = this.getAttribute("data-disability-toggle");
      var panel = panelId ? document.getElementById(panelId) : null;
      if (!panel) return;
      var isOpen = this.getAttribute("aria-expanded") === "true";
      var nextOpen = !isOpen;
      this.setAttribute("aria-expanded", nextOpen ? "true" : "false");
      var chev = this.querySelector("[data-chevron]");
      if (nextOpen) {
        panel.classList.remove("hidden");
        if (chev) chev.classList.add("rotate-180");
      } else {
        panel.classList.add("hidden");
        if (chev) chev.classList.remove("rotate-180");
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
