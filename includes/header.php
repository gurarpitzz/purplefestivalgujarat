<?php
declare(strict_types=1);

if (!function_exists('renderPurpleFestEmblemSvg')) {
    function renderPurpleFestEmblemSvg(string $className = 'h-8 w-auto'): string
    {
        $escapedClass = htmlspecialchars($className, ENT_QUOTES, 'UTF-8');
        return <<<SVG
<svg viewBox="315 10 270 175" fill="none" xmlns="http://www.w3.org/2000/svg" class="{$escapedClass}" aria-hidden="true" focusable="false">
  <path d="M 360.15 118.4 C 350.74 157.88 368.27 174.29 374.54 171.88 C 380.81 169.47 369.56 135.42 374.54 133.58 C 379.58 131.78 395.88 170.65 402.09 168.24 C 408.36 165.78 399.63 115.38 366.42 95.33 C 333.21 75.9 318.2 97.12 326.94 109.89 C 336.96 123.27 363.28 107.48 377.06 83.18 C 397.72 46.72 435.3 46.72 460.33 84.97 C 477.24 111.12 470.97 171.88 462.24 174.29 C 453.44 176.75 445.94 127.53 439.67 127.53 C 433.4 127.53 425.89 176.75 418.39 174.29 C 410.88 171.88 408.36 111.12 429.03 84.97 C 454.06 52.77 497.91 54.62 524.84 83.18 C 539.85 100.2 564.94 117.17 573.68 105.07 C 581.18 94.1 564.94 77.07 535.48 95.33 C 506.08 113.53 493.54 163.98 499.81 168.24 C 506.08 171.88 522.32 131.78 527.36 133.58 C 532.35 135.42 521.09 169.47 527.36 171.88 C 533.64 174.29 548.03 157.88 539.85 118.4" stroke="currentColor" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" />
  <path d="M 356.96 43.64 C 349.4 34.96 335.12 36.92 336.24 48.12 C 337.36 58.2 353.04 65.2 364.8 68.84 C 369.28 58.2 371.52 41.96 362.56 36.08 C 355.84 31.6 354.72 40.28 356.96 43.64 Z" fill="currentColor" />
  <path d="M 447.68 26.84 C 442.08 14.52 428.08 14.52 428.08 26.84 C 428.08 38.04 442.08 45.88 447.68 52.88 C 453.28 45.88 467.28 38.04 467.28 26.84 C 467.28 14.52 453.28 14.52 447.68 26.84 Z" fill="currentColor" />
  <path d="M 536.72 43.64 C 540.08 40.28 538.96 31.6 532.24 36.08 C 523.28 41.96 525.52 58.2 530 68.84 C 541.76 65.2 557.44 58.2 558.56 48.12 C 559.68 36.92 545.4 34.96 536.72 43.64 Z" fill="currentColor" />
</svg>
SVG;
    }
}

$siteUrl = defined('APP_URL') ? rtrim((string)APP_URL, '/') : 'https://purplefestivalgujarat.com';
$defaultTitle = 'Purple Festival Gujarat 2027 | Inclusion, celebrated.';
$resolvedTitle = isset($pageTitle) && trim((string)$pageTitle) !== ''
    ? (str_contains((string)$pageTitle, 'Purple Festival Gujarat 2027')
        ? (string)$pageTitle
        : (string)$pageTitle . ' | Purple Festival Gujarat 2027')
    : $defaultTitle;

$resolvedDescription = isset($pageDescription) && trim((string)$pageDescription) !== ''
    ? (string)$pageDescription
    : "Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat) — India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016).";

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = rtrim($requestPath, '/') ?: '/';
$isHomePage = ($normalizedPath === '/' || $normalizedPath === '/index.php');

$resolvedCanonical = isset($pageCanonical) && trim((string)$pageCanonical) !== ''
    ? (string)$pageCanonical
    : $siteUrl . ($isHomePage ? '/' : $normalizedPath);

