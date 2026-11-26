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

// Group active DB/fallback accommodation services into 4 clear editorial rows
// while guaranteeing every single active accommodation item is displayed.
$accommodationGroups = [
    'communication' => [
        'title' => 'Communication',
        'codes' => ['ISL_INTERPRETER', 'TACTILE_INTERPRETATION', 'CAPTIONING'],
        'items' => [],
    ],
    'formats' => [
        'title' => 'Accessible formats',
        'codes' => ['BRAILLE_SCHEDULE', 'LARGE_PRINT', 'SCREEN_READER_DIGITAL'],
        'items' => [],
    ],
    'mobility' => [
        'title' => 'Getting around & seating',
        'codes' => ['WHEELCHAIR_SEATING', 'WHEELCHAIR_CHARGING', 'SIGHTED_GUIDE', 'MOBILITY_ASSISTANCE'],
        'items' => [],
    ],
    'other' => [
        'title' => 'Sensory, dietary & custom support',
        'codes' => ['SENSORY_SANCTUARY', 'DIETARY', 'OTHER'],
        'items' => [],
    ],
];

foreach ($accommodationList as $accItem) {
    $code = strtoupper(trim((string)($accItem['code'] ?? '')));
    $placed = false;
    foreach ($accommodationGroups as $gKey => &$groupDef) {
        if (in_array($code, $groupDef['codes'], true)) {
            $groupDef['items'][] = $accItem;
            $placed = true;
            break;
        }
    }
    unset($groupDef);
    if (!$placed) {
        $accommodationGroups['other']['items'][] = $accItem;
    }
}

$rpwdGroups = [];
foreach ($disabilityList as $dItem) {
    $grp = trim((string)($dItem['rpwdGroup'] ?? ''));
    if ($grp !== '' && !in_array($grp, $rpwdGroups, true)) {
        $rpwdGroups[] = $grp;
    }
}

$totalDisabilities = count($disabilityList);
$officialEmail = defined('OFFICIAL_FESTIVAL_EMAIL') && is_string(OFFICIAL_FESTIVAL_EMAIL) && trim(OFFICIAL_FESTIVAL_EMAIL) !== ''
    ? trim(OFFICIAL_FESTIVAL_EMAIL)
    : 'purplefestivalvadodara@gmail.com';

$extraHeadHtml = (isset($extraHeadHtml) ? $extraHeadHtml . "\n" : '')
    . '  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,400..800;1,8..60,400..700&display=swap" />' . "\n"
    . '  <link rel="stylesheet" href="/assets/css/accessibility-page.css?v=1" />';

