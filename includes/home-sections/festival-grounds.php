<?php
declare(strict_types=1);

/**
 * Section 8: Festival Grounds & Facilities
 * Editorial 3-photo asymmetric Vadodara venue mosaic with right-edge Gujarati folk-art ornamental border.
 *
 * Expected optional context variables from parent template:
 * - array $galleryItems  Optional DB-managed gallery items with 'url', 'altText', 'caption' (and optional 'id')
 * - callable e()         Optional HTML escape helper
 */

$pfgGroundsEscape = static function (string $value): string {
    if (function_exists('e')) {
        return (string)e($value);
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pfgGroundsSafeUrl = static function (?string $rawUrl): ?string {
    if ($rawUrl === null) {
        return null;
    }
    $trimmed = trim($rawUrl);
    if ($trimmed === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)) {
        return null;
    }
    if (function_exists('pfgValidateSafeUrl')) {
        $checked = pfgValidateSafeUrl($trimmed, true);
        return ($checked !== null && $checked !== '') ? $checked : null;
    }
    if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
        return $trimmed;
    }
    if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string)parse_url($trimmed, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $trimmed : null;
    }
    return null;
};

$pfgDefaultGrounds = [
    [
        'url' => '/assets/gallery/venue-15.jpg',
        'caption' => 'Indoor Sports Hall',
        'altText' => 'Sama Indoor Stadium main indoor sports hall in Vadodara prepared for Purple Festival Gujarat 2027',
    ],
    [
        'url' => '/assets/gallery/venue-22.jpg',
        'caption' => 'Outdoor Grounds',
        'altText' => 'Outdoor sports grounds and inclusive athletic courts at The Maharaja Sayajirao University of Baroda, Vadodara',
    ],
    [
        'url' => '/assets/gallery/venue-25.jpg',
        'caption' => 'Table Tennis Room',
        'altText' => 'Indoor table tennis hall in Vadodara for adaptive para-sports at Purple Festival Gujarat 2027',
    ],
];

$pfgUseCuratedDefaults = true;
$pfgResolvedGrounds = [];

if (isset($galleryItems) && is_array($galleryItems) && count($galleryItems) > 0) {
    $pfgFirstItem = reset($galleryItems);
    $pfgFirstId = is_array($pfgFirstItem) ? trim((string)($pfgFirstItem['id'] ?? '')) : '';
    $pfgFirstUrl = is_array($pfgFirstItem) ? trim((string)($pfgFirstItem['url'] ?? '')) : '';

    $pfgHasLegacyFallbackUrl = false;
    foreach ($galleryItems as $pfgCheckItem) {
        if (!is_array($pfgCheckItem)) {
            continue;
        }
        $pfgCheckId = trim((string)($pfgCheckItem['id'] ?? ''));
        $pfgCheckUrl = trim((string)($pfgCheckItem['url'] ?? ''));
        if ($pfgCheckUrl === '/assets/gallery/venue-01.jpg' || $pfgCheckId === 'g-1') {
            $pfgHasLegacyFallbackUrl = true;
            break;
        }
    }

    if ($pfgFirstId !== 'g-1' && $pfgFirstUrl !== '/assets/gallery/venue-01.jpg' && !$pfgHasLegacyFallbackUrl) {
        $pfgUseCuratedDefaults = false;
    }
}

if (!$pfgUseCuratedDefaults) {
    foreach ($galleryItems as $pfgIdx => $pfgRawItem) {
        if (count($pfgResolvedGrounds) >= 3) {
            break;
        }
        if (!is_array($pfgRawItem)) {
            continue;
        }
        $pfgSafeImgUrl = $pfgGroundsSafeUrl(isset($pfgRawItem['url']) ? (string)$pfgRawItem['url'] : null);
        if ($pfgSafeImgUrl === null) {
            continue;
        }
        $pfgFallbackSlot = $pfgDefaultGrounds[count($pfgResolvedGrounds)];
        $pfgCaption = trim((string)($pfgRawItem['caption'] ?? ''));
        $pfgAltText = trim((string)($pfgRawItem['altText'] ?? ''));

        $pfgResolvedGrounds[] = [
            'url' => $pfgSafeImgUrl,
            'caption' => $pfgCaption !== '' ? $pfgCaption : $pfgFallbackSlot['caption'],
            'altText' => $pfgAltText !== '' ? $pfgAltText : ($pfgCaption !== '' ? $pfgCaption . ' — Purple Festival Gujarat 2027 venue in Vadodara' : $pfgFallbackSlot['altText']),
        ];
    }
}

