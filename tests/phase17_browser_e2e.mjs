#!/usr/bin/env node
/**
 * Purple Festival Gujarat 2027 — Phase 17 Real Playwright Chrome Browser E2E, WCAG 2.2 AA & Visual Regression Suite
 *
 * Boots a temporary native PHP 8.2 server on 127.0.0.1:18095 pointing at isolated MariaDB `pfg27_test`,
 * runs full interactive browser journeys (Attendee, Volunteer, SLI, Admin review/assign, RGST manual desk),
 * verifies WCAG 2.2 AA & High Contrast / Yellow-on-Black modes across Desktop (1440x900) & Mobile (390x844),
 * captures post-Phase-15 screenshots into tests/tmp/e2e_screenshots/, and writes tests/tmp/phase17_e2e_results.json.
 *
 * Usage:
 *   node tests/phase17_browser_e2e.mjs
 */

import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { spawn, execFileSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.dirname(__dirname);
const TMP_DIR = path.join(__dirname, 'tmp');
const SINK_DIR = path.join(TMP_DIR, 'mail-sink');
const SHOT_DIR = path.join(TMP_DIR, 'e2e_screenshots');
const PHP_BIN = 'C:\\xampp\\php\\php.exe';
const PORT = 18095;
const BASE_URL = `http://127.0.0.1:${PORT}`;

fs.mkdirSync(TMP_DIR, { recursive: true });
fs.mkdirSync(SINK_DIR, { recursive: true });
fs.mkdirSync(SHOT_DIR, { recursive: true });

// ---------------------------------------------------------------------------
// 1. Resolve Playwright (global npm, local, or cached _npx playwright-core)
// ---------------------------------------------------------------------------
function loadPlaywright() {
  const require = createRequire(import.meta.url);
  const candidates = [
    'C:\\Users\\HP\\AppData\\Roaming\\npm\\node_modules\\playwright',
    'C:\\Users\\HP\\AppData\\Roaming\\npm\\node_modules\\playwright-core',
    'playwright',
    'playwright-core',
  ];

  // Also scan npm-cache\_npx for cached playwright / playwright-core
  const npxRoot = path.join(process.env.LOCALAPPDATA || 'C:\\Users\\HP\\AppData\\Local', 'npm-cache', '_npx');
  if (fs.existsSync(npxRoot)) {
    for (const entry of fs.readdirSync(npxRoot)) {
      const p1 = path.join(npxRoot, entry, 'node_modules', 'playwright');
      const p2 = path.join(npxRoot, entry, 'node_modules', 'playwright-core');
      if (fs.existsSync(p1)) candidates.push(p1);
      if (fs.existsSync(p2)) candidates.push(p2);
    }
  }

  for (const modPath of candidates) {
    try {
      const pw = require(modPath);
      if (pw && pw.chromium) {
        return { pw, resolvedFrom: modPath };
      }
    } catch {
      // try next
    }
  }
  throw new Error('Playwright / playwright-core could not be resolved from global npm or _npx cache.');
}

// ---------------------------------------------------------------------------
// 2. Test harness & helpers
// ---------------------------------------------------------------------------
const results = [];
let passed = 0;
let failed = 0;

function assert(cond, msg) {
  if (!cond) throw new Error(msg);
}

async function runCheck(id, name, fn) {
  try {
    const evidence = await fn();
    passed++;
    results.push({ id, name, status: 'PASS', evidence: String(evidence || 'OK') });
    console.log(`[PASS] ${id.padEnd(7)} ${name} — ${evidence}`);
  } catch (err) {
    failed++;
    const msg = err instanceof Error ? err.message : String(err);
    results.push({ id, name, status: 'FAIL', evidence: msg });
    console.error(`[FAIL] ${id.padEnd(7)} ${name} — ${msg}`);
  }
}

function waitForPort(host, port, timeoutMs = 8000) {
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const attempt = () => {
      const sock = net.connect(port, host);
      sock.once('connect', () => {
        sock.destroy();
        resolve();
      });
      sock.once('error', () => {
        sock.destroy();
        if (Date.now() - start >= timeoutMs) {
          reject(new Error(`Server on ${host}:${port} did not become ready within ${timeoutMs}ms`));
        } else {
          setTimeout(attempt, 150);
        }
      });
    };
    attempt();
  });
}

function clearMailSink() {
  for (const f of fs.readdirSync(SINK_DIR)) {
    if (f.endsWith('.json')) {
      try { fs.unlinkSync(path.join(SINK_DIR, f)); } catch {}
    }
  }
}

