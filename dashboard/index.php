<?php
declare(strict_types=1);

/**
 * Participant Dashboard (/dashboard -> php-site/dashboard/index.php)
 * Exact PHP + Vanilla JS port of src/app/dashboard/page.tsx & src/components/ParticipantDashboard.tsx.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/qr-service.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /login?next=' . rawurlencode('/dashboard'));
    exit;
}
enforceStaffMfaGate($user);

$pdo = getDb();
$userId = (string)$user['id'];
$userFullName = (string)($user['fullName'] ?? $user['full_name'] ?? 'Participant');
$userEmail = (string)($user['email'] ?? '');
$userPhone = (string)($user['phone'] ?? '');
$userCountry = (string)($user['country'] ?? 'India');
$userMfaEnabled = (bool)($user['mfaEnabled'] ?? $user['mfa_enabled'] ?? false);
$userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : ['PARTICIPANT'];

$isAdmin = in_array('SUPERADMIN', $userRoles, true) || in_array('ADMIN', $userRoles, true);
$isRgst = in_array('RGST', $userRoles, true) || $isAdmin;

// 1. Fetch user's registrations
$regStmt = $pdo->prepare(
    'SELECT * FROM registrations
     WHERE user_id = :user_id AND deleted_at IS NULL
     ORDER BY created_at DESC'
);
$regStmt->execute([':user_id' => $userId]);
$userRegs = $regStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 2. Fetch registration categories
$catStmt = $pdo->query('SELECT code, name FROM registration_categories');
$catRows = $catStmt ? ($catStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
$categoryNameMap = [];
foreach ($catRows as $c) {
    $categoryNameMap[(string)$c['code']] = (string)$c['name'];
}

// 3. Fetch active accommodation types
$accTypeStmt = $pdo->query(
    'SELECT code, label FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC'
);
$accommodationTypes = $accTypeStmt ? ($accTypeStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
if (count($accommodationTypes) === 0 && defined('ACCOMMODATION_TYPES_SEED')) {
    $accommodationTypes = ACCOMMODATION_TYPES_SEED;
}
$accLabelMap = [];
foreach ($accommodationTypes as $at) {
    $accLabelMap[(string)$at['code']] = (string)$at['label'];
}

// 4. Fetch related records for user's registrations
$regIds = array_map(static fn(array $r): string => (string)$r['id'], $userRegs);
$credsByReg = [];
$volByReg = [];
$sliByReg = [];
$checkinsByReg = [];
$accommodations = [];

if (count($regIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($regIds), '?'));

    // QR Credentials
    $credStmt = $pdo->prepare(
        "SELECT * FROM qr_credentials WHERE registration_id IN ({$placeholders}) ORDER BY issued_at DESC"
    );
    $credStmt->execute($regIds);
    foreach ($credStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $cred) {
        $rId = (string)$cred['registration_id'];
        if (!isset($credsByReg[$rId]) && ($cred['status'] ?? '') === 'ACTIVE') {
            $credsByReg[$rId] = $cred;
        }
    }

    // Volunteer applications
    $volStmt = $pdo->prepare(
        "SELECT * FROM volunteer_applications WHERE registration_id IN ({$placeholders})"
    );
    $volStmt->execute($regIds);
    foreach ($volStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $vol) {
        $volByReg[(string)$vol['registration_id']] = $vol;
    }

    // SLI applications
    $sliStmt = $pdo->prepare(
        "SELECT * FROM sli_applications WHERE registration_id IN ({$placeholders})"
    );
    $sliStmt->execute($regIds);
    foreach ($sliStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $sli) {
        $sliByReg[(string)$sli['registration_id']] = $sli;
    }

    // Check-ins
    $chkStmt = $pdo->prepare(
        "SELECT id, registration_id, venue_label, checkin_date_ist, checked_in_at
         FROM checkins
         WHERE registration_id IN ({$placeholders})
         ORDER BY checked_in_at DESC"
    );
    $chkStmt->execute($regIds);
    foreach ($chkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $chk) {
        $rId = (string)$chk['registration_id'];
        $checkinsByReg[$rId][] = $chk;
    }

    // Accommodation requests (canonical `accommodation_requests` ONLY)
    $regCodeMap = [];
    foreach ($userRegs as $ur) {
        $regCodeMap[(string)$ur['id']] = [
            'code'         => (string)$ur['registration_code'],
            'categoryCode' => (string)$ur['category_code'],
        ];
    }

    $accStmt = $pdo->prepare(
        "SELECT * FROM accommodation_requests WHERE registration_id IN ({$placeholders}) ORDER BY created_at DESC"
    );
    $accStmt->execute($regIds);
    $rawAccRows = $accStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rawAccRows as $ar) {
        $rId = (string)$ar['registration_id'];
        $selTypes = jsonDecode($ar['selected_types'] ?? '[]', []);
        if (!is_array($selTypes)) {
            $selTypes = [];
        }
        if (count($selTypes) === 0 && !empty($ar['accommodation_code'])) {
            $selTypes = [(string)$ar['accommodation_code']];
        }
        $accommodations[] = [
            'id'               => (string)$ar['id'],
            'registrationId'   => $rId,
            'registrationCode' => $regCodeMap[$rId]['code'] ?? '',
            'categoryCode'     => $regCodeMap[$rId]['categoryCode'] ?? '',
            'selectedTypes'    => array_values($selTypes),
            'freeTextDetails'  => pfgDecryptValue($ar['free_text_details'] ?? $ar['notes'] ?? null, 'data'),
            'coordinatorNotes' => pfgDecryptValue($ar['coordinator_notes'] ?? null, 'data'),
            'status'           => (string)($ar['status'] ?? 'REQUESTED'),
            'createdAt'        => (string)$ar['created_at'],
        ];
    }
}

function pfgStatusBadgeClasses(string $status): string
{
    switch ($status) {
        case 'CONFIRMED':
        case 'APPROVED':
        case 'ARRANGED':
        case 'COMPLETED':
            return 'bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30';
        case 'SUBMITTED':
        case 'UNDER_REVIEW':
        case 'REQUESTED':
        case 'IN_PROGRESS':
        case 'ASSIGNED':
            return 'bg-[#59436D]/15 text-[#432F56] border-[#59436D]/30';
        case 'WAITLISTED':
        case 'ADDITIONAL_INFO_REQUIRED':
            return 'bg-[#7A5C1F]/15 text-[#5E4514] border-[#7A5C1F]/30';
        case 'REJECTED':
        case 'CANCELLED':
        case 'DECLINED':
            return 'bg-[#B42332]/15 text-[#8A1824] border-[#B42332]/30';
        default:
            return 'bg-[#E8DFF0] text-[#432F56] border-[#59436D]/20';
    }
}

$pageTitle = 'Participant Dashboard | Purple Festival Gujarat 2027';
$pageDescription = 'Manage your registrations, digital QR entry passes, volunteer/SLI duty assignments, accessibility accommodations, and account security.';
$hideSiteHeader = true;
$hideSiteFooter = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen editorial-mesh-light">
  <!-- Top Charcoal Header -->
  <header class="bg-[#17151C] text-[#F9F7FC] border-b border-[#383242]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#D5B77B]">
            <?= htmlspecialchars(FESTIVAL_NAME . ' • ' . FESTIVAL_DATES_DISPLAY, ENT_QUOTES, 'UTF-8') ?>
          </p>
          <h1 id="dash-welcome-heading" class="mt-1 font-display text-2xl sm:text-3xl font-semibold">
            Welcome, <?= htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <p class="text-sm text-[#D7CCE2] mt-0.5">
            <?= htmlspecialchars($userEmail . ' • ' . FESTIVAL_LOCATION_DISPLAY, ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <a
            href="/"
            class="min-h-[44px] px-3.5 py-2 rounded-xl border border-[#383242] bg-[#25212B] hover:bg-[#383242] text-[#D7CCE2] hover:text-[#F9F7FC] text-sm font-semibold inline-flex items-center gap-1.5"
          >
            Festival Home
          </a>

          <?php if ($isAdmin): ?>
            <a
              href="/admin"
              class="min-h-[44px] px-4 py-2 rounded-xl bg-[#D5B77B] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5"
            >
              Open Admin Portal
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                <path d="M15 3h6v6" />
                <path d="M10 14 21 3" />
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
              </svg>
            </a>
          <?php endif; ?>

          <?php if ($isRgst): ?>
            <a
              href="/rgst"
              class="min-h-[44px] px-4 py-2 rounded-xl border border-[#D5B77B]/60 bg-[#25212B] hover:bg-[#D5B77B] text-[#D5B77B] hover:text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
            >
              RGST QR Scanner
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                <rect width="5" height="5" x="3" y="3" rx="1" />
                <rect width="5" height="5" x="16" y="3" rx="1" />
                <rect width="5" height="5" x="3" y="16" rx="1" />
                <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
                <path d="M21 21v.01" />
                <path d="M12 7v3a2 2 0 0 1-2 2H7" />
              </svg>
            </a>
          <?php endif; ?>

          <a
            href="/apply"
            class="min-h-[44px] px-4 py-2 rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
              <circle cx="12" cy="12" r="10" />
              <path d="M8 12h8" />
              <path d="M12 8v8" />
            </svg>
            Add Role / Registration
          </a>

          <button
            type="button"
            id="dash-logout-btn"
            class="min-h-[44px] px-4 py-2 rounded-xl border border-[#383242] bg-[#25212B] hover:bg-[#383242] text-[#F9F7FC] text-sm font-semibold inline-flex items-center gap-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" x2="9" y1="12" y2="12" />
            </svg>
            <span id="dash-logout-label">Sign Out</span>
          </button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <nav
        aria-label="Participant dashboard sections"
        class="mt-6 flex flex-wrap gap-2"
        role="tablist"
      >
        <button
          type="button"
          role="tab"
          data-dash-tab="passes"
          aria-selected="true"
          class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#F6F1F8] text-[#17151C]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
            <rect width="5" height="5" x="3" y="3" rx="1" />
            <rect width="5" height="5" x="16" y="3" rx="1" />
            <rect width="5" height="5" x="3" y="16" rx="1" />
            <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
            <path d="M12 7v3a2 2 0 0 1-2 2H7" />
          </svg>
          <span>Registrations &amp; QR Passes (<?= count($userRegs) ?>)</span>
        </button>

        <button
          type="button"
          role="tab"
          data-dash-tab="accommodations"
          aria-selected="false"
          class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
            <circle cx="16" cy="4" r="1" />
            <path d="m18 19 1-7-6 1" />
            <path d="m5 8 3-3 5.5 3-2.36 3.5" />
            <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
            <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
          </svg>
          <span id="dash-acc-tab-label">Accommodations (<?= count($accommodations) ?>)</span>
        </button>

        <button
          type="button"
          role="tab"
          data-dash-tab="settings"
          aria-selected="false"
          class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          <span>Profile &amp; Security (MFA)</span>
        </button>
      </nav>
    </div>
  </header>

  <main id="main-content" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- ================= TAB 1: REGISTRATIONS & QR PASSES ================= -->
    <section
      id="dash-panel-passes"
      data-dash-panel="passes"
      aria-label="Your registrations and digital QR passes"
      class="space-y-6"
    >
      <?php if (count($userRegs) === 0): ?>
        <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-8 text-center space-y-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-[#9273B5] mx-auto" aria-hidden="true">
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="M12 11h4" />
            <path d="M12 16h4" />
            <path d="M8 11h.01" />
            <path d="M8 16h.01" />
          </svg>
          <h2 class="font-display text-2xl font-semibold text-[#17151C]">
            No Registrations Yet
          </h2>
          <p class="text-sm text-[#4B4257] max-w-md mx-auto">
            Register as an Attendee, Delegate, Volunteer, or Indian Sign Language
            Interpreter for Purple Festival Gujarat 2027.
          </p>
          <a
            href="/apply"
            class="btn-primary-pfg inline-flex items-center gap-2 min-h-[48px] px-6 py-2.5 rounded-xl bg-[#59436D] text-[#F9F7FC] font-bold text-sm"
          >
            Start Registration
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($userRegs as $reg):
            $rId = (string)$reg['id'];
            $catCode = (string)$reg['category_code'];
            $catName = $categoryNameMap[$catCode] ?? $catCode;
            $attDays = jsonDecode($reg['attending_days'] ?? '[]', []);
            if (!is_array($attDays)) {
                $attDays = [];
            }
            $activeCred = $credsByReg[$rId] ?? null;
            $qrPayload = $activeCred ? reconstructQrTokenForCredential($activeCred) : '';
            $regCheckins = $checkinsByReg[$rId] ?? [];
            $vol = $volByReg[$rId] ?? null;
            $sli = $sliByReg[$rId] ?? null;

            $hasVolDuty = $vol && (
                !empty($vol['assigned_department']) ||
                !empty($vol['assigned_venue']) ||
                !empty($vol['assigned_shift'])
            );

            $sliSessions = $sli ? jsonDecode($sli['assigned_sessions'] ?? '[]', []) : [];
            if (!is_array($sliSessions)) {
                $sliSessions = [];
            }
            $hasSliDuty = $sli && (
                !empty($sli['assigned_venue']) ||
                !empty($sli['duty_times']) ||
                count($sliSessions) > 0
            );
        ?>
          <article class="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-[#59436D]/15 flex flex-wrap items-start justify-between gap-4">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-mono text-sm font-bold text-[#432F56] bg-[#E8DFF0] px-2.5 py-1 rounded-lg">
                    <?= htmlspecialchars((string)$reg['registration_code'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= pfgStatusBadgeClasses((string)$reg['status']) ?>">
                    <?= htmlspecialchars(str_replace('_', ' ', (string)$reg['status']), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <?php if (($reg['payment_status'] ?? 'NOT_REQUIRED') !== 'NOT_REQUIRED'): ?>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#F6F1F8] text-[#4B4257] border border-[#59436D]/20">
                      Payment: <?= htmlspecialchars((string)$reg['payment_status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                </div>
                <h2 class="mt-2 font-display text-xl font-semibold text-[#17151C]">
                  <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="text-xs text-[#4B4257] mt-1 flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5" aria-hidden="true">
                    <path d="M8 2v4" />
                    <path d="M16 2v4" />
                    <rect width="18" height="18" x="3" y="4" rx="2" />
                    <path d="M3 10h18" />
                  </svg>
                  Days: <?= count($attDays) > 0 ? htmlspecialchars(implode(', ', $attDays), ENT_QUOTES, 'UTF-8') : 'All Festival Days' ?>
                </p>
              </div>

              <?php if ($activeCred): ?>
                <a
                  href="/dashboard/pass-print.php?registrationId=<?= rawurlencode($rId) ?>"
                  class="btn-primary-pfg inline-flex items-center gap-2 min-h-[44px] px-4 py-2 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] text-sm font-semibold transition-colors"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" x2="12" y1="15" y2="3" />
                  </svg>
                  Download / Print Accessible Pass
                </a>
              <?php endif; ?>
            </div>

            <div class="p-6 space-y-5">
              <!-- Digital QR Pass Card -->
              <?php if ($activeCred): ?>
                <div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 flex flex-col md:flex-row items-center gap-6">
                  <div class="bg-white p-3 rounded-xl border border-[#59436D]/20 shrink-0 shadow-sm">
                    <div
                      data-qr-payload="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                      data-qr-size="176"
                      data-qr-label="Digital QR pass for <?= htmlspecialchars((string)$reg['full_name'], ENT_QUOTES, 'UTF-8') ?>, credential code <?= htmlspecialchars((string)$activeCred['credential_code'], ENT_QUOTES, 'UTF-8') ?>"
                      class="w-44 h-44 flex items-center justify-center"
                    ></div>
                  </div>

                  <div class="space-y-2 text-sm text-[#211B29] w-full">
                    <h3 class="font-display text-lg font-semibold text-[#17151C]">
                      Active Digital QR Entry Pass
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div>
                        <dt class="text-xs text-[#4B4257]">
                          Accessible Text Pass ID
                        </dt>
                        <dd class="font-mono font-bold text-[#432F56]">
                          <?= htmlspecialchars((string)$activeCred['credential_code'], ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                      </div>
                      <div>
                        <dt class="text-xs text-[#4B4257]">
                          Credential Status
                        </dt>
                        <dd class="font-semibold text-[#19734B]">
                          <?= htmlspecialchars((string)$activeCred['status'], ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                      </div>
                      <div>
                        <dt class="text-xs text-[#4B4257]">
                          Participant Name
                        </dt>
                        <dd class="font-semibold">
                          <?= htmlspecialchars((string)$reg['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                      </div>
                      <div>
                        <dt class="text-xs text-[#4B4257]">
                          Verified Check-ins
                        </dt>
                        <dd class="font-semibold">
                          <?= count($regCheckins) > 0 ? count($regCheckins) . ' recorded' : 'Not yet scanned at venue' ?>
                        </dd>
                      </div>
                    </dl>
                    <p class="text-xs text-[#4B4257] pt-1">
                      Screen-reader &amp; manual fallback: Staff at reception can verify your entry using Pass ID
                      <strong class="font-mono"><?= htmlspecialchars((string)$activeCred['credential_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                      without scanning the image.
                    </p>
                  </div>
                </div>
              <?php else: ?>
                <div class="rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 text-sm text-[#4B4257]">
                  <?php if (in_array($reg['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)): ?>
                    Your application is currently under coordinator review. Your digital QR entry pass will appear here automatically once approved.
                  <?php else: ?>
                    Status: <?= htmlspecialchars(str_replace('_', ' ', (string)$reg['status']), ENT_QUOTES, 'UTF-8') ?>. No active QR credential issued.
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Volunteer Duty Assignment -->
              <?php if ($hasVolDuty && $vol): ?>
                <div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-2">
                  <h3 class="font-display text-base font-semibold text-[#D5B77B]">
                    Volunteer Duty Assignment
                  </h3>
                  <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <?php if (!empty($vol['assigned_department'])): ?>
                      <div>
                        <dt class="text-xs text-[#D7CCE2]">Department</dt>
                        <dd class="font-semibold"><?= htmlspecialchars((string)$vol['assigned_department'], ENT_QUOTES, 'UTF-8') ?></dd>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($vol['assigned_venue'])): ?>
                      <div>
                        <dt class="text-xs text-[#D7CCE2]">Venue</dt>
                        <dd class="font-semibold"><?= htmlspecialchars((string)$vol['assigned_venue'], ENT_QUOTES, 'UTF-8') ?></dd>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($vol['assigned_shift'])): ?>
                      <div>
                        <dt class="text-xs text-[#D7CCE2]">Shift / Reporting</dt>
                        <dd class="font-semibold">
                          <?= htmlspecialchars((string)$vol['assigned_shift'] . (!empty($vol['reporting_time']) ? ' (' . $vol['reporting_time'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($vol['assigned_supervisor'])): ?>
                      <div>
                        <dt class="text-xs text-[#D7CCE2]">Supervisor</dt>
                        <dd class="font-semibold"><?= htmlspecialchars((string)$vol['assigned_supervisor'], ENT_QUOTES, 'UTF-8') ?></dd>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($vol['assigned_responsibility'])): ?>
                      <div class="sm:col-span-2">
                        <dt class="text-xs text-[#D7CCE2]">Responsibility</dt>
                        <dd class="font-semibold"><?= htmlspecialchars((string)$vol['assigned_responsibility'], ENT_QUOTES, 'UTF-8') ?></dd>
                      </div>
                    <?php endif; ?>
                  </dl>
                </div>
              <?php endif; ?>

              <!-- SLI Duty Assignment -->
              <?php if ($hasSliDuty && $sli): ?>
                <div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-3">
                  <h3 class="font-display text-base font-semibold text-[#D5B77B]">
                    SLI Interpretation Assignment
                  </h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <?php if (!empty($sli['assigned_venue'])): ?>
                      <div>
                        <p class="text-xs text-[#D7CCE2]">Assigned Venue</p>
                        <p class="font-semibold"><?= htmlspecialchars((string)$sli['assigned_venue'], ENT_QUOTES, 'UTF-8') ?></p>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($sli['duty_times'])): ?>
                      <div>
                        <p class="text-xs text-[#D7CCE2]">Duty Schedule</p>
                        <p class="font-semibold"><?= htmlspecialchars((string)$sli['duty_times'], ENT_QUOTES, 'UTF-8') ?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php if (count($sliSessions) > 0): ?>
                    <ul class="space-y-1.5 text-sm border-t border-[#383242] pt-3">
                      <?php foreach ($sliSessions as $sess): ?>
                        <li class="text-[#E8DFF0]">
                          <strong><?= htmlspecialchars((string)($sess['day'] ?? 'Session'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                          <?= htmlspecialchars((string)($sess['sessionTitle'] ?? ''), ENT_QUOTES, 'UTF-8') ?> —
                          <?= htmlspecialchars((string)($sess['venue'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                          (<?= htmlspecialchars((string)($sess['dutyTime'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- ================= TAB 2: ACCOMMODATIONS ================= -->
    <section
      id="dash-panel-accommodations"
      data-dash-panel="accommodations"
      aria-label="Accessibility and accommodation requests"
      class="hidden grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <h2 class="font-display text-xl font-semibold text-[#17151C]">
          Submit Accommodation Request
        </h2>
        <p class="text-sm text-[#4B4257]">
          Need ISL interpretation, Braille materials, wheelchair seating, or
          sensory sanctuary access? Requests are visible only to authorized
          accessibility coordinators.
        </p>

        <div id="dash-acc-message" class="hidden rounded-xl p-3.5 text-sm"></div>

        <?php if (count($userRegs) === 0): ?>
          <p class="text-sm text-[#4B4257]">
            Please complete a registration first to link an accommodation request.
          </p>
        <?php else: ?>
          <form id="dash-acc-form" class="space-y-4">
            <div>
              <label for="acc-reg-select" class="block text-sm font-semibold text-[#211B29] mb-1.5">
                Select Registration
              </label>
              <select
                id="acc-reg-select"
                name="registrationId"
                class="w-full min-h-[44px] px-3 py-2 rounded-xl border border-[#59436D]/30 bg-white text-sm"
              >
                <?php foreach ($userRegs as $ur):
                    $cCode = (string)$ur['category_code'];
                    $cName = $categoryNameMap[$cCode] ?? $cCode;
                ?>
                  <option value="<?= htmlspecialchars((string)$ur['id'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string)$ur['registration_code'] . ' — ' . $cName, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <fieldset>
              <legend class="text-sm font-semibold text-[#211B29] mb-2">
                Select Accommodations Needed
              </legend>
              <div class="grid grid-cols-1 gap-2 max-h-56 overflow-y-auto pr-1">
                <?php foreach ($accommodationTypes as $at): ?>
                  <label class="cursor-pointer rounded-xl border border-[#59436D]/20 hover:bg-[#F6F1F8] p-2.5 flex items-center gap-2.5 text-sm">
                    <input
                      type="checkbox"
                      name="selectedTypes[]"
                      value="<?= htmlspecialchars((string)$at['code'], ENT_QUOTES, 'UTF-8') ?>"
                      class="w-4 h-4 accent-[#59436D]"
                    />
                    <span><?= htmlspecialchars((string)$at['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div>
              <label for="acc-notes" class="block text-sm font-semibold text-[#211B29] mb-1.5">
                Additional Details (Optional)
              </label>
              <textarea
                id="acc-notes"
                name="freeTextDetails"
                rows="3"
                placeholder="Share specific timing, seating, or communication preferences..."
                class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
              ></textarea>
            </div>

            <button
              type="submit"
              id="dash-acc-submit-btn"
              class="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm disabled:opacity-60"
            >
              Submit Accommodation Request
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <h2 class="font-display text-xl font-semibold text-[#17151C]">
          Your Submitted Requests
        </h2>
        <div id="dash-acc-list" class="space-y-3">
          <?php if (count($accommodations) === 0): ?>
            <p id="dash-acc-empty" class="text-sm text-[#4B4257]">
              No accommodation requests submitted yet.
            </p>
          <?php else: ?>
            <?php foreach ($accommodations as $req): ?>
              <div class="rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-4 space-y-2">
                <div class="flex items-center justify-between gap-2">
                  <span class="font-mono text-xs font-bold text-[#432F56]">
                    <?= htmlspecialchars((string)$req['registrationCode'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="px-2.5 py-0.5 rounded-md text-xs font-bold border <?= pfgStatusBadgeClasses((string)$req['status']) ?>">
                    <?= htmlspecialchars(str_replace('_', ' ', (string)$req['status']), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <?php if (!empty($req['selectedTypes'])): ?>
                  <ul class="flex flex-wrap gap-1.5">
                    <?php foreach ($req['selectedTypes'] as $tCode): ?>
                      <li class="px-2 py-0.5 rounded bg-white border border-[#59436D]/20 text-xs font-medium text-[#211B29]">
                        <?= htmlspecialchars($accLabelMap[$tCode] ?? $tCode, ENT_QUOTES, 'UTF-8') ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (!empty($req['freeTextDetails'])): ?>
                  <p class="text-sm text-[#4B4257]">
                    <?= htmlspecialchars((string)$req['freeTextDetails'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($req['coordinatorNotes'])): ?>
                  <p class="text-xs font-semibold text-[#145C3B] border-t border-[#59436D]/15 pt-1.5">
                    Coordinator Note: <?= htmlspecialchars((string)$req['coordinatorNotes'], ENT_QUOTES, 'UTF-8') ?>
                  </p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ================= TAB 3: PROFILE & SECURITY (MFA) ================= -->
    <section
      id="dash-panel-settings"
      data-dash-panel="settings"
      aria-label="Profile and account security settings"
      class="hidden grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <!-- Profile & Password Form -->
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <h2 class="font-display text-xl font-semibold text-[#17151C]">
          Profile &amp; Password
        </h2>

        <div id="dash-profile-message" class="hidden rounded-xl p-3.5 text-sm"></div>

        <form id="dash-profile-form" class="space-y-4">
          <div>
            <label for="prof-name" class="block text-sm font-semibold mb-1">
              Full Name
            </label>
            <input
              id="prof-name"
              type="text"
              required
              value="<?= htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') ?>"
              class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
            />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="prof-phone" class="block text-sm font-semibold mb-1">
                Phone Number
              </label>
              <input
                id="prof-phone"
                type="tel"
                value="<?= htmlspecialchars($userPhone, ENT_QUOTES, 'UTF-8') ?>"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
              />
            </div>
            <div>
              <label for="prof-country" class="block text-sm font-semibold mb-1">
                Country
              </label>
              <input
                id="prof-country"
                type="text"
                value="<?= htmlspecialchars($userCountry, ENT_QUOTES, 'UTF-8') ?>"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
              />
            </div>
          </div>

          <div class="pt-3 border-t border-[#59436D]/15 space-y-3">
            <p class="text-sm font-semibold text-[#432F56] flex items-center gap-1.5">
              Change Password (Optional)
            </p>
            <div>
              <label for="prof-curr-pw" class="block text-xs font-semibold mb-1">
                Current Password
              </label>
              <input
                id="prof-curr-pw"
                type="password"
                autocomplete="current-password"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
              />
            </div>
            <div>
              <label for="prof-new-pw" class="block text-xs font-semibold mb-1">
                New Password (minimum 8 characters)
              </label>
              <input
                id="prof-new-pw"
                type="password"
                autocomplete="new-password"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
              />
            </div>
          </div>

          <button
            type="submit"
            id="dash-profile-submit-btn"
            class="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm disabled:opacity-60"
          >
            Save Changes
          </button>
        </form>
      </div>

      <!-- MFA Setup Card -->
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <div class="flex items-center justify-between gap-2">
          <h2 class="font-display text-xl font-semibold text-[#17151C] flex items-center gap-2">
            Two-Factor Authentication (MFA)
          </h2>
          <span
            id="dash-mfa-status-badge"
            class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $userMfaEnabled ? 'bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30' : 'bg-[#F6F1F8] text-[#4B4257] border-[#59436D]/20' ?>"
          >
            <?= $userMfaEnabled ? 'Enabled' : 'Not Enabled' ?>
          </span>
        </div>

        <p class="text-sm text-[#4B4257]">
          Protect your account with a 6-digit TOTP authenticator app (Google
          Authenticator, Authy, 1Password, or Microsoft Authenticator).
        </p>

        <div id="dash-mfa-message" class="hidden rounded-xl p-3.5 text-sm"></div>

        <!-- Start MFA button -->
        <div id="dash-mfa-start-wrap" class="<?= $userMfaEnabled ? 'hidden' : '' ?>">
          <button
            type="button"
            id="dash-mfa-start-btn"
            class="min-h-[46px] px-5 py-2.5 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-bold hover:bg-[#25212B]"
          >
            Set Up Authenticator App
          </button>
        </div>

        <!-- MFA QR Setup box -->
        <div id="dash-mfa-setup-box" class="hidden rounded-xl bg-[#F6F1F8] border border-[#9273B5]/40 p-4 space-y-4">
          <div class="flex flex-col sm:flex-row items-center gap-4">
            <div
              id="dash-mfa-qr-target"
              class="w-40 h-40 bg-white p-2 rounded-xl border border-[#59436D]/20 flex items-center justify-center shrink-0"
            ></div>
            <div class="text-xs space-y-1.5">
              <p class="font-semibold text-[#17151C]">
                1. Scan this QR code in your authenticator app
              </p>
              <p class="text-[#4B4257]">
                Or enter this manual secret key:
              </p>
              <code id="dash-mfa-secret-code" class="block p-2 rounded bg-white border border-[#59436D]/20 font-mono text-xs break-all select-all"></code>
            </div>
          </div>

          <div class="space-y-2">
            <label for="mfa-verify-code" class="block text-sm font-semibold text-[#211B29]">
              2. Enter the 6-digit code to activate MFA
            </label>
            <div class="flex gap-2">
              <input
                id="mfa-verify-code"
                type="text"
                inputmode="numeric"
                maxlength="8"
                placeholder="000000"
                class="flex-1 min-h-[44px] px-4 rounded-xl border border-[#59436D]/30 bg-white font-mono text-center tracking-widest"
              />
              <button
                type="button"
                id="dash-mfa-enable-btn"
                class="min-h-[44px] px-5 rounded-xl bg-[#59436D] text-white font-bold text-sm"
              >
                Verify &amp; Enable
              </button>
            </div>
          </div>
        </div>

        <!-- Disable MFA box -->
        <div id="dash-mfa-disable-box" class="<?= $userMfaEnabled ? '' : 'hidden' ?> rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 space-y-3">
          <p class="text-xs text-[#4B4257]">
            To disable MFA, enter a current 6-digit code from your authenticator app:
          </p>
          <div class="flex gap-2">
            <label for="mfa-disable-code" class="sr-only">
              6-digit authenticator code to disable MFA
            </label>
            <input
              id="mfa-disable-code"
              type="text"
              inputmode="numeric"
              maxlength="8"
              placeholder="000000"
              class="flex-1 min-h-[44px] px-4 rounded-xl border border-[#59436D]/30 bg-white font-mono text-center tracking-widest"
            />
            <button
              type="button"
              id="dash-mfa-disable-btn"
              class="min-h-[44px] px-4 rounded-xl border border-[#B42332] text-[#B42332] font-bold text-sm hover:bg-[#B42332]/10"
            >
              Disable MFA
            </button>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="/assets/js/qrcode-gen.js"></script>
<script>
(function () {
  "use strict";

  var csrfToken = <?= pfgSafeJsonForScript(getCsrfToken()) ?>;
  var accLabelMap = <?= pfgSafeJsonForScript($accLabelMap) ?>;
  var accCount = <?= count($accommodations) ?>;

  // 1. Tab switching
  var tabButtons = document.querySelectorAll("[data-dash-tab]");
  var tabPanels = document.querySelectorAll("[data-dash-panel]");

  function activateTab(tabId) {
    for (var i = 0; i < tabButtons.length; i++) {
      var btn = tabButtons[i];
      var isSel = btn.getAttribute("data-dash-tab") === tabId;
      btn.setAttribute("aria-selected", isSel ? "true" : "false");
      btn.className =
        "min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors " +
        (isSel
          ? "bg-[#F6F1F8] text-[#17151C]"
          : "bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]");
    }
    for (var j = 0; j < tabPanels.length; j++) {
      var panel = tabPanels[j];
      var pId = panel.getAttribute("data-dash-panel");
      if (pId === tabId) {
        panel.classList.remove("hidden");
        if (pId === "accommodations" || pId === "settings") {
          panel.classList.add("grid");
        }
      } else {
        panel.classList.add("hidden");
        panel.classList.remove("grid");
      }
    }
  }

  for (var t = 0; t < tabButtons.length; t++) {
    tabButtons[t].addEventListener("click", function () {
      activateTab(this.getAttribute("data-dash-tab"));
    });
  }

  // 2. Logout
  var logoutBtn = document.getElementById("dash-logout-btn");
  var logoutLabel = document.getElementById("dash-logout-label");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logoutBtn.disabled = true;
      if (logoutLabel) logoutLabel.textContent = "Signing out...";
      fetch("/api/auth/logout", {
        method: "POST",
        headers: { "X-CSRF-Token": csrfToken }
      })
        .catch(function () {})
        .finally(function () {
          window.location.href = "/login";
        });
    });
  }

  function showBanner(el, type, text) {
    if (!el) return;
    el.classList.remove("hidden");
    el.setAttribute("role", type === "error" ? "alert" : "status");
    el.className =
      "rounded-xl p-3.5 text-sm " +
      (type === "error"
        ? "bg-[#B42332]/10 border border-[#B42332] text-[#7A121D]"
        : "bg-[#19734B]/10 border border-[#19734B] text-[#145C3B]");
    el.textContent = text;
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // 3. Submit Accommodation Request
  var accForm = document.getElementById("dash-acc-form");
  var accMsg = document.getElementById("dash-acc-message");
  var accSubmitBtn = document.getElementById("dash-acc-submit-btn");
  var accList = document.getElementById("dash-acc-list");
  var accTabLabel = document.getElementById("dash-acc-tab-label");

  if (accForm) {
    accForm.addEventListener("submit", function (e) {
      e.preventDefault();
      if (accMsg) accMsg.classList.add("hidden");

      var regSelect = document.getElementById("acc-reg-select");
      var notesInput = document.getElementById("acc-notes");
      var checkedBoxes = accForm.querySelectorAll('input[name="selectedTypes[]"]:checked');
      var selectedTypes = [];
      for (var i = 0; i < checkedBoxes.length; i++) {
        selectedTypes.push(checkedBoxes[i].value);
      }

      var registrationId = regSelect ? regSelect.value : "";
      var freeTextDetails = notesInput ? notesInput.value.trim() : "";

      if (!registrationId) {
        showBanner(accMsg, "error", "Please select a registration first.");
        return;
      }

      if (accSubmitBtn) {
        accSubmitBtn.disabled = true;
        accSubmitBtn.textContent = "Submitting...";
      }

      fetch("/api/account/accommodations.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({
          registrationId: registrationId,
          selectedTypes: selectedTypes,
          freeTextDetails: freeTextDetails
        })
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            showBanner(
              accMsg,
              "error",
              (result.data && result.data.error) || "Unable to submit accommodation request."
            );
            return;
          }

          var req = result.data.request;
          for (var k = 0; k < checkedBoxes.length; k++) {
            checkedBoxes[k].checked = false;
          }
          if (notesInput) notesInput.value = "";

          var emptyNote = document.getElementById("dash-acc-empty");
          if (emptyNote) emptyNote.remove();

          if (accList && req) {
            var pillsHtml = "";
            if (Array.isArray(req.selectedTypes) && req.selectedTypes.length > 0) {
              pillsHtml = '<ul class="flex flex-wrap gap-1.5">';
              for (var p = 0; p < req.selectedTypes.length; p++) {
                var code = req.selectedTypes[p];
                var lbl = accLabelMap[code] || code;
                pillsHtml +=
                  '<li class="px-2 py-0.5 rounded bg-white border border-[#59436D]/20 text-xs font-medium text-[#211B29]">' +
                  escapeHtml(lbl) +
                  "</li>";
              }
              pillsHtml += "</ul>";
            }

            var card = document.createElement("div");
            card.className = "rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-4 space-y-2";
            card.innerHTML =
              '<div class="flex items-center justify-between gap-2">' +
              '<span class="font-mono text-xs font-bold text-[#432F56]">' +
              escapeHtml(req.registrationCode) +
              "</span>" +
              '<span class="px-2.5 py-0.5 rounded-md text-xs font-bold border bg-[#59436D]/15 text-[#432F56] border-[#59436D]/30">' +
              escapeHtml(req.status) +
              "</span>" +
              "</div>" +
              pillsHtml +
              (req.freeTextDetails
                ? '<p class="text-sm text-[#4B4257]">' + escapeHtml(req.freeTextDetails) + "</p>"
                : "");

            accList.insertBefore(card, accList.firstChild);
            accCount++;
            if (accTabLabel) {
              accTabLabel.textContent = "Accommodations (" + accCount + ")";
            }
          }

          showBanner(
            accMsg,
            "success",
            result.data.message || "Accommodation request submitted to our accessibility coordinators."
          );
        })
        .catch(function () {
          showBanner(accMsg, "error", "Network error while submitting request.");
        })
        .finally(function () {
          if (accSubmitBtn) {
            accSubmitBtn.disabled = false;
            accSubmitBtn.textContent = "Submit Accommodation Request";
          }
        });
    });
  }

  // 4. Profile & Password Form
  var profForm = document.getElementById("dash-profile-form");
  var profMsg = document.getElementById("dash-profile-message");
  var profSubmitBtn = document.getElementById("dash-profile-submit-btn");
  var welcomeHeading = document.getElementById("dash-welcome-heading");

  if (profForm) {
    profForm.addEventListener("submit", function (e) {
      e.preventDefault();
      if (profMsg) profMsg.classList.add("hidden");

      var fullName = document.getElementById("prof-name").value.trim();
      var phone = document.getElementById("prof-phone").value.trim();
      var country = document.getElementById("prof-country").value.trim() || "India";
      var currentPassword = document.getElementById("prof-curr-pw").value;
      var newPassword = document.getElementById("prof-new-pw").value;

      var payload = {
        fullName: fullName,
        phone: phone,
        country: country
      };
      if (newPassword) {
        payload.currentPassword = currentPassword;
        payload.newPassword = newPassword;
      }

      if (profSubmitBtn) {
        profSubmitBtn.disabled = true;
        profSubmitBtn.textContent = "Saving...";
      }

      fetch("/api/account/profile.php", {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            showBanner(
              profMsg,
              "error",
              (result.data && result.data.error) || "Could not update profile."
            );
            return;
          }
          if (welcomeHeading && result.data.user && result.data.user.fullName) {
            welcomeHeading.textContent = "Welcome, " + result.data.user.fullName;
          }
          document.getElementById("prof-curr-pw").value = "";
          document.getElementById("prof-new-pw").value = "";
          showBanner(
            profMsg,
            "success",
            result.data.message || "Profile updated successfully."
          );
        })
        .catch(function () {
          showBanner(profMsg, "error", "Network error while saving profile.");
        })
        .finally(function () {
          if (profSubmitBtn) {
            profSubmitBtn.disabled = false;
            profSubmitBtn.textContent = "Save Changes";
          }
        });
    });
  }

  // 5. Two-Factor Authentication (MFA)
  var mfaStartWrap = document.getElementById("dash-mfa-start-wrap");
  var mfaStartBtn = document.getElementById("dash-mfa-start-btn");
  var mfaSetupBox = document.getElementById("dash-mfa-setup-box");
  var mfaQrTarget = document.getElementById("dash-mfa-qr-target");
  var mfaSecretCode = document.getElementById("dash-mfa-secret-code");
  var mfaEnableBtn = document.getElementById("dash-mfa-enable-btn");
  var mfaDisableBox = document.getElementById("dash-mfa-disable-box");
  var mfaDisableBtn = document.getElementById("dash-mfa-disable-btn");
  var mfaStatusBadge = document.getElementById("dash-mfa-status-badge");
  var mfaMsg = document.getElementById("dash-mfa-message");

  if (mfaStartBtn) {
    mfaStartBtn.addEventListener("click", function () {
      if (mfaMsg) mfaMsg.classList.add("hidden");
      mfaStartBtn.disabled = true;
      mfaStartBtn.textContent = "Generating QR Code...";

      fetch("/api/auth/mfa", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({ action: "setup" })
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            showBanner(
              mfaMsg,
              "error",
              (result.data && result.data.error) || "Could not initialize MFA."
            );
            return;
          }
          if (mfaSecretCode) {
            mfaSecretCode.textContent = result.data.secret || "";
          }
          var otpUri = result.data.otpauthUrl || result.data.qrPayload || result.data.secret || "";
          if (mfaQrTarget && window.PFGQrCode && otpUri) {
            window.PFGQrCode.renderToElement(mfaQrTarget, otpUri, {
              width: 144,
              ariaLabel: "TOTP authenticator setup QR code"
            });
          }
          if (mfaStartWrap) mfaStartWrap.classList.add("hidden");
          if (mfaSetupBox) mfaSetupBox.classList.remove("hidden");
        })
        .catch(function () {
          showBanner(mfaMsg, "error", "Network error starting MFA.");
        })
        .finally(function () {
          mfaStartBtn.disabled = false;
          mfaStartBtn.textContent = "Set Up Authenticator App";
        });
    });
  }

  function verifyMfaAction(action) {
    if (mfaMsg) mfaMsg.classList.add("hidden");
    var inputEl =
      action === "enable"
        ? document.getElementById("mfa-verify-code")
        : document.getElementById("mfa-disable-code");
    var code = inputEl ? inputEl.value.trim() : "";

    fetch("/api/auth/mfa", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({ code: code, action: action })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          showBanner(
            mfaMsg,
            "error",
            (result.data && result.data.error) || "Verification failed."
          );
          return;
        }
        var enabled = Boolean(result.data.mfaEnabled);
        if (inputEl) inputEl.value = "";
        if (mfaStatusBadge) {
          mfaStatusBadge.textContent = enabled ? "Enabled" : "Not Enabled";
          mfaStatusBadge.className =
            "px-2.5 py-1 rounded-lg text-xs font-bold border " +
            (enabled
              ? "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30"
              : "bg-[#F6F1F8] text-[#4B4257] border-[#59436D]/20");
        }
        if (enabled) {
          if (mfaSetupBox) mfaSetupBox.classList.add("hidden");
          if (mfaStartWrap) mfaStartWrap.classList.add("hidden");
          if (mfaDisableBox) mfaDisableBox.classList.remove("hidden");
          showBanner(
            mfaMsg,
            "success",
            "Two-factor authentication is now enabled on your account."
          );
        } else {
          if (mfaDisableBox) mfaDisableBox.classList.add("hidden");
          if (mfaSetupBox) mfaSetupBox.classList.add("hidden");
          if (mfaStartWrap) mfaStartWrap.classList.remove("hidden");
          showBanner(mfaMsg, "success", "Two-factor authentication has been disabled.");
        }
      })
      .catch(function () {
        showBanner(mfaMsg, "error", "Network error verifying MFA.");
      });
  }

  if (mfaEnableBtn) {
    mfaEnableBtn.addEventListener("click", function () {
      verifyMfaAction("enable");
    });
  }
  if (mfaDisableBtn) {
    mfaDisableBtn.addEventListener("click", function () {
      verifyMfaAction("disable");
    });
  }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