while (count($pfgResolvedGrounds) < 3) {
    $pfgResolvedGrounds[] = $pfgDefaultGrounds[count($pfgResolvedGrounds)];
}

$pfgFeaturePhoto = $pfgResolvedGrounds[0];
$pfgRightTopPhoto = $pfgResolvedGrounds[1];
$pfgRightBottomPhoto = $pfgResolvedGrounds[2];
?>
<section id="gallery" aria-labelledby="gallery-heading" class="pfg-ed-grounds">
  <div class="pfg-ed-grounds__ornament" aria-hidden="true"></div>

  <div class="pfg-ed-grounds__inner">
    <h2 id="gallery-heading" class="pfg-ed-grounds__heading">Festival Grounds &amp; Facilities</h2>

    <div class="pfg-ed-grounds__mosaic">
      <figure class="pfg-ed-grounds__feature">
        <div class="pfg-ed-grounds__media">
          <img
            src="<?= $pfgGroundsEscape($pfgFeaturePhoto['url']) ?>"
            alt="<?= $pfgGroundsEscape($pfgFeaturePhoto['altText']) ?>"
            width="960"
            height="680"
            loading="lazy"
            decoding="async"
          />
        </div>
        <figcaption class="pfg-ed-grounds__caption"><?= $pfgGroundsEscape($pfgFeaturePhoto['caption']) ?></figcaption>
      </figure>

      <div class="pfg-ed-grounds__stack">
        <figure class="pfg-ed-grounds__thumb">
          <div class="pfg-ed-grounds__media">
            <img
              src="<?= $pfgGroundsEscape($pfgRightTopPhoto['url']) ?>"
              alt="<?= $pfgGroundsEscape($pfgRightTopPhoto['altText']) ?>"
              width="640"
              height="360"
              loading="lazy"
              decoding="async"
            />
          </div>
          <figcaption class="pfg-ed-grounds__caption"><?= $pfgGroundsEscape($pfgRightTopPhoto['caption']) ?></figcaption>
        </figure>

        <figure class="pfg-ed-grounds__thumb">
          <div class="pfg-ed-grounds__media">
            <img
              src="<?= $pfgGroundsEscape($pfgRightBottomPhoto['url']) ?>"
              alt="<?= $pfgGroundsEscape($pfgRightBottomPhoto['altText']) ?>"
              width="640"
              height="360"
              loading="lazy"
              decoding="async"
            />
          </div>
          <figcaption class="pfg-ed-grounds__caption"><?= $pfgGroundsEscape($pfgRightBottomPhoto['caption']) ?></figcaption>
        </figure>
      </div>
    </div>

    <div class="pfg-ed-grounds__venues">
      <div class="pfg-ed-grounds__venue">
        <h3 class="pfg-ed-grounds__venue-name">MSU Baroda</h3>
        <p class="pfg-ed-grounds__venue-addr">The Maharaja Sayajirao University of Baroda, Vadodara</p>
      </div>

      <div class="pfg-ed-grounds__vdivider" aria-hidden="true"></div>

      <div class="pfg-ed-grounds__venue">
        <h3 class="pfg-ed-grounds__venue-name">Sama Indoor Stadium</h3>
        <p class="pfg-ed-grounds__venue-addr">Sama, Vadodara</p>
      </div>

      <a href="/programme" class="pfg-ed-grounds__cta">Venue directions <span aria-hidden="true">→</span></a>
    </div>
  </div>
</section>
