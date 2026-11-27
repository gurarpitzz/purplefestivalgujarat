<?php
declare(strict_types=1);

/**
 * Homepage Section 4 — "What's On" (Six Festival Pillars)
 * Editorial split layout: 4:3 para-sports photograph + 6 collapsible typographic pillars.
 *
 * Expected context:
 *   - $FESTIVAL_HIGHLIGHTS (array of items with 'title', 'category', 'description')
 *   - e() escaping helper (falls back cleanly to htmlspecialchars if unavailable)
 */

$whatsonEscape = static function ($value): string {
    $str = is_scalar($value) ? (string)$value : '';
    if (function_exists('e')) {
        return (string)e($str);
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

$whatsonFallback = [
    [
        'title' => 'Cultural Performances',
        'category' => 'Stage & Music',
        'description' => "Inclusive folk heritage, contemporary dance, sign-language music ensembles, and audio-described stage showcases celebrating Gujarat's cultural spirit.",
    ],
    [
        'title' => 'Disability Arts & Cinema',
        'category' => 'Creative Expression',
        'description' => 'Curated inclusive film screenings with closed captions and audio description, fine-arts pavilions, and tactile exhibitions by artists with disabilities.',
    ],
    [
        'title' => 'Inclusive Education',
        'category' => 'NEP 2020 & UDL',
        'description' => 'Dialogues with educators, researchers, and students on Universal Design for Learning, teacher preparedness, and accessible school & university ecosystems.',
    ],
    [
        'title' => 'Assistive Technology Expo',
        'category' => 'Innovation Pavilion',
        'description' => 'Hands-on demonstrations of screen readers, Braille displays, AAC communication tools, adaptive mobility solutions, and Indian assistive startups.',
    ],
    [
        'title' => 'Para Sports Championships',
        'category' => '25 Adaptive Disciplines',
        'description' => 'Unified athletic events, wheelchair basketball, boccia, blind cricket, and inclusive youth sports hosted at Sama Indoor Stadium and university grounds.',
    ],
    [
        'title' => 'Workshops & Policy Panels',
        'category' => 'Rights & Livelihoods',
        'description' => 'Action-oriented sessions on the RPwD Act 2016, barrier-free urban infrastructure, employer inclusion conclaves, and digital accessibility compliance.',
    ],
];

$whatsonItems = (isset($FESTIVAL_HIGHLIGHTS) && is_array($FESTIVAL_HIGHLIGHTS) && count($FESTIVAL_HIGHLIGHTS) > 0)
    ? array_values($FESTIVAL_HIGHLIGHTS)
    : $whatsonFallback;
?>

<section id="highlights" aria-labelledby="highlights-heading" class="pfg-ed-whatson">
  <div class="pfg-ed-whatson__inner">
    <h2 id="highlights-heading" class="pfg-ed-whatson__heading">What’s On</h2>

    <div class="pfg-ed-whatson__grid">
      <div class="pfg-ed-whatson__media">
        <figure class="pfg-ed-whatson__figure">
          <img
            src="/assets/editorial/whats-on-boccia.jpg"
            alt="Indian wheelchair para-athletes competing in an indoor boccia match at Purple Festival Gujarat 2027"
            width="1200"
            height="900"
            loading="lazy"
            decoding="async"
            class="pfg-ed-whatson__img"
          />
        </figure>
      </div>

      <ul class="pfg-ed-whatson__list">
        <?php foreach ($whatsonItems as $idx => $item):
            if (!is_array($item)) {
                continue;
            }
            $itemTitle = trim((string)($item['title'] ?? ''));
            if ($itemTitle === '') {
                continue;
            }
            $itemCategory = trim((string)($item['category'] ?? ''));
            $itemDescription = trim((string)($item['description'] ?? ''));
            $panelId = 'pfg-whatson-detail-' . (int)$idx;
        ?>
          <li class="pfg-ed-whatson__item">
            <h3 class="pfg-ed-whatson__item-title">
              <button
                type="button"
                class="pfg-ed-whatson__trigger"
                aria-expanded="false"
                aria-controls="<?= $whatsonEscape($panelId) ?>"
              >
                <span class="pfg-ed-whatson__label"><?= $whatsonEscape($itemTitle) ?></span>
                <span class="pfg-ed-whatson__indicator" aria-hidden="true">+</span>
              </button>
            </h3>
            <div id="<?= $whatsonEscape($panelId) ?>" class="pfg-ed-whatson__detail" hidden>
              <?php if ($itemCategory !== ''): ?>
                <p class="pfg-ed-whatson__category"><?= $whatsonEscape($itemCategory) ?></p>
              <?php endif; ?>
              <?php if ($itemDescription !== ''): ?>
                <p class="pfg-ed-whatson__desc"><?= $whatsonEscape($itemDescription) ?></p>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <script>
  (function () {
    try {
      var section = document.getElementById('highlights');
      if (!section || section.getAttribute('data-pfg-whatson-bound') === '1') {
        return;
      }
      section.setAttribute('data-pfg-whatson-bound', '1');

      var triggers = section.querySelectorAll('.pfg-ed-whatson__trigger');
      for (var i = 0; i < triggers.length; i++) {
        triggers[i].addEventListener('click', function () {
          var btn = this;
          var panelId = btn.getAttribute('aria-controls');
          if (!panelId) {
            return;
          }
          var panel = document.getElementById(panelId);
          if (!panel) {
            return;
          }
          var nextExpanded = btn.getAttribute('aria-expanded') !== 'true';
          btn.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
          panel.hidden = !nextExpanded;

          var indicator = btn.querySelector('.pfg-ed-whatson__indicator');
          if (indicator) {
            indicator.textContent = nextExpanded ? '\u2212' : '+';
          }
        });
      }
    } catch (err) {
      // Fail gracefully without affecting page rendering
    }
  })();
  </script>
</section>