$noIndexPrefixes = [
    '/admin',
    '/rgst',
    '/staff',
    '/auth',
    '/api',
    '/dashboard',
    '/login',
    '/register',
    '/verify-email',
    '/forgot-password',
    '/reset-password',
];
$isPrivateOrPortalRoute = false;
foreach ($noIndexPrefixes as $prefix) {
    if ($normalizedPath === $prefix || str_starts_with($normalizedPath, $prefix . '/')) {
        $isPrivateOrPortalRoute = true;
        break;
    }
}

$resolvedRobots = isset($pageRobots) && trim((string)$pageRobots) !== ''
    ? (string)$pageRobots
    : ($isPrivateOrPortalRoute
        ? 'noindex, nofollow, noarchive'
        : 'index, follow, max-video-preview:-1, max-image-preview:large, max-snippet:-1');

$defaultKeywords = 'Purple Festival Gujarat 2027, PurpleFest Gujarat 2027, PurpleFest Vadodara 2027, Gujarat Disability Inclusion Festival 2027, 21–23 January 2027, Disability Inclusion India 2027, Accessibility India, RPwD Act 2016, 21 Benchmark Disabilities, Para Sports Championships Gujarat 2027, Assistive Technology Expo 2027, Inclusive Education NEP 2020, Viksit Bharat 2047, Indian Sign Language, Sama Indoor Stadium Vadodara, MSU Baroda';
$resolvedKeywords = isset($pageKeywords) && trim((string)$pageKeywords) !== ''
    ? trim((string)$pageKeywords) . ', ' . $defaultKeywords
    : $defaultKeywords;

$ogImage = isset($pageOgImage) && trim((string)$pageOgImage) !== ''
    ? (string)$pageOgImage
    : $siteUrl . '/assets/logos/purple-festival.jpg';
$ogImageAlt = isset($pageOgImageAlt) && trim((string)$pageOgImageAlt) !== ''
    ? (string)$pageOgImageAlt
    : 'Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) — Inclusion, celebrated.';

// Safely resolve authenticated state and session-bound CSRF token for header/footer navigation
$pfgHeaderUser = null;
$pfgHeaderRoles = [];
$pfgHeaderCsrfToken = '';
$pfgNavState = 'GUEST'; // GUEST | PARTICIPANT | ADMIN | RGST

try {
    if (!function_exists('getCurrentUser') && is_file(__DIR__ . '/auth.php')) {
        require_once __DIR__ . '/auth.php';
    }
    if (function_exists('getCurrentUser')) {
        $sessionCookieName = defined('SESSION_COOKIE_NAME') ? SESSION_COOKIE_NAME : 'pfg_session';
        if (!empty($_COOKIE[$sessionCookieName]) && is_string($_COOKIE[$sessionCookieName])) {
            $pfgHeaderUser = getCurrentUser();
        }
    }
    if (is_array($pfgHeaderUser)) {
        $pfgHeaderRoles = is_array($pfgHeaderUser['roles'] ?? null) ? $pfgHeaderUser['roles'] : [];
        if (in_array('SUPERADMIN', $pfgHeaderRoles, true) || in_array('ADMIN', $pfgHeaderRoles, true)) {
            $pfgNavState = 'ADMIN';
        } elseif (in_array('RGST', $pfgHeaderRoles, true)) {
            $pfgNavState = 'RGST';
        } else {
            // CAD-17: Non-staff / historical participant sessions render public navigation only
            $pfgHeaderUser = null;
            $pfgHeaderRoles = [];
            $pfgNavState = 'GUEST';
        }
        if ($pfgHeaderUser !== null && function_exists('getCsrfToken')) {
            $pfgHeaderCsrfToken = getCsrfToken();
        }
    }
} catch (\Throwable $e) {
    $pfgHeaderUser = null;
    $pfgHeaderRoles = [];
    $pfgHeaderCsrfToken = '';
    $pfgNavState = 'GUEST';
}