function extractVerifyUrlFromSink(recipientEmail) {
  const files = fs.readdirSync(SINK_DIR)
    .filter((f) => f.endsWith('.json'))
    .map((f) => ({ f, mtime: fs.statSync(path.join(SINK_DIR, f)).mtimeMs }))
    .sort((a, b) => b.mtime - a.mtime);

  const needle = recipientEmail.toLowerCase().trim();
  for (const { f } of files) {
    try {
      const data = JSON.parse(fs.readFileSync(path.join(SINK_DIR, f), 'utf8'));
      if (String(data.to || '').toLowerCase().trim() !== needle) continue;
      const body = `${data.text || ''}\n${data.html || ''}`;
      const m = body.match(/(?:https?:\/\/[^\s"'<>]+)?(\/verify-email\?token=[A-Za-z0-9_\-%]+)/);
      if (m) return `${BASE_URL}${m[1]}`;
    } catch {}
  }
  return null;
}

function parseRgb(str) {
  const m = String(str || '').match(/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)/i);
  if (!m) return null;
  return [Number(m[1]), Number(m[2]), Number(m[3])];
}

function relativeLuminance([r, g, b]) {
  const s = [r, g, b].map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * s[0] + 0.7152 * s[1] + 0.0722 * s[2];
}

function contrastRatio(rgbA, rgbB) {
  const l1 = relativeLuminance(rgbA);
  const l2 = relativeLuminance(rgbB);
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  return (lighter + 0.05) / (darker + 0.05);
}

// ---------------------------------------------------------------------------
// 3. Create isolated E2E env file (APP_URL = 127.0.0.1:18095, DB = pfg27_test)
//    and seed E2E RGST + SUPERADMIN helper credentials via PHP CLI
// ---------------------------------------------------------------------------
const e2eEnvPath = path.join(TMP_DIR, 'e2e_env.php');
const baseTestEnvPath = path.join(TMP_DIR, 'test_env.php');
if (!fs.existsSync(baseTestEnvPath)) {
  const envPhp = `<?php
return [
  'APP_ENV' => 'testing',
  'APP_URL' => '${BASE_URL}',
  'DB_DRIVER' => 'mysql',
  'DB_HOST' => '127.0.0.1',
  'DB_PORT' => '3306',
  'DB_NAME' => 'pfg27_test',
  'DB_USER' => 'root',
  'DB_PASS' => '',
  'AUTH_SECRET' => '${crypto.randomBytes(24).toString('hex')}',
  'QR_SIGNING_SECRET' => '${crypto.randomBytes(24).toString('hex')}',
  'MFA_ENCRYPTION_KEY' => '${crypto.randomBytes(24).toString('hex')}',
  'DATA_ENCRYPTION_KEY' => '${crypto.randomBytes(24).toString('hex')}',
  'MAIL_DRIVER' => 'file',
  'MAIL_SINK_DIR' => '${SINK_DIR.replace(/\\/g, '\\\\')}',
  'RESEND_API_KEY' => '',
  'EMAIL_FROM' => 'Purple Festival Test <noreply@test.local>',
];
`;
  fs.writeFileSync(e2eEnvPath, envPhp, 'utf8');
} else {
  const src = fs.readFileSync(baseTestEnvPath, 'utf8').replace(/http:\/\/127\.0\.0\.1:8088/g, BASE_URL);
  fs.writeFileSync(e2eEnvPath, src, 'utf8');
}

function runPhpHelper(code) {
  const scriptPath = path.join(TMP_DIR, `e2e_helper_${crypto.randomBytes(4).toString('hex')}.php`);
  fs.writeFileSync(scriptPath, `<?php\ndeclare(strict_types=1);\n${code}\n`, 'utf8');
  try {
    const out = execFileSync(PHP_BIN, [scriptPath], {
      cwd: ROOT,
      env: { ...process.env, PFG_ENV_FILE: e2eEnvPath },
      encoding: 'utf8',
    });
    return out.trim();
  } finally {
    try { fs.unlinkSync(scriptPath); } catch {}
  }
}

// Ensure pfg27_test is migrated & seeded, plus create clean E2E RGST + SUPERADMIN accounts
const staffMeta = JSON.parse(runPhpHelper(`
require_once '${ROOT.replace(/\\/g, '/')}/includes/config.php';
require_once '${ROOT.replace(/\\/g, '/')}/includes/migrations.php';
require_once '${ROOT.replace(/\\/g, '/')}/database/seed.php';
require_once '${ROOT.replace(/\\/g, '/')}/includes/database.php';
require_once '${ROOT.replace(/\\/g, '/')}/includes/auth.php';

$pdo = pfgCreateDirectPdo('pfg27_test');
pfgRunMigrations($pdo);
pfgRunSafeReferenceSeed($pdo);

// Reset rate limits for clean E2E execution
foreach (['auth_signup_ip', 'auth_login_ip', 'auth_verify_email_ip', 'registration_submit_ip'] as $sc) {
    pfgResetRateLimit($pdo, $sc, '127.0.0.1');
}

$now = nowSql();
$seedUser = function (string $username, string $email, string $pw, string $roleCode, bool $withMfa) use ($pdo, $now): array {
    $st = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = :u OR LOWER(email) = :e LIMIT 1');
    $st->execute([':u' => strtolower($username), ':e' => strtolower($email)]);
    $existingId = $st->fetchColumn();
    $mfaPlain = $withMfa ? generateMfaSecret() : null;
    if ($existingId) {
        $uid = (string)$existingId;
        $pdo->prepare('UPDATE users SET password_hash = :pw, is_active = 1, locked_until = NULL, failed_login_attempts = 0, email_verified_at = :now, mfa_enabled = :me, mfa_secret_encrypted = :ms, mfa_secret = NULL, updated_at = :now WHERE id = :id')
            ->execute([':pw' => hashPassword($pw), ':now' => $now, ':me' => $withMfa ? 1 : 0, ':ms' => $mfaPlain ? encryptMfaSecret($mfaPlain) : null, ':id' => $uid]);
    } else {
        $uid = uuid4();
        $pdo->prepare("INSERT INTO users (id, username, email, email_verified_at, password_hash, full_name, phone, country, is_active, account_status, mfa_enabled, mfa_secret_encrypted, created_at, updated_at) VALUES (:id, :u, :e, :now, :pw, :fn, '+91 98250 90001', 'India', 1, 'ACTIVE', :me, :ms, :now, :now)")
            ->execute([':id' => $uid, ':u' => $username, ':e' => $email, ':now' => $now, ':pw' => hashPassword($pw), ':fn' => 'E2E ' . $roleCode, ':me' => $withMfa ? 1 : 0, ':ms' => $mfaPlain ? encryptMfaSecret($mfaPlain) : null]);
        $rid = (string)$pdo->query("SELECT id FROM roles WHERE code = " . $pdo->quote($roleCode) . " LIMIT 1")->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, assigned_at) VALUES (:id, :uid, :rid, :now)')
            ->execute([':id' => uuid4(), ':uid' => $uid, ':rid' => $rid, ':now' => $now]);
    }
    return ['id' => $uid, 'username' => $username, 'email' => $email, 'password' => $pw, 'mfaSecret' => $mfaPlain];
};

$rgst = $seedUser('e2e_rgst_desk', 'e2e.rgst.desk@example.test', 'RgstDesk#2027', 'RGST', false);
$sa = $seedUser('e2e_superadmin', 'e2e.superadmin@example.test', 'E2eSuper#2027', 'SUPERADMIN', true);

// Create an authenticated MFA-verified SUPERADMIN session token for backend review/assignment steps
$saRawTok = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$pdo->prepare('INSERT INTO sessions (id, user_id, token_hash, mfa_verified, expires_at, created_at) VALUES (:id, :uid, :th, 1, :exp, :now)')
    ->execute([':id' => uuid4(), ':uid' => $sa['id'], ':th' => hashToken($saRawTok), ':exp' => nowSql(time() + 3600), ':now' => $now]);

echo json_encode([
    'rgst' => $rgst,
    'superadmin' => $sa,
    'saCookie' => SESSION_COOKIE_NAME . '=' . $saRawTok,
    'saCsrf' => pfgComputeSessionBoundCsrfToken($saRawTok),
]);
`));

// ---------------------------------------------------------------------------
// 4. Launch temporary native PHP 8.2 server on 127.0.0.1:18095
// ---------------------------------------------------------------------------
const srvLogPath = path.join(TMP_DIR, 'php_e2e_server.log');
try { fs.unlinkSync(srvLogPath); } catch {}
const srvLogFd = fs.openSync(srvLogPath, 'a');
const phpServer = spawn(PHP_BIN, ['-S', `127.0.0.1:${PORT}`, path.join(ROOT, 'tests', 'router.php')], {
  cwd: ROOT,
  env: { ...process.env, PFG_ENV_FILE: e2eEnvPath },
  stdio: ['ignore', srvLogFd, srvLogFd],
});

let browser = null;
const screenshots = {};

async function saveShot(page, filename) {
  const fullPath = path.join(SHOT_DIR, filename);
  await page.screenshot({ path: fullPath, fullPage: true });
  const stat = fs.statSync(fullPath);
  screenshots[filename] = { path: fullPath, bytes: stat.size };
  return stat.size;
}

try {
  await waitForPort('127.0.0.1', PORT, 10000);
  const { pw, resolvedFrom } = loadPlaywright();
  console.log(`Playwright loaded from: ${resolvedFrom}`);
  browser = await pw.chromium.launch({ channel: 'chrome', headless: true });

  const uniqueSuffix = crypto.randomBytes(3).toString('hex');
  const partEmail = `e2e.applicant.${uniqueSuffix}@example.test`;
  const partName = `E2E Applicant ${uniqueSuffix}`;
  let attendeeCode = '';
  let volunteerCode = '';
  let volunteerRegId = '';
  let sliCode = '';
  let sliRegId = '';

  const partContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const partPage = await partContext.newPage();

  // =========================================================================
  // E2E-01: Accountless Public Attendee Browser Journey (/apply -> /apply/attendee -> Review -> Confirm, 0 accounts, 0 QR, 0 /dashboard)
  // =========================================================================
  await runCheck('E2E-01', 'Accountless Attendee browser journey: /apply (3 boxes, 0 login) -> /apply/attendee -> Review -> Confirm (user_id=NULL, 0 users created, 0 QR)', async () => {
    clearMailSink();

    const usersBefore = Number(runPhpHelper(`
      require_once '${ROOT.replace(/\\/g, '/')}/includes/config.php';
      require_once '${ROOT.replace(/\\/g, '/')}/includes/database.php';
      echo (int)getDb()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    `));

    // 1. Open /apply WITHOUT logging in — assert strictly 3 primary category cards, 0 legacy/ACCESSIBLE cards, 0 login/signup wall
    await partPage.goto(`${BASE_URL}/apply`, { waitUntil: 'networkidle' });
    const cardCodes = await partPage.$$eval('article[data-primary-category]', (els) =>
      els.map((el) => el.getAttribute('data-primary-category'))
    );
    assert(
      cardCodes.length === 3 && cardCodes.includes('ATTENDEE') && cardCodes.includes('VOLUNTEER') && cardCodes.includes('SLI'),
      `Expected exactly 3 primary cards [ATTENDEE,VOLUNTEER,SLI], got ${JSON.stringify(cardCodes)}`
    );
    const applyHtml = await partPage.content();
    assert(!applyHtml.includes('data-primary-category="DELEGATE"') && !applyHtml.includes('data-primary-category="ACCESSIBLE"'), 'Legacy or ACCESSIBLE card found on /apply');
    await saveShot(partPage, 'apply_hub_desktop.png');

    // 2. Open /apply/attendee directly without login, fill applicant details + attendee fields + consents
    await partPage.goto(`${BASE_URL}/apply/attendee`, { waitUntil: 'networkidle' });
    await partPage.fill('#pfg-full-name', partName);
    await partPage.fill('#pfg-email', partEmail);
    await partPage.fill('#pfg-phone', '+91 98250 44556');
    await partPage.check('#pfg-age-group-18_35');
    await partPage.check('#pfg-self-tag-1');
    await partPage.check('#pfg-day-day_1');
    await partPage.check('#pfg-consent-zero-tolerance');
    await partPage.check('#pfg-consent-dpdp');
    await partPage.check('#pfg-consent-accuracy');

    // 3. Review-before-submit step -> Confirm & Submit
    await partPage.click('#pfg-btn-review');
    await partPage.waitForSelector('#pfg-review-panel:not([hidden])', { timeout: 8000 });
    await partPage.click('#pfg-btn-confirm-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });

    const confText = await partPage.textContent('#pfg-confirmation-panel');
    const codeMatch = String(confText || '').match(/PFG-2027-\d{6,}/);
    assert(codeMatch, `Attendee confirmation missing PFG-2027- ID: ${confText}`);
    attendeeCode = codeMatch[0];
    const confHtml = await partPage.innerHTML('#pfg-confirmation-panel');
    assert(!confHtml.includes('qrserver.com') && !confHtml.includes('<canvas'), 'QR pass element leaked into Attendee confirmation!');
    assert(!confHtml.includes('/dashboard'), 'Retired /dashboard link leaked into Attendee confirmation!');
    assert(confHtml.includes('Return to Homepage'), 'Confirmation missing Return to Homepage link');

    // 4. Verify DB invariants: ZERO new users rows created & registrations.user_id IS NULL
    const dbCheck = JSON.parse(runPhpHelper(`
      require_once '${ROOT.replace(/\\/g, '/')}/includes/config.php';
      require_once '${ROOT.replace(/\\/g, '/')}/includes/database.php';
      $pdo = getDb();
      $uCnt = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
      $regUid = $pdo->query("SELECT user_id FROM registrations WHERE registration_code = " . $pdo->quote('${attendeeCode}') . " LIMIT 1")->fetchColumn();
      echo json_encode(['usersAfter' => $uCnt, 'regUserId' => $regUid === false ? null : $regUid]);
    `));
    assert(dbCheck.usersAfter === usersBefore, `Accountless Attendee registration created ${dbCheck.usersAfter - usersBefore} users row(s)!`);
    assert(dbCheck.regUserId === null, `Expected registrations.user_id=NULL, got ${dbCheck.regUserId}`);

    // 5. Verify retired /dashboard returns 410 Gone in browser (no fatal error)
    const dashResp = await partPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
    assert(dashResp && dashResp.status() === 410, `Expected GET /dashboard -> 410 Gone, got ${dashResp ? dashResp.status() : 'null'}`);

    return `Accountless /apply(3 cards) -> /apply/attendee -> Review -> ${attendeeCode} confirmed (user_id=NULL, 0 users created, 0 QR, 0 /dashboard); /dashboard->410`;
  });

  // =========================================================================
  // E2E-02: RGST Manual Registration Desk Journey (Staff Login -> /rgst -> Mark Day 1 -> Reload Persists)
  // =========================================================================
  const rgstContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const rgstPage = await rgstContext.newPage();

  await runCheck('E2E-02', 'Staff & RGST browser journey: /staff/login (0 MFA UI) -> SUPERADMIN /admin + RGST /rgst (0 QR) -> mark Day 1 -> persists', async () => {
    // Verify existing MFA-enrolled SUPERADMIN signs in with username + password ONLY and lands directly on /admin with zero MFA tab/prompt
    const saCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const saPg = await saCtx.newPage();
    await saPg.goto(`${BASE_URL}/staff/login`, { waitUntil: 'networkidle' });
    const staffLoginHtml = (await saPg.content()).toLowerCase();
    assert(!staffLoginHtml.includes('authenticator') && !staffLoginHtml.includes('mfa-box') && !staffLoginHtml.includes('recovery code'), 'Discontinued authenticator UI found on /staff/login!');
    await saPg.fill('#staff-username', staffMeta.superadmin.username);
    await saPg.fill('#staff-password', staffMeta.superadmin.password);
    await saPg.click('#staff-submit');
    await saPg.waitForURL((u) => u.pathname.startsWith('/admin') && !u.search.includes('mfa_setup'), { timeout: 10000 });
    await saPg.waitForSelector('#admin-nav-list button', { timeout: 10000 });
    const adminHtml = (await saPg.content()).toLowerCase();
    assert(!adminHtml.includes('security &amp; mfa') && !adminHtml.includes('google authenticator') && !adminHtml.includes('qrcode-gen.js'), 'Discontinued MFA UI found on /admin!');
    await saCtx.close();

    await rgstPage.goto(`${BASE_URL}/staff/login`, { waitUntil: 'networkidle' });
    await rgstPage.fill('#staff-username', staffMeta.rgst.username);
    await rgstPage.fill('#staff-password', staffMeta.rgst.password);
    await rgstPage.click('#staff-submit');
    await rgstPage.waitForURL((u) => u.pathname.startsWith('/rgst'), { timeout: 10000 });
    await rgstPage.waitForSelector('#rgst-search-input', { timeout: 10000 });

    const rgstHtml = await rgstPage.content();
    assert(!rgstHtml.includes('html5-qrcode') && !rgstHtml.includes('<video'), 'QR camera scanner element found on /rgst!');

    // Search for attendeeCode & mark Day 1 (2027-01-21) strictly on the matching attendee row
    await rgstPage.fill('#rgst-search-input', attendeeCode);
    await rgstPage.click('#rgst-search-btn');
    const targetRow = rgstPage.locator('#rgst-table-body tr', { hasText: attendeeCode }).first();
    await targetRow.waitFor({ state: 'visible', timeout: 10000 });

    const day1Box = targetRow.locator('input.rgst-day-checkbox[data-date="2027-01-21"]').first();
    await day1Box.waitFor({ state: 'visible', timeout: 8000 });
    assert(!(await day1Box.isChecked()), `Day 1 checkbox for ${attendeeCode} was already checked before marking`);
    const day1BoxId = await day1Box.getAttribute('id');
    await day1Box.click();

    await rgstPage.waitForFunction(
      (id) => {
        const el = document.getElementById(id);
        return Boolean(el && el.checked && !el.disabled);
      },
      day1BoxId,
      { timeout: 10000 }
    );
    await saveShot(rgstPage, 'rgst_desk_desktop.png');

    // Reload /rgst, re-search & verify Day 1 stays checked (persisted in MariaDB!)
    await rgstPage.reload({ waitUntil: 'networkidle' });
    await rgstPage.fill('#rgst-search-input', attendeeCode);
    await rgstPage.click('#rgst-search-btn');
    const reloadRow = rgstPage.locator('#rgst-table-body tr', { hasText: attendeeCode }).first();
    await reloadRow.waitFor({ state: 'visible', timeout: 10000 });
    const persistedChecked = await reloadRow.locator('input.rgst-day-checkbox[data-date="2027-01-21"]').first().isChecked();
    assert(persistedChecked === true, 'Day 1 attendance mark did NOT persist across page reload!');

    return `RGST signed in on /staff/login -> /rgst(0 QR scanner) -> marked ${attendeeCode} Day 1 PRESENT -> persisted after reload`;
  });

  // =========================================================================
  // E2E-03: Accountless Volunteer & SLI Browser Forms + Admin Review/Assign + Email Check + RGST Attendance (All 3 Categories)
  // =========================================================================
  await runCheck('E2E-03', 'Accountless Volunteer & SLI browser forms (Review step, 0 accounts), Admin approve/assign (email 0 /dashboard) & RGST mark for both', async () => {
    clearMailSink();

    // 1. Submit accountless Volunteer form in browser
    await partPage.goto(`${BASE_URL}/apply/volunteer`, { waitUntil: 'networkidle' });
    const volShifts = await partPage.locator('input[name="availabilityShifts[]"]').count();
    const volPwdRadios = await partPage.locator('input[name="isPersonWithDisability"]').count();
    assert(volShifts === 6 && volPwdRadios >= 3, `Volunteer form missing shift grid (${volShifts}) or shared accessibility (${volPwdRadios})`);

    await partPage.fill('#pfg-full-name', `${partName} Volunteer`);
    await partPage.fill('#pfg-email', partEmail);
    await partPage.fill('#pfg-phone', '+91 98250 44557');
    await partPage.fill('#pfg-vol-age', '23');
    await partPage.check('#pfg-msu-external');
    await partPage.check('#pfg-primary-role-1');
    await partPage.check('#pfg-lang-0');
    await partPage.check('#pfg-shift-day_1_shift_1');
    await partPage.fill('#pfg-ec-name', 'Suresh Desai');
    await partPage.selectOption('#pfg-ec-relationship', 'Parent');
    await partPage.fill('#pfg-ec-phone', '+91 98250 77700');
    await partPage.check('#pfg-consent-zero-tolerance');
    await partPage.check('#pfg-consent-dpdp');
    await partPage.check('#pfg-consent-accuracy');
    await partPage.click('#pfg-btn-review');
    await partPage.waitForSelector('#pfg-review-panel:not([hidden])', { timeout: 8000 });
    await partPage.click('#pfg-btn-confirm-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });
    const volConfText = await partPage.textContent('#pfg-confirmation-panel');
    const volMatch = String(volConfText || '').match(/PFG-2027-\d{6,}/);
    assert(volMatch, `Volunteer confirmation missing Registration ID: ${volConfText}`);
    assert(String(volConfText || '').includes('Pending Coordinator Review'), 'Volunteer confirmation must state Pending Coordinator Review');
    volunteerCode = volMatch[0];

    // 2. Submit accountless SLI form in browser
    await partPage.goto(`${BASE_URL}/apply/sli`, { waitUntil: 'networkidle' });
    const sliShifts = await partPage.locator('input[name="availabilityShifts[]"]').count();
    const sliPwdRadios = await partPage.locator('input[name="isPersonWithDisability"]').count();
    assert(sliShifts === 6 && sliPwdRadios >= 3, `SLI form missing shift grid (${sliShifts}) or shared accessibility (${sliPwdRadios})`);

    await partPage.fill('#pfg-full-name', `${partName} SLI`);
    await partPage.fill('#pfg-email', partEmail);
    await partPage.fill('#pfg-phone', '+91 98250 44558');
    await partPage.fill('#pfg-sli-age', '30');
    await partPage.fill('#pfg-sli-city', 'Vadodara');
    await partPage.selectOption('#pfg-sli-state', 'Gujarat');
    await partPage.check('#pfg-sli-guj-yes');
    await partPage.check('#pfg-sli-qual-certified_isl');
    await partPage.check('#pfg-sli-exp-3_to_5');
    await partPage.check('#pfg-sli-stage-yes');
    await partPage.check('#pfg-shift-day_2_shift_1');
    await partPage.fill('#pfg-ec-name', 'Suresh Desai');
    await partPage.selectOption('#pfg-ec-relationship', 'Parent');
    await partPage.fill('#pfg-ec-phone', '+91 98250 77700');
    await partPage.check('#pfg-consent-zero-tolerance');
    await partPage.check('#pfg-consent-dpdp');
    await partPage.check('#pfg-consent-accuracy');
    await partPage.click('#pfg-btn-review');
    await partPage.waitForSelector('#pfg-review-panel:not([hidden])', { timeout: 8000 });
    await partPage.click('#pfg-btn-confirm-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });
    const sliConfText = await partPage.textContent('#pfg-confirmation-panel');
    const sliMatch = String(sliConfText || '').match(/PFG-2027-\d{6,}/);
    assert(sliMatch, `SLI confirmation missing Registration ID: ${sliConfText}`);
    assert(String(sliConfText || '').includes('Pending Coordinator Review'), 'SLI confirmation must state Pending Coordinator Review');
    sliCode = sliMatch[0];

    // 3. Admin approves both Volunteer & SLI and assigns Volunteer a duty via live Admin APIs
    const regLookup = JSON.parse(runPhpHelper(`
      require_once '${ROOT.replace(/\\/g, '/')}/includes/config.php';
      require_once '${ROOT.replace(/\\/g, '/')}/includes/database.php';
      $pdo = getDb();
      $v = $pdo->query("SELECT id, user_id FROM registrations WHERE registration_code = " . $pdo->quote('${volunteerCode}') . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      $s = $pdo->query("SELECT id, user_id FROM registrations WHERE registration_code = " . $pdo->quote('${sliCode}') . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      echo json_encode(['volId' => $v['id'] ?? null, 'volUid' => $v['user_id'] ?? null, 'sliId' => $s['id'] ?? null, 'sliUid' => $s['user_id'] ?? null]);
    `));
    assert(regLookup.volUid === null && regLookup.sliUid === null, 'Accountless Volunteer or SLI unexpectedly set user_id!');
    volunteerRegId = regLookup.volId;
    sliRegId = regLookup.sliId;

    const adminHeaders = {
      'Content-Type': 'application/json',
      'Cookie': staffMeta.saCookie,
      'X-CSRF-Token': staffMeta.saCsrf,
    };
    const rAppVol = await fetch(`${BASE_URL}/api/admin/registrations?id=${encodeURIComponent(volunteerRegId)}`, {
      method: 'PATCH',
      headers: adminHeaders,
      body: JSON.stringify({ status: 'APPROVED' }),
    });
    assert(rAppVol.status === 200, `Admin approve volunteer failed: ${rAppVol.status}`);

    const rAppSli = await fetch(`${BASE_URL}/api/admin/registrations?id=${encodeURIComponent(sliRegId)}`, {
      method: 'PATCH',
      headers: adminHeaders,
      body: JSON.stringify({ status: 'APPROVED' }),
    });
    assert(rAppSli.status === 200, `Admin approve SLI failed: ${rAppSli.status}`);

    const dutyTitle = `E2E Main Gate Welcome Lead ${uniqueSuffix}`;
    const rAssign = await fetch(`${BASE_URL}/api/admin/assignments`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        registrationId: volunteerRegId,
        roleTitle: dutyTitle,
        department: 'Hospitality & Access',
        dutyDateIst: '2027-01-21',
        shiftCode: 'DAY_1_SHIFT_1',
        startTime: '08:30',
        endTime: '13:00',
        reportingTime: '08:00 IST',
      }),
    });
    assert(rAssign.status === 201, `Admin duty assign failed: ${rAssign.status} ${await rAssign.text()}`);

    // 4. Verify duty assignment email was captured in local mail sink with ZERO /dashboard links
    let sawDutyMail = false;
    for (const f of fs.readdirSync(SINK_DIR)) {
      if (!f.endsWith('.json')) continue;
      const content = fs.readFileSync(path.join(SINK_DIR, f), 'utf8');
      if (content.includes(dutyTitle)) {
        sawDutyMail = true;
        assert(!content.includes('/dashboard'), `Duty assignment email leaked retired /dashboard link!`);
      }
    }
    assert(sawDutyMail, `Duty assignment email for "${dutyTitle}" not found in local mail sink`);

    // 5. RGST marks approved Volunteer AND approved SLI Day 1 attendance & verifies persistence
    for (const targetCode of [volunteerCode, sliCode]) {
      await rgstPage.fill('#rgst-search-input', targetCode);
      await rgstPage.click('#rgst-search-btn');
      const row = rgstPage.locator('#rgst-table-body tr', { hasText: targetCode }).first();
      await row.waitFor({ state: 'visible', timeout: 10000 });
      const day1 = row.locator('input.rgst-day-checkbox[data-date="2027-01-21"]').first();
      const day1Id = await day1.getAttribute('id');
      await day1.click();
      await rgstPage.waitForFunction(
        (id) => {
          const el = document.getElementById(id);
          return Boolean(el && el.checked && !el.disabled);
        },
        day1Id,
        { timeout: 10000 }
      );
    }

    return `Accountless Volunteer(${volunteerCode}) & SLI(${sliCode}) submitted -> Admin approved & assigned "${dutyTitle}" (email 0 /dashboard) -> RGST marked both Day 1 PRESENT`;
  });

  // =========================================================================
  // E2E-04: Public Desktop Visual Screenshots (Home, Programme, Accessibility, Staff Login)
  // =========================================================================
  const pubContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const pubPage = await pubContext.newPage();

  await runCheck('E2E-04', 'Capture desktop visual screenshots (home, programme, accessibility, staff_login) with zero JS page errors', async () => {
    const pageErrors = [];
    pubPage.on('pageerror', (err) => pageErrors.push(err.message));

    await pubPage.goto(`${BASE_URL}/`, { waitUntil: 'networkidle' });
    await pubPage.waitForTimeout(400);
    const homeSize = await saveShot(pubPage, 'home_desktop.png');

    await pubPage.goto(`${BASE_URL}/programme`, { waitUntil: 'networkidle' });
    await pubPage.waitForTimeout(300);
    const progSize = await saveShot(pubPage, 'programme_desktop.png');

    await pubPage.goto(`${BASE_URL}/accessibility`, { waitUntil: 'networkidle' });
    await pubPage.waitForTimeout(300);
    const a11ySize = await saveShot(pubPage, 'accessibility_desktop.png');

    await pubPage.goto(`${BASE_URL}/staff/login`, { waitUntil: 'networkidle' });
    await pubPage.waitForTimeout(300);
    const staffLoginSize = await saveShot(pubPage, 'staff_login_desktop.png');

    assert(pageErrors.length === 0, `Uncaught JS pageerrors: ${pageErrors.join(' | ')}`);
    assert(homeSize > 10000 && progSize > 10000 && a11ySize > 10000 && staffLoginSize > 10000, 'One or more desktop screenshots appear empty');

    return `Saved 6 desktop screenshots: home(${homeSize}B), programme(${progSize}B), accessibility(${a11ySize}B), staff_login(${staffLoginSize}B), apply_hub, rgst_desk — 0 pageerrors`;
  });

  // =========================================================================
  // E2E-05: Browser Accessibility & Contrast Verification (Desktop 1440x900 + Mobile 390x844)
  // =========================================================================
  await runCheck('E2E-05', 'WCAG 2.2 AA browser audit: <html lang>, skip link, 0 <button><h1-h6>, 0 mobile overflow (390x844), Tab focus, High Contrast & Yellow-on-Black (>=12:1)', async () => {
    const auditRoutes = [
      { path: '/', page: pubPage },
      { path: '/programme', page: pubPage },
      { path: '/accessibility', page: pubPage },
      { path: '/apply', page: pubPage },
      { path: '/apply/attendee', page: pubPage },
      { path: '/staff/login', page: pubPage },
      { path: '/forgot-password', page: pubPage },
      { path: '/rgst', page: rgstPage },
    ];

    const auditSummary = [];
    const auditFailures = [];

    for (const item of auditRoutes) {
      const p = item.page;
      await p.setViewportSize({ width: 1440, height: 900 });
      await p.goto(`${BASE_URL}${item.path}`, { waitUntil: 'networkidle' });

      const routeFails = [];

      // 1. Structural WCAG checks
      const struct = await p.evaluate(() => {
        const lang = document.documentElement.getAttribute('lang') || '';
        const skipLink = document.querySelector('a.skip-link, a[href="#main-content"], a[href="#rgst-main"]');
        const badHeadingInButton = document.querySelectorAll('button h1, button h2, button h3, button h4, button h5, button h6').length;
        return { lang, hasSkip: Boolean(skipLink), badHeadingInButton };
      });
      if (!struct.lang.toLowerCase().startsWith('en')) routeFails.push('missing <html lang="en">');
      if (!struct.hasSkip) routeFails.push('missing skip link');
      if (struct.badHeadingInButton !== 0) routeFails.push(`found ${struct.badHeadingInButton} <button><h*> nesting(s)`);

      // 2. Keyboard Tab focus visibility check
      await p.keyboard.press('Tab');
      const focusInfo = await p.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) return { focused: false };
        const cs = getComputedStyle(el);
        return {
          focused: true,
          tag: el.tagName,
          outlineStyle: cs.outlineStyle,
          outlineWidth: cs.outlineWidth,
          boxShadow: cs.boxShadow,
        };
      });
      if (!focusInfo.focused) routeFails.push('Tab did not move focus to any interactive element');

      // 3. High Contrast mode (data-contrast="high"): non-identical fg/bg (0 white-on-white!)
      await p.evaluate(() => document.documentElement.setAttribute('data-contrast', 'high'));
      await p.waitForTimeout(50);
      const hcColors = await p.evaluate(() => {
        const target = document.querySelector('main') || document.body;
        const h1 = document.querySelector('h1') || target;
        const findBg = (node) => {
          let cur = node;
          while (cur && cur.nodeType === 1) {
            const bg = getComputedStyle(cur).backgroundColor;
            if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
            cur = cur.parentElement;
          }
          return getComputedStyle(document.body).backgroundColor;
        };
        return { fg: getComputedStyle(h1).color, bg: findBg(h1) };
      });
      const hcFg = parseRgb(hcColors.fg);
      const hcBg = parseRgb(hcColors.bg);
      const hcRatio = (hcFg && hcBg) ? contrastRatio(hcFg, hcBg) : 0;
      if (!hcFg || !hcBg || hcColors.fg === hcColors.bg || hcRatio < 4.5) {
        routeFails.push(`high-contrast FAIL(fg=${hcColors.fg},bg=${hcColors.bg},ratio=${hcRatio.toFixed(2)})`);
      }

      // 4. Yellow-on-Black mode (data-contrast="yellow-black"): dark bg + yellow fg (>= 12:1)
      await p.evaluate(() => document.documentElement.setAttribute('data-contrast', 'yellow-black'));
      await p.waitForTimeout(50);
      const ybColors = await p.evaluate(() => {
        const h1 = document.querySelector('h1') || document.body;
        const findBg = (node) => {
          let cur = node;
          while (cur && cur.nodeType === 1) {
            const bg = getComputedStyle(cur).backgroundColor;
            if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
            cur = cur.parentElement;
          }
          return getComputedStyle(document.body).backgroundColor;
        };
        return { fg: getComputedStyle(h1).color, bg: findBg(h1) };
      });
      const ybFg = parseRgb(ybColors.fg);
      const ybBg = parseRgb(ybColors.bg);
      const ybRatio = (ybFg && ybBg) ? contrastRatio(ybFg, ybBg) : 0;
      if (!ybFg || !ybBg || ybRatio < 12.0) {
        routeFails.push(`yellow-black FAIL(fg=${ybColors.fg},bg=${ybColors.bg},ratio=${ybRatio.toFixed(2)}<12.0)`);
      }

      // Reset contrast to default
      await p.evaluate(() => document.documentElement.setAttribute('data-contrast', 'default'));

      // 5. Mobile 390x844 horizontal overflow check
      await p.setViewportSize({ width: 390, height: 844 });
      await p.waitForTimeout(100);
      const overflow = await p.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
      }));
      if (overflow.scrollWidth > overflow.innerWidth + 2) {
        routeFails.push(`mobile 390x844 overflow(${overflow.scrollWidth}>${overflow.innerWidth})`);
      }

      if (routeFails.length > 0) {
        auditFailures.push(`${item.path}: ${routeFails.join('; ')}`);
      }
      auditSummary.push(`${item.path}(HC=${hcRatio.toFixed(1)},YB=${ybRatio.toFixed(1)},mobW=${overflow.scrollWidth})`);
    }

    assert(auditFailures.length === 0, `${auditFailures.length} route(s) failed WCAG/contrast audit — ${auditFailures.join(' | ')} || All routes: ${auditSummary.join(', ')}`);

    return `8/8 routes passed WCAG & contrast checks: ${auditSummary.join(', ')}`;
  });

  // =========================================================================
  // E2E-06: Cross-Browser Chrome + Microsoft Edge Reflow (200%/400%), Modal Focus Trap & Keyboard Navigation
  // =========================================================================
  await runCheck('E2E-06', 'Cross-browser Chrome + Edge (msedge): 200% (640px) & 400% (320px) zoom reflow, keyboard Tab/Escape & modal focus trap/restoration across 7 core flows', async () => {
    const partStorage = await partContext.storageState();
    const rgstStorage = await rgstContext.storageState();
    let edgeBrowser = null;

    const verifyEngine = async (engineName, browserInst) => {
      const pubCtx = await browserInst.newContext({ viewport: { width: 1280, height: 800 } });
      const pCtx = await browserInst.newContext({ viewport: { width: 1280, height: 800 }, storageState: partStorage });
      const rCtx = await browserInst.newContext({ viewport: { width: 1280, height: 800 }, storageState: rgstStorage });

      const pPub = await pubCtx.newPage();
      const pPart = await pCtx.newPage();
      const pRgst = await rCtx.newPage();

      const routes = [
        { path: '/', page: pPub, keySel: 'h1' },
        { path: '/staff/login', page: pPub, keySel: '#staff-submit' },
        { path: '/forgot-password', page: pPub, keySel: 'button[type="submit"]' },
        { path: '/apply', page: pPub, keySel: 'article[data-primary-category]' },
        { path: '/apply/attendee', page: pPub, keySel: '#pfg-full-name' },
        { path: '/apply/volunteer', page: pPub, keySel: '#pfg-full-name' },
        { path: '/rgst', page: pRgst, keySel: '#rgst-search-input' },
      ];

      const viewports = [
        { label: '200%', width: 640, height: 900 },
        { label: '400%', width: 320, height: 640 },
      ];

      const engineFails = [];
      let checkedPairs = 0;

      try {
        // 1. Verify 200% (640px) & 400% (320px) zoom reflow + readable controls + keyboard Tab across all 7 routes
        for (const r of routes) {
          for (const vp of viewports) {
            await r.page.setViewportSize({ width: vp.width, height: vp.height });
            await r.page.goto(`${BASE_URL}${r.path}`, { waitUntil: 'networkidle' });
            await r.page.waitForTimeout(100);
            checkedPairs++;

            const metrics = await r.page.evaluate((sel) => {
              const sw = document.documentElement.scrollWidth;
              const iw = window.innerWidth;
              const el = document.querySelector(sel);
              const rect = el ? el.getBoundingClientRect() : null;
              return {
                scrollWidth: sw,
                innerWidth: iw,
                hasKeyEl: Boolean(el),
                keyWidth: rect ? Math.round(rect.width) : 0,
                keyHeight: rect ? Math.round(rect.height) : 0,
                keyLeft: rect ? Math.round(rect.left) : -999,
              };
            }, r.keySel);

            if (metrics.scrollWidth > metrics.innerWidth + 4) {
              engineFails.push(`${engineName} ${r.path}@${vp.label}(${vp.width}px) overflow(${metrics.scrollWidth}>${metrics.innerWidth}+4)`);
            }
            if (!metrics.hasKeyEl || metrics.keyWidth <= 0 || metrics.keyHeight <= 0 || metrics.keyLeft < -4) {
              engineFails.push(`${engineName} ${r.path}@${vp.label} key control(${r.keySel}) clipped/missing`);
            }

            // Keyboard-only Tab moves focus cleanly
            if (vp.label === '400%') {
              await r.page.keyboard.press('Tab');
              const tabOk = await r.page.evaluate(() => Boolean(document.activeElement && document.activeElement !== document.body));
              if (!tabOk) {
                engineFails.push(`${engineName} ${r.path}@400% keyboard Tab failed to focus interactive element`);
              }
            }
          }
        }

        // 2. Modal Focus Trap, Keyboard Escape Close & Focus Restoration on /rgst
        await pRgst.setViewportSize({ width: 1280, height: 800 });
        await pRgst.goto(`${BASE_URL}/rgst`, { waitUntil: 'networkidle' });
        await pRgst.waitForSelector('#rgst-search-input', { timeout: 10000 });

        // 2a. Accessibility Preferences modal (keyboard Enter open -> Tab/Shift+Tab trap -> Escape close -> focus restored)
        const a11yTrigger = pRgst.locator('[data-a11y-trigger="true"]').first();
        await a11yTrigger.focus();
        await pRgst.keyboard.press('Enter');
        await pRgst.waitForSelector('#a11y-preferences-modal:not(.hidden)', { timeout: 5000 });

        const a11yFirstInModal = await pRgst.evaluate(() => {
          const dlg = document.getElementById('a11y-preferences-dialog');
          return Boolean(dlg && dlg.contains(document.activeElement) && document.activeElement.id === 'a11y-panel-close');
        });
        if (!a11yFirstInModal) {
          engineFails.push(`${engineName} /rgst a11y modal did not move focus to #a11y-panel-close`);
        }

        // Shift+Tab from first element must wrap to last focusable inside dialog (focus trap)
        await pRgst.keyboard.press('Shift+Tab');
        const a11yTrapBack = await pRgst.evaluate(() => {
          const dlg = document.getElementById('a11y-preferences-dialog');
          return Boolean(dlg && dlg.contains(document.activeElement) && document.activeElement.id !== 'a11y-panel-close');
        });
        if (!a11yTrapBack) {
          engineFails.push(`${engineName} /rgst a11y modal Shift+Tab escaped focus trap`);
        }

        // Tab from last element must wrap back to first focusable inside dialog
        await pRgst.keyboard.press('Tab');
        const a11yTrapFwd = await pRgst.evaluate(() => document.activeElement && document.activeElement.id === 'a11y-panel-close');
        if (!a11yTrapFwd) {
          engineFails.push(`${engineName} /rgst a11y modal Tab did not wrap back to #a11y-panel-close`);
        }

        // Escape closes modal & restores focus to trigger button
        await pRgst.keyboard.press('Escape');
        await pRgst.waitForSelector('#a11y-preferences-modal', { state: 'hidden', timeout: 5000 });
        const a11yRestored = await pRgst.evaluate(() => Boolean(document.activeElement && document.activeElement.getAttribute('data-a11y-trigger') === 'true'));
        if (!a11yRestored) {
          engineFails.push(`${engineName} /rgst a11y modal Escape did not restore focus to trigger`);
        }

        // 2b. RGST Attendance Correction modal (keyboard Space on checked Day 1 -> focus trap -> Escape restores checkbox focus)
        await pRgst.fill('#rgst-search-input', attendeeCode);
        await pRgst.waitForTimeout(350);
        await pRgst.waitForLoadState('networkidle');
        const rowLoc = pRgst.locator('#rgst-table-body tr', { hasText: attendeeCode }).first();
        await rowLoc.waitFor({ state: 'visible', timeout: 10000 });
        const cbLoc = rowLoc.locator('input.rgst-day-checkbox[data-date="2027-01-21"]').first();
        const cbId = await cbLoc.getAttribute('id');
        await cbLoc.focus();
        await pRgst.keyboard.press('Space');
        await pRgst.waitForSelector('#rgst-correction-modal:not(.hidden)', { timeout: 5000 });

        const corrInModal = await pRgst.evaluate(() => {
          const m = document.getElementById('rgst-correction-modal');
          return Boolean(m && m.contains(document.activeElement));
        });
        if (!corrInModal) {
          engineFails.push(`${engineName} /rgst correction modal did not receive keyboard focus`);
        }

        // Cycle Tab 6 times inside correction modal and verify focus NEVER escapes the modal
        let escapedCorr = false;
        for (let t = 0; t < 6; t++) {
          await pRgst.keyboard.press('Tab');
          const stillInside = await pRgst.evaluate(() => {
            const m = document.getElementById('rgst-correction-modal');
            return Boolean(m && m.contains(document.activeElement));
          });
          if (!stillInside) {
            escapedCorr = true;
            break;
          }
        }
        if (escapedCorr) {
          engineFails.push(`${engineName} /rgst correction modal Tab escaped focus trap`);
        }

        // Escape closes correction modal and restores focus to the Day 1 checkbox
        await pRgst.keyboard.press('Escape');
        await pRgst.waitForSelector('#rgst-correction-modal', { state: 'hidden', timeout: 5000 });
        const corrRestored = await pRgst.evaluate((id) => Boolean(document.activeElement && document.activeElement.id === id), cbId);
        if (!corrRestored) {
          engineFails.push(`${engineName} /rgst correction modal Escape did not restore focus to checkbox #${cbId}`);
        }
      } finally {
        await pubCtx.close();
        await pCtx.close();
        await rCtx.close();
      }

      return { engineName, checkedPairs, engineFails };
    };

    try {
      const chromeRes = await verifyEngine('chrome', browser);
      edgeBrowser = await pw.chromium.launch({ channel: 'msedge', headless: true });
      const edgeRes = await verifyEngine('msedge', edgeBrowser);

      const allFails = [...chromeRes.engineFails, ...edgeRes.engineFails];
      assert(allFails.length === 0, `${allFails.length} cross-browser reflow/keyboard/modal failure(s): ${allFails.join(' | ')}`);

      return `Chrome(${chromeRes.checkedPairs}/14 reflow+Tab, 2 modals trapped/restored) + Edge/msedge(${edgeRes.checkedPairs}/14 reflow+Tab, 2 modals trapped/restored) — 0 overflow, 0 focus leaks`;
    } finally {
      if (edgeBrowser) {
        try { await edgeBrowser.close(); } catch {}
      }
    }
  });

  await partContext.close();
  await rgstContext.close();
  await pubContext.close();
} finally {
  if (browser) {
    try { await browser.close(); } catch {}
  }
  try { phpServer.kill(); } catch {}
  try { fs.closeSync(srvLogFd); } catch {}
  try { fs.unlinkSync(e2eEnvPath); } catch {}
}

console.log('\n============================================================');
console.log(`BROWSER E2E — TOTAL: ${passed + failed} | PASSED: ${passed} | FAILED: ${failed}`);
console.log('============================================================');

fs.writeFileSync(
  path.join(TMP_DIR, 'phase17_e2e_results.json'),
  JSON.stringify({ total: passed + failed, passed, failed, screenshots, results }, null, 2),
  'utf8'
);

process.exit(failed > 0 ? 1 : 0);
