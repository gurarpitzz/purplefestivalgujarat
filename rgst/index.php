<?php
declare(strict_types=1);

/**
 * RGST Reception Desk QR Scanner & Attendance Portal (/rgst -> php-site/rgst/index.php)
 * Exact PHP + Vanilla JS port of src/app/rgst/page.tsx & src/components/RgstScannerPortal.tsx.
 *
 * Enforces RGST, ADMIN, or SUPERADMIN role.
 * Data-minimized view: NEVER displays disability, medical, or accommodation disclosures.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/qr-service.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /login?next=/rgst');
    exit;
}

$userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$canScan = (function_exists('hasPermission') && hasPermission($user, PERMISSIONS['QR_SCAN']))
    || count(array_intersect($userRoles, ['RGST', 'ADMIN', 'SUPERADMIN'])) > 0;

if (!$canScan) {
    header('Location: /dashboard');
    exit;
}

$pdo = getDb();
$todayIst = getIstDateString();
$operatorId = (string)$user['id'];
$operatorName = (string)($user['fullName'] ?? $user['full_name'] ?? 'RGST Operator');
$operatorEmail = (string)($user['email'] ?? '');
$isAdmin = in_array('SUPERADMIN', $userRoles, true) || in_array('ADMIN', $userRoles, true);
$roleLabel = in_array('SUPERADMIN', $userRoles, true)
    ? 'SUPERADMIN'
    : (in_array('ADMIN', $userRoles, true) ? 'ADMIN' : 'RGST');

// Fetch active check-in stations
$stStmt = $pdo->query(
    'SELECT id, name, gate_code
     FROM checkin_stations
     WHERE is_active = 1
     ORDER BY name ASC'
);
$stations = $stStmt ? ($stStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
if (count($stations) === 0) {
    $stations = [
        [
            'id'        => '',
            'name'      => 'Main Gate — Sama Indoor Stadium',
            'gate_code' => 'MAIN-GATE',
        ],
    ];
}

// Fetch operator's recent check-ins
$ownStmt = $pdo->prepare(
    'SELECT c.id, c.venue_label, c.method, c.checkin_date_ist, c.entitlement_scope, c.checked_in_at,
            r.full_name AS participant_name, r.registration_code, r.category_code,
            q.credential_code
     FROM checkins c
     INNER JOIN registrations r ON r.id = c.registration_id
     INNER JOIN qr_credentials q ON q.id = c.credential_id
     WHERE c.operator_user_id = :operator_id
     ORDER BY c.checked_in_at DESC
     LIMIT 50'
);
$ownStmt->execute([':operator_id' => $operatorId]);
$ownRows = $ownStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$initialTodayCount = 0;
foreach ($ownRows as $row) {
    if (($row['checkin_date_ist'] ?? '') === $todayIst) {
        $initialTodayCount++;
    }
}

$pageTitle = 'RGST Reception Desk — Purple Festival Gujarat 2027';
$pageDescription = 'Authorized QR verification and venue check-in reception desk portal for Purple Festival Gujarat 2027.';
$hideSiteHeader = true;
$hideSiteFooter = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen bg-[#17151C] text-[#F9F7FC] selection:bg-[#9273B5] selection:text-white">
  <!-- Skip Link -->
  <a
    href="#rgst-main"
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-[#D5B77B] focus:px-4 focus:py-2 focus:font-semibold focus:text-[#17151C]"
  >
    Skip to reception scanner
  </a>

  <!-- Distraction-Free Reception Desk Header -->
  <header class="sticky top-0 z-30 border-b border-[#9273B5]/30 bg-[#17151C]/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
      <div class="flex items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#59436D] text-[#D5B77B] ring-1 ring-[#D5B77B]/40"
          aria-hidden="true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
            <rect width="5" height="5" x="3" y="3" rx="1" />
            <rect width="5" height="5" x="16" y="3" rx="1" />
            <rect width="5" height="5" x="3" y="16" rx="1" />
            <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
            <path d="M12 7v3a2 2 0 0 1-2 2H7" />
          </svg>
        </div>
        <div>
          <h1 class="text-base font-bold tracking-tight text-[#F9F7FC] sm:text-lg">
            Purple Festival Gujarat 2027 — RGST Desk
          </h1>
          <p class="text-xs text-[#C6B6D8]">
            Reception Verification &amp; Attendance Portal • Data-Minimized View
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Station Selector -->
        <div class="flex items-center gap-2 rounded-lg border border-[#9273B5]/40 bg-[#221E2A] px-3 py-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#D5B77B]" aria-hidden="true">
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
            <circle cx="12" cy="10" r="3" />
          </svg>
          <label for="rgst-station-select" class="sr-only">
            Active Check-in Station
          </label>
          <select
            id="rgst-station-select"
            class="bg-transparent text-sm font-medium text-[#F9F7FC] focus:outline-none"
          >
            <?php foreach ($stations as $st): ?>
              <option
                value="<?= htmlspecialchars((string)$st['id'], ENT_QUOTES, 'UTF-8') ?>"
                data-station-name="<?= htmlspecialchars((string)$st['name'], ENT_QUOTES, 'UTF-8') ?>"
                class="bg-[#17151C] text-[#F9F7FC]"
              >
                <?= htmlspecialchars((string)$st['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Entitlement Scope Selector -->
        <div class="flex items-center gap-2 rounded-lg border border-[#9273B5]/40 bg-[#221E2A] px-3 py-1.5">
          <label for="rgst-scope-select" class="text-[11px] font-semibold uppercase tracking-wider text-[#D5B77B]">
            Scope:
          </label>
          <select
            id="rgst-scope-select"
            class="bg-transparent text-xs font-semibold text-[#F9F7FC] focus:outline-none"
          >
            <option value="GENERAL_ENTRY" class="bg-[#17151C] text-[#F9F7FC]">GENERAL_ENTRY (Today: <?= htmlspecialchars($todayIst, ENT_QUOTES, 'UTF-8') ?>)</option>
            <option value="DAY_1" class="bg-[#17151C] text-[#F9F7FC]">DAY_1 — 21 Jan 2027</option>
            <option value="DAY_2" class="bg-[#17151C] text-[#F9F7FC]">DAY_2 — 22 Jan 2027</option>
            <option value="DAY_3" class="bg-[#17151C] text-[#F9F7FC]">DAY_3 — 23 Jan 2027</option>
          </select>
        </div>

        <!-- Live IST Clock -->
        <div
          class="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs font-mono text-[#E8DFF2]"
          aria-label="Current Indian Standard Time"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
          <span id="rgst-ist-clock"><?= htmlspecialchars($todayIst . ' IST', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- Operator Badge -->
        <div class="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs">
          <span class="font-semibold text-[#F9F7FC]"><?= htmlspecialchars($operatorName, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="rounded bg-[#59436D] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#F9F7FC]">
            <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <a
          href="/dashboard"
          class="rounded-lg border border-[#9273B5]/40 px-3 py-1.5 text-xs font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435]"
        >
          My Pass
        </a>

        <?php if ($isAdmin): ?>
          <a
            href="/admin"
            class="rounded-lg border border-[#D5B77B]/50 px-3 py-1.5 text-xs font-semibold text-[#D5B77B] transition hover:bg-[#D5B77B] hover:text-[#17151C]"
          >
            Admin Portal
          </a>
        <?php endif; ?>

        <button
          type="button"
          id="rgst-logout-btn"
          class="inline-flex items-center gap-1.5 rounded-lg border border-red-400/40 bg-red-950/40 px-3 py-1.5 text-xs font-semibold text-red-200 transition hover:bg-red-900/60"
        >
          Logout
        </button>
      </div>
    </div>
  </header>

  <main id="rgst-main" class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
    <!-- Top Row: Camera QR Scanner + USB/Manual Entry -->
    <section
      aria-label="Scan or enter participant credential"
      class="grid gap-6 lg:grid-cols-2"
    >
      <!-- Camera QR Scanner Card -->
      <div class="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2.5">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-[#D5B77B]" aria-hidden="true">
              <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
              <circle cx="12" cy="13" r="3" />
            </svg>
            <h2 class="text-base font-bold tracking-wide text-[#F9F7FC]">
              SCAN PARTICIPANT QR
            </h2>
          </div>
          <button
            type="button"
            id="rgst-camera-toggle-btn"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#9273B5] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#A688C7]"
          >
            <span id="rgst-camera-btn-label">Start Camera Scanner</span>
          </button>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-dashed border-[#9273B5]/50 bg-[#141219] min-h-[260px] flex flex-col items-center justify-center">
          <div id="rgst-qr-reader" class="w-full max-w-[360px]"></div>
          <div id="rgst-camera-placeholder" class="p-6 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-3 h-12 w-12 text-[#9273B5]/60" aria-hidden="true">
              <rect width="5" height="5" x="3" y="3" rx="1" />
              <rect width="5" height="5" x="16" y="3" rx="1" />
              <rect width="5" height="5" x="3" y="16" rx="1" />
              <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
              <path d="M12 7v3a2 2 0 0 1-2 2H7" />
            </svg>
            <p class="text-sm font-medium text-[#E8DFF2]">
              Camera scanner is paused
            </p>
            <p class="mt-1 text-xs text-[#B6A5C9]">
              Click <strong>Start Camera Scanner</strong> to scan a participant&apos;s
              digital or printed QR pass. Works on mobile, tablet, and laptop cameras.
            </p>
          </div>
        </div>

        <div
          id="rgst-camera-error"
          role="alert"
          class="hidden mt-3 items-start gap-2.5 rounded-xl border border-amber-400/50 bg-amber-950/50 p-3 text-xs text-amber-100"
        ></div>
      </div>

      <!-- USB QR Scanner / Manual ID Entry Card -->
      <div class="flex flex-col justify-between rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg">
        <div>
          <div class="mb-4 flex items-center gap-2.5">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-[#D5B77B]" aria-hidden="true">
              <rect width="20" height="16" x="2" y="4" rx="2" />
              <path d="M6 8h.001" />
              <path d="M10 8h.001" />
              <path d="M14 8h.001" />
              <path d="M18 8h.001" />
              <path d="M8 12h.001" />
              <path d="M12 12h.001" />
              <path d="M16 12h.001" />
              <path d="M7 16h10" />
            </svg>
            <h2 class="text-base font-bold tracking-wide text-[#F9F7FC]">
              USB QR SCANNER / MANUAL ID ENTRY
            </h2>
          </div>

          <p class="mb-4 text-sm text-[#C6B6D8]">
            Focus the field below to scan directly with a USB barcode/QR reader, or type
            the participant&apos;s Registration / Credential ID (e.g.
            <code class="rounded bg-[#17151C] px-1.5 py-0.5 font-mono text-[#D5B77B]">PFG-2027-000001</code>
            or <code class="rounded bg-[#17151C] px-1.5 py-0.5 font-mono text-[#D5B77B]">PFG27....</code>)
            and press <strong>Enter</strong>.
          </p>

          <form id="rgst-manual-form" class="space-y-4">
            <div>
              <label
                for="rgst-credential-input"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#D5B77B]"
              >
                Registration ID / Credential Code / QR Token
              </label>
              <input
                id="rgst-credential-input"
                type="text"
                placeholder="PFG-2027-000001 or scan PFG27 QR token..."
                autocomplete="off"
                autofocus
                class="w-full min-h-[52px] rounded-xl border-2 border-[#9273B5]/50 bg-[#141219] px-4 py-3 font-mono text-base text-[#F9F7FC] placeholder-[#7B6E8C] focus:border-[#D5B77B] focus:outline-none"
              />
            </div>

            <div class="flex flex-wrap gap-3">
              <button
                type="submit"
                id="rgst-verify-btn"
                class="inline-flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-[#D5B77B] px-5 py-2.5 text-sm font-bold text-[#17151C] transition hover:bg-[#E2C78E] disabled:cursor-not-allowed disabled:opacity-50"
              >
                Verify Credential
              </button>
              <button
                type="button"
                id="rgst-clear-btn"
                class="min-h-[48px] rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-4 py-2.5 text-sm font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435]"
              >
                Clear
              </button>
            </div>
          </form>
        </div>

        <!-- Operator Desk Summary Strip -->
        <div class="mt-6 grid grid-cols-2 gap-3 border-t border-[#9273B5]/25 pt-4">
          <div class="rounded-xl bg-[#17151C] p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
              Your Check-ins Today
            </p>
            <p id="rgst-today-count" class="mt-1 text-2xl font-extrabold text-[#D5B77B]">
              <?= (int)$initialTodayCount ?>
            </p>
          </div>
          <div class="rounded-xl bg-[#17151C] p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
              Active Gate / Station
            </p>
            <p id="rgst-active-station-label" class="mt-1 truncate text-sm font-semibold text-[#F9F7FC]">
              <?= htmlspecialchars((string)$stations[0]['name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- 2-Step Verification Result Panel (aria-live="polite") -->
    <section
      id="rgst-result-panel"
      aria-label="Verification result"
      aria-live="polite"
      aria-atomic="true"
      class="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-6 shadow-lg"
    >
      <div id="rgst-result-idle" class="flex flex-col items-center justify-center py-6 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-2 h-10 w-10 text-[#9273B5]/70" aria-hidden="true">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <polyline points="16 11 18 13 22 9" />
        </svg>
        <p class="text-base font-semibold text-[#E8DFF2]">
          Ready for Participant Verification
        </p>
        <p class="mt-1 max-w-xl text-sm text-[#B6A5C9]">
          Scan a participant&apos;s QR code or enter their Registration ID above. Check-in
          is never automatic — you will review the participant&apos;s identity and click
          <strong>VERIFY AND CHECK IN</strong> to record attendance.
        </p>
      </div>
      <div id="rgst-result-dynamic" class="hidden"></div>
    </section>

    <!-- Operator's Own Recent Check-ins Table -->
    <section
      aria-label="Your recent check-ins"
      class="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg"
    >
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-[#F9F7FC]">
            Your Recent Check-ins
          </h2>
          <p class="text-xs text-[#B6A5C9]">
            Showing check-ins confirmed by <?= htmlspecialchars($operatorName, ENT_QUOTES, 'UTF-8') ?> (IST timestamps)
          </p>
        </div>
        <button
          type="button"
          id="rgst-refresh-btn"
          class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-[#9273B5]/40 bg-[#17151C] px-3.5 py-1.5 text-xs font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435]"
        >
          Refresh
        </button>
      </div>

      <div class="overflow-x-auto rounded-xl border border-[#9273B5]/25">
        <table class="min-w-full divide-y divide-[#9273B5]/20 text-left text-sm">
          <thead class="bg-[#17151C] text-xs uppercase tracking-wider text-[#C6B6D8]">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold">Time (IST)</th>
              <th scope="col" class="px-4 py-3 font-semibold">Participant</th>
              <th scope="col" class="px-4 py-3 font-semibold">Registration ID</th>
              <th scope="col" class="px-4 py-3 font-semibold">Category</th>
              <th scope="col" class="px-4 py-3 font-semibold">Venue / Station</th>
              <th scope="col" class="px-4 py-3 font-semibold">Scope / Method</th>
            </tr>
          </thead>
          <tbody id="rgst-checkins-tbody" class="divide-y divide-[#9273B5]/15 bg-[#1C1823]">
            <?php if (count($ownRows) === 0): ?>
              <tr id="rgst-empty-row">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-[#B6A5C9]">
                  No participants checked in by your station yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($ownRows as $row): ?>
                <tr class="hover:bg-[#262030]">
                  <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#E8DFF2]">
                    <?= htmlspecialchars(formatIstTimestamp($row['checked_in_at']), ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="px-4 py-3 font-semibold text-white">
                    <?= htmlspecialchars((string)$row['participant_name'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#D5B77B]">
                    <?= htmlspecialchars((string)$row['registration_code'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <span class="rounded-md bg-[#59436D]/60 px-2 py-0.5 text-xs font-semibold text-[#F9F7FC]">
                      <?= htmlspecialchars((string)$row['category_code'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs text-[#E8DFF2]">
                    <?= htmlspecialchars((string)$row['venue_label'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#B6A5C9]">
                    <?= htmlspecialchars((string)($row['entitlement_scope'] ?? '') . ' • ' . (string)$row['method'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script src="/assets/js/html5-qrcode.min.js"></script>
<script>
(function () {
  "use strict";

  var todayIst = <?= jsonEncode($todayIst) ?>;
  var stationSelect = document.getElementById("rgst-station-select");
  var scopeSelect = document.getElementById("rgst-scope-select");
  var activeStationLabel = document.getElementById("rgst-active-station-label");
  var istClockEl = document.getElementById("rgst-ist-clock");
  var credentialInput = document.getElementById("rgst-credential-input");
  var manualForm = document.getElementById("rgst-manual-form");
  var verifyBtn = document.getElementById("rgst-verify-btn");
  var clearBtn = document.getElementById("rgst-clear-btn");
  var todayCountEl = document.getElementById("rgst-today-count");
  var resultIdle = document.getElementById("rgst-result-idle");
  var resultDynamic = document.getElementById("rgst-result-dynamic");
  var checkinsTbody = document.getElementById("rgst-checkins-tbody");
  var refreshBtn = document.getElementById("rgst-refresh-btn");
  var logoutBtn = document.getElementById("rgst-logout-btn");
  var cameraToggleBtn = document.getElementById("rgst-camera-toggle-btn");
  var cameraBtnLabel = document.getElementById("rgst-camera-btn-label");
  var cameraPlaceholder = document.getElementById("rgst-camera-placeholder");
  var cameraErrorEl = document.getElementById("rgst-camera-error");

  var todayCount = <?= (int)$initialTodayCount ?>;
  var currentVerification = null;
  var lastMethod = "MANUAL_ID";
  var cameraScanner = null;
  var cameraActive = false;
  var lastScanned = { text: "", at: 0 };

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // Live IST clock
  function updateIstClock() {
    if (!istClockEl) return;
    try {
      var formatted = new Intl.DateTimeFormat("en-IN", {
        timeZone: "Asia/Kolkata",
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: true
      }).format(new Date());
      istClockEl.textContent = formatted + " IST";
    } catch (e) {}
  }
  updateIstClock();
  setInterval(updateIstClock, 1000);

  if (stationSelect) {
    stationSelect.addEventListener("change", function () {
      var opt = stationSelect.options[stationSelect.selectedIndex];
      var stName = opt ? opt.getAttribute("data-station-name") || opt.textContent : "";
      if (activeStationLabel) {
        activeStationLabel.textContent = stName.trim();
      }
    });
  }

  function getSelectedScopeOverride() {
    if (!scopeSelect) return todayIst;
    var val = scopeSelect.value;
    if (val === "GENERAL_ENTRY") {
      return "GENERAL_ENTRY";
    }
    return val;
  }

  function getSelectedStation() {
    if (!stationSelect) {
      return { id: null, name: "Main Gate — Sama Indoor Stadium" };
    }
    var opt = stationSelect.options[stationSelect.selectedIndex];
    var id = stationSelect.value || null;
    var name = opt
      ? (opt.getAttribute("data-station-name") || opt.textContent || "").trim()
      : "Main Gate — Sama Indoor Stadium";
    return { id: id, name: name };
  }

  function resetForNext() {
    currentVerification = null;
    if (credentialInput) {
      credentialInput.value = "";
      credentialInput.focus();
    }
    if (resultDynamic) {
      resultDynamic.classList.add("hidden");
      resultDynamic.innerHTML = "";
    }
    if (resultIdle) {
      resultIdle.classList.remove("hidden");
    }
  }

  if (clearBtn) {
    clearBtn.addEventListener("click", resetForNext);
  }

  function showResultHtml(html) {
    if (resultIdle) resultIdle.classList.add("hidden");
    if (resultDynamic) {
      resultDynamic.innerHTML = html;
      resultDynamic.classList.remove("hidden");
    }
  }

  function renderVerificationState(v) {
    currentVerification = v;

    // 1. VALID CREDENTIAL -> Green banner with 2-step Confirm Check-In button
    if (v.reason === "VALID" && v.valid) {
      var daysText =
        Array.isArray(v.attendingDays) && v.attendingDays.length > 0
          ? v.attendingDays.join(", ")
          : "All Festival Days";

      showResultHtml(
        '<div class="rounded-xl border-2 border-emerald-400 bg-emerald-950/45 p-5">' +
          '<div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-emerald-400/30 pb-3">' +
            '<div class="flex items-center gap-2.5">' +
              '<div>' +
                '<p class="text-lg font-extrabold tracking-wide text-emerald-300">VALID CREDENTIAL — READY FOR ENTRY</p>' +
                '<p class="text-xs text-emerald-100">Confirm participant identity before pressing Verify and Check In.</p>' +
              '</div>' +
            '</div>' +
            '<span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-200 ring-1 ring-emerald-400/50">' +
              escapeHtml(v.credentialStatus || "ACTIVE") +
            '</span>' +
          '</div>' +
          '<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Participant Name</dt>' +
              '<dd class="mt-1 text-lg font-bold text-white">' + escapeHtml(v.participantName) + '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Registration ID</dt>' +
              '<dd class="mt-1 font-mono text-base font-bold text-[#D5B77B]">' + escapeHtml(v.registrationCode) + '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Category</dt>' +
              '<dd class="mt-1 text-base font-bold text-[#F9F7FC]">' + escapeHtml(v.categoryName || v.categoryCode) + '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Status / Scope</dt>' +
              '<dd class="mt-1 text-sm font-bold text-emerald-300">' +
                escapeHtml(v.registrationStatus) + ' • ' + escapeHtml(v.entitlementScope || getSelectedScopeOverride()) +
              '</dd>' +
              '<dd class="mt-0.5 text-xs text-[#C6B6D8]">Days: ' + escapeHtml(daysText) + '</dd>' +
            '</div>' +
          '</dl>' +
          '<div class="mt-5 flex flex-wrap items-center gap-3">' +
            '<button type="button" id="rgst-confirm-checkin-btn" class="inline-flex min-h-[54px] flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-base font-extrabold tracking-wide text-[#0B1711] shadow-lg transition hover:bg-emerald-400 sm:flex-none">' +
              'VERIFY AND CHECK IN' +
            '</button>' +
            '<button type="button" id="rgst-cancel-checkin-btn" class="min-h-[54px] rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-5 py-3 text-sm font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435]">' +
              'Cancel' +
            '</button>' +
          '</div>' +
        '</div>'
      );

      var confirmBtn = document.getElementById("rgst-confirm-checkin-btn");
      var cancelBtn = document.getElementById("rgst-cancel-checkin-btn");
      if (confirmBtn) {
        confirmBtn.focus();
        confirmBtn.addEventListener("click", confirmParticipantEntry);
      }
      if (cancelBtn) {
        cancelBtn.addEventListener("click", resetForNext);
      }
      return;
    }

    // 2. ALREADY CHECKED IN -> Amber duplicate-prevented banner
    if (v.reason === "ALREADY_CHECKED_IN") {
      var prevTime =
        (v.existingCheckin && (v.existingCheckin.checkedInAtIst || v.existingCheckin.checkedInAt)) ||
        "Recorded earlier";
      var prevVenue =
        (v.existingCheckin && v.existingCheckin.venueLabel) || "Festival Gate";

      showResultHtml(
        '<div class="rounded-xl border-2 border-amber-400 bg-amber-950/50 p-5">' +
          '<div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-amber-400/30 pb-3">' +
            '<div>' +
              '<p class="text-lg font-extrabold tracking-wide text-amber-300">ALREADY CHECKED IN</p>' +
              '<p class="text-xs text-amber-100">Duplicate entry is blocked for this attendance period (' +
                escapeHtml(v.entitlementScope || getSelectedScopeOverride()) +
              ').</p>' +
            '</div>' +
            '<span class="rounded-full bg-amber-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-200 ring-1 ring-amber-400/50">' +
              'DUPLICATE PREVENTED' +
            '</span>' +
          '</div>' +
          '<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Participant Name</dt>' +
              '<dd class="mt-1 text-base font-bold text-white">' + escapeHtml(v.participantName) + '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Registration ID &amp; Category</dt>' +
              '<dd class="mt-1 font-mono text-sm font-bold text-[#D5B77B]">' +
                escapeHtml(v.registrationCode) + ' • ' + escapeHtml(v.categoryCode) +
              '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Checked In At (IST)</dt>' +
              '<dd class="mt-1 text-sm font-bold text-amber-200">' + escapeHtml(prevTime) + '</dd>' +
            '</div>' +
            '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
              '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Check-in Venue</dt>' +
              '<dd class="mt-1 text-sm font-bold text-amber-200">' + escapeHtml(prevVenue) + '</dd>' +
            '</div>' +
          '</dl>' +
          '<div class="mt-5 flex flex-wrap items-center gap-3">' +
            '<button type="button" disabled class="inline-flex min-h-[48px] cursor-not-allowed items-center gap-2 rounded-xl bg-amber-900/50 px-5 py-2.5 text-sm font-bold text-amber-200/70 ring-1 ring-amber-400/30">' +
              'VERIFY AND CHECK IN (Disabled — Already Checked In)' +
            '</button>' +
            '<button type="button" id="rgst-next-after-dup-btn" class="min-h-[48px] rounded-xl bg-[#9273B5] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#A688C7]">' +
              'Scan Next Participant' +
            '</button>' +
          '</div>' +
        '</div>'
      );

      var nextDupBtn = document.getElementById("rgst-next-after-dup-btn");
      if (nextDupBtn) nextDupBtn.addEventListener("click", resetForNext);
      return;
    }

    // 3. INVALID / REVOKED / INELIGIBLE / PAYMENT_PENDING -> Red banner
    var detailsGrid = "";
    if (v.participantName) {
      detailsGrid =
        '<dl class="mt-3 grid gap-3 sm:grid-cols-3">' +
          '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
            '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Participant</dt>' +
            '<dd class="mt-1 text-sm font-bold text-white">' + escapeHtml(v.participantName) + '</dd>' +
          '</div>' +
          '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
            '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Registration ID</dt>' +
            '<dd class="mt-1 font-mono text-sm font-bold text-[#D5B77B]">' + escapeHtml(v.registrationCode) + '</dd>' +
          '</div>' +
          '<div class="rounded-lg bg-[#17151C]/80 p-3">' +
            '<dt class="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">Status</dt>' +
            '<dd class="mt-1 text-sm font-bold text-red-300">Cred: ' +
              escapeHtml(v.credentialStatus) + ' / Reg: ' + escapeHtml(v.registrationStatus) +
            '</dd>' +
          '</div>' +
        '</dl>';
    }

    showResultHtml(
      '<div role="alert" class="rounded-xl border-2 border-red-500 bg-red-950/55 p-5">' +
        '<div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-red-500/30 pb-3">' +
          '<div>' +
            '<p class="text-lg font-extrabold tracking-wide text-red-300">ENTRY NOT PERMITTED — ' +
              escapeHtml(String(v.reason || "INVALID").replace(/_/g, " ")) +
            '</p>' +
            '<p class="text-sm text-red-100">' + escapeHtml(v.message || "Credential is not eligible for entry.") + '</p>' +
          '</div>' +
        '</div>' +
        detailsGrid +
        '<div class="mt-4">' +
          '<button type="button" id="rgst-next-after-err-btn" class="min-h-[44px] rounded-xl bg-[#9273B5] px-5 py-2 text-sm font-bold text-white transition hover:bg-[#A688C7]">' +
            'Scan Next Participant' +
          '</button>' +
        '</div>' +
      '</div>'
    );

    var nextErrBtn = document.getElementById("rgst-next-after-err-btn");
    if (nextErrBtn) nextErrBtn.addEventListener("click", resetForNext);
  }

  function verifyCredential(rawCode, method) {
    var trimmed = String(rawCode || "").trim();
    if (!trimmed) return;

    lastMethod = method;
    if (verifyBtn) {
      verifyBtn.disabled = true;
      verifyBtn.textContent = "Verifying...";
    }

    fetch("/api/qr/verify.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        input: trimmed,
        scopeOverride: getSelectedScopeOverride()
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data) {
          showResultHtml(
            '<div role="alert" class="rounded-xl border-2 border-red-500/70 bg-red-950/60 p-4 text-red-100">' +
              '<p class="text-base font-bold">Verification Error</p>' +
              '<p class="mt-1 text-sm text-red-200">' +
                escapeHtml((result.data && result.data.error) || "Unable to verify credential. Please retry.") +
              '</p>' +
            '</div>'
          );
          return;
        }
        renderVerificationState(result.data);
      })
      .catch(function () {
        showResultHtml(
          '<div role="alert" class="rounded-xl border-2 border-red-500/70 bg-red-950/60 p-4 text-red-100">' +
            '<p class="text-base font-bold">Network Error</p>' +
            '<p class="mt-1 text-sm text-red-200">Network error while verifying credential. Check connection.</p>' +
          '</div>'
        );
      })
      .finally(function () {
        if (verifyBtn) {
          verifyBtn.disabled = false;
          verifyBtn.textContent = "Verify Credential";
        }
      });
  }

  function prependCheckinRow(c) {
    if (!checkinsTbody || !c) return;
    var emptyRow = document.getElementById("rgst-empty-row");
    if (emptyRow) emptyRow.remove();

    var tr = document.createElement("tr");
    tr.className = "hover:bg-[#262030]";
    tr.innerHTML =
      '<td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#E8DFF2]">' +
        escapeHtml(c.checkedInAtIst || c.checkedInAt) +
      '</td>' +
      '<td class="px-4 py-3 font-semibold text-white">' +
        escapeHtml(c.participantName) +
      '</td>' +
      '<td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#D5B77B]">' +
        escapeHtml(c.registrationCode) +
      '</td>' +
      '<td class="whitespace-nowrap px-4 py-3">' +
        '<span class="rounded-md bg-[#59436D]/60 px-2 py-0.5 text-xs font-semibold text-[#F9F7FC]">' +
          escapeHtml(c.categoryCode) +
        '</span>' +
      '</td>' +
      '<td class="px-4 py-3 text-xs text-[#E8DFF2]">' +
        escapeHtml(c.venueLabel) +
      '</td>' +
      '<td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#B6A5C9]">' +
        escapeHtml((c.entitlementScope ? c.entitlementScope + " • " : "") + c.method) +
      '</td>';

    checkinsTbody.insertBefore(tr, checkinsTbody.firstChild);
  }

  function confirmParticipantEntry() {
    if (!currentVerification || !currentVerification.valid || !currentVerification.credentialId) {
      return;
    }

    var confirmBtn = document.getElementById("rgst-confirm-checkin-btn");
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = "Recording Check-in...";
    }

    var st = getSelectedStation();

    fetch("/api/checkins/confirm.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        credentialId: currentVerification.credentialId,
        venueLabel: st.name,
        stationId: st.id,
        method: lastMethod,
        scopeOverride: currentVerification.entitlementScope || getSelectedScopeOverride()
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (result.status === 409 && result.data && result.data.duplicate) {
          renderVerificationState({
            valid: false,
            reason: "ALREADY_CHECKED_IN",
            message: result.data.message || "ALREADY CHECKED IN",
            participantName: currentVerification.participantName,
            registrationCode: currentVerification.registrationCode,
            categoryCode: currentVerification.categoryCode,
            entitlementScope: currentVerification.entitlementScope,
            existingCheckin: result.data.existingCheckin || currentVerification.existingCheckin
          });
          return;
        }

        if (!result.ok || !result.data || !result.data.success) {
          showResultHtml(
            '<div role="alert" class="rounded-xl border-2 border-red-500/70 bg-red-950/60 p-4 text-red-100">' +
              '<p class="text-base font-bold">Check-in Confirmation Failed</p>' +
              '<p class="mt-1 text-sm text-red-200">' +
                escapeHtml((result.data && (result.data.error || result.data.message)) || "Unable to confirm check-in.") +
              '</p>' +
            '</div>'
          );
          return;
        }

        var confirmed = result.data.checkin;
        currentVerification = null;
        if (credentialInput) credentialInput.value = "";
        todayCount++;
        if (todayCountEl) todayCountEl.textContent = String(todayCount);
        prependCheckinRow(confirmed);

        showResultHtml(
          '<div class="rounded-xl border-2 border-emerald-400 bg-emerald-950/60 p-5">' +
            '<div class="flex flex-wrap items-center justify-between gap-4">' +
              '<div>' +
                '<p class="text-lg font-extrabold tracking-wide text-emerald-300">CHECK-IN CONFIRMED</p>' +
                '<p class="mt-1 text-base font-bold text-white">' +
                  escapeHtml(confirmed.participantName) + ' (' + escapeHtml(confirmed.registrationCode) + ')' +
                '</p>' +
                '<p class="mt-1 text-sm text-emerald-100">' +
                  'Category: <strong>' + escapeHtml(confirmed.categoryCode) + '</strong> • ' +
                  'Venue: <strong>' + escapeHtml(confirmed.venueLabel) + '</strong> • ' +
                  'Time: <strong>' + escapeHtml(confirmed.checkedInAtIst) + '</strong>' +
                '</p>' +
              '</div>' +
              '<button type="button" id="rgst-scan-next-btn" class="min-h-[48px] rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-[#0F1713] transition hover:bg-emerald-400">' +
                'Scan Next Participant' +
              '</button>' +
            '</div>' +
          '</div>'
        );

        var scanNextBtn = document.getElementById("rgst-scan-next-btn");
        if (scanNextBtn) scanNextBtn.addEventListener("click", resetForNext);
      })
      .catch(function () {
        showResultHtml(
          '<div role="alert" class="rounded-xl border-2 border-red-500/70 bg-red-950/60 p-4 text-red-100">' +
            '<p class="text-base font-bold">Network Error</p>' +
            '<p class="mt-1 text-sm text-red-200">Network error while confirming check-in. Please retry.</p>' +
          '</div>'
        );
      });
  }

  if (manualForm) {
    manualForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var cleaned = credentialInput ? credentialInput.value.trim() : "";
      if (!cleaned) return;
      var method = cleaned.indexOf("PFG27.") === 0 ? "USB_SCANNER" : "MANUAL_ID";
      verifyCredential(cleaned, method);
    });
  }

  // Camera QR Scanner via /assets/js/html5-qrcode.min.js
  function stopCamera() {
    if (cameraScanner) {
      try {
        cameraScanner
          .stop()
          .then(function () {
            cameraScanner.clear();
          })
          .catch(function () {});
      } catch (e) {}
      cameraScanner = null;
    }
    cameraActive = false;
    if (cameraBtnLabel) cameraBtnLabel.textContent = "Start Camera Scanner";
    if (cameraToggleBtn) {
      cameraToggleBtn.className =
        "inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#9273B5] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#A688C7]";
    }
    if (cameraPlaceholder) cameraPlaceholder.classList.remove("hidden");
  }

  if (cameraToggleBtn) {
    cameraToggleBtn.addEventListener("click", function () {
      if (cameraErrorEl) {
        cameraErrorEl.classList.add("hidden");
        cameraErrorEl.textContent = "";
      }
      if (cameraActive) {
        stopCamera();
        return;
      }

      if (typeof window.Html5Qrcode !== "function") {
        if (cameraErrorEl) {
          cameraErrorEl.textContent =
            "Camera scanner library could not be initialized. Use the USB QR Scanner or Manual ID entry on the right.";
          cameraErrorEl.classList.remove("hidden");
        }
        return;
      }

      try {
        cameraScanner = new window.Html5Qrcode("rgst-qr-reader");
        cameraScanner
          .start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1.0 },
            function (decodedText) {
              var now = Date.now();
              if (decodedText === lastScanned.text && now - lastScanned.at < 2500) {
                return;
              }
              lastScanned = { text: decodedText, at: now };
              if (credentialInput) credentialInput.value = decodedText;
              verifyCredential(decodedText, "QR_CAMERA");
            },
            function () {}
          )
          .then(function () {
            cameraActive = true;
            if (cameraPlaceholder) cameraPlaceholder.classList.add("hidden");
            if (cameraBtnLabel) cameraBtnLabel.textContent = "Stop Camera";
            cameraToggleBtn.className =
              "inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-500";
          })
          .catch(function (err) {
            stopCamera();
            var msg = String(err || "").toLowerCase();
            if (cameraErrorEl) {
              cameraErrorEl.textContent =
                msg.indexOf("permission") !== -1 || msg.indexOf("notallowed") !== -1
                  ? "Camera permission was denied. Allow camera access in your browser settings, or use the USB QR Scanner / Manual ID input."
                  : "Camera could not be started on this device. Use the USB QR Scanner or Manual Registration ID entry on the right.";
              cameraErrorEl.classList.remove("hidden");
            }
          });
      } catch (err) {
        stopCamera();
      }
    });
  }

  // Refresh recent check-ins
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      refreshBtn.disabled = true;
      fetch("/api/checkins/index.php", { cache: "no-store" })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (typeof data.todayCount === "number" && todayCountEl) {
            todayCount = data.todayCount;
            todayCountEl.textContent = String(todayCount);
          }
          if (Array.isArray(data.recentCheckins) && checkinsTbody) {
            checkinsTbody.innerHTML = "";
            if (data.recentCheckins.length === 0) {
              checkinsTbody.innerHTML =
                '<tr id="rgst-empty-row"><td colspan="6" class="px-4 py-6 text-center text-sm text-[#B6A5C9]">No participants checked in by your station yet.</td></tr>';
            } else {
              for (var i = data.recentCheckins.length - 1; i >= 0; i--) {
                prependCheckinRow(data.recentCheckins[i]);
              }
            }
          }
        })
        .catch(function () {})
        .finally(function () {
          refreshBtn.disabled = false;
        });
    });
  }

  // Logout
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logoutBtn.disabled = true;
      stopCamera();
      fetch("/api/auth/logout", { method: "POST" })
        .catch(function () {})
        .finally(function () {
          window.location.href = "/login";
        });
    });
  }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
