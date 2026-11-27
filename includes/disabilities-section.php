<?php
declare(strict_types=1);

if (!function_exists('renderDisabilityIconSvg')) {
    /**
     * Returns a custom 24x24 stroke SVG icon tailored to each of the 21 RPwD disabilities.
     */
    function renderDisabilityIconSvg(string $code, int $displayOrder = 0): string
    {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '' && $displayOrder > 0) {
            $orderMap = [
                1 => 'BLINDNESS',
                2 => 'LOW_VISION',
                3 => 'LEPROSY_CURED',
                4 => 'HEARING_IMPAIRMENT',
                5 => 'LOCOMOTOR_DISABILITY',
                6 => 'DWARFISM',
                7 => 'INTELLECTUAL_DISABILITY',
                8 => 'MENTAL_ILLNESS',
                9 => 'AUTISM_SPECTRUM_DISORDER',
                10 => 'CEREBRAL_PALSY',
                11 => 'MUSCULAR_DYSTROPHY',
                12 => 'CHRONIC_NEUROLOGICAL',
                13 => 'SPECIFIC_LEARNING_DISABILITIES',
                14 => 'MULTIPLE_SCLEROSIS',
                15 => 'SPEECH_AND_LANGUAGE',
                16 => 'THALASSEMIA',
                17 => 'HEMOPHILIA',
                18 => 'SICKLE_CELL_DISEASE',
                19 => 'MULTIPLE_DISABILITIES',
                20 => 'ACID_ATTACK_VICTIM',
                21 => 'PARKINSONS_DISEASE',
            ];
            $normalizedCode = $orderMap[$displayOrder] ?? '';
        }

        switch ($normalizedCode) {
            case 'BLINDNESS':
                $inner = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>';
                break;

            case 'LOW_VISION':
                $inner = '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>';
                break;

            case 'LEPROSY_CURED':
                $inner = '<circle cx="13" cy="4.5" r="1.75"/><path d="M13 8v5l3.5 3 1 5"/><path d="M13 13l-3 3.5-1.5 4.5"/><path d="M9.5 10.5L13 8l3.5 2.5"/><path d="M7.5 11.5L5.5 21"/>';
                break;

            case 'HEARING_IMPAIRMENT':
                $inner = '<path d="M6 8.5a6 6 0 1 1 12 0c0 2.8-1.8 4-2.8 5.2-.9 1-1.2 2-1.2 3.3a2 2 0 0 1-4 0"/><path d="M10 8.5a2 2 0 0 1 4 0c0 1.4-1.2 2-2 2.8"/><path d="M19.5 6.5a5 5 0 0 1 0 6"/><path d="M21.5 4.5a8 8 0 0 1 0 10"/>';
                break;

            case 'LOCOMOTOR_DISABILITY':
                $inner = '<circle cx="14.5" cy="4.5" r="1.5"/><path d="M13.5 8v5.5h4.5l1.5 5"/><path d="M13.5 10.5h3.5"/><circle cx="10.5" cy="16.5" r="4.5"/>';
                break;

            case 'DWARFISM':
                $inner = '<circle cx="9.5" cy="7" r="1.6"/><path d="M9.5 10.5v5"/><path d="M7 13h5"/><path d="M8 20l1.5-4.5L11 20"/><path d="M17 5v15"/><path d="M15.5 5h3"/><path d="M15.5 12.5h3"/><path d="M15.5 20h3"/>';
                break;

            case 'INTELLECTUAL_DISABILITY':
                $inner = '<path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M9 13a4.5 4.5 0 0 0 3-4"/><path d="M15 13a4.5 4.5 0 0 1-3-4"/>';
                break;

            case 'MENTAL_ILLNESS':
                $inner = '<path d="M6.5 20v-2.2a6.8 6.8 0 0 1-2.5-5.3 7 7 0 0 1 14 0v1.2l1.8 2.1a.8.8 0 0 1-.6 1.3H18v2.2a1.7 1.7 0 0 1-1.7 1.7H12v2"/><circle cx="11" cy="10.5" r="2.2"/><path d="M11 8.3v-.8"/><path d="M13.2 10.5h.8"/>';
                break;

            case 'AUTISM_SPECTRUM_DISORDER':
                $inner = '<path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z"/>';
                break;

            case 'CEREBRAL_PALSY':
                $inner = '<circle cx="12" cy="7.5" r="2"/><path d="M9 14c0-1.7 1.3-3 3-3s3 1.3 3 3"/><path d="M5 10.5v5.2a5.5 5.5 0 0 0 5.5 5.3"/><path d="M19 10.5v5.2a5.5 5.5 0 0 1-5.5 5.3"/><path d="M5 14.5l2.5 2.2"/><path d="M19 14.5l-2.5 2.2"/>';
                break;

            case 'MUSCULAR_DYSTROPHY':
                $inner = '<path d="M12.409 13.017A5 5 0 0 1 22 15c0 3.866-4 7-9 7-4.077 0-8.153-.82-10.371-2.462-.426-.316-.631-.832-.62-1.362C2.118 12.723 2.627 2 10 2a3 3 0 0 1 3 3 2 2 0 0 1-2 2c-1.105 0-1.64-.444-2-1"/><path d="M15 14a5 5 0 0 0-7.584 2"/>';
                break;

            case 'CHRONIC_NEUROLOGICAL':
                $inner = '<circle cx="12" cy="12" r="2.2"/><circle cx="12" cy="4.5" r="1.4"/><circle cx="18.5" cy="8.2" r="1.4"/><circle cx="18.5" cy="15.8" r="1.4"/><circle cx="12" cy="19.5" r="1.4"/><circle cx="5.5" cy="15.8" r="1.4"/><circle cx="5.5" cy="8.2" r="1.4"/><path d="M12 9.8V6"/><path d="M13.9 10.9l3.3-1.9"/><path d="M13.9 13.1l3.3 1.9"/><path d="M12 14.2V18"/><path d="M10.1 13.1l-3.3 1.9"/><path d="M10.1 10.9L6.8 9"/>';
                break;

            case 'SPECIFIC_LEARNING_DISABILITIES':
                $inner = '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/><path d="M6 8h3"/><path d="M6 12h3"/><path d="M15 8h3"/><path d="M15 12h3"/>';
                break;

            case 'MULTIPLE_SCLEROSIS':
                $inner = '<path d="M9.2 14.2 6.5 20l3.2 1.2L12 16l2.3 5.2L17.5 20l-2.7-5.8"/><path d="M12 16c-2.6-3.2-4-5.9-4-8.2a4 4 0 0 1 8 0c0 2.3-1.4 5-4 8.2Z"/>';
                break;

            case 'SPEECH_AND_LANGUAGE':
                $inner = '<path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/>';
                break;

            case 'THALASSEMIA':
                $inner = '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>';
                break;

            case 'HEMOPHILIA':
                $inner = '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/><path d="M12 11v5"/><path d="M9.5 13.5h5"/>';
                break;

            case 'SICKLE_CELL_DISEASE':
                $inner = '<circle cx="8" cy="9" r="3"/><circle cx="15.5" cy="7.5" r="2"/><path d="M17.5 13.5c1.8 2.4.7 6-2.5 6.8-2.2.5-4.3-.6-5.2-2.4 2 .2 3.8-.7 4.8-2.4.6-1 .8-1.5 2.9-2z"/>';
                break;

            case 'MULTIPLE_DISABILITIES':
                $inner = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3.2"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a3.2 3.2 0 0 1 0 6.2"/>';
                break;

            case 'ACID_ATTACK_VICTIM':
                $inner = '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>';
                break;

            case 'PARKINSONS_DISEASE':
                $inner = '<path d="M12 6a2.6 2.6 0 0 0-5.1.1 3.3 3.3 0 0 0-1.9 4.8 3.2 3.2 0 0 0 .5 5.4A3.2 3.2 0 0 0 12 17Z"/><path d="M12 6a2.6 2.6 0 0 1 5.1.1 3.3 3.3 0 0 1 1.9 4.8 3.2 3.2 0 0 1-.5 5.4A3.2 3.2 0 0 1 12 17Z"/><path d="M2.5 9.5a4 4 0 0 0 0 5"/><path d="M21.5 9.5a4 4 0 0 1 0 5"/>';
                break;

            default:
                $inner = '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l2.5 2.5"/>';
                break;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}

$cfg = isset($disabilitiesSectionConfig) && is_array($disabilitiesSectionConfig) ? $disabilitiesSectionConfig : [];
$sectionId = (string)($cfg['sectionId'] ?? '');
$headingId = (string)($cfg['headingId'] ?? 'disabilities-heading');
$eyebrow = (string)($cfg['eyebrow'] ?? 'Rights of Persons with Disabilities Act, 2016');
$title = (string)($cfg['title'] ?? 'Understanding the 21 Specified Disabilities');
$subtitle = (string)($cfg['subtitle'] ?? "Every individual's access needs are unique. These categories are an educational guide — never a restrictive gate for festival participation.");
$showGuideCta = array_key_exists('showGuideCta', $cfg) ? (bool)$cfg['showGuideCta'] : true;
$guideCtaHref = (string)($cfg['guideCtaHref'] ?? '/accessibility#rpwd-disabilities');
$showExplorer = array_key_exists('showExplorer', $cfg) ? (bool)$cfg['showExplorer'] : true;
$initialExpandedCount = (int)($cfg['initialExpandedCount'] ?? 0);
$totalDisabilities = isset($disabilityList) && is_array($disabilityList) ? count($disabilityList) : 0;
?>

<section
  <?= $sectionId !== '' ? 'id="' . htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
  aria-labelledby="<?= htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8') ?>"
  class="pfg-dis-section<?= $showExplorer ? '' : ' pfg-dis-section--teaser' ?>"
  <?= $showExplorer ? 'data-disabilities-explorer="true"' : '' ?>
>
  <!-- Ambient Translucent Lavender Sweeping Waves -->
  <svg
    class="pfg-dis-bg-waves"
    viewBox="0 0 1600 940"
    preserveAspectRatio="none"
    aria-hidden="true"
  >
    <defs>
      <linearGradient id="pfgWaveSoft" x1="0" y1="0" x2="1" y2="0.4">
        <stop offset="0%" stop-color="#CBB0FB" stop-opacity="0.46" />
        <stop offset="50%" stop-color="#DFCAFF" stop-opacity="0.30" />
        <stop offset="100%" stop-color="#C09AF8" stop-opacity="0.46" />
      </linearGradient>
    </defs>
    <path d="M0,190 C360,120 740,260 1160,175 C1370,132 1495,148 1600,170 L1600,360 C1280,310 860,390 420,295 C190,245 70,255 0,285 Z" fill="url(#pfgWaveSoft)" />
    <path d="M0,510 C320,445 690,565 1120,475 C1350,425 1490,445 1600,480 L1600,720 C1230,660 820,765 390,655 C165,595 65,610 0,635 Z" fill="#C09AF8" fill-opacity="0.36" />
  </svg>

  <!-- Perimeter Illustrations -->
  <img
    src="/assets/illustrations/disabilities-branch-tl.png"
    alt=""
    aria-hidden="true"
    loading="lazy"
    decoding="async"
    class="pfg-dis-art-tl"
  />
  <img
    src="/assets/illustrations/disabilities-heritage-tr.png"
    alt=""
    aria-hidden="true"
    loading="lazy"
    decoding="async"
    class="pfg-dis-art-tr"
  />
  <img
    src="/assets/illustrations/disabilities-wheelchair-bl.png"
    alt=""
    aria-hidden="true"
    loading="lazy"
    decoding="async"
    class="pfg-dis-art-bl"
  />
  <img
    src="/assets/illustrations/disabilities-cane-br.png"
    alt=""
    aria-hidden="true"
    loading="lazy"
    decoding="async"
    class="pfg-dis-art-br"
  />

  <!-- Multi-Layered Flowing Lavender Bottom Wave -->
  <svg
    class="pfg-dis-bottom-wave"
    viewBox="0 0 1600 120"
    preserveAspectRatio="none"
    aria-hidden="true"
  >
    <defs>
      <linearGradient id="pfgBottomWaveGrad" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#A569F2" />
        <stop offset="50%" stop-color="#BE8EF9" />
        <stop offset="100%" stop-color="#8E4EE8" />
      </linearGradient>
    </defs>
    <path d="M0,46 C260,86 580,22 930,68 C1210,104 1430,36 1600,52 L1600,120 L0,120 Z" fill="#BD8EF8" fill-opacity="0.78" />
    <path d="M0,70 C340,26 670,96 1040,54 C1290,26 1465,62 1600,38 L1600,120 L0,120 Z" fill="url(#pfgBottomWaveGrad)" />
  </svg>

  <!-- Main Content Container -->
  <div class="pfg-dis-inner">
    <div class="pfg-dis-header">
      <div class="pfg-dis-header-copy">
        <p class="pfg-dis-eyebrow">
          <?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="pfg-dis-accent-bar" aria-hidden="true"></div>
        <h2
          id="<?= htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8') ?>"
          class="pfg-dis-title"
        >
          <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="pfg-dis-subtitle">
          <?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <?php if ($showGuideCta): ?>
        <a href="<?= htmlspecialchars($guideCtaHref, ENT_QUOTES, 'UTF-8') ?>" class="pfg-dis-guide-cta">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9.5" />
            <circle cx="12" cy="7.5" r="1.55" fill="currentColor" stroke="none" />
            <path d="M7.6 10.6c1.45.55 2.95.82 4.4.82s2.95-.27 4.4-.82" />
            <path d="M12 11.45v3.35" />
            <path d="m9.95 18.4 2.05-3.6 2.05 3.6" />
          </svg>
          <span>Full Accessibility Guide</span>
        </a>
      <?php endif; ?>
    </div>

    <?php if ($showExplorer): ?>
    <!-- Search Input & Category Filter Pills -->
    <div class="pfg-dis-controls">
      <div class="pfg-dis-search-wrap">
        <label for="disability-search" class="sr-only">
          Search the 21 specified disabilities or accommodations
        </label>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pfg-dis-search-icon" aria-hidden="true">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.3-4.3" />
        </svg>
        <input
          id="disability-search"
          type="search"
          placeholder="Search disabilities or accommodations..."
          class="pfg-dis-search-input"
        />
      </div>

      <div
        role="group"
        aria-label="Filter disabilities by RPwD category"
        class="pfg-dis-pills"
      >
        <?php foreach ($rpwdGroups as $gIdx => $grp): ?>
          <button
            type="button"
            data-rpwd-group-btn="<?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>"
            aria-pressed="<?= $gIdx === 0 ? 'true' : 'false' ?>"
            class="pfg-dis-pill<?= $gIdx === 0 ? ' is-active' : '' ?>"
          >
            <?= htmlspecialchars($grp, ENT_QUOTES, 'UTF-8') ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Result Count -->
    <p id="disability-count-status" class="pfg-dis-count" aria-live="polite">
      Showing <?= $totalDisabilities ?> of <?= $totalDisabilities ?> specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.
    </p>

    <!-- Empty Filter State -->
    <div id="disability-empty-state" class="pfg-dis-empty hidden">
      <p>
        No disabilities match your search filter. Try clearing your search filter.
      </p>
    </div>

    <!-- 21 Benchmark Disability Cards Grid -->
    <div id="disability-grid" class="pfg-dis-grid">
      <?php foreach ($disabilityList as $dIdx => $dItem):
          $isOpen = $initialExpandedCount > 0 && $dIdx < $initialExpandedCount;
          $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$dItem['code']);
          $panelId = 'disability-panel-' . $safeCode;
          $btnId = 'disability-btn-' . $safeCode;
          $displayOrder = (int)($dItem['displayOrder'] ?? ($dIdx + 1));
          $accommList = isset($dItem['accommodationExamples']) && is_array($dItem['accommodationExamples'])
              ? $dItem['accommodationExamples']
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
          class="pfg-dis-card"
        >
          <h3 class="pfg-dis-card-heading">
            <button
              id="<?= htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') ?>"
              type="button"
              data-disability-toggle="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
              aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
              class="pfg-dis-card-btn"
            >
              <span class="pfg-dis-icon-box" aria-hidden="true">
                <?= renderDisabilityIconSvg((string)$dItem['code'], $displayOrder) ?>
              </span>

              <span class="pfg-dis-card-body">
                <span class="pfg-dis-card-meta">
                  <span class="pfg-dis-num">
                    <?= str_pad((string)$displayOrder, 2, '0', STR_PAD_LEFT) ?>
                  </span>
                  <span class="pfg-dis-group">
                    <?= htmlspecialchars((string)$dItem['rpwdGroup'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </span>
                <span class="pfg-dis-card-title">
                  <?= htmlspecialchars((string)$dItem['name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </span>

              <svg data-chevron="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pfg-dis-chevron" aria-hidden="true">
                <path d="m6 9 6 6 6-6" />
              </svg>
            </button>
          </h3>

          <div
            id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
            role="region"
            aria-labelledby="<?= htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') ?>"
            class="pfg-dis-panel<?= $isOpen ? '' : ' hidden' ?>"
          >
            <p class="pfg-dis-desc">
              <?= htmlspecialchars((string)$dItem['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php if (!empty($accommList)): ?>
              <div class="pfg-dis-accomm">
                <p class="pfg-dis-accomm-heading">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                    <path d="m9 12 2 2 4-4" />
                  </svg>
                  Festival Accommodations
                </p>
                <ul class="pfg-dis-accomm-list">
                  <?php foreach ($accommList as $ex): ?>
                    <li class="pfg-dis-accomm-item">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pfg-dis-accomm-check" aria-hidden="true">
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
    <?php endif; ?>
  </div>
</section>

<?php if ($showExplorer): ?>
<script>
(function () {
  "use strict";

  var explorers = document.querySelectorAll('[data-disabilities-explorer="true"]');
  for (var eIdx = 0; eIdx < explorers.length; eIdx++) {
    var root = explorers[eIdx];
    if (root.dataset.pfgDisBound === "true") continue;
    root.dataset.pfgDisBound = "true";

    (function (container) {
      var selectedGroup = "All";
      var searchInput = container.querySelector("#disability-search");
      var groupBtns = container.querySelectorAll("[data-rpwd-group-btn]");
      var cards = container.querySelectorAll("[data-disability-card]");
      var countStatus = container.querySelector("#disability-count-status");
      var emptyState = container.querySelector("#disability-empty-state");
      var gridEl = container.querySelector("#disability-grid");

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
            visibleCount++;
          } else {
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
            if (active) {
              btn.classList.add("is-active");
            } else {
              btn.classList.remove("is-active");
            }
          }
          filterDisabilities();
        });
      }

      if (searchInput) {
        searchInput.addEventListener("input", filterDisabilities);
      }

      var toggleBtns = container.querySelectorAll("[data-disability-toggle]");
      function getVisibleToggleBtns() {
        var visible = [];
        for (var v = 0; v < toggleBtns.length; v++) {
          var cardEl = toggleBtns[v].closest("[data-disability-card]");
          if (!cardEl || !cardEl.classList.contains("hidden")) {
            visible.push(toggleBtns[v]);
          }
        }
        return visible;
      }

      for (var t = 0; t < toggleBtns.length; t++) {
        toggleBtns[t].addEventListener("click", function () {
          var panelId = this.getAttribute("data-disability-toggle");
          var panel = panelId ? document.getElementById(panelId) : null;
          if (!panel) return;
          var nextOpen = this.getAttribute("aria-expanded") !== "true";
          this.setAttribute("aria-expanded", nextOpen ? "true" : "false");
          if (nextOpen) {
            panel.classList.remove("hidden");
          } else {
            panel.classList.add("hidden");
          }
        });

        toggleBtns[t].addEventListener("keydown", function (e) {
          if (
            e.key !== "ArrowDown" &&
            e.key !== "ArrowUp" &&
            e.key !== "Home" &&
            e.key !== "End"
          ) {
            return;
          }
          var visibleBtns = getVisibleToggleBtns();
          var currentIdx = visibleBtns.indexOf(this);
          if (currentIdx === -1 || visibleBtns.length === 0) return;

          var targetIdx = currentIdx;
          if (e.key === "ArrowDown") {
            targetIdx = (currentIdx + 1) % visibleBtns.length;
          } else if (e.key === "ArrowUp") {
            targetIdx = (currentIdx - 1 + visibleBtns.length) % visibleBtns.length;
          } else if (e.key === "Home") {
            targetIdx = 0;
          } else if (e.key === "End") {
            targetIdx = visibleBtns.length - 1;
          }

          e.preventDefault();
          visibleBtns[targetIdx].focus();
        });
      }
    })(root);
  }
})();
</script>
<?php endif; ?>

