<?php
declare(strict_types=1);

/**
 * /apply -> apply/index.php
 * Purple Festival Gujarat 2027 — Minimal Stacked 3-Box Category Selector Hub
 *
 * Strictly renders ONLY the 3 Primary Categories in user-specified order:
 *   1. ATTENDEE  -> /apply/attendee
 *   2. SLI       -> /apply/sli
 *   3. VOLUNTEER -> /apply/volunteer
 *
 * Zero legacy cards (DELEGATE, STUDENT, PROFESSIONAL, INSTITUTIONAL, ALLY),
 * ZERO standalone ACCESSIBLE card, and ZERO "Instant QR Pass" text (Decision D-01).
 * Legacy query parameters (?category=DELEGATE, ?category=ACCESSIBLE, ?pathway=PWD, etc.)
 * show an accessible, XSS-safe guidance banner.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = null;
$registrationOpen = true;

try {
    $pdo = getDb();
    $registrationOpen = pfgIsPublicRegistrationOpen($pdo);
} catch (Throwable $e) {
    error_log('[/apply] hub load warning: ' . $e->getMessage());
    $registrationOpen = defined('REGISTRATION_PUBLIC_OPEN') ? (bool)REGISTRATION_PUBLIC_OPEN : false;
}

// Detect legacy query parameters (?category=DELEGATE, ?category=ACCESSIBLE, ?pathway=PWD, etc.)
$rawLegacyParam = '';
if (!empty($_GET['category']) && is_string($_GET['category'])) {
    $rawLegacyParam = strtoupper(trim($_GET['category']));
} elseif (!empty($_GET['pathway']) && is_string($_GET['pathway'])) {
    $rawLegacyParam = strtoupper(trim($_GET['pathway']));
}

$legacyBanner = null;
$legacyCodes = ['DELEGATE', 'STUDENT', 'PROFESSIONAL', 'INSTITUTIONAL', 'ALLY', 'ACCESSIBLE', 'PWD', 'DISABILITY'];
if ($rawLegacyParam !== '' && in_array($rawLegacyParam, $legacyCodes, true)) {
    if (in_array($rawLegacyParam, ['ACCESSIBLE', 'PWD', 'DISABILITY'], true)) {
        $legacyBanner = [
            'title' => 'Accessibility Support Is Built Into Every Registration Category',
            'body'  => 'Persons with disabilities do not need a separate pathway. Choose Attendee, Sign Language Interpreter (SLI), or Festival Volunteer below — all three forms include our integrated, confidential Accessibility & Reasonable Accommodations section (no medical diagnosis required).',
        ];
    } else {
        $legacyBanner = [
            'title' => 'Unified Three-Category Registration for Purple Festival Gujarat 2027',
            'body'  => "The earlier \"{$rawLegacyParam}\" category has been unified into our Attendee registration. Choose Attendee below to select your role tags (Student, Professional, Institution/NGO, Parent/Caregiver, Athlete/Coach, Ally, or General Public) along with optional travel, stay, and accessibility preferences.",
        ];
    }
} elseif ($registrationOpen && in_array($rawLegacyParam, ['ATTENDEE', 'VOLUNTEER', 'SLI'], true)) {
    $slug = strtolower($rawLegacyParam);
    header('Location: /apply/' . $slug);
    exit;
}

// Strictly 3 Primary Category Boxes in exact order: Attendee, SLI, Volunteer
$primaryBoxes = [
    [
        'code'     => 'ATTENDEE',
        'slug'     => '/apply/attendee',
        'title'    => 'Attendee',
        'subtitle' => 'Free · Instant Registration ID · Visitors, Students, Delegates & Allies',
    ],
    [
        'code'     => 'SLI',
        'slug'     => '/apply/sli',
        'title'    => 'Sign Language Interpreter (SLI)',
        'subtitle' => 'Free · Verification & Assignment · Certified, Deaf & Trainee Interpreters',
    ],
    [
        'code'     => 'VOLUNTEER',
        'slug'     => '/apply/volunteer',
        'title'    => 'Festival Volunteer',
        'subtitle' => 'Free · Coordinator Review · MSU Students, Alumni & Community',
    ],
];

$pageTitle = 'Register & Apply | Purple Festival Gujarat 2027';
$pageDescription = 'Choose your registration category — Attendee, Sign Language Interpreter (SLI), or Festival Volunteer — for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).';

$extraHeadHtml = <<<'HTML'
<style>
  .pfg-apply-main {
    min-height: calc(100vh - 76px);
    background-color: #ffffff;
    color: #202D59;
    padding: 52px 20px 72px;
    overflow-x: clip;
  }

  .pfg-apply-wrap {
    width: 100%;
    max-width: 580px;
    margin: 0 auto;
    box-sizing: border-box;
  }

  .pfg-apply-heading {
    margin: 0;
    font-size: clamp(1.65rem, 4.2vw, 2.25rem);
    font-weight: 800;
    line-height: 1.18;
    letter-spacing: -0.025em;
    color: #202D59;
    overflow-wrap: break-word;
  }

  .pfg-apply-banner {
    margin-top: 20px;
    padding: 14px 16px;
    border: 2px solid #5B2A86;
    background-color: #F2E9FE;
    color: #202D59;
    border-radius: 2px;
  }

  .pfg-apply-banner-title {
    margin: 0 0 4px;
    font-size: 0.9375rem;
    font-weight: 700;
    line-height: 1.35;
    color: #2B144D;
  }

  .pfg-apply-banner-body {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.5;
    color: #202D59;
  }

  .pfg-apply-account {
    margin-top: 18px;
    padding: 12px 16px;
    border: 1px solid #DCE5F7;
    background-color: #FAF9FF;
    color: #202D59;
    border-radius: 2px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 14px;
    font-size: 0.875rem;
    line-height: 1.45;
  }

  .pfg-apply-account-text {
    margin: 0;
    color: #46537C;
    overflow-wrap: anywhere;
  }

  .pfg-apply-account-text strong {
    color: #202D59;
  }

  .pfg-apply-account-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .pfg-apply-alert {
    margin-top: 18px;
    padding: 14px 16px;
    border: 2px solid #B45309;
    background-color: #FFFBEB;
    color: #451A03;
    border-radius: 2px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .pfg-apply-alert-text {
    flex: 1 1 260px;
    min-width: 0;
    font-size: 0.875rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
  }

  .pfg-apply-alert-title {
    margin: 0 0 2px;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #451A03;
  }

  .pfg-apply-alert-body {
    margin: 0;
    color: #451A03;
  }

  .pfg-apply-signedin {
    margin-top: 16px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    font-size: 0.875rem;
    line-height: 1.45;
    color: #46537C;
  }

  .pfg-apply-signedin-text {
    margin: 0;
    overflow-wrap: anywhere;
  }

  .pfg-apply-signedin-text strong {
    color: #202D59;
  }

  .pfg-apply-dash-link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 0 4px;
    font-size: 0.875rem;
    font-weight: 700;
    color: #5B2A86;
    text-decoration: underline;
    text-underline-offset: 3px;
    white-space: nowrap;
  }

  .pfg-apply-dash-link:hover,
  .pfg-apply-dash-link:focus-visible {
    color: #202D59;
  }

  .pfg-apply-btn-outline,
  .pfg-apply-btn-solid {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    min-width: 44px;
    padding: 0 14px;
    font-size: 0.875rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 2px;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
    box-sizing: border-box;
  }

  .pfg-apply-btn-outline {
    border: 2px solid #202D59;
    background-color: #ffffff;
    color: #202D59;
  }

  .pfg-apply-btn-outline:hover,
  .pfg-apply-btn-outline:focus-visible {
    background-color: #202D59;
    color: #ffffff;
  }

  .pfg-apply-btn-solid {
    border: 2px solid #202D59;
    background-color: #202D59;
    color: #ffffff;
  }

  .pfg-apply-btn-solid:hover,
  .pfg-apply-btn-solid:focus-visible {
    background-color: #5B2A86;
    border-color: #5B2A86;
    color: #ffffff;
  }

  /* Strictly 3 Stacked Lavender Selector Boxes */
  .pfg-apply-stack {
    margin-top: 28px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .pfg-apply-item {
    width: 100%;
    box-sizing: border-box;
  }

  .pfg-apply-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    width: 100%;
    min-height: 68px;
    padding: 16px 20px;
    background-color: #DDDDF9;
    border: 2px solid transparent;
    border-radius: 2px;
    color: #202D59;
    text-decoration: none;
    box-sizing: border-box;
    transition: background-color 150ms ease, border-color 150ms ease;
  }

  .pfg-apply-box:hover,
  .pfg-apply-box:focus-visible {
    background-color: #CFCFF8;
    border-color: #202D59;
  }

  .pfg-apply-box:focus-visible {
    outline: 3px solid #A459FF;
    outline-offset: 2px;
  }

  .pfg-apply-box-active {
    width: 100%;
    min-height: 68px;
    padding: 16px 20px;
    background-color: #DDDDF9;
    border: 2px solid #202D59;
    border-radius: 2px;
    color: #202D59;
    box-sizing: border-box;
  }

  .pfg-apply-box-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .pfg-apply-box-text {
    flex: 1 1 auto;
    min-width: 0;
  }

  .pfg-apply-box-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .pfg-apply-box-title {
    display: block;
    font-size: 17px;
    font-weight: 700;
    line-height: 1.3;
    color: #202D59;
    overflow-wrap: break-word;
  }

  .pfg-apply-box-sub {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
    color: #46537C;
    overflow-wrap: break-word;
  }

  .pfg-apply-box-icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    color: #202D59;
    transition: transform 150ms ease;
  }

  .pfg-apply-box:hover .pfg-apply-box-icon,
  .pfg-apply-box:focus-visible .pfg-apply-box-icon {
    transform: translateX(3px);
  }

  .pfg-apply-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    line-height: 1.4;
    border-radius: 9999px;
    white-space: nowrap;
  }

  .pfg-apply-pill-draft {
    background-color: #ffffff;
    color: #202D59;
    border: 1.5px solid #202D59;
  }

  .pfg-apply-pill-status {
    background-color: #202D59;
    color: #ffffff;
    border: 1.5px solid #202D59;
  }

  .pfg-apply-active-foot {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(32, 45, 89, 0.22);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 14px;
  }

  .pfg-apply-reg-id {
    font-size: 13px;
    color: #202D59;
    overflow-wrap: anywhere;
  }

  .pfg-apply-reg-code {
    display: inline-block;
    margin-left: 4px;
    padding: 2px 7px;
    background-color: #ffffff;
    color: #202D59;
    border: 1px solid rgba(32, 45, 89, 0.28);
    border-radius: 2px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    font-weight: 700;
  }

  .pfg-apply-active-links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .pfg-apply-back-row {
    margin-top: 22px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
  }

  .pfg-apply-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 4px;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #202D59;
    text-decoration: none;
  }

  .pfg-apply-back-link:hover,
  .pfg-apply-back-link:focus-visible {
    color: #5B2A86;
    text-decoration: underline;
    text-underline-offset: 3px;
  }

  .pfg-apply-note {
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #E5E7F5;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: #46537C;
  }

  .pfg-apply-note p {
    margin: 0;
  }

  .pfg-apply-note p + p {
    margin-top: 8px;
  }

  .pfg-apply-note strong {
    color: #202D59;
  }

  .pfg-apply-note a {
    color: #5B2A86;
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
    overflow-wrap: anywhere;
  }

  @media (max-width: 640px) {
    .pfg-apply-main {
      padding: 36px 16px 56px;
    }

    .pfg-apply-box,
    .pfg-apply-box-active {
      padding: 16px;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .pfg-apply-box,
    .pfg-apply-box-icon,
    .pfg-apply-btn-outline,
    .pfg-apply-btn-solid {
      transition: none !important;
    }

    .pfg-apply-box:hover .pfg-apply-box-icon,
    .pfg-apply-box:focus-visible .pfg-apply-box-icon {
      transform: none !important;
    }
  }

  html[data-reduced-motion="true"] .pfg-apply-box,
  html[data-reduced-motion="true"] .pfg-apply-box-icon,
  html[data-reduced-motion="true"] .pfg-apply-btn-outline,
  html[data-reduced-motion="true"] .pfg-apply-btn-solid {
    transition: none !important;
  }

  html[data-reduced-motion="true"] .pfg-apply-box:hover .pfg-apply-box-icon,
  html[data-reduced-motion="true"] .pfg-apply-box:focus-visible .pfg-apply-box-icon {
    transform: none !important;
  }

  /* High Contrast Mode (#000000 surface, #FFFFFF / #FFEA00 text, >= 17:1 contrast) */
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-main,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-banner,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-account,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-alert,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box-active,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-reg-code,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-pill-draft,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-pill-status,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-outline {
    background-color: #000000 !important;
    color: #ffffff !important;
    border-color: #ffffff !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box {
    border: 2px solid #ffffff !important;
    color: #ffea00 !important;
    text-decoration: none !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box .pfg-apply-box-title,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box .pfg-apply-box-icon {
    color: #ffea00 !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box .pfg-apply-box-sub,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box-active .pfg-apply-box-title,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box-active .pfg-apply-box-sub,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box-active .pfg-apply-box-icon,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-heading,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-note,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-note strong,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-account-text,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-account-text strong,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-signedin,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-signedin strong,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-alert-title,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-alert-body,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-banner-title,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-banner-body {
    color: #ffffff !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-active-foot,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-note {
    border-top-color: #ffffff !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box:hover,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box:focus-visible,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-solid,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-outline:hover,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-outline:focus-visible {
    background-color: #ffea00 !important;
    color: #000000 !important;
    border-color: #ffffff !important;
  }

  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box:hover *,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-box:focus-visible *,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-solid *,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-outline:hover *,
  :is(html[data-contrast="high"], html.high-contrast) .pfg-apply-btn-outline:focus-visible * {
    color: #000000 !important;
    background-color: transparent !important;
    border-color: #000000 !important;
  }

  /* Yellow-on-Black Mode (#FFEA00 on #000000, >= 17.7:1 contrast) */
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-main,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-banner,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-account,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-alert,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box-active,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-reg-code,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-pill-draft,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-pill-status,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-outline {
    background-color: #000000 !important;
    color: #ffea00 !important;
    border-color: #ffea00 !important;
  }

  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box {
    border: 2px solid #ffea00 !important;
    text-decoration: none !important;
  }

  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-heading,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box-title,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box-sub,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box-icon,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-note,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-note strong,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-note a,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-account-text,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-account-text strong,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-signedin,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-signedin strong,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-dash-link,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-back-link,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-alert-title,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-alert-body,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-banner-title,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-banner-body {
    color: #ffea00 !important;
  }

  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-active-foot,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-note {
    border-top-color: #ffea00 !important;
  }

  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box:hover,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box:focus-visible,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-solid,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-outline:hover,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-outline:focus-visible {
    background-color: #ffea00 !important;
    color: #000000 !important;
    border-color: #ffea00 !important;
  }

  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box:hover *,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-box:focus-visible *,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-solid *,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-outline:hover *,
  :is(html[data-contrast="yellow-black"], html.yellow-black) .pfg-apply-btn-outline:focus-visible * {
    color: #000000 !important;
    background-color: transparent !important;
    border-color: #000000 !important;
  }
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="pfg-apply-main focus:outline-none">
  <div class="pfg-apply-wrap">
    <h1 class="pfg-apply-heading">
      What type of registration are you interested in for Purple Fest Gujarat?
    </h1>

    <?php if ($legacyBanner !== null): ?>
      <div role="status" aria-live="polite" class="pfg-apply-banner">
        <h2 class="pfg-apply-banner-title"><?= $esc($legacyBanner['title']) ?></h2>
        <p class="pfg-apply-banner-body"><?= $esc($legacyBanner['body']) ?></p>
      </div>
    <?php endif; ?>

    <?php if (!$registrationOpen): ?>
      <div role="status" aria-live="polite" class="pfg-apply-banner">
        <h2 class="pfg-apply-banner-title">Public Registration Is Currently Closed</h2>
        <p class="pfg-apply-banner-body">
          Registration for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) is not yet open. Please check back once registration opens or contact <a href="mailto:purplefestivalvadodara@gmail.com" style="font-weight:700;text-decoration:underline;">purplefestivalvadodara@gmail.com</a>.
        </p>
      </div>
    <?php else: ?>
      <div class="pfg-apply-account">
        <p class="pfg-apply-account-text">
          <strong>No website account or login required.</strong> Choose your category below to register directly — confirmation and official updates will be sent via email.
        </p>
      </div>
    <?php endif; ?>

    <!-- Strictly 3 Stacked Primary Category Boxes -->
    <section aria-label="Primary Registration Categories" class="pfg-apply-stack">
      <?php foreach ($primaryBoxes as $box):
          $code = $box['code'];
      ?>
        <article data-primary-category="<?= $esc($code) ?>" class="pfg-apply-item">
          <?php if ($registrationOpen): ?>
            <a href="<?= $esc($box['slug']) ?>" class="pfg-apply-box">
              <span class="pfg-apply-box-text">
                <span class="pfg-apply-box-title-row">
                  <span class="pfg-apply-box-title"><?= $esc($box['title']) ?></span>
                </span>
                <span class="pfg-apply-box-sub"><?= $esc($box['subtitle']) ?></span>
              </span>
              <span class="pfg-apply-box-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14" />
                  <path d="m12 5 7 7-7 7" />
                </svg>
              </span>
            </a>
          <?php else: ?>
            <div class="pfg-apply-box-active" aria-disabled="true" style="opacity:0.78;">
              <div class="pfg-apply-box-head">
                <div class="pfg-apply-box-text">
                  <div class="pfg-apply-box-title-row">
                    <span class="pfg-apply-box-title"><?= $esc($box['title']) ?></span>
                    <span class="pfg-apply-pill pfg-apply-pill-draft">Closed</span>
                  </div>
                  <span class="pfg-apply-box-sub"><?= $esc($box['subtitle']) ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="pfg-apply-back-row">
      <a href="/" class="pfg-apply-back-link">
        <span aria-hidden="true">&larr;</span>
        <span>Back to Home</span>
      </a>
    </div>

    <aside aria-label="Accessibility and Secretariat Information" class="pfg-apply-note">
      <p>
        <strong>Accessibility included in every category:</strong> All three forms contain our confidential Accessibility &amp; Reasonable Accommodations section (ISL, tactile interpretation, Braille, large print, wheelchair seating/charging, sighted guide, quiet space, live captions, escort support, and more) — no medical diagnosis required.
      </p>
      <p>
        Registration is free and issues an official <strong>Registration ID</strong> for manual check-in at the Vadodara Registration Desk (21–23 January 2027). For performers, exhibition stalls, sponsorships, or speaker nominations, email <a href="mailto:purplefestivalvadodara@gmail.com">purplefestivalvadodara@gmail.com</a>.
      </p>
    </aside>
  </div>
</main>
<?php
require_once __DIR__ . '/../includes/footer.php';