$navLinks = [
    ['label' => 'Programme & Zones', 'href' => '/programme'],
    ['label' => 'About', 'href' => '/#about'],
    ['label' => 'Accessibility', 'href' => '/accessibility'],
    ['label' => 'Partners', 'href' => '/#partners'],
    ['label' => 'Gallery', 'href' => '/#gallery'],
    ['label' => 'Contact', 'href' => '/#contact'],
];

$defaultSchemaGraphItems = [
    [
        '@type' => 'WebSite',
        '@id' => $siteUrl . '/#website',
        'url' => $siteUrl . '/',
        'name' => 'Purple Festival Gujarat 2027',
        'alternateName' => ['PurpleFest Gujarat 2027', 'PurpleFest Vadodara 2027'],
        'description' => $resolvedDescription,
        'inLanguage' => 'en-IN',
        'publisher' => ['@id' => $siteUrl . '/#organization'],
    ],
    [
        '@type' => 'Organization',
        '@id' => $siteUrl . '/#organization',
        'name' => 'Purple Festival Gujarat 2027 Secretariat',
        'url' => $siteUrl . '/',
        'logo' => [
            '@type' => 'ImageObject',
            'url' => $siteUrl . '/assets/logos/purple-festival.jpg',
            'width' => 497,
            'height' => 474,
        ],
        'email' => 'purplefestivalgujarat@gmail.com',
        'areaServed' => [
            '@type' => 'AdministrativeArea',
            'name' => 'Gujarat, India',
        ],
    ],
    [
        '@type' => ['Festival', 'Event'],
        '@id' => $siteUrl . '/#festival-2027',
        'name' => 'Purple Festival Gujarat 2027',
        'alternateName' => ['PurpleFest Gujarat 2027', 'PurpleFest Vadodara 2027'],
        'description' => "Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) — India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities under the RPwD Act 2016.",
        'url' => $siteUrl . '/',
        'startDate' => '2027-01-21T09:00:00+05:30',
        'endDate' => '2027-01-23T21:00:00+05:30',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'isAccessibleForFree' => true,
        'inLanguage' => ['en-IN', 'gu', 'hi'],
        'keywords' => 'Purple Festival Gujarat 2027, PurpleFest 2027, Vadodara, Disability Inclusion, RPwD Act 2016, Para Sports 2027, Assistive Technology Expo 2027',
        'image' => [$ogImage],
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
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => 22.3326,
                'longitude' => 73.1982,
            ],
        ],
        'organizer' => [
            [
                '@type' => 'Organization',
                'name' => 'Brotherhood',
                'url' => $siteUrl . '/',
            ],
            [
                '@type' => 'Organization',
                'name' => 'Global Inclusive Education Network (GIEN)',
                'url' => $siteUrl . '/',
            ],
        ],
        'offers' => [
            '@type' => 'Offer',
            'name' => 'Free Registration — Purple Festival Gujarat 2027',
            'url' => $siteUrl . '/apply',
            'price' => '0',
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'validFrom' => '2026-11-01T00:00:00+05:30',
        ],
    ],
];

if (!$isHomePage && !$isPrivateOrPortalRoute) {
    $breadcrumbName = isset($pageTitle) && trim((string)$pageTitle) !== ''
        ? trim(explode('|', (string)$pageTitle)[0])
        : 'Purple Festival Gujarat 2027';
    $defaultSchemaGraphItems[] = [
        '@type' => 'BreadcrumbList',
        '@id' => $resolvedCanonical . '#breadcrumb',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Purple Festival Gujarat 2027',
                'item' => $siteUrl . '/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $breadcrumbName,
                'item' => $resolvedCanonical,
            ],
        ],
    ];
}

$defaultSchemaGraph = [
    '@context' => 'https://schema.org',
    '@graph' => $defaultSchemaGraphItems,
];

$schemaPayload = isset($structuredData) && is_array($structuredData)
    ? $structuredData
    : $defaultSchemaGraph;
