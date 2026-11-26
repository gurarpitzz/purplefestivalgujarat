<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/database.php')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
}

$fallbackCategories = [
    [
        'id'               => 'ATTENDEE',
        'code'             => 'ATTENDEE',
        'name'             => 'General Attendee / Visitor',
        'description'      => 'Free entry to cultural stages, exhibitions, para-sports & festival zones.',
        'requiresApproval' => false,
        'requiresPayment'  => false,
        'feeAmountInr'     => 0,
    ],
    [
        'id'               => 'DELEGATE',
        'code'             => 'DELEGATE',
        'name'             => 'Conference Delegate',
        'description'      => 'Full access to policy panels, NEP 2020 & UDL symposiums, and delegate kit.',
        'requiresApproval' => false,
        'requiresPayment'  => false,
        'feeAmountInr'     => 0,
    ],
    [
        'id'               => 'VOLUNTEER',
        'code'             => 'VOLUNTEER',
        'name'             => 'Festival Volunteer',
        'description'      => 'Join the accessibility & hospitality volunteer corps (requires approval).',
        'requiresApproval' => true,
        'requiresPayment'  => false,
        'feeAmountInr'     => 0,
    ],
    [
        'id'               => 'SLI',
        'code'             => 'SLI',
        'name'             => 'Sign Language Interpreter (SLI)',
        'description'      => 'Certified Indian Sign Language interpreter supporting festival stages & panels.',
        'requiresApproval' => true,
        'requiresPayment'  => false,
        'feeAmountInr'     => 0,
    ],
];

$categories = [];
$disabilityCategories = [];
$accommodationTypes = [];
$currentUser = null;