require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content" tabindex="-1" class="pfg-a11y-main min-h-screen focus:outline-none">
  <!-- 1. DEEP PLUM EDITORIAL HERO -->
  <section
    aria-labelledby="accessibility-page-title"
    class="pfg-a11y-hero"
    data-surface="dark"
  >
    <div class="pfg-a11y-container">
      <div class="pfg-a11y-hero__grid">
        <div>
          <p class="pfg-a11y-hero__eyebrow">
            Barrier-Free Festival Architecture
          </p>
          <h1 id="accessibility-page-title" class="pfg-a11y-hero__title">
            Accessibility &amp; Reasonable Accommodations
          </h1>
          <p class="pfg-a11y-hero__lead">
            Purple Festival Gujarat 2027 is engineered for dignified, independent participation
            across physical, sensory, intellectual, neurodevelopmental, psychosocial, and
            communication access needs. Request accommodations when you register, or email the
            accessibility team if you have already applied.
          </p>

          <div class="pfg-a11y-hero__actions">
            <a href="/apply" class="pfg-a11y-btn-gold">
              <span>Register &amp; Request Accommodations</span>
            </a>
            <div class="pfg-a11y-hero__secondary">
              <a
                href="mailto:<?= htmlspecialchars($officialEmail, ENT_QUOTES, 'UTF-8') ?>"
                class="pfg-a11y-hero__link"
              >
                Email the accessibility team
              </a>
              <a
                href="/legal/accessibility-statement"
                class="pfg-a11y-hero__link"
              >
                WCAG 2.2 Accessibility Statement
              </a>
            </div>
          </div>
        </div>

        <div class="pfg-a11y-hero__ornament-col" aria-hidden="true">
          <img
            src="/assets/editorial/accessibility-hero-motif.svg"
            alt=""
            width="260"
            height="280"
            decoding="async"
            class="pfg-a11y-hero__motif"
          />
        </div>
      </div>
    </div>
  </section>

  <!-- 2. ON THIS PAGE JUMP BAR -->
  <div class="pfg-a11y-toc">
    <div class="pfg-a11y-container">
      <nav aria-label="On this page" class="pfg-a11y-toc__nav">
        <span class="pfg-a11y-toc__label">On this page:</span>
        <ul class="pfg-a11y-toc__list">
          <li>
            <a href="#accommodation-services" class="pfg-a11y-toc__link">Accommodation services</a>
          </li>
          <?php if (!empty($venues)): ?>
            <li>
              <a href="#venue-access" class="pfg-a11y-toc__link">Venue access</a>
            </li>
          <?php endif; ?>
          <li>
            <a href="#rpwd-disabilities" class="pfg-a11y-toc__link">21 RPwD disabilities</a>
          </li>
          <li>
            <a href="#accessibility-help" class="pfg-a11y-toc__link">Accessibility desk</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>

  <!-- 3. REQUESTABLE ACCOMMODATION SERVICES (WARM IVORY RULED LEDGER) -->
  <section
    id="accommodation-services"
    aria-labelledby="services-heading"
    class="pfg-a11y-support"
  >
    <div class="pfg-a11y-container">
      <h2 id="services-heading" class="pfg-a11y-section-title">
        Requestable Accommodation Services
      </h2>
      <p class="pfg-a11y-section-lead">
        Select any of the following on-site and digital accommodations during registration.
        Disability disclosure is completely optional — no medical diagnosis or certificate is
        required to request support.
      </p>

      <div class="pfg-a11y-support__table" role="region" aria-label="Grouped accommodation services">
        <?php foreach ($accommodationGroups as $grp):
            if (empty($grp['items'])) {
                continue;
            }
        ?>
          <div class="pfg-a11y-support__row">
            <h3 class="pfg-a11y-support__cat">
              <?= htmlspecialchars($grp['title'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <p class="pfg-a11y-support__items-inline">
              <?php
              $lastIdx = count($grp['items']) - 1;
              foreach ($grp['items'] as $idx => $item):
                  $label = trim((string)($item['label'] ?? ''));
                  $desc = trim((string)($item['description'] ?? ''));
              ?>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?php if ($desc !== ''): ?> (<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>)<?php endif; ?></span><?php if ($idx < $lastIdx): ?><span class="pfg-a11y-support__sep" aria-hidden="true">&middot;</span><?php endif; ?>
              <?php endforeach; ?>
            </p>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="pfg-a11y-support__privacy">
        Privacy assurance: Sensitive disclosures and accommodation free-text details are
        encrypted at rest (AES-256-GCM) and restricted to authorized Accessibility Coordinators
        and Administrators with access audit logging.
      </p>
    </div>
  </section>

  <!-- 4. VENUE ACCESSIBILITY & CONFIRMATION STATUS (SOFT LAVENDER BAND) -->
  <?php if (!empty($venues)): ?>
    <section
      id="venue-access"
      aria-labelledby="venues-heading"
      class="pfg-a11y-venues"
    >
      <div class="pfg-a11y-container">
        <h2 id="venues-heading" class="pfg-a11y-section-title">
          Venue Accessibility &amp; Confirmation Status
        </h2>

        <div class="pfg-a11y-venues__list">
          <?php foreach ($venues as $v):
              $statusRaw = strtoupper(trim((string)($v['confirmationStatus'] ?? '')));
              $isConfirmed = $statusRaw === 'CONFIRMED';
              $statusLabel = $isConfirmed ? 'Confirmed Venue' : 'Proposed / Subject to Confirmation';
              $features = isset($v['accessibilityFeatures']) && is_array($v['accessibilityFeatures'])
                  ? array_values(array_filter(array_map('trim', array_map('strval', $v['accessibilityFeatures']))))
                  : [];
          ?>
            <article class="pfg-a11y-venues__row">
              <div>
                <h3 class="pfg-a11y-venues__name">
                  <?= htmlspecialchars((string)$v['name'], ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <?php if (!empty($v['address'])): ?>
                  <p class="pfg-a11y-venues__address">
                    <?= htmlspecialchars((string)$v['address'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                <?php endif; ?>
              </div>

              <div>
                <span class="pfg-a11y-venues__status <?= $isConfirmed ? 'pfg-a11y-venues__status--confirmed' : 'pfg-a11y-venues__status--proposed' ?>">
                  <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>

              <div>
                <p class="pfg-a11y-venues__features">
                  <?= htmlspecialchars(!empty($features) ? implode(', ', $features) : 'Venue accessibility audit details forthcoming', ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="pfg-a11y-venues__footer">
          <a href="/programme" class="pfg-a11y-inline-link">
            <span>View 3-day festival programme &amp; venue schedule</span>
            <span aria-hidden="true">&rarr;</span>
          </a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- 5. ALL 21 SPECIFIED DISABILITIES (RPwD ACT, 2016) -->
  <section
    id="rpwd-disabilities"
    aria-labelledby="rpwd-heading"
    class="pfg-a11y-directory"
    data-disabilities-explorer="true"
  >
    <div class="pfg-a11y-container">
      <h2 id="rpwd-heading" class="pfg-a11y-section-title">
        All 21 Specified Disabilities &amp; Tailored Supports
      </h2>
      <p class="pfg-a11y-directory__act">
        Rights of Persons with Disabilities (RPwD) Act, 2016
      </p>
      <p class="pfg-a11y-section-lead">
        Disability disclosure is always voluntary at Purple Festival Gujarat 2027. Persons with
        disabilities are welcome in every role — attendee, volunteer, Indian Sign Language
        interpreter, speaker, artist, or para-athlete.
      </p>

      <!-- Search & Category Filter Controls -->
      <div class="pfg-a11y-directory__controls">
        <label for="disability-search" class="pfg-a11y-directory__label">
          Search disabilities or accommodations
        </label>
        <div class="pfg-a11y-directory__bar">
          <div class="pfg-a11y-search">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="pfg-a11y-search__icon"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
            <input
              id="disability-search"
              type="search"
              placeholder="Search by disability, RPwD category, or support (e.g., Braille, ISL, sensory)..."
              class="pfg-a11y-search__input"
              autocomplete="off"
            />
          </div>

          <div>
            <label for="disability-category-select" class="sr-only">
              Filter by RPwD disability category
            </label>
            <select id="disability-category-select" class="pfg-a11y-select">
              <option value="All">All categories</option>
              <?php foreach ($rpwdGroups as $grp): ?>
                <option value="<?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <p id="disability-count-status" class="pfg-a11y-directory__status" aria-live="polite">
          Showing <?= $totalDisabilities ?> of <?= $totalDisabilities ?> specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.
        </p>
      </div>

      <!-- Empty Filter State -->
      <div id="disability-empty-state" class="pfg-a11y-directory__empty" hidden>
        <p style="margin:0; font-family: var(--pfg-a11y-serif);">
          No disabilities match your current search or category filter.
        </p>
        <button type="button" id="disability-reset-btn" class="pfg-a11y-directory__reset">
          Reset filters
        </button>
      </div>

      <!-- Ruled Editorial Accordion List -->
      <div id="disability-grid" class="pfg-a11y-accordion">
        <?php foreach ($disabilityList as $dIdx => $dItem):
            $isOpen = ($dIdx === 0);
            $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$dItem['code']);
            $panelId = 'disability-panel-' . $safeCode;
            $btnId = 'disability-btn-' . $safeCode;
            $displayOrder = (int)($dItem['displayOrder'] ?? ($dIdx + 1));
            $accommList = isset($dItem['accommodationExamples']) && is_array($dItem['accommodationExamples'])
                ? array_values(array_filter(array_map('trim', array_map('strval', $dItem['accommodationExamples']))))
                : [];
            $searchBlob = strtolower(
                (string)$dItem['name'] . ' ' .
                (string)$dItem['rpwdGroup'] . ' ' .
                (string)$dItem['description'] . ' ' .
                implode(' ', $accommList)
            );
        ?>
          <article
            data-disability-card="true"
            data-rpwd-group="<?= htmlspecialchars((string)$dItem['rpwdGroup'], ENT_QUOTES, 'UTF-8') ?>"
            data-search-text="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
            class="pfg-a11y-dis-row"
          >
            <h3 class="pfg-a11y-dis-heading">
              <button
                id="<?= htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') ?>"
                type="button"
                data-disability-toggle="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
                aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
                class="pfg-a11y-dis-trigger"
              >
                <span class="pfg-a11y-dis-title-wrap">
                  <span class="pfg-a11y-dis-num">
                    <?= str_pad((string)$displayOrder, 2, '0', STR_PAD_LEFT) ?>
                  </span>
                  <span class="pfg-a11y-dis-name">
                    <?= htmlspecialchars((string)$dItem['name'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="pfg-a11y-dis-group">
                    <?= htmlspecialchars((string)$dItem['rpwdGroup'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </span>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="pfg-a11y-dis-chevron"
                  aria-hidden="true"
                >
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </button>
            </h3>

            <div
              id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              role="region"
              aria-labelledby="<?= htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') ?>"
              class="pfg-a11y-dis-panel"
              <?= $isOpen ? '' : 'hidden' ?>
            >
              <p class="pfg-a11y-dis-desc">
                <?= htmlspecialchars((string)$dItem['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
              <?php if (!empty($accommList)): ?>
                <p class="pfg-a11y-dis-support">
                  <strong>Festival support:</strong>
                  <?= htmlspecialchars(implode(' · ', $accommList), ENT_QUOTES, 'UTF-8') ?>.
                </p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- 6. BOTTOM DEEP ROYAL PLUM HELP BAND -->
  <section
    id="accessibility-help"
    aria-labelledby="accessibility-help-heading"
    class="pfg-a11y-help"
    data-surface="dark"
  >
    <div class="pfg-a11y-container">
      <div class="pfg-a11y-help__grid">
        <div>
          <h2 id="accessibility-help-heading" class="pfg-a11y-help__title">
            Need custom support before you arrive?
          </h2>
          <a
            href="mailto:<?= htmlspecialchars($officialEmail, ENT_QUOTES, 'UTF-8') ?>"
            class="pfg-a11y-help__email"
          >
            <?= htmlspecialchars($officialEmail, ENT_QUOTES, 'UTF-8') ?>
          </a>
          <p class="pfg-a11y-help__note">
            Dedicated Accessibility Desk — submit your accommodation needs during registration or
            write directly to our coordinators for personalised pre-arrival assistance.
          </p>
        </div>

        <div class="pfg-a11y-help__actions">
          <a href="/apply" class="pfg-a11y-btn-gold">
            <span>Apply Now</span>
          </a>
          <a href="/legal/accessibility-statement" class="pfg-a11y-hero__link">
            WCAG 2.2 Statement
          </a>
          <a href="/#contact" class="pfg-a11y-hero__link">
            FAQs &amp; Contact
          </a>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  var section = document.getElementById('rpwd-disabilities');
  if (!section) return;

  var searchInput = document.getElementById('disability-search');
  var categorySelect = document.getElementById('disability-category-select');
  var countStatus = document.getElementById('disability-count-status');
  var emptyState = document.getElementById('disability-empty-state');
  var resetBtn = document.getElementById('disability-reset-btn');
  var rows = Array.prototype.slice.call(section.querySelectorAll('[data-disability-card="true"]'));
  var total = rows.length;

  function applyFilters() {
    var query = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
    var selectedGroup = categorySelect ? String(categorySelect.value || 'All') : 'All';
    var visibleCount = 0;

    rows.forEach(function (row) {
      var group = row.getAttribute('data-rpwd-group') || '';
      var blob = row.getAttribute('data-search-text') || '';
      var matchesGroup = selectedGroup === 'All' || group === selectedGroup;
      var matchesQuery = query === '' || blob.indexOf(query) !== -1;
      var show = matchesGroup && matchesQuery;
      row.hidden = !show;
      if (show) visibleCount += 1;
    });

    if (countStatus) {
      countStatus.textContent =
        'Showing ' + visibleCount + ' of ' + total +
        ' specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.';
    }
    if (emptyState) {
      emptyState.hidden = visibleCount > 0;
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }
  if (categorySelect) {
    categorySelect.addEventListener('change', applyFilters);
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (searchInput) searchInput.value = '';
      if (categorySelect) categorySelect.value = 'All';
      applyFilters();
      if (searchInput) searchInput.focus();
    });
  }

  section.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-disability-toggle]') : null;
    if (!btn) return;
    var panelId = btn.getAttribute('data-disability-toggle');
    if (!panelId) return;
    var panel = document.getElementById(panelId);
    if (!panel) return;
    var isExpanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
    panel.hidden = isExpanded;
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
