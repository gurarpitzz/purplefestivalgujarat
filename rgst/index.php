<?php
declare(strict_types=1);

/**
 * Manual Registration Desk Attendance Portal (/rgst -> rgst/index.php)
 * Phase 9 Accessible Manual Checkbox Attendance — ZERO QR Scanner Dependency (CAD-05, CAD-06, D-01..D-04).
 *
 * Security & Privacy Guarantees:
 * - Enforces RGST, ADMIN (with attendance/registration permission), or SUPERADMIN role.
 * - Data-minimized view: NEVER displays disability disclosures, medical notes, emergency contacts, or review notes.
 * - ZERO walk-in creation buttons for RGST (Decision D-04).
 * - ZERO camera scanner library, camera preview, USB barcode input, or legacy pass dependency.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /login?next=/rgst');
    exit;
}

$userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$isSuperadmin = in_array('SUPERADMIN', $userRoles, true);
$isAdminRole = in_array('ADMIN', $userRoles, true);
$isRgstRole = in_array('RGST', $userRoles, true);

$hasAnyDeskPerm = static function (array $u, array $perms): bool {
    foreach ($perms as $p) {
        if (function_exists('hasPermission') && hasPermission($u, $p)) {
            return true;
        }
    }
    return false;
};

$canAccessDesk = $isSuperadmin
    || $isRgstRole
    || ($isAdminRole && $hasAnyDeskPerm($user, [
        PERMISSIONS['ATTENDANCE_READ_FULL'],
        PERMISSIONS['ATTENDANCE_OVERRIDE'],
        PERMISSIONS['REGISTRATIONS_READ_ALL'],
        PERMISSIONS['CHECKINS_CONFIRM'],
    ]));

if (!$canAccessDesk) {
    header('Location: /dashboard');
    exit;
}

$canOverrideAnytime = $isSuperadmin
    || ($isAdminRole && (
        hasPermission($user, PERMISSIONS['ATTENDANCE_OVERRIDE'])
        || hasPermission($user, PERMISSIONS['REGISTRATIONS_WRITE'])
    ));

$pdo = getDb();
$todayIst = getIstDateString();
$operatorId = (string)$user['id'];
$operatorName = (string)($user['fullName'] ?? $user['full_name'] ?? 'Registration Desk Staff');
$isAdmin = $isSuperadmin || $isAdminRole;
$roleLabel = $isSuperadmin ? 'SUPERADMIN' : ($isAdminRole ? 'ADMIN' : 'RGST');

// Load configurable festival dates and attendance scope from system_settings
$festivalDates = ['2027-01-21', '2027-01-22', '2027-01-23'];
$attendanceScope = 'FESTIVAL_DAY';
try {
    $cfgStmt = $pdo->prepare(
        "SELECT `key`, `value` FROM system_settings
         WHERE `key` IN ('attendance.festival_dates', 'festival.dates', 'attendance.scope', 'attendance.mode')"
    );
    $cfgStmt->execute();
    $cfgRows = $cfgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $cfgMap = [];
    foreach ($cfgRows as $r) {
        $cfgMap[(string)$r['key']] = jsonDecode($r['value'] ?? null, $r['value'] ?? null);
    }
    $candDates = null;
    if (isset($cfgMap['attendance.festival_dates'])) {
        $v = $cfgMap['attendance.festival_dates'];
        $candDates = is_array($v) && isset($v['dates']) && is_array($v['dates']) ? $v['dates'] : (is_array($v) ? $v : null);
    } elseif (isset($cfgMap['festival.dates']) && is_array($cfgMap['festival.dates'])) {
        $candDates = isset($cfgMap['festival.dates']['dates']) && is_array($cfgMap['festival.dates']['dates']) ? $cfgMap['festival.dates']['dates'] : $cfgMap['festival.dates'];
    } elseif (isset($cfgMap['attendance.mode']) && is_array($cfgMap['attendance.mode']) && isset($cfgMap['attendance.mode']['festivalDates']) && is_array($cfgMap['attendance.mode']['festivalDates'])) {
        $candDates = $cfgMap['attendance.mode']['festivalDates'];
    }
    if (is_array($candDates)) {
        $cleanDates = [];
        foreach ($candDates as $d) {
            if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($d)) === 1) {
                $cd = trim($d);
                if (!in_array($cd, $cleanDates, true)) {
                    $cleanDates[] = $cd;
                }
            }
        }
        if (count($cleanDates) > 0) {
            $festivalDates = $cleanDates;
        }
    }
    $candScope = null;
    if (isset($cfgMap['attendance.scope'])) {
        $sv = $cfgMap['attendance.scope'];
        $candScope = is_array($sv) ? (string)($sv['scope'] ?? $sv['defaultScope'] ?? '') : (string)$sv;
    } elseif (isset($cfgMap['attendance.mode']) && is_array($cfgMap['attendance.mode']) && !empty($cfgMap['attendance.mode']['defaultScope'])) {
        $candScope = (string)$cfgMap['attendance.mode']['defaultScope'];
    }
    if (is_string($candScope) && preg_match('/^[A-Z0-9_-]{2,64}$/', strtoupper(trim($candScope))) === 1) {
        $attendanceScope = strtoupper(trim($candScope));
    }
} catch (Throwable $e) {
    // Use defaults
}

$festivalDays = [];
foreach ($festivalDates as $idx => $dateStr) {
    $shortDate = $dateStr;
    try {
        $dt = new DateTimeImmutable($dateStr, new DateTimeZone('Asia/Kolkata'));
        $shortDate = $dt->format('d M');
    } catch (Throwable $e) {
    }
    $dayNum = $idx + 1;
    $festivalDays[] = [
        'date'      => $dateStr,
        'dayNumber' => $dayNum,
        'label'     => 'Day ' . $dayNum,
        'shortDate' => $shortDate,
        'display'   => 'Day ' . $dayNum . ' (' . $shortDate . ')',
    ];
}

// Fetch active check-in stations (optional venue context for attendance_records)
$stations = [];
try {
    $stStmt = $pdo->query(
        'SELECT id, name, gate_code
         FROM checkin_stations
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $stations = $stStmt ? ($stStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
    $stations = [];
}
if (count($stations) === 0) {
    $stations = [
        [
            'id'        => '',
            'name'      => 'Main Registration Desk — Vadodara',
            'gate_code' => 'MAIN-DESK',
        ],
    ];
}

$bootstrapData = [
    'csrfToken'             => getCsrfToken(),
    'todayIst'              => $todayIst,
    'enforceEventDayWindow' => pfgIsAttendanceEventDayWindowEnforced($pdo),
    'attendanceScope'       => $attendanceScope,
    'festivalDates'         => $festivalDates,
    'days'                  => $festivalDays,
    'operator'              => [
        'id'                 => $operatorId,
        'fullName'           => $operatorName,
        'role'               => $roleLabel,
        'canOverrideAnytime' => $canOverrideAnytime,
        'undoWindowMinutes'  => 15,
    ],
];

$pageTitle = 'Registration Desk — Purple Festival Gujarat 2027';
$pageDescription = 'Accessible manual attendance desk for Purple Festival Gujarat 2027.';
$hideSiteHeader = true;
$hideSiteFooter = true;
$extraHeadHtml = <<<'HTML'
<style>
  .rgst-shell {
    min-height: 100vh;
    background-color: #17151C;
    color: #F9F7FC;
  }
  .rgst-card {
    background-color: #201C27;
    border: 1px solid rgba(146, 115, 181, 0.35);
    border-radius: 1rem;
  }
  .rgst-input,
  .rgst-select,
  .rgst-textarea {
    background-color: #141219;
    color: #F9F7FC;
    border: 2px solid rgba(146, 115, 181, 0.45);
    border-radius: 0.75rem;
    min-height: 44px;
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    width: 100%;
  }
  .rgst-input::placeholder,
  .rgst-textarea::placeholder {
    color: #9E93B5;
  }
  .rgst-input:focus-visible,
  .rgst-select:focus-visible,
  .rgst-textarea:focus-visible,
  .rgst-btn:focus-visible,
  .rgst-day-checkbox:focus-visible {
    outline: 3px solid #D5B77B;
    outline-offset: 2px;
  }
  .rgst-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }
  @media (min-width: 768px) {
    .rgst-kpi-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (min-width: 1200px) {
    .rgst-kpi-grid {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
  }
  .rgst-filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  @media (min-width: 768px) {
    .rgst-filter-grid {
      grid-template-columns: 2fr 1fr 1fr 1fr auto;
      align-items: end;
    }
  }
  /* Large >= 44x44px accessible checkbox hit area */
  .rgst-check-wrap {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    min-height: 48px;
    padding: 0.25rem 0.5rem;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    border: 1px solid rgba(146, 115, 181, 0.28);
    background-color: #17151C;
  }
  .rgst-check-wrap:hover:not(.rgst-check-disabled) {
    border-color: #D5B77B;
    background-color: #272133;
  }
  .rgst-check-wrap.rgst-check-present {
    border-color: rgba(52, 211, 153, 0.65);
    background-color: rgba(6, 78, 59, 0.45);
  }
  .rgst-check-wrap.rgst-check-busy {
    opacity: 0.7;
    cursor: progress;
  }
  .rgst-check-wrap.rgst-check-disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .rgst-day-checkbox {
    width: 24px;
    height: 24px;
    accent-color: #10B981;
    cursor: pointer;
    margin: 0;
  }
  .rgst-day-checkbox:disabled {
    cursor: not-allowed;
  }
  .rgst-check-meta {
    margin-top: 0.2rem;
    font-size: 11px;
    line-height: 1.2;
    color: #C6B6D8;
    text-align: center;
    max-width: 110px;
  }
  .rgst-check-present .rgst-check-meta {
    color: #A7F3D0;
  }
  .rgst-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.18rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    white-space: nowrap;
  }
  .rgst-badge-eligible {
    background-color: rgba(16, 185, 129, 0.2);
    color: #A7F3D0;
    border: 1px solid rgba(52, 211, 153, 0.55);
  }
  .rgst-badge-pending {
    background-color: rgba(245, 158, 11, 0.2);
    color: #FDE68A;
    border: 1px solid rgba(251, 191, 36, 0.5);
  }
  .rgst-badge-blocked {
    background-color: rgba(239, 68, 68, 0.2);
    color: #FECACA;
    border: 1px solid rgba(248, 113, 113, 0.5);
  }
  .rgst-badge-cat {
    background-color: rgba(146, 115, 181, 0.28);
    color: #F9F7FC;
    border: 1px solid rgba(146, 115, 181, 0.55);
  }
  .rgst-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1050;
    background-color: rgba(10, 8, 14, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .rgst-modal-backdrop.hidden {
    display: none;
  }
  .rgst-modal-panel {
    width: 100%;
    max-width: 32rem;
    background-color: #201C27;
    border: 2px solid #D5B77B;
    border-radius: 1rem;
    padding: 1.5rem;
    color: #F9F7FC;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
  }
  /* High-contrast accessibility overrides */
  html[data-contrast="high"] .rgst-shell,
  html[data-contrast="high"] .rgst-card,
  html[data-contrast="high"] .rgst-modal-panel {
    background-color: #000000 !important;
    color: #FFFFFF !important;
    border-color: #FFFFFF !important;
  }
  html[data-contrast="yellow-black"] .rgst-shell,
  html[data-contrast="yellow-black"] .rgst-card,
  html[data-contrast="yellow-black"] .rgst-modal-panel {
    background-color: #000000 !important;
    color: #FFFF00 !important;
    border-color: #FFFF00 !important;
  }
</style>
HTML;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="rgst-shell" data-surface="dark">
  <!-- Skip Link -->
  <a
    href="#rgst-main"
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-[#D5B77B] focus:px-4 focus:py-2 focus:font-semibold focus:text-[#17151C]"
  >
    Skip to registration desk workspace
  </a>

  <!-- Distraction-Free Registration Desk Header -->
  <header class="sticky top-0 z-30 border-b border-[#9273B5]/30 bg-[#17151C]/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
      <div class="flex items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#59436D] text-[#D5B77B] ring-1 ring-[#D5B77B]/40"
          aria-hidden="true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <polyline points="16 11 18 13 22 9" />
          </svg>
        </div>
        <div>
          <h1 class="text-base font-bold tracking-tight text-[#F9F7FC] sm:text-lg">
            Purple Festival Gujarat 2027 — Registration Desk
          </h1>
          <p class="text-xs text-[#C6B6D8]">
            Manual 3-Day Attendance Verification &bull; Data-Minimized Desk View
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Active Desk / Station Selector -->
        <div class="flex items-center gap-2 rounded-lg border border-[#9273B5]/40 bg-[#221E2A] px-3 py-1.5">
          <label for="rgst-station-select" class="text-[11px] font-semibold uppercase tracking-wider text-[#D5B77B]">
            Desk:
          </label>
          <select
            id="rgst-station-select"
            aria-label="Active Registration Desk Station"
            class="bg-transparent text-xs font-semibold text-[#F9F7FC] focus:outline-none"
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

        <!-- Live IST Clock -->
        <div
          class="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs font-mono text-[#E8DFF2]"
          aria-label="Current Indian Standard Time"
        >
          <span id="rgst-ist-clock"><?= htmlspecialchars($todayIst . ' IST', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- Operator Badge -->
        <div class="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs">
          <span class="font-semibold text-[#F9F7FC]"><?= htmlspecialchars($operatorName, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="rounded bg-[#59436D] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#F9F7FC]">
            <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <!-- Accessibility Preferences Trigger -->
        <button
          type="button"
          data-a11y-trigger="true"
          aria-expanded="false"
          aria-controls="a11y-preferences-modal"
          aria-label="Open Accessibility Preferences"
          class="rgst-btn inline-flex min-h-[40px] items-center justify-center rounded-lg border border-[#9273B5]/40 bg-[#221E2A] px-3 py-1.5 text-xs font-semibold text-[#E8DFF2] hover:border-[#D5B77B]"
        >
          Accessibility
        </button>

        <?php if ($isAdmin): ?>
          <a
            href="/admin"
            class="rgst-btn inline-flex min-h-[40px] items-center rounded-lg border border-[#D5B77B]/50 px-3 py-1.5 text-xs font-semibold text-[#D5B77B] transition hover:bg-[#D5B77B] hover:text-[#17151C]"
          >
            Admin Portal
          </a>
        <?php endif; ?>

        <button
          type="button"
          id="rgst-logout-btn"
          class="rgst-btn inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-red-400/40 bg-red-950/40 px-3 py-1.5 text-xs font-semibold text-red-200 transition hover:bg-red-900/60"
        >
          Logout
        </button>
      </div>
    </div>
  </header>

  <main id="rgst-main" tabindex="-1" class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
    <!-- Live KPI Metrics Section -->
    <section aria-label="Live festival attendance summary" class="space-y-3">
      <div class="rgst-kpi-grid">
        <div class="rgst-card p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-[#C6B6D8]">Eligible</p>
          <p id="kpi-eligible" class="mt-1 text-2xl font-extrabold text-[#F9F7FC] tabular-nums">—</p>
          <p id="kpi-total-reg" class="mt-0.5 text-xs text-[#B6A5C9]">of — registered</p>
        </div>

        <div class="rgst-card p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-[#C6B6D8]">Unique Present</p>
          <p id="kpi-unique-present" class="mt-1 text-2xl font-extrabold text-emerald-300 tabular-nums">—</p>
          <p id="kpi-total-events" class="mt-0.5 text-xs text-[#B6A5C9]">— day check-ins</p>
        </div>

        <?php foreach ($festivalDays as $dayInfo): ?>
          <div class="rgst-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#D5B77B]">
              <?= htmlspecialchars($dayInfo['display'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p
              id="kpi-day-<?= htmlspecialchars($dayInfo['date'], ENT_QUOTES, 'UTF-8') ?>"
              data-kpi-day="<?= htmlspecialchars($dayInfo['date'], ENT_QUOTES, 'UTF-8') ?>"
              class="mt-1 text-2xl font-extrabold text-[#F9F7FC] tabular-nums"
            >—</p>
            <p class="mt-0.5 text-xs text-[#B6A5C9]">marked present</p>
          </div>
        <?php endforeach; ?>

        <div class="rgst-card p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-[#C6B6D8]">By Category</p>
          <dl class="mt-1 space-y-0.5 text-xs">
            <div class="flex items-center justify-between gap-2">
              <dt class="text-[#C6B6D8]">Attendee</dt>
              <dd id="kpi-cat-ATTENDEE" class="font-bold text-[#F9F7FC] tabular-nums">—</dd>
            </div>
            <div class="flex items-center justify-between gap-2">
              <dt class="text-[#C6B6D8]">Volunteer</dt>
              <dd id="kpi-cat-VOLUNTEER" class="font-bold text-[#F9F7FC] tabular-nums">—</dd>
            </div>
            <div class="flex items-center justify-between gap-2">
              <dt class="text-[#C6B6D8]">SLI</dt>
              <dd id="kpi-cat-SLI" class="font-bold text-[#F9F7FC] tabular-nums">—</dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <!-- Search & Filter Controls -->
    <section aria-label="Search and filter participants" class="rgst-card p-5">
      <form id="rgst-filter-form" class="rgst-filter-grid" role="search">
        <div>
          <label for="rgst-search-input" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#D5B77B]">
            Search Registration ID / Name / Phone
          </label>
          <input
            id="rgst-search-input"
            type="search"
            class="rgst-input"
            placeholder="e.g. PFG-2027-000100, participant name, or last 4 phone digits"
            autocomplete="off"
          />
        </div>

        <div>
          <label for="rgst-category-filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#C6B6D8]">
            Category
          </label>
          <select id="rgst-category-filter" class="rgst-select">
            <option value="ALL">All Categories</option>
            <option value="ATTENDEE">Attendee</option>
            <option value="VOLUNTEER">Volunteer</option>
            <option value="SLI">SLI Interpreter</option>
          </select>
        </div>

        <div>
          <label for="rgst-eligibility-filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#C6B6D8]">
            Eligibility
          </label>
          <select id="rgst-eligibility-filter" class="rgst-select">
            <option value="ALL">All Registrations</option>
            <option value="ELIGIBLE">Eligible Only (Confirmed / Approved)</option>
            <option value="INELIGIBLE">Not Eligible (Pending / Rejected)</option>
          </select>
        </div>

        <div>
          <label for="rgst-day-filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#C6B6D8]">
            Present on Day
          </label>
          <select id="rgst-day-filter" class="rgst-select">
            <option value="">Any Day</option>
            <?php foreach ($festivalDays as $dayInfo): ?>
              <option value="<?= htmlspecialchars($dayInfo['date'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($dayInfo['display'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="submit"
            id="rgst-search-btn"
            class="rgst-btn inline-flex min-h-[44px] items-center justify-center rounded-xl bg-[#D5B77B] px-4 py-2 text-sm font-bold text-[#17151C] hover:bg-[#E2C78E]"
          >
            Search
          </button>
          <button
            type="button"
            id="rgst-clear-btn"
            class="rgst-btn inline-flex min-h-[44px] items-center justify-center rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-3.5 py-2 text-sm font-semibold text-[#E8DFF2] hover:bg-[#2A2435]"
          >
            Reset
          </button>
          <button
            type="button"
            id="rgst-refresh-btn"
            class="rgst-btn inline-flex min-h-[44px] items-center justify-center rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-3.5 py-2 text-sm font-semibold text-[#E8DFF2] hover:bg-[#2A2435]"
          >
            Refresh
          </button>
        </div>
      </form>

      <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-[#B6A5C9]">
        <p id="rgst-table-meta" aria-live="polite">Loading registered participants...</p>
        <div class="flex items-center gap-2">
          <label for="rgst-perpage-select" class="text-xs text-[#C6B6D8]">Rows per page:</label>
          <select id="rgst-perpage-select" class="rgst-select" style="width: auto; min-height: 36px; padding: 0.25rem 0.6rem;">
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
    </section>

    <!-- Accessible Status & Error Feedback Regions -->
    <div
      id="rgst-status-banner"
      role="status"
      aria-live="polite"
      class="hidden rounded-xl border border-emerald-400/50 bg-emerald-950/50 px-4 py-3 text-sm font-medium text-emerald-100"
    ></div>

    <div
      id="rgst-error-banner"
      role="alert"
      aria-live="assertive"
      class="hidden flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-red-500/70 bg-red-950/60 px-4 py-3 text-sm text-red-100"
    >
      <span id="rgst-error-text"></span>
      <div class="flex items-center gap-2">
        <button
          type="button"
          id="rgst-error-retry-btn"
          class="rgst-btn hidden min-h-[40px] rounded-lg bg-red-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-red-500"
        >
          Retry
        </button>
        <button
          type="button"
          id="rgst-error-dismiss-btn"
          class="rgst-btn min-h-[40px] rounded-lg border border-red-400/50 px-3 py-1.5 text-xs font-semibold text-red-100"
        >
          Dismiss
        </button>
      </div>
    </div>

    <!-- Accessible Manual Attendance Table -->
    <section aria-label="Participant attendance list" class="rgst-card p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-base font-bold text-[#F9F7FC]">Participant Attendance Roster</h2>
          <p class="text-xs text-[#B6A5C9]">
            Check a day box to mark arrival. Only Confirmed Attendees and Approved Volunteers/SLIs are eligible.
            <?php if (!$canOverrideAnytime): ?>
              Desk staff may undo their own mark within 15 minutes with a reason; older records require Admin override.
            <?php else: ?>
              Admin/Superadmin override enabled for audited corrections anytime.
            <?php endif; ?>
          </p>
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border border-[#9273B5]/25">
        <table class="min-w-full divide-y divide-[#9273B5]/20 text-left text-sm" aria-describedby="rgst-table-meta">
          <caption class="sr-only">
            Registered participants with manual Day 1, Day 2, and Day 3 attendance checkboxes
          </caption>
          <thead class="bg-[#17151C] text-xs uppercase tracking-wider text-[#C6B6D8]">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold">Registration ID</th>
              <th scope="col" class="px-4 py-3 font-semibold">Participant</th>
              <th scope="col" class="px-4 py-3 font-semibold">Category</th>
              <th scope="col" class="px-4 py-3 font-semibold">Eligibility</th>
              <?php foreach ($festivalDays as $dayInfo): ?>
                <th scope="col" class="px-3 py-3 text-center font-semibold">
                  <?= htmlspecialchars($dayInfo['display'], ENT_QUOTES, 'UTF-8') ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody id="rgst-table-body" class="divide-y divide-[#9273B5]/15 bg-[#1C1823]">
            <tr>
              <td colspan="<?= 4 + count($festivalDays) ?>" class="px-4 py-8 text-center text-sm text-[#B6A5C9]">
                Loading participants...
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Server-Side Pagination Controls -->
      <nav
        aria-label="Participant roster pagination"
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
      >
        <p id="rgst-pagination-summary" class="text-xs text-[#C6B6D8]">Page 1 of 1</p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            id="rgst-prev-page"
            class="rgst-btn inline-flex min-h-[44px] items-center rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-4 py-2 text-xs font-semibold text-[#E8DFF2] disabled:cursor-not-allowed disabled:opacity-40"
            disabled
          >
            Previous
          </button>
          <button
            type="button"
            id="rgst-next-page"
            class="rgst-btn inline-flex min-h-[44px] items-center rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-4 py-2 text-xs font-semibold text-[#E8DFF2] disabled:cursor-not-allowed disabled:opacity-40"
            disabled
          >
            Next
          </button>
        </div>
      </nav>
    </section>
  </main>
</div>

<!-- Accessible Attendance Correction / Undo Modal (Decision D-03) -->
<div
  id="rgst-correction-modal"
  class="rgst-modal-backdrop hidden"
  role="dialog"
  aria-modal="true"
  aria-labelledby="rgst-corr-title"
  aria-describedby="rgst-corr-desc"
>
  <div class="rgst-modal-panel">
    <div class="flex items-start justify-between gap-3 border-b border-[#9273B5]/30 pb-3">
      <div>
        <h2 id="rgst-corr-title" class="text-lg font-bold text-[#F9F7FC]">
          Correct / Revoke Attendance
        </h2>
        <p id="rgst-corr-desc" class="mt-0.5 text-xs text-[#C6B6D8]">
          All attendance changes are permanently audited. Records are never deleted.
        </p>
      </div>
      <button
        type="button"
        id="rgst-corr-close-btn"
        aria-label="Close correction dialog"
        class="rgst-btn inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-[#9273B5]/40 bg-[#17151C] text-sm font-bold text-[#F9F7FC]"
      >
        &times;
      </button>
    </div>

    <div id="rgst-corr-details" class="mt-4 rounded-xl bg-[#17151C] p-3.5 text-xs space-y-1"></div>

    <div
      id="rgst-corr-blocked-notice"
      role="alert"
      class="hidden mt-4 rounded-xl border border-amber-400/60 bg-amber-950/50 p-3.5 text-xs text-amber-100"
    ></div>

    <form id="rgst-corr-form" class="mt-4 space-y-4">
      <div id="rgst-corr-reason-group">
        <label for="rgst-corr-reason" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#D5B77B]">
          Reason for Correction (required, min 5 characters)
        </label>
        <textarea
          id="rgst-corr-reason"
          rows="3"
          minlength="5"
          maxlength="1000"
          required
          class="rgst-textarea"
          placeholder="e.g. Marked wrong participant with similar name during check-in."
        ></textarea>
      </div>

      <div
        id="rgst-corr-error"
        role="alert"
        class="hidden rounded-xl border border-red-500/70 bg-red-950/60 p-3 text-xs text-red-100"
      ></div>

      <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2">
        <button
          type="button"
          id="rgst-corr-cancel-btn"
          class="rgst-btn min-h-[44px] rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-4 py-2 text-sm font-semibold text-[#E8DFF2]"
        >
          Cancel
        </button>
        <button
          type="submit"
          id="rgst-corr-submit-btn"
          class="rgst-btn min-h-[44px] rounded-xl bg-red-600 px-5 py-2 text-sm font-bold text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Confirm Revoke Attendance
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.__PFG_RGST_BOOTSTRAP__ = <?= pfgSafeJsonForScript($bootstrapData) ?>;
</script>
<script src="/assets/js/rgst-desk.js"></script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