$schemaJsonOutput = function_exists('pfgSafeJsonForScript')
    ? pfgSafeJsonForScript($schemaPayload)
    : (json_encode(
        $schemaPayload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?: '{}');
?>
<!DOCTYPE html>
<html lang="en-IN" data-lang="en" data-text-scale="100" data-contrast="default" data-reduced-motion="false" data-spacing="normal" data-highlight-links="false">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#5B2A86" />
  <title><?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="application-name" content="Purple Festival Gujarat 2027" />
  <meta name="apple-mobile-web-app-title" content="PurpleFest 2027" />
  <meta name="author" content="Brotherhood, Global Inclusive Education Network (GIEN)" />
  <meta name="creator" content="Purple Festival Gujarat 2027 Secretariat" />
  <meta name="publisher" content="Purple Festival Gujarat 2027" />
  <meta name="keywords" content="<?= htmlspecialchars($resolvedKeywords, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="robots" content="<?= htmlspecialchars($resolvedRobots, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="googlebot" content="<?= htmlspecialchars($resolvedRobots, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="geo.region" content="IN-GJ" />
  <meta name="geo.placename" content="Vadodara, Gujarat, India" />
  <meta name="geo.position" content="22.3326;73.1982" />
  <meta name="ICBM" content="22.3326, 73.1982" />
  <link rel="canonical" href="<?= htmlspecialchars($resolvedCanonical, ENT_QUOTES, 'UTF-8') ?>" />
  <?php if (!$isPrivateOrPortalRoute): ?>
  <link rel="alternate" hreflang="en-IN" href="<?= htmlspecialchars($resolvedCanonical, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($resolvedCanonical, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>

  <!-- Icons & Web App Manifest -->
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <link rel="apple-touch-icon" href="/assets/logos/purple-festival.jpg" />
  <link rel="manifest" href="/site.webmanifest" />

  <!-- OpenGraph -->
  <meta property="og:title" content="<?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($resolvedCanonical, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:locale" content="en_IN" />
  <meta property="og:site_name" content="Purple Festival Gujarat 2027" />
  <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image:type" content="image/jpeg" />
  <meta property="og:image:width" content="497" />
  <meta property="og:image:height" content="474" />
  <meta property="og:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Google Fonts: DM Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&display=swap" />

  <?php if ($isHomePage): ?>
  <!-- Homepage Above-the-Fold Hero Illustration Preloads -->
  <link rel="preload" as="image" href="/assets/illustrations/statue-of-unity.png" fetchpriority="high" />
  <link rel="preload" as="image" href="/assets/illustrations/somnath-temple.png" fetchpriority="high" />
  <?php endif; ?>

  <!-- Early Accessibility Preferences Init (prevents FOUC before first paint — FND-46) -->
  <script>
  (function(){try{var r=document.documentElement,raw=localStorage.getItem('pfg27_a11y_prefs')||localStorage.getItem('pfg27_a11y_prefs_v1'),osRM=false;try{osRM=window.matchMedia('(prefers-reduced-motion: reduce)').matches;}catch(e){}var p=raw?JSON.parse(raw):{};var scales=[90,100,115,130,150,175,200],sc=Number(p&&p.textScale);if(scales.indexOf(sc)===-1)sc=100;var ct=(p&&(p.contrast==='high'||p.contrast==='yellow-black'))?p.contrast:'default';var rm=(p&&typeof p.reducedMotion==='boolean')?p.reducedMotion:osRM;var sp=Boolean(p&&p.wideSpacing);var hl=Boolean(p&&p.highlightLinks);r.setAttribute('data-text-scale',String(sc));r.setAttribute('data-contrast',ct);r.setAttribute('data-reduced-motion',rm?'true':'false');r.setAttribute('data-spacing',sp?'wide':'normal');r.setAttribute('data-highlight-links',hl?'true':'false');r.style.fontSize=sc+'%';try{var lm=(window.location.search||'').match(/[?&]lang=(en|hi|gu)(?:&|$)/i);var lg=lm?lm[1].toLowerCase():localStorage.getItem('pfg27_lang');if(lg==='hi'||lg==='gu'){r.setAttribute('data-lang',lg);r.lang=lg==='hi'?'hi-IN':'gu-IN';}else{r.setAttribute('data-lang','en');}}catch(e){}}catch(e){}})();
  </script>

  <!-- Compiled Tailwind & Design System CSS -->
  <link rel="stylesheet" href="/assets/css/app.css?v=2" />
  <link rel="stylesheet" href="/assets/css/a11y-tools.css?v=3" />

  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json"><?= $schemaJsonOutput ?></script>
  <?php if (!empty($extraHeadHtml)): ?>
    <?= $extraHeadHtml ?>
  <?php endif; ?>
</head>
<body class="min-h-screen pfg-ivory-body text-[#202D59] antialiased selection:bg-[#A459FF]/25 selection:text-[#202D59]">
  <a href="#main-content" class="skip-link">
    Skip to main content
  </a>

  <?php if (empty($hideSiteHeader)): ?>
  <header class="sticky top-0 z-50 bg-[#F2E9FE]/95 text-[#202D59] backdrop-blur-md">
    <div class="pfg-header-main-row mx-auto flex w-full max-w-[1440px] items-center justify-between gap-6 px-6 py-4 sm:px-10">
      <!-- Flat Crisp PurpleFest Logo (#header-logo docking target for Preloader) -->
      <a
        id="header-logo"
        data-header-logo="true"
        href="/"
        aria-label="Purple Festival Gujarat 2027 Home"
        class="group flex shrink-0 items-center gap-2.5 text-[#202D59] transition-opacity"
      >
        <?= renderPurpleFestEmblemSvg('h-9 w-auto text-[#202D59]') ?>
        <span class="pfg-header-logo-text text-[23px] font-bold tracking-[-0.02em] text-[#202D59]">
          PurpleFest
        </span>
      </a>

      <!-- Primary Desktop Navigation — exact scale & spacing -->
      <nav aria-label="Primary Navigation" class="hidden items-center gap-8 lg:flex">
        <?php foreach ($navLinks as $item):
            $isActive = !str_starts_with($item['href'], '/#') && str_starts_with($requestPath, $item['href']);
        ?>
          <a
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
            <?= $isActive ? 'aria-current="page"' : '' ?>
            class="text-[16px] font-normal tracking-[-0.01em] transition-colors xl:text-[17px] <?= $isActive ? 'font-semibold text-[#A459FF]' : 'text-[#202D59] hover:text-[#A459FF]' ?>"
          >
            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <!-- Right Controls: Auth-Aware Portal Controls, Apply Now, Circular Accessibility -->
      <div class="pfg-header-right-controls flex items-center gap-3">
        <?php if ($pfgNavState === 'ADMIN'): ?>
          <a
            href="/admin"
            class="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-5 py-2 text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2]"
          >
            Admin Portal
          </a>
          <form
            method="post"
            action="/api/auth/logout"
            data-pfg-logout-form="true"
            class="hidden sm:inline-flex"
          >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pfgHeaderCsrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="redirect" value="/" />
            <button
              type="submit"
              data-pfg-logout-btn="true"
              class="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-2 text-[15px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
            >
              Logout
            </button>
          </form>
        <?php elseif ($pfgNavState === 'RGST'): ?>
          <a
            href="/rgst"
            class="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-5 py-2 text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2]"
          >
            Registration Desk
          </a>
          <form
            method="post"
            action="/api/auth/logout"
            data-pfg-logout-form="true"
            class="hidden sm:inline-flex"
          >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pfgHeaderCsrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="redirect" value="/" />
            <button
              type="submit"
              data-pfg-logout-btn="true"
              class="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-2 text-[15px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
            >
              Logout
            </button>
          </form>
        <?php else: ?>
          <a
            href="/staff/login"
            class="touch-target hidden items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-2 text-[15px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white sm:inline-flex"
          >
            Staff Login
          </a>
          <a
            href="/apply"
            class="pfg-header-apply-cta btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-6 py-2 text-[16px] font-semibold text-white transition-colors hover:bg-[#8D3BF2]"
          >
            Apply Now
          </a>
        <?php endif; ?>

        <button
          type="button"
          data-a11y-trigger="true"
          aria-expanded="false"
          aria-controls="a11y-preferences-modal"
          aria-label="Open Accessibility Preferences"
          title="Accessibility Preferences"
          class="touch-target inline-flex h-11 w-11 items-center justify-center rounded-full bg-[#1C0D30] text-white shadow-sm transition-transform hover:scale-105 hover:bg-[#202D59]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <circle cx="16" cy="4" r="1" />
            <path d="m18 19 1-7-6 1" />
            <path d="m5 8 3-3 5.5 3-2.36 3.5" />
            <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
            <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
          </svg>
        </button>

        <button
          id="mobile-nav-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="mobile-nav-drawer"
          aria-label="Open navigation menu"
          class="touch-target inline-flex items-center justify-center border border-[#202D59]/30 bg-white/60 text-[#202D59] hover:border-[#202D59] lg:hidden"
        >
          <svg id="mobile-nav-icon-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <line x1="4" x2="20" y1="12" y2="12" />
            <line x1="4" x2="20" y1="6" y2="6" />
            <line x1="4" x2="20" y1="18" y2="18" />
          </svg>
          <svg id="mobile-nav-icon-close" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden h-5 w-5" aria-hidden="true">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Responsive Mobile Navigation Drawer -->
    <div
      id="mobile-nav-drawer"
      class="hidden border-t border-[#202D59]/15 bg-[#DDDDF9] px-5 pb-6 pt-4 lg:hidden"
    >
      <nav aria-label="Mobile Navigation" class="flex flex-col space-y-1">
        <?php foreach ($navLinks as $item): ?>
          <a
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
            data-mobile-nav-link="true"
            class="touch-target flex items-center rounded px-3 py-2.5 text-base font-semibold text-[#202D59] hover:bg-white/60"
          >
            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="mt-4 flex flex-col gap-2.5 border-t border-[#202D59]/15 pt-4">
        <?php if ($pfgNavState === 'ADMIN'): ?>
          <a
            href="/admin"
            data-mobile-nav-link="true"
            class="btn-primary-pfg touch-target flex items-center justify-center bg-[#A459FF] px-4 py-2.5 text-sm font-bold text-white"
          >
            Admin Portal
          </a>
          <form method="post" action="/api/auth/logout" data-pfg-logout-form="true" class="w-full">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pfgHeaderCsrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="redirect" value="/" />
            <button
              type="submit"
              data-pfg-logout-btn="true"
              class="touch-target flex w-full items-center justify-center border-2 border-[#202D59] px-4 py-2.5 text-sm font-bold text-[#202D59]"
            >
              Logout
            </button>
          </form>
        <?php elseif ($pfgNavState === 'RGST'): ?>
          <a
            href="/rgst"
            data-mobile-nav-link="true"
            class="btn-primary-pfg touch-target flex items-center justify-center bg-[#A459FF] px-4 py-2.5 text-sm font-bold text-white"
          >
            Registration Desk
          </a>
          <form method="post" action="/api/auth/logout" data-pfg-logout-form="true" class="w-full">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($pfgHeaderCsrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="redirect" value="/" />
            <button
              type="submit"
              data-pfg-logout-btn="true"
              class="touch-target flex w-full items-center justify-center border-2 border-[#202D59] px-4 py-2.5 text-sm font-bold text-[#202D59]"
            >
              Logout
            </button>
          </form>
        <?php else: ?>
          <a
            href="/apply"
            data-mobile-nav-link="true"
            class="btn-primary-pfg touch-target flex items-center justify-center bg-[#A459FF] px-4 py-2.5 text-sm font-bold text-white"
          >
            Apply Now
          </a>
          <a
            href="/staff/login"
            data-mobile-nav-link="true"
            class="touch-target flex items-center justify-center border-2 border-[#202D59] px-4 py-2.5 text-sm font-bold text-[#202D59]"
          >
            Staff Login
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <?php endif; ?>
