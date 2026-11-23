<?php
declare(strict_types=1);

/**
 * Accessible Printable / Save-as-PDF Digital Entry Pass View
 * Path: /dashboard/pass-print.php?registrationId=...
 * Port of src/lib/pass-pdf.ts & src/app/api/qr/[registrationId]/pdf/route.ts
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/qr-service.php';

$user = getCurrentUser();
if (!$user) {
    $next = '/dashboard/pass-print.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: /login?next=' . rawurlencode($next));
    exit;
}

$registrationId = trim((string)($_GET['registrationId'] ?? $_GET['id'] ?? $_GET['code'] ?? ''));
if ($registrationId === '') {
    header('Location: /dashboard');
    exit;
}

$pdo = getDb();
$userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$isAdmin = in_array('ADMIN', $userRoles, true) || in_array('SUPERADMIN', $userRoles, true);

$regStmt = $pdo->prepare(
    'SELECT r.id, r.registration_code, r.user_id, r.category_id, r.category_code,
            r.status, r.payment_status, r.full_name, r.attending_days,
            c.name AS category_name
     FROM registrations r
     LEFT JOIN registration_categories c ON c.id = r.category_id
     WHERE (r.id = :id OR r.registration_code = :code) AND r.deleted_at IS NULL
     LIMIT 1'
);
$regStmt->execute([
    ':id'   => $registrationId,
    ':code' => $registrationId,
]);
$reg = $regStmt->fetch(PDO::FETCH_ASSOC);

if (!$reg) {
    http_response_code(404);
    $errorMsg = 'Registration record not found.';
} else {
    $isOwner = ((string)$reg['user_id'] === (string)$user['id']);
    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        $errorMsg = 'You do not have permission to view or print this entry pass.';
        $reg = null;
    }
}

$credential = null;
$roleAssignment = null;
$qrPayload = '';
$categoryName = '';
$attendingDaysLabel = 'All Festival Days (21–23 Jan 2027)';

if ($reg) {
    $categoryName = !empty($reg['category_name']) ? (string)$reg['category_name'] : (string)$reg['category_code'];
    $attendingDays = jsonDecode($reg['attending_days'] ?? '[]', []);
    if (is_array($attendingDays) && count($attendingDays) > 0) {
        $attendingDaysLabel = implode(', ', $attendingDays);
    }

    $credStmt = $pdo->prepare(
        "SELECT * FROM qr_credentials
         WHERE registration_id = :reg_id AND status = 'ACTIVE'
         ORDER BY issued_at DESC
         LIMIT 1"
    );
    $credStmt->execute([':reg_id' => $reg['id']]);
    $credential = $credStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($credential) {
        $qrPayload = reconstructQrTokenForCredential($credential);
    }

    if ($reg['category_code'] === 'VOLUNTEER') {
        $volStmt = $pdo->prepare(
            'SELECT assigned_department, assigned_venue, assigned_shift, reporting_time
             FROM volunteer_applications
             WHERE registration_id = :reg_id
             LIMIT 1'
        );
        $volStmt->execute([':reg_id' => $reg['id']]);
        $vol = $volStmt->fetch(PDO::FETCH_ASSOC);
        if ($vol && (!empty($vol['assigned_department']) || !empty($vol['assigned_venue']) || !empty($vol['assigned_shift']))) {
            $parts = array_filter([
                $vol['assigned_department'] ?? null,
                $vol['assigned_venue'] ?? null,
                $vol['assigned_shift'] ?? null,
                !empty($vol['reporting_time']) ? 'Report: ' . $vol['reporting_time'] : null,
            ]);
            $roleAssignment = implode(' • ', $parts);
        }
    } elseif ($reg['category_code'] === 'SLI') {
        $sliStmt = $pdo->prepare(
            'SELECT assigned_venue, duty_times
             FROM sli_applications
             WHERE registration_id = :reg_id
             LIMIT 1'
        );
        $sliStmt->execute([':reg_id' => $reg['id']]);
        $sli = $sliStmt->fetch(PDO::FETCH_ASSOC);
        if ($sli && (!empty($sli['assigned_venue']) || !empty($sli['duty_times']))) {
            $parts = array_filter([
                $sli['assigned_venue'] ?? null,
                $sli['duty_times'] ?? null,
            ]);
            $roleAssignment = implode(' • ', $parts);
        }
    }
}

$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars(FESTIVAL_NAME, ENT_QUOTES, 'UTF-8') ?> — Digital Entry Pass<?= $reg ? ' (' . htmlspecialchars((string)$reg['registration_code'], ENT_QUOTES, 'UTF-8') . ')' : '' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&display=swap" />
  <link rel="stylesheet" href="/assets/css/app.css" />
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm;
    }
    body {
      font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
      background-color: #F6F1F8;
      color: #17151C;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .pass-card {
      max-width: 480px;
      margin: 0 auto;
      background-color: #17151C;
      color: #F9F7FC;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(23, 21, 28, 0.25);
      border: 2px solid #59436D;
    }
    body[data-bw-mode="true"] .pass-card {
      background-color: #FFFFFF !important;
      color: #000000 !important;
      border: 3px solid #000000 !important;
      box-shadow: none !important;
    }
    body[data-bw-mode="true"] .pass-header {
      background-color: #000000 !important;
      color: #FFFFFF !important;
    }
    body[data-bw-mode="true"] .pass-inner-card {
      background-color: #FFFFFF !important;
      color: #000000 !important;
      border: 2px solid #000000 !important;
    }
    body[data-bw-mode="true"] .pass-muted {
      color: #222222 !important;
    }
    body[data-bw-mode="true"] .pass-accent {
      color: #000000 !important;
    }
    @media print {
      body {
        background-color: #FFFFFF !important;
      }
      .no-print {
        display: none !important;
      }
      .pass-card {
        box-shadow: none !important;
        margin: 0 auto !important;
      }
    }
  </style>
</head>
<body class="min-h-screen py-8 px-4" data-bw-mode="false">
  <!-- Top Action Bar (Hidden when printing) -->
  <div class="no-print max-w-[480px] mx-auto mb-6 flex flex-wrap items-center justify-between gap-3">
    <a
      href="/dashboard"
      class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-[#59436D]/30 bg-white px-4 py-2 text-sm font-semibold text-[#211B29] hover:bg-[#E8DFF0]"
    >
      &larr; Back to Dashboard
    </a>

    <?php if ($reg && $credential): ?>
    <div class="flex flex-wrap items-center gap-2">
      <button
        type="button"
        id="toggle-bw-btn"
        class="inline-flex min-h-[44px] items-center gap-1.5 rounded-xl border border-[#59436D]/40 bg-white px-3.5 py-2 text-xs font-bold text-[#211B29] hover:bg-[#E8DFF0]"
      >
        B&amp;W Print Mode
      </button>
      <button
        type="button"
        onclick="window.print()"
        class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#59436D] px-5 py-2.5 text-sm font-bold text-[#F9F7FC] hover:bg-[#432F56]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
          <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6" />
          <rect x="6" y="14" width="12" height="8" rx="1" />
        </svg>
        Print / Save as PDF
      </button>
    </div>
    <?php endif; ?>
  </div>

  <main id="main-content" aria-label="Accessible Digital Festival Entry Pass">
    <?php if (!$reg || !$credential): ?>
      <div class="max-w-[480px] mx-auto rounded-2xl bg-white border border-[#59436D]/25 p-8 text-center space-y-4">
        <h1 class="text-xl font-bold text-[#17151C]">
          <?= htmlspecialchars($errorMsg ?? 'No Active QR Entry Pass Available', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="text-sm text-[#4B4257]">
          Passes are automatically issued once your registration is confirmed or approved by festival coordinators.
        </p>
        <a
          href="/dashboard"
          class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-[#59436D] px-6 py-2.5 text-sm font-bold text-white"
        >
          Return to Dashboard
        </a>
      </div>
    <?php else: ?>
      <article class="pass-card" aria-label="Digital Entry Pass for <?= htmlspecialchars((string)$reg['full_name'], ENT_QUOTES, 'UTF-8') ?>">
        <!-- Top Dusty Purple Accent Header (#59436D) -->
        <header class="pass-header bg-[#59436D] px-7 pt-6 pb-5 border-b-4 border-[#D5B77B]">
          <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#D5B77B]">
            OFFICIAL FESTIVAL ENTRY CREDENTIAL
          </p>
          <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#F9F7FC]">
            PURPLE FESTIVAL GUJARAT 2027
          </h1>
          <p class="mt-0.5 text-xs text-[#E8DFF0]">
            Celebrating Accessibility, Inclusion, Dignity &amp; Diversity
          </p>
          <p class="mt-2 text-xs font-bold text-[#D5B77B]">
            <?= htmlspecialchars(FESTIVAL_DATES_DISPLAY . '  |  ' . FESTIVAL_LOCATION_DISPLAY, ENT_QUOTES, 'UTF-8') ?>
          </p>
        </header>

        <!-- Body -->
        <div class="p-6 space-y-5">
          <!-- Participant Details Box -->
          <div class="pass-inner-card rounded-xl bg-[#25212B] border border-[#9273B5]/50 p-5 space-y-4">
            <div>
              <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                PARTICIPANT NAME
              </p>
              <p class="mt-0.5 text-xl font-extrabold text-current">
                <?= htmlspecialchars((string)$reg['full_name'], ENT_QUOTES, 'UTF-8') ?>
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                  REGISTRATION CATEGORY
                </p>
                <p class="pass-accent mt-0.5 text-sm font-extrabold uppercase text-[#D5B77B]">
                  <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
              <div>
                <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                  PASS STATUS
                </p>
                <p class="mt-0.5 text-sm font-extrabold uppercase text-emerald-400">
                  <?= htmlspecialchars((string)$credential['status'] . ' • ' . (string)$reg['status'], ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-[#9273B5]/25">
              <div>
                <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                  CREDENTIAL ID
                </p>
                <p class="mt-0.5 font-mono text-sm font-bold text-current">
                  <?= htmlspecialchars((string)$credential['credential_code'], ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
              <div>
                <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                  REGISTRATION ID
                </p>
                <p class="mt-0.5 font-mono text-sm font-bold text-current">
                  <?= htmlspecialchars((string)$reg['registration_code'], ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
            </div>

            <div class="pt-2 border-t border-[#9273B5]/25">
              <p class="pass-muted text-[11px] font-bold uppercase tracking-wider text-[#B6A1C8]">
                ATTENDING DAYS &amp; VENUE
              </p>
              <p class="mt-0.5 text-xs font-semibold text-current">
                <?= htmlspecialchars($attendingDaysLabel, ENT_QUOTES, 'UTF-8') ?> • Sama Indoor Stadium &amp; MSU Baroda, Vadodara
              </p>
            </div>
          </div>

          <!-- High-Contrast Scannable QR Code Container -->
          <div class="flex flex-col items-center justify-center">
            <div
              id="pass-qr-container"
              data-qr-payload="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
              data-qr-size="220"
              data-qr-label="Digital QR entry pass for <?= htmlspecialchars((string)$reg['full_name'], ENT_QUOTES, 'UTF-8') ?>, Credential ID <?= htmlspecialchars((string)$credential['credential_code'], ENT_QUOTES, 'UTF-8') ?>"
              class="rounded-2xl bg-[#F9F7FC] p-4 border-2 border-[#D5B77B] shadow-md flex items-center justify-center"
            ></div>
            <p class="mt-2 font-mono text-xs font-bold tracking-wider text-[#D5B77B] pass-accent">
              PASS ID: <?= htmlspecialchars((string)$credential['credential_code'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>

          <?php if ($roleAssignment): ?>
          <div class="pass-inner-card rounded-xl bg-[#25212B] border border-[#D5B77B]/40 p-3.5 text-xs">
            <p class="font-bold uppercase tracking-wider text-[#D5B77B] pass-accent">
              Assigned Duty / Schedule
            </p>
            <p class="mt-1 font-semibold text-current">
              <?= htmlspecialchars($roleAssignment, ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
          <?php endif; ?>

          <!-- Accessible Instructions & Privacy Notice -->
          <footer class="border-t border-[#9273B5]/25 pt-4 space-y-1.5 text-xs text-[#C6B6D8] pass-muted">
            <p>
              <strong>Reception Check-In Instructions:</strong> Present this QR pass or quote your Credential ID (<code class="font-mono font-bold"><?= htmlspecialchars((string)$credential['credential_code'], ENT_QUOTES, 'UTF-8') ?></code>) at the Registration Desk (RGST).
            </p>
            <p>
              <strong>Privacy &amp; Dignity Guarantee:</strong> No personal medical, disability, or accommodation information is embedded in this QR code.
            </p>
            <p class="pt-1 text-[11px] text-[#9273B5] pass-muted">
              Official Digital Credential — www.purplefestivalgujarat.com
            </p>
          </footer>
        </div>
      </article>
    <?php endif; ?>
  </main>

  <script src="/assets/js/qrcode-gen.js"></script>
  <script>
    (function () {
      var bwBtn = document.getElementById("toggle-bw-btn");
      if (bwBtn) {
        bwBtn.addEventListener("click", function () {
          var current = document.body.getAttribute("data-bw-mode") === "true";
          document.body.setAttribute("data-bw-mode", current ? "false" : "true");
          bwBtn.textContent = current ? "B&W Print Mode" : "Default Dark Mode";
        });
      }
      <?php if ($autoPrint && $reg && $credential): ?>
      window.addEventListener("load", function () {
        setTimeout(function () {
          window.print();
        }, 300);
      });
      <?php endif; ?>
    })();
  </script>
</body>
</html>
