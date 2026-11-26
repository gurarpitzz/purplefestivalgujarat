<?php
declare(strict_types=1);

/**
 * Home Section 6: Programme Highlights (Editorial Deep Royal Plum Band)
 *
 * Expects in scope:
 * - array $programme: published programme items with keys:
 *   'dayNumber', 'timeSlot', 'title', 'category', 'venueName', 'description'
 * - callable e(): HTML escaping helper (falls back to htmlspecialchars if unavailable)
 */

$pfgProgEsc = static function (mixed $value): string {
    $str = (string)($value ?? '');
    if (function_exists('e')) {
        return (string)e($str);
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

$pfgProgParseStartTime = static function (?string $timeSlot): string {
    $trimmed = trim((string)($timeSlot ?? ''));
    if ($trimmed === '') {
        return 'TBA';
    }
    if (preg_match('/\b(\d{1,2}:\d{2})\b/', $trimmed, $matches) === 1) {
        return $matches[1];
    }
    return $trimmed;
};

$pfgProgItems = isset($programme) && is_array($programme) ? array_values($programme) : [];
?>
<section aria-labelledby="programme-preview-heading" data-surface="dark" class="pfg-ed-prog">
  <div class="pfg-ed-prog__inner">
    <div class="pfg-ed-prog__header">
      <div class="pfg-ed-prog__title-group">
        <p class="pfg-ed-prog__eyebrow">Thursday, 21 January</p>
        <h2 id="programme-preview-heading" class="pfg-ed-prog__heading">Programme Highlights</h2>
      </div>
      <a href="/programme" class="pfg-ed-prog__cta">
        <span class="pfg-ed-prog__cta-text">View full 3-day programme</span>
        <span class="pfg-ed-prog__arrow" aria-hidden="true">&rarr;</span>
      </a>
    </div>

    <?php if (!empty($pfgProgItems)): ?>
      <div class="pfg-ed-prog__table-wrap">
        <table class="pfg-ed-prog__table">
          <caption class="sr-only">Thursday, 21 January 2027 Programme Highlights</caption>
          <thead class="pfg-ed-prog__thead">
            <tr class="pfg-ed-prog__head-row">
              <th scope="col" class="pfg-ed-prog__th pfg-ed-prog__col-time">Time</th>
              <th scope="col" class="pfg-ed-prog__th pfg-ed-prog__col-event">Event</th>
              <th scope="col" class="pfg-ed-prog__th pfg-ed-prog__col-venue">Venue</th>
            </tr>
          </thead>
          <tbody class="pfg-ed-prog__tbody">
            <?php foreach ($pfgProgItems as $item):
                $rawTimeSlot = trim((string)($item['timeSlot'] ?? ''));
                $startTime   = $pfgProgParseStartTime($rawTimeSlot);
                $title       = trim((string)($item['title'] ?? ''));
                $venueName   = trim((string)($item['venueName'] ?? ''));
                $hasFullSlot = $rawTimeSlot !== '' && $rawTimeSlot !== $startTime;
            ?>
              <tr class="pfg-ed-prog__tr">
                <td class="pfg-ed-prog__td pfg-ed-prog__col-time pfg-ed-prog__cell-time" data-label="Time">
                  <?php if ($hasFullSlot): ?>
                    <span class="pfg-ed-prog__time-short" title="<?= $pfgProgEsc($rawTimeSlot) ?>" aria-hidden="true"><?= $pfgProgEsc($startTime) ?></span>
                    <span class="sr-only"><?= $pfgProgEsc($rawTimeSlot) ?></span>
                  <?php else: ?>
                    <span class="pfg-ed-prog__time-short"><?= $pfgProgEsc($startTime) ?></span>
                  <?php endif; ?>
                </td>
                <td class="pfg-ed-prog__td pfg-ed-prog__col-event pfg-ed-prog__cell-event" data-label="Event">
                  <?= $pfgProgEsc($title !== '' ? $title : 'Festival Session') ?>
                </td>
                <td class="pfg-ed-prog__td pfg-ed-prog__col-venue pfg-ed-prog__cell-venue" data-label="Venue">
                  <?= $pfgProgEsc($venueName !== '' ? $venueName : 'Venue TBA') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="pfg-ed-prog__empty" role="status">
        <p class="pfg-ed-prog__empty-title">Programme schedule updates are in progress.</p>
        <p class="pfg-ed-prog__empty-copy">
          Confirmed sessions for Thursday, 21 January through Saturday, 23 January 2027 will appear here once published.
          <a href="/programme" class="pfg-ed-prog__empty-link">Visit the full programme page</a> for the latest schedule details.
        </p>
      </div>
    <?php endif; ?>
  </div>
</section>
