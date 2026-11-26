<?php
declare(strict_types=1);

/**
 * /apply -> apply/index.php
 * Purple Festival Gujarat 2027 — Clean 3-Card Primary Category Hub (Phase 6, CAD-02, CAD-07)
 *
 * Strictly renders ONLY the 3 Primary Categories:
 *   1. ATTENDEE   -> /apply/attendee
 *   2. VOLUNTEER  -> /apply/volunteer
 *   3. SLI        -> /apply/sli
 *
 * Zero legacy cards (DELEGATE, STUDENT, PROFESSIONAL, INSTITUTIONAL, ALLY) and ZERO standalone ACCESSIBLE card.
 * Legacy query parameters (?category=DELEGATE, ?category=ACCESSIBLE, ?pathway=PWD, etc.) show an accessible
 * guidance banner explaining how to select one of the 3 primary categories and use the integrated Accessibility section.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = null;
$currentUser = null;
$emailVerified = false;
$activeRegsByCat = [];
$draftsByCat = [];
$allowMultiCategory = PFG_ALLOW_MULTI_CATEGORY;

try {
    $pdo = getDb();
    $allowMultiCategory = pfgIsMultiCategoryAllowed($pdo);
    $currentUser = getCurrentUser($pdo);

    if (is_array($currentUser) && !empty($currentUser['id'])) {
        $stmtU = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = :id LIMIT 1');
        $stmtU->execute([':id' => $currentUser['id']]);
        $emailVerified = !empty($stmtU->fetchColumn());

        $stmtR = $pdo->prepare(
            "SELECT id, registration_code, category_code, status, assignment_status, submitted_at
             FROM registrations
             WHERE user_id = :uid AND deleted_at IS NULL
               AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
             ORDER BY created_at DESC"
        );
        $stmtR->execute([':uid' => $currentUser['id']]);
        foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $cc = strtoupper((string)$r['category_code']);
            if (!isset($activeRegsByCat[$cc])) {
                $activeRegsByCat[$cc] = $r;
            }
        }

        $stmtD = $pdo->prepare(
            'SELECT category_code, updated_at FROM registration_drafts WHERE user_id = :uid'
        );
        $stmtD->execute([':uid' => $currentUser['id']]);
        foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [] as $d) {
            $draftsByCat[strtoupper((string)$d['category_code'])] = (string)$d['updated_at'];
        }
    }
} catch (Throwable $e) {
    error_log('[/apply] hub load warning: ' . $e->getMessage());
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
            'body'  => 'Persons with disabilities do not need a separate pathway. Choose Attendee, Festival Volunteer, or Sign Language Interpreter below — all three forms include our integrated, confidential Accessibility & Reasonable Accommodations section (no medical diagnosis required).',
        ];
    } else {
        $legacyBanner = [
            'title' => 'Unified Three-Category Registration for Purple Festival Gujarat 2027',
            'body'  => "The earlier \"{$rawLegacyParam}\" category has been unified into our Attendee registration. Choose Attendee below to select your role tags (Student, Professional, Institution/NGO, Parent/Caregiver, Athlete/Coach, Ally, or General Public) along with optional travel, stay, and accessibility preferences.",
        ];
    }
} elseif (in_array($rawLegacyParam, ['ATTENDEE', 'VOLUNTEER', 'SLI'], true) && $currentUser && $emailVerified) {
    $slug = strtolower($rawLegacyParam);
    header('Location: /apply/' . $slug);
    exit;
}

$primaryCards = [
    [
        'code'        => 'ATTENDEE',
        'slug'        => '/apply/attendee',
        'title'       => 'Attendee',
        'subtitle'    => 'Visitors, Families, Students, Professionals, Institutions, Para-Sports & Allies',
        'badge'       => 'Free · Instant Registration ID',
        'description' => 'Join cultural programmes, the Purple March, We Care Film Festival, policy panels, assistive-tech stalls, and para-sports across 21–23 January 2027 in Vadodara.',
        'highlights'  => [
            'Instant confirmed official Registration ID (PFG-2027-NNNNNN)',
            'Integrated Accessibility & Reasonable Accommodations module',
            'Optional outstation travel, stay guidance & para-sports interest',
        ],
        'cta'         => 'Register as Attendee',
    ],
    [
        'code'        => 'VOLUNTEER',
        'slug'        => '/apply/volunteer',
        'title'       => 'Festival Volunteer',
        'subtitle'    => 'MSU Baroda Students, Alumni, NSS/NCC & Community Volunteers (16+)',
        'badge'       => 'Free · Coordinator Review',
        'description' => 'Support stage anchoring, venue logistics, hospitality, peer accessibility assistance, or technical media teams. Volunteers with and without disabilities are equally welcomed.',
        'highlights'  => [
            'Choose from 5 official festival roles & 2-shift availability grid',
            'Reviewed by Volunteer Coordinators before shift assignment',
            'Includes pre-festival sensitisation & full accessibility support',
        ],
        'cta'         => 'Apply as Volunteer',
    ],
    [
        'code'        => 'SLI',
        'slug'        => '/apply/sli',
        'title'       => 'Sign Language Interpreter (SLI)',
        'subtitle'    => 'Certified ISL Interpreters, Deaf Interpreters, Practitioners & Trainees (18+)',
        'badge'       => 'Free · Coordinator Verification',
        'description' => 'Provide Indian Sign Language (ISL) and tactile interpretation across plenaries, panels, ceremonies, and cultural performances with sustainable paired 20–30 min rotations.',
        'highlights'  => [
            'Supports Certified ISL, Deaf/Community Signers & Trainees',
            'Gujarat priority with national/ISLRTC coordinator review supported',
            'Paired shift scheduling & built-in interpreter accessibility support',
        ],
        'cta'         => 'Apply as Interpreter (SLI)',
    ],
];

$pageTitle = 'Register & Apply | Purple Festival Gujarat 2027';
$pageDescription = 'Register as an Attendee or apply as a Festival Volunteer or Sign Language Interpreter (SLI) for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[1160px] px-5 py-12 sm:px-8 sm:py-16">
    <!-- Top Header -->
    <div class="max-w-3xl">
      <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#5B2A86]">
        Purple Festival Gujarat 2027 · 21–23 January 2027 · Vadodara
      </p>
      <h1 class="mt-2 text-[clamp(1.85rem,3.6vw,2.75rem)] font-bold leading-[1.15] tracking-[-0.02em] text-[#202D59]">
        Choose Your Participation Category
      </h1>
      <p class="mt-3 text-base leading-relaxed text-[#46537C]">
        Purple Festival Gujarat 2027 offers <strong>three clear, inclusive participation categories</strong>.
        Registration is free, uses one verified account, issues an official <strong>Registration ID</strong> for manual check-in at the Vadodara Registration Desk (no QR pass needed), and includes confidential accessibility support inside every form.
      </p>
    </div>

    <!-- Legacy URL Guidance Banner -->
    <?php if ($legacyBanner !== null): ?>
      <div
        role="status"
        aria-live="polite"
        class="mt-6 rounded-2xl border-2 border-[#5B2A86] bg-[#F2E9FE] p-5 text-[#202D59]"
      >
        <h2 class="text-base font-bold text-[#2B144D]"><?= $esc($legacyBanner['title']) ?></h2>
        <p class="mt-1 text-sm leading-relaxed text-[#202D59]"><?= $esc($legacyBanner['body']) ?></p>
      </div>
    <?php endif; ?>

    <!-- Account Session Callout -->
    <?php if (!$currentUser): ?>
      <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 shadow-xs">
        <div class="max-w-2xl">
          <h2 class="text-base font-bold text-[#202D59]">Sign In or Create a Verified Account First</h2>
          <p class="mt-1 text-sm text-[#46537C]">
            One verified account lets you save drafts, track application status, update accessibility requests anytime, and register across categories without re-typing your details.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <a
            href="/login?next=<?= rawurlencode('/apply') ?>"
            class="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
          >
            Sign In
          </a>
          <a
            href="/register?next=<?= rawurlencode('/apply') ?>"
            class="touch-target inline-flex items-center justify-center bg-[#A459FF] px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-[#8D3BF2]"
          >
            Create Account
          </a>
        </div>
      </div>
    <?php elseif (!$emailVerified): ?>
      <div
        role="alert"
        class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-amber-600/40 bg-amber-50 p-5 text-[#202D59]"
      >
        <div class="max-w-2xl">
          <h2 class="text-base font-bold text-amber-950">Email Verification Required Before Applying</h2>
          <p class="mt-1 text-sm text-amber-900">
            You are signed in as <strong><?= $esc($currentUser['email'] ?? '') ?></strong>, but your email address has not been verified yet. Please verify your email so we can send your official Registration ID and updates.
          </p>
        </div>
        <a
          href="/verify-email"
          class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#5B2A86]"
        >
          Verify Email Address
        </a>
      </div>
    <?php else: ?>
      <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#DCE5F7] bg-white px-5 py-4">
        <p class="text-sm text-[#202D59]">
          Signed in as <strong><?= $esc($currentUser['fullName'] ?? $currentUser['full_name'] ?? '') ?></strong>
          (<span class="text-[#46537C]"><?= $esc($currentUser['email'] ?? '') ?></span>)
          <?php if ($allowMultiCategory): ?>
            · You may hold up to one active registration per category.
          <?php endif; ?>
        </p>
        <a href="/dashboard" class="text-sm font-bold text-[#5B2A86] underline hover:text-[#202D59]">
          Go to My Dashboard &rarr;
        </a>
      </div>
    <?php endif; ?>

    <!-- Strictly 3 Primary Category Cards -->
    <section aria-label="Primary Registration Categories" class="mt-8 grid gap-6 lg:grid-cols-3">
      <?php foreach ($primaryCards as $card):
          $code = $card['code'];
          $activeReg = $activeRegsByCat[$code] ?? null;
          $hasDraft = isset($draftsByCat[$code]);
          $targetHref = $currentUser
              ? ($emailVerified ? $card['slug'] : '/verify-email')
              : '/login?next=' . rawurlencode($card['slug']);
      ?>
        <article
          data-primary-category="<?= $esc($code) ?>"
          class="flex flex-col justify-between rounded-2xl border-2 <?= $activeReg ? 'border-emerald-700/40 bg-white' : 'border-[#202D59]/15 bg-white hover:border-[#A459FF]' ?> p-6 shadow-xs transition-colors"
        >
          <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
                <?= $esc($card['badge']) ?>
              </span>
              <?php if ($activeReg): ?>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">
                  <?= $esc($activeReg['status']) ?>
                </span>
              <?php elseif ($hasDraft): ?>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950">
                  Draft Saved
                </span>
              <?php endif; ?>
            </div>

            <h2 class="mt-4 text-2xl font-bold text-[#202D59]"><?= $esc($card['title']) ?></h2>
            <p class="mt-1 text-sm font-semibold text-[#5B2A86]"><?= $esc($card['subtitle']) ?></p>
            <p class="mt-3 text-sm leading-relaxed text-[#46537C]"><?= $esc($card['description']) ?></p>

            <ul class="mt-4 space-y-2 text-sm text-[#202D59]">
              <?php foreach ($card['highlights'] as $hl): ?>
                <li class="flex items-start gap-2">
                  <span aria-hidden="true" class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full bg-[#A459FF]"></span>
                  <span><?= $esc($hl) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="mt-6 border-t border-[#DCE5F7] pt-5">
            <?php if ($activeReg): ?>
              <div class="mb-3 rounded-xl bg-[#F6F5FE] px-3.5 py-2.5 text-xs text-[#202D59]">
                <span class="block font-semibold text-[#46537C]">Your Official Registration ID</span>
                <span class="mt-0.5 block font-mono text-sm font-bold text-[#202D59]"><?= $esc($activeReg['registration_code']) ?></span>
              </div>
              <div class="flex flex-wrap gap-2">
                <a
                  href="/dashboard"
                  class="touch-target inline-flex flex-1 items-center justify-center rounded-lg border-2 border-[#202D59] bg-[#202D59] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#5B2A86]"
                >
                  View in Dashboard
                </a>
                <?php if (in_array((string)$activeReg['status'], ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true) || ($code === 'ATTENDEE' && (string)$activeReg['status'] === 'CONFIRMED')): ?>
                  <a
                    href="<?= $esc($card['slug'] . '?edit=' . rawurlencode((string)$activeReg['id'])) ?>"
                    class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-4 py-2.5 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]"
                  >
                    Edit
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <a
                href="<?= $esc($targetHref) ?>"
                class="touch-target inline-flex w-full items-center justify-center rounded-lg bg-[#A459FF] px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-[#8D3BF2]"
              >
                <?= $esc($hasDraft ? 'Resume ' . $card['title'] . ' Draft' : $card['cta']) ?>
              </a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <!-- Accessibility & Out-of-Scope Inquiries Note -->
    <section class="mt-10 grid gap-5 md:grid-cols-2">
      <div class="rounded-2xl border border-[#DCE5F7] bg-white p-5">
        <h2 class="text-base font-bold text-[#202D59]">Need Accessibility Accommodations?</h2>
        <p class="mt-1.5 text-sm leading-relaxed text-[#46537C]">
          Every category form includes our shared Accessibility &amp; Reasonable Accommodations section (ISL, Tactile interpretation, Braille, Large Print, Wheelchair seating/charging, Sighted guide, Quiet space, Live captions, Escort support, and more). You never have to disclose a disability diagnosis to request support.
        </p>
      </div>
      <div class="rounded-2xl border border-[#DCE5F7] bg-white p-5">
        <h2 class="text-base font-bold text-[#202D59]">Performers, Exhibitors, Sponsors &amp; Speakers</h2>
        <p class="mt-1.5 text-sm leading-relaxed text-[#46537C]">
          Cultural performance bookings, exhibition stalls (Purple Fair), institutional sponsorships, and speaker nominations are coordinated directly by the Festival Secretariat. Contact
          <a href="mailto:purplefestivalvadodara@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalvadodara@gmail.com</a>.
        </p>
      </div>
    </section>
  </div>
</main>
<?php
require_once __DIR__ . '/../includes/footer.php';