try {
    if (function_exists('getCurrentUser')) {
        $u = getCurrentUser();
        if (is_array($u) && !empty($u['id'])) {
            $currentUser = [
                'id'       => (string)$u['id'],
                'fullName' => (string)($u['fullName'] ?? $u['full_name'] ?? ''),
                'email'    => (string)($u['email'] ?? ''),
                'phone'    => isset($u['phone']) ? (string)$u['phone'] : null,
                'country'  => (string)($u['country'] ?? 'India'),
            ];
        }
    }

    if (function_exists('getDb')) {
        $pdo = getDb();

        $stmtEd = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
        $stmtEd->execute([':slug' => defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027']);
        $edition = $stmtEd->fetch(PDO::FETCH_ASSOC);

        if (!$edition) {
            $stmtEd2 = $pdo->query('SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1');
            $edition = $stmtEd2 ? $stmtEd2->fetch(PDO::FETCH_ASSOC) : null;
        }

        if ($edition && !empty($edition['id'])) {
            $stmtCat = $pdo->prepare(
                'SELECT * FROM registration_categories WHERE edition_id = :edition_id AND is_active = 1 ORDER BY display_order ASC'
            );
            $stmtCat->execute([':edition_id' => $edition['id']]);
            $catRows = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
            foreach ($catRows as $row) {
                $categories[] = [
                    'id'               => (string)$row['id'],
                    'code'             => (string)$row['code'],
                    'name'             => (string)$row['name'],
                    'description'      => isset($row['description']) ? (string)$row['description'] : null,
                    'requiresApproval' => !empty($row['requires_approval']),
                    'requiresPayment'  => !empty($row['requires_payment']),
                    'feeAmountInr'     => (int)($row['fee_amount_inr'] ?? 0),
                ];
            }
        }

        $stmtDis = $pdo->query('SELECT * FROM disability_categories ORDER BY display_order ASC');
        $disRows = $stmtDis ? $stmtDis->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($disRows as $row) {
            $disabilityCategories[] = [
                'id'          => (string)$row['id'],
                'code'        => (string)$row['code'],
                'name'        => (string)$row['name'],
                'rpwdGroup'   => (string)($row['rpwd_group'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
            ];
        }

        $stmtAcc = $pdo->query('SELECT * FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC');
        $accRows = $stmtAcc ? $stmtAcc->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($accRows as $row) {
            $accommodationTypes[] = [
                'id'          => (string)$row['id'],
                'code'        => (string)$row['code'],
                'label'       => (string)$row['label'],
                'description' => isset($row['description']) ? (string)$row['description'] : null,
            ];
        }
    }
} catch (Throwable $e) {
    // Graceful fallback to constants if DB is not yet initialized
}

if (empty($categories)) {
    $categories = $fallbackCategories;
}

if (empty($disabilityCategories) && defined('RPWD_21_DISABILITIES')) {
    foreach (RPWD_21_DISABILITIES as $d) {
        $disabilityCategories[] = [
            'id'          => (string)$d['code'],
            'code'        => (string)$d['code'],
            'name'        => (string)$d['name'],
            'rpwdGroup'   => (string)$d['rpwdGroup'],
            'description' => (string)$d['description'],
        ];
    }
}

if (empty($accommodationTypes) && defined('ACCOMMODATION_TYPES_SEED')) {
    foreach (ACCOMMODATION_TYPES_SEED as $a) {
        $accommodationTypes[] = [
            'id'          => (string)$a['code'],
            'code'        => (string)$a['code'],
            'label'       => (string)$a['label'],
            'description' => null,
        ];
    }
}

$initialCategoryParam = null;
if (!empty($_GET['category']) && is_string($_GET['category'])) {
    $initialCategoryParam = trim($_GET['category']);
} elseif (!empty($_GET['pathway']) && is_string($_GET['pathway'])) {
    $initialCategoryParam = trim($_GET['pathway']);
}

$wizardBootData = [
    'initialCategories'    => $categories,
    'disabilityCategories' => $disabilityCategories,
    'accommodationTypes'   => $accommodationTypes,
    'currentUser'          => $currentUser,
    'initialCategoryParam' => $initialCategoryParam,
];

$pageTitle = 'Register & Apply | Purple Festival Gujarat 2027';
$pageDescription = 'Accessible registration for Attendees, Delegates, Volunteers, and Indian Sign Language Interpreters for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).';
$hideSiteFooter = true;

$ssrPathways = [
    [
        'id'    => 'ATTENDEE',
        'title' => 'General Attendee',
        'badge' => 'Instant Registration ID',
    ],
    [
        'id'    => 'ACCESSIBLE',
        'title' => 'Person with Disability / Accessible Registration',
        'badge' => 'Any Role + Full Support',
    ],
    [
        'id'    => 'DELEGATE',
        'title' => 'Delegate (3-Day Conference)',
        'badge' => 'All-Access Track',
    ],
    [
        'id'    => 'VOLUNTEER',
        'title' => 'Festival Volunteer',
        'badge' => 'Coordinator Review',
    ],
    [
        'id'    => 'SLI',
        'title' => 'SLI / Indian Sign Language Professional',
        'badge' => 'Verification & Assignment',
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>
  <main
    id="main-content"
    tabindex="-1"
    class="min-h-[calc(100vh-76px)] bg-white text-[#202D59] focus:outline-none"
  >
    <div id="pfg-registration-wizard-root">
      <!-- Server-Rendered Step 0: Pathway Selector Hub (Hydrated by registration-wizard.js) -->
      <div class="min-h-[calc(100vh-76px)] bg-white text-[#202D59]">
        <div class="mx-auto flex max-w-[560px] flex-col px-5 pb-20 pt-14 sm:pt-20">
          <h1 class="text-[clamp(1.65rem,3.4vw,2.3rem)] font-bold leading-[1.2] tracking-[-0.02em] text-[#202D59]">
            <span class="sr-only">Register &amp; Apply — </span>What type of registration are you interested in for Purple Fest Gujarat?
          </h1>

          <div
            role="radiogroup"
            aria-label="Registration pathway options"
            class="mt-8 flex flex-col gap-3"
          >
            <?php foreach ($ssrPathways as $idx => $p):
                $isSelected = ($idx === 0);
            ?>
              <button
                type="button"
                role="radio"
                aria-checked="<?= $isSelected ? 'true' : 'false' ?>"
                data-pathway-id="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"
                class="touch-target flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors <?= $isSelected ? 'bg-[#CFCFF8] ring-2 ring-[#202D59]' : 'bg-[#DDDDF9] hover:bg-[#D2D2F7]' ?>"
              >
                <div class="min-w-0">
                  <span class="block text-[17px] font-semibold leading-snug text-[#202D59]">
                    <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="mt-0.5 block text-xs font-medium text-[#46537C]">
                    <?= htmlspecialchars($p['badge'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <?php if ($isSelected): ?>
                  <svg class="h-5 w-5 shrink-0 text-[#202D59]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 flex items-center justify-between gap-4">
            <a
              href="/"
              class="inline-flex items-center gap-1.5 text-sm font-medium text-[#46537C] hover:text-[#202D59] hover:underline"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
              </svg>
              Back to Home
            </a>

            <button
              type="button"
              id="btn-hub-next"
              class="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-[#DDDDF9] px-7 py-2.5 text-[16px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>

<?php
$bootJson = pfgSafeJsonForScript($wizardBootData);
$extraFooterHtml = <<<HTML
<script>window.__PFG_WIZARD_BOOT__ = {$bootJson};</script>
<script src="/assets/js/registration-wizard.js"></script>
HTML;

require_once __DIR__ . '/../includes/footer.php';
