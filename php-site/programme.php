<?php
declare(strict_types=1);

if (is_file(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}
if (is_file(__DIR__ . '/includes/database.php')) {
    require_once __DIR__ . '/includes/database.php';
}

$pageTitle = '3-Day Programme Schedule';
$pageDescription = 'Explore the 3-day schedule for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) with ISL interpretation, live captioning, para-sports, and calendar downloads.';

$FALLBACK_PROGRAMME_FULL = [
    // Day 1 — 21 Jan 2027
    [
        'id' => 'prog-1',
        'dayNumber' => 1,
        'timeSlot' => '08:30 – 09:30 IST',
        'title' => 'Participant Check-in, Accessible Welcome & Registration Desk',
        'category' => 'Registration',
        'venueName' => 'Sama Indoor Stadium & MSU Baroda',
        'description' => 'QR pass verification, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.',
        'accessibilityServices' => ['ISL Interpretation', 'Braille Programme', 'Step-Free Access', 'Sighted Guides'],
        'displayOrder' => 1,
    ],
    [
        'id' => 'prog-2',
        'dayNumber' => 1,
        'timeSlot' => '09:30 – 11:00 IST',
        'title' => 'Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity',
        'category' => 'Plenary',
        'venueName' => 'Main Auditorium',
        'description' => "Opening keynote addresses celebrating Gujarat's inaugural Purple Festival edition and India's inclusive future under Viksit Bharat@2047.",
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Audio Description', 'Wheelchair Seating'],
        'displayOrder' => 2,
    ],
    [
        'id' => 'prog-3',
        'dayNumber' => 1,
        'timeSlot' => '11:30 – 13:00 IST',
        'title' => 'Panel 1: Universal Design & Accessible Infrastructure for 2047',
        'category' => 'Panel Discussion',
        'venueName' => 'Hall A',
        'description' => 'Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.',
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Wheelchair Seating'],
        'displayOrder' => 3,
    ],
    [
        'id' => 'prog-4',
        'dayNumber' => 1,
        'timeSlot' => '14:00 – 16:30 IST',
        'title' => 'Para Sports Championships — Opening Heats (25 Adaptive Disciplines)',
        'category' => 'Inclusive Sports',
        'venueName' => 'Sama Indoor Stadium',
        'description' => 'Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.',
        'accessibilityServices' => ['Step-Free Courts', 'Wheelchair Charging Bay', 'Medical Support', 'ISL Volunteers'],
        'displayOrder' => 4,
    ],
    [
        'id' => 'prog-5',
        'dayNumber' => 1,
        'timeSlot' => '14:30 – 17:00 IST',
        'title' => 'Assistive Technology & Inclusive Innovation Expo',
        'category' => 'Exhibition',
        'venueName' => 'Innovation Pavilion',
        'description' => 'Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.',
        'accessibilityServices' => ['Tactile Demos', 'ISL Interpretation', 'Accessible Counters'],
        'displayOrder' => 5,
    ],
    [
        'id' => 'prog-6',
        'dayNumber' => 1,
        'timeSlot' => '18:30 – 20:30 IST',
        'title' => 'Evening Cultural Showcase: Disability Arts & Inclusive Theatre',
        'category' => 'Cultural Performance',
        'venueName' => 'Open-Air Stage',
        'description' => "Performances by artists with and without disabilities celebrating Gujarat's folk heritage, contemporary dance, and sign-language music.",
        'accessibilityServices' => ['ISL Interpretation', 'Audio Description', 'Seizure-Safe Lighting', 'Sensory Sanctuary Nearby'],
        'displayOrder' => 6,
    ],
    // Day 2 — 22 Jan 2027
    [
        'id' => 'prog-7',
        'dayNumber' => 2,
        'timeSlot' => '09:00 – 11:00 IST',
        'title' => 'Panel 2: Inclusive Education & NEP 2020 Implementation',
        'category' => 'Inclusive Education',
        'venueName' => 'MSU Baroda — Hall A',
        'description' => 'Educators, researchers, and students discuss Universal Design for Learning (UDL), teacher preparedness, and accessible classrooms.',
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Large-Print Handouts'],
        'displayOrder' => 7,
    ],
    [
        'id' => 'prog-8',
        'dayNumber' => 2,
        'timeSlot' => '11:30 – 13:00 IST',
        'title' => 'Panel 3: Rights, Legal Empowerment & the RPwD Act 2016',
        'category' => 'Panel Discussion',
        'venueName' => 'Hall B',
        'description' => 'Legal scholars and DPO representatives examine reasonable accommodation, employment equity, and digital accessibility compliance.',
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Braille Summary'],
        'displayOrder' => 8,
    ],
    [
        'id' => 'prog-9',
        'dayNumber' => 2,
        'timeSlot' => '14:00 – 16:00 IST',
        'title' => 'Inclusive Film Festival & Filmmaker Dialogues',
        'category' => 'Disability Arts',
        'venueName' => 'Screening Hall',
        'description' => 'Curated short films and documentaries on disability leadership with closed captions and audio description.',
        'accessibilityServices' => ['Closed Captions', 'Audio Description', 'ISL Q&A'],
        'displayOrder' => 9,
    ],
    [
        'id' => 'prog-10',
        'dayNumber' => 2,
        'timeSlot' => '16:30 – 18:30 IST',
        'title' => 'Sitaare Zameen Par — Inclusive Celebrity Exhibition Match',
        'category' => 'Inclusive Sports',
        'venueName' => 'Sama Indoor Stadium',
        'description' => 'Flagship unified exhibition match bringing together para-athletes, youth players, and guest personalities at Sama Indoor Stadium.',
        'accessibilityServices' => ['Wheelchair Seating', 'ISL Interpretation', 'Audio Commentary', 'Accessible Restrooms'],
        'displayOrder' => 10,
    ],
    // Day 3 — 23 Jan 2027
    [
        'id' => 'prog-11',
        'dayNumber' => 3,
        'timeSlot' => '09:30 – 11:30 IST',
        'title' => 'Inclusive Livelihoods, Entrepreneurship & Employer Conclave',
        'category' => 'Networking',
        'venueName' => 'Hall A',
        'description' => 'Connecting talented persons with disabilities with inclusive employers, startups, and skill-development ecosystems.',
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Quiet Interview Pods'],
        'displayOrder' => 11,
    ],
    [
        'id' => 'prog-12',
        'dayNumber' => 3,
        'timeSlot' => '12:00 – 14:00 IST',
        'title' => 'Para Sports Finals & Medal Ceremonies',
        'category' => 'Inclusive Sports',
        'venueName' => 'Sama Indoor Stadium',
        'description' => 'Championship finals across adaptive disciplines celebrating athletic excellence and inclusive sportsmanship.',
        'accessibilityServices' => ['Step-Free Podium', 'ISL Interpretation', 'Medical Support'],
        'displayOrder' => 12,
    ],
    [
        'id' => 'prog-13',
        'dayNumber' => 3,
        'timeSlot' => '15:30 – 17:30 IST',
        'title' => 'Valedictory Ceremony & Vadodara Declaration on Inclusion',
        'category' => 'Plenary',
        'venueName' => 'Main Auditorium',
        'description' => 'Closing keynote, volunteer and SLI recognition, and adoption of the Purple Festival Gujarat 2027 Inclusion Charter.',
        'accessibilityServices' => ['ISL Interpretation', 'Live Captioning (CART)', 'Braille Charter', 'Wheelchair Seating'],
        'displayOrder' => 13,
    ],
];

$items = [];
if (function_exists('getDb')) {
    try {
        $pdo = getDb();
        $stmt = $pdo->query("
            SELECT id, day_number, time_slot, title, description, category, venue_name, speakers, accessibility_services, display_order
            FROM programme_items
            WHERE status = 'PUBLISHED'
            ORDER BY day_number ASC, display_order ASC
        ");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $services = $row['accessibility_services'] ?? [];
                if (is_string($services) && $services !== '') {
                    $decoded = json_decode($services, true);
                    $services = is_array($decoded) ? $decoded : [];
                }
                $items[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'dayNumber' => (int)($row['day_number'] ?? 1),
                    'timeSlot' => (string)($row['time_slot'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'description' => $row['description'] ?? null,
                    'category' => (string)($row['category'] ?? ''),
                    'venueName' => $row['venue_name'] ?? null,
                    'accessibilityServices' => is_array($services) ? $services : [],
                    'displayOrder' => (int)($row['display_order'] ?? 0),
                ];
            }
        }
    } catch (\Throwable $e) {
        // Fallback used
    }
}

if (empty($items)) {
    $items = $FALLBACK_PROGRAMME_FULL;
}

$dayMeta = [
    1 => ['label' => 'Day 1', 'dateDisplay' => 'Thursday, 21 January 2027', 'isoDate' => '20270121', 'shortDate' => '21 Jan'],
    2 => ['label' => 'Day 2', 'dateDisplay' => 'Friday, 22 January 2027', 'isoDate' => '20270122', 'shortDate' => '22 Jan'],
    3 => ['label' => 'Day 3', 'dateDisplay' => 'Saturday, 23 January 2027', 'isoDate' => '20270123', 'shortDate' => '23 Jan'],
];

$categories = ['All'];
foreach ($items as $it) {
    if (!in_array($it['category'], $categories, true)) {
        $categories[] = $it['category'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content" tabindex="-1" class="min-h-screen bg-[#F6F1F8] focus:outline-none">
  <!-- Hero Banner -->
  <section
    aria-labelledby="programme-page-title"
    class="editorial-hero-bg border-b border-[#383242] bg-[#17151C] py-16 text-[#F9F7FC] sm:py-20"
    data-surface="dark"
  >
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-wrap items-center gap-3 text-xs font-bold tracking-[0.2em] uppercase text-[#D5B77B]">
        <span class="inline-flex items-center gap-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M3 10h18" />
          </svg>
          21–23 January 2027
        </span>
        <span aria-hidden="true">&bull;</span>
        <span class="inline-flex items-center gap-1.5 text-[#E8DFF0]">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
            <circle cx="12" cy="10" r="3" />
          </svg>
          Vadodara, Gujarat
        </span>
      </div>

      <h1
        id="programme-page-title"
        class="font-display mt-4 text-4xl font-bold text-[#F9F7FC] sm:text-5xl"
      >
        3-Day Festival Programme
      </h1>
      <p class="mt-4 max-w-2xl text-base leading-relaxed text-[#E8DFF0] sm:text-lg">
        Filter sessions by day, category, or accessibility support, and download individual
        sessions or the complete schedule directly to your calendar (.ics).
      </p>

      <div class="mt-6 flex flex-wrap gap-3">
        <a
          href="/apply"
          class="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
        >
          <span>Register to Attend</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
          </svg>
        </a>
        <a
          href="/accessibility"
          class="touch-target inline-flex items-center gap-2 rounded-full border border-[#B6A1C8]/50 bg-[#25212B] px-5 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
            <circle cx="16" cy="4" r="1" />
            <path d="m18 19 1-7-6 1" />
            <path d="m5 8 3-3 5.5 3-2.36 3.5" />
            <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
            <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
          </svg>
          <span>Venue Accessibility Services</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Interactive Schedule Explorer -->
  <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="space-y-8" id="programme-explorer">
      <!-- Day Tabs + Download Full Schedule -->
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div
          role="tablist"
          aria-label="Festival Days"
          class="flex flex-wrap gap-2"
        >
          <button
            type="button"
            role="tab"
            data-day-tab="0"
            aria-selected="true"
            class="touch-target rounded-xl border border-[#59436D] bg-[#59436D] px-4 py-2.5 text-sm font-semibold text-white transition-colors"
          >
            All 3 Days
          </button>
          <?php foreach ([1, 2, 3] as $dNum):
              $m = $dayMeta[$dNum];
          ?>
            <button
              type="button"
              role="tab"
              data-day-tab="<?= $dNum ?>"
              aria-selected="false"
              class="touch-target rounded-xl border border-[#59436D]/25 bg-white px-4 py-2.5 text-left text-sm text-[#211B29] transition-colors hover:bg-[#E8DFF0]"
            >
              <span class="font-bold"><?= htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span data-day-sub="true" class="ml-2 text-xs text-[#4B4257]">
                <?= htmlspecialchars($m['shortDate'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </button>
          <?php endforeach; ?>
        </div>

        <button
          id="programme-download-filtered-ics"
          type="button"
          class="touch-target inline-flex items-center justify-center gap-2 rounded-xl border border-[#59436D] bg-[#17151C] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" x2="12" y1="15" y2="3" />
          </svg>
          <span id="programme-download-filtered-label">Save Filtered Schedule (.ics)</span>
        </button>
      </div>

      <!-- Search & Category Filter -->
      <div class="surface-card flex flex-col gap-4 rounded-2xl border border-[#59436D]/20 bg-white p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
          <label for="programme-search" class="sr-only">
            Search sessions, venues, or accessibility services
          </label>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#59436D]" aria-hidden="true">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <input
            id="programme-search"
            type="search"
            placeholder="Search sessions, venues, ISL, Braille, para-sports..."
            class="touch-target w-full rounded-xl border border-[#59436D]/25 bg-[#F6F1F8] pl-10 pr-4 text-sm text-[#211B29] placeholder:text-[#4B4257]"
          />
        </div>

        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#59436D]" aria-hidden="true">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
          </svg>
          <label for="programme-category" class="sr-only">
            Filter by session category
          </label>
          <select
            id="programme-category"
            class="touch-target rounded-xl border border-[#59436D]/25 bg-[#F6F1F8] px-3.5 text-sm font-medium text-[#211B29]"
          >
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                <?= $cat === 'All' ? 'All Categories' : htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Results Count -->
      <p id="programme-results-count" class="text-xs font-semibold text-[#4B4257]" aria-live="polite">
        Showing <?= count($items) ?> session<?= count($items) === 1 ? '' : 's' ?>
      </p>

      <!-- Empty State -->
      <div id="programme-empty-state" class="surface-card hidden rounded-2xl border border-[#59436D]/20 bg-white p-10 text-center">
        <p class="text-base font-semibold text-[#211B29]">No matching sessions found.</p>
        <p class="mt-1 text-sm text-[#4B4257]">
          Try selecting a different day or clearing your search keywords.
        </p>
      </div>

      <!-- Session Cards -->
      <div id="programme-cards-list" class="space-y-4">
        <?php foreach ($items as $item):
            $m = $dayMeta[$item['dayNumber']] ?? $dayMeta[1];
            $searchBlob = strtolower(
                $item['title'] . ' ' .
                ($item['description'] ?? '') . ' ' .
                ($item['venueName'] ?? '') . ' ' .
                $item['category'] . ' ' .
                implode(' ', $item['accessibilityServices'])
            );
            $itemJson = htmlspecialchars(json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
          <article
            data-programme-card="true"
            data-day="<?= (int)$item['dayNumber'] ?>"
            data-category="<?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>"
            data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
            data-item-json="<?= $itemJson ?>"
            class="surface-card rounded-2xl border border-[#59436D]/20 bg-white p-6 shadow-xs transition-shadow hover:shadow-md"
          >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="space-y-2.5">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-[#17151C] px-3 py-1 text-[#D5B77B]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                      <path d="M8 2v4" />
                      <path d="M16 2v4" />
                      <rect width="18" height="18" x="3" y="4" rx="2" />
                      <path d="M3 10h18" />
                    </svg>
                    <?= htmlspecialchars($m['label'] . ' · ' . $m['dateDisplay'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E8DFF0] px-3 py-1 text-[#432F56]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <?= htmlspecialchars($item['timeSlot'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="rounded-full border border-[#59436D]/25 bg-[#F6F1F8] px-3 py-1 text-[#59436D]">
                    <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>

                <h2 class="font-display text-xl font-semibold text-[#211B29]">
                  <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <?php if (!empty($item['description'])): ?>
                  <p class="max-w-3xl text-sm leading-relaxed text-[#4B4257]">
                    <?= htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($item['venueName'])): ?>
                  <p class="flex items-center gap-1.5 text-sm font-medium text-[#211B29]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#59436D]" aria-hidden="true">
                      <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    <span><?= htmlspecialchars((string)$item['venueName'], ENT_QUOTES, 'UTF-8') ?></span>
                  </p>
                <?php endif; ?>

                <?php if (!empty($item['accessibilityServices'])): ?>
                  <div class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-[#19734B]">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                        <circle cx="16" cy="4" r="1" />
                        <path d="m18 19 1-7-6 1" />
                        <path d="m5 8 3-3 5.5 3-2.36 3.5" />
                        <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
                        <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
                      </svg>
                      Access:
                    </span>
                    <?php foreach ($item['accessibilityServices'] as $svc): ?>
                      <span class="rounded-md border border-[#19734B]/25 bg-[#19734B]/10 px-2.5 py-0.5 text-xs font-semibold text-[#135A3A]">
                        <?= htmlspecialchars((string)$svc, ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="shrink-0">
                <button
                  type="button"
                  data-download-single-ics="true"
                  aria-label="Save <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?> to calendar"
                  class="touch-target inline-flex items-center gap-2 rounded-xl border border-[#59436D]/30 bg-[#F6F1F8] px-4 py-2 text-xs font-bold text-[#432F56] transition-colors hover:border-[#59436D] hover:bg-[#59436D] hover:text-white"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" x2="12" y1="15" y2="3" />
                  </svg>
                  Add to Calendar (.ics)
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  "use strict";

  var DAY_META = {
    1: { label: "Day 1", dateDisplay: "Thursday, 21 January 2027", isoDate: "20270121" },
    2: { label: "Day 2", dateDisplay: "Friday, 22 January 2027", isoDate: "20270122" },
    3: { label: "Day 3", dateDisplay: "Saturday, 23 January 2027", isoDate: "20270123" },
  };

  var activeDay = 0;
  var dayTabs = document.querySelectorAll("[data-day-tab]");
  var searchInput = document.getElementById("programme-search");
  var categorySelect = document.getElementById("programme-category");
  var cards = document.querySelectorAll("[data-programme-card]");
  var countEl = document.getElementById("programme-results-count");
  var emptyEl = document.getElementById("programme-empty-state");
  var listEl = document.getElementById("programme-cards-list");
  var downloadFilteredBtn = document.getElementById("programme-download-filtered-ics");
  var downloadFilteredLabel = document.getElementById("programme-download-filtered-label");

  function parseTimeRangeToUtc(isoDate, timeSlot) {
    var match = String(timeSlot || "").match(/(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/);
    var year = Number(isoDate.slice(0, 4));
    var month = Number(isoDate.slice(4, 6)) - 1;
    var day = Number(isoDate.slice(6, 8));

    var sh = match ? Number(match[1]) : 9;
    var sm = match ? Number(match[2]) : 0;
    var eh = match ? Number(match[3]) : sh + 1;
    var em = match ? Number(match[4]) : sm;

    var startUtc = new Date(Date.UTC(year, month, day, sh, sm) - 330 * 60 * 1000);
    var endUtc = new Date(Date.UTC(year, month, day, eh, em) - 330 * 60 * 1000);

    function fmt(d) {
      return d.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");
    }

    return { dtStart: fmt(startUtc), dtEnd: fmt(endUtc) };
  }

  function escapeIcsText(val) {
    return String(val || "")
      .replace(/\\/g, "\\\\")
      .replace(/\n/g, "\\n")
      .replace(/,/g, "\\,")
      .replace(/;/g, "\\;");
  }

  function buildIcsFile(items) {
    var lines = [
      "BEGIN:VCALENDAR",
      "VERSION:2.0",
      "PRODID:-//Purple Festival Gujarat 2027//Programme//EN",
      "CALSCALE:GREGORIAN",
      "METHOD:PUBLISH",
      "X-WR-CALNAME:Purple Festival Gujarat 2027",
    ];

    var dtStamp = new Date().toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");

    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var meta = DAY_META[item.dayNumber] || DAY_META[1];
      var times = parseTimeRangeToUtc(meta.isoDate, item.timeSlot);
      var descParts = [];
      if (item.description) descParts.push(item.description);
      if (item.accessibilityServices && item.accessibilityServices.length > 0) {
        descParts.push("Accessibility Services: " + item.accessibilityServices.join(", "));
      }

      lines.push(
        "BEGIN:VEVENT",
        "UID:pfg2027-" + item.id + "@purplefestivalgujarat.org",
        "DTSTAMP:" + dtStamp,
        "DTSTART:" + times.dtStart,
        "DTEND:" + times.dtEnd,
        "SUMMARY:" + escapeIcsText(item.title + " — Purple Festival Gujarat 2027"),
        "DESCRIPTION:" + escapeIcsText(descParts.join(" | ")),
        "LOCATION:" + escapeIcsText(item.venueName || "Vadodara, Gujarat"),
        "CATEGORIES:" + escapeIcsText(item.category),
        "END:VEVENT"
      );
    }

    lines.push("END:VCALENDAR");
    return lines.join("\r\n");
  }

  function triggerIcsDownload(filename, content) {
    var blob = new Blob([content], { type: "text/calendar;charset=utf-8" });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function getVisibleItems() {
    var result = [];
    var all = [];
    for (var i = 0; i < cards.length; i++) {
      var raw = cards[i].getAttribute("data-item-json");
      if (!raw) continue;
      try {
        var parsed = JSON.parse(raw);
        all.push(parsed);
        if (!cards[i].classList.contains("hidden")) {
          result.push(parsed);
        }
      } catch (e) {
        // Ignore
      }
    }
    return result.length > 0 ? result : all;
  }

  function applyFilters() {
    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";
    var cat = categorySelect ? categorySelect.value : "All";
    var visibleCount = 0;

    for (var i = 0; i < cards.length; i++) {
      var card = cards[i];
      var dayNum = Number(card.getAttribute("data-day") || "1");
      var cardCat = card.getAttribute("data-category") || "";
      var searchBlob = card.getAttribute("data-search") || "";

      var matchesDay = activeDay === 0 || dayNum === activeDay;
      var matchesCat = cat === "All" || cardCat === cat;
      var matchesQuery = !q || searchBlob.indexOf(q) !== -1;

      if (matchesDay && matchesCat && matchesQuery) {
        card.classList.remove("hidden");
        visibleCount++;
      } else {
        card.classList.add("hidden");
      }
    }

    if (countEl) {
      countEl.textContent =
        "Showing " + visibleCount + " session" + (visibleCount === 1 ? "" : "s");
    }

    if (emptyEl && listEl) {
      if (visibleCount === 0) {
        emptyEl.classList.remove("hidden");
        listEl.classList.add("hidden");
      } else {
        emptyEl.classList.add("hidden");
        listEl.classList.remove("hidden");
      }
    }

    if (downloadFilteredLabel) {
      downloadFilteredLabel.textContent =
        "Save " + (activeDay === 0 ? "Filtered Schedule" : "Day " + activeDay) + " (.ics)";
    }
  }

  for (var t = 0; t < dayTabs.length; t++) {
    dayTabs[t].addEventListener("click", function () {
      activeDay = Number(this.getAttribute("data-day-tab") || "0");
      for (var k = 0; k < dayTabs.length; k++) {
        var tab = dayTabs[k];
        var dVal = Number(tab.getAttribute("data-day-tab") || "0");
        var selected = dVal === activeDay;
        tab.setAttribute("aria-selected", selected ? "true" : "false");
        tab.className =
          "touch-target rounded-xl border px-4 py-2.5 text-sm transition-colors " +
          (dVal === 0 ? "font-semibold " : "text-left ") +
          (selected
            ? "border-[#59436D] bg-[#59436D] text-white"
            : "border-[#59436D]/25 bg-white text-[#211B29] hover:bg-[#E8DFF0]");
        var sub = tab.querySelector("[data-day-sub]");
        if (sub) {
          sub.className = "ml-2 text-xs " + (selected ? "text-[#E8DFF0]" : "text-[#4B4257]");
        }
      }
      applyFilters();
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", applyFilters);
  }
  if (categorySelect) {
    categorySelect.addEventListener("change", applyFilters);
  }

  if (downloadFilteredBtn) {
    downloadFilteredBtn.addEventListener("click", function () {
      var itemsToSave = getVisibleItems();
      var fname =
        activeDay === 0
          ? "purple-festival-gujarat-2027-full-programme.ics"
          : "purple-festival-gujarat-2027-day-" + activeDay + ".ics";
      triggerIcsDownload(fname, buildIcsFile(itemsToSave));
    });
  }

  var singleBtns = document.querySelectorAll("[data-download-single-ics]");
  for (var s = 0; s < singleBtns.length; s++) {
    singleBtns[s].addEventListener("click", function () {
      var card = this.closest("[data-programme-card]");
      if (!card) return;
      var raw = card.getAttribute("data-item-json");
      if (!raw) return;
      try {
        var item = JSON.parse(raw);
        var slug = String(item.title || "session")
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "-")
          .slice(0, 40);
        triggerIcsDownload(
          "pfg2027-day" + item.dayNumber + "-" + slug + ".ics",
          buildIcsFile([item])
        );
      } catch (e) {
        // Ignore
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
