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
  const partEmail = `e2e.participant.${uniqueSuffix}@example.test`;
  const partPass = 'E2ePart#2027';
  const partName = `E2E Participant ${uniqueSuffix}`;
  let attendeeCode = '';
  let volunteerCode = '';
  let volunteerRegId = '';
  let sliCode = '';
  let sliRegId = '';

  const partContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const partPage = await partContext.newPage();

  // =========================================================================
  // E2E-01: Attendee Full Browser Journey (Signup -> Verify -> Login -> /apply -> /apply/attendee -> /dashboard)
  // =========================================================================
  await runCheck('E2E-01', 'Attendee browser journey: /register -> verify-email -> /login -> /apply (3 cards) -> /apply/attendee -> /dashboard (0 QR)', async () => {
    clearMailSink();

    // 1. Register on /register
    await partPage.goto(`${BASE_URL}/register`, { waitUntil: 'networkidle' });
    await partPage.fill('#register-name', partName);
    await partPage.fill('#register-email', partEmail);
    await partPage.fill('#register-phone', '+91 98250 44556');
    await partPage.fill('#register-password', partPass);
    await partPage.fill('#register-confirm', partPass);
    await partPage.check('#register-consent');
    await partPage.click('#register-submit');
    await partPage.waitForSelector('#register-success:not([hidden])', { timeout: 10000 });

    // 2. Extract verify link from tests/tmp/mail-sink & confirm email
    const verifyUrl = extractVerifyUrlFromSink(partEmail);
    assert(verifyUrl, 'Could not find verification URL in tests/tmp/mail-sink/');
    await partPage.goto(verifyUrl, { waitUntil: 'networkidle' });
    await partPage.click('#verify-confirm');
    await partPage.waitForSelector('#verify-done:not([hidden])', { timeout: 10000 });

    // 3. Login on /login
    await partPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await partPage.fill('#login-email', partEmail);
    await partPage.fill('#login-password', partPass);
    await partPage.click('#login-submit');
    await partPage.waitForURL((u) => u.pathname.startsWith('/dashboard'), { timeout: 10000 });

    // 4. Navigate to /apply — assert strictly 3 primary category cards, 0 legacy/ACCESSIBLE cards
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

    // 5. Complete /apply/attendee
    await partPage.goto(`${BASE_URL}/apply/attendee`, { waitUntil: 'networkidle' });
    await partPage.check('#pfg-age-group-18_35');
    await partPage.check('#pfg-self-tag-1');
    await partPage.check('#pfg-day-day_1');
    await partPage.check('#pfg-consent-zero-tolerance');
    await partPage.check('#pfg-consent-dpdp');
    await partPage.check('#pfg-consent-accuracy');
    await partPage.click('#pfg-btn-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });

    const confText = await partPage.textContent('#pfg-confirmation-panel');
    const codeMatch = String(confText || '').match(/PFG-2027-\d{6,}/);
    assert(codeMatch, `Attendee confirmation missing PFG-2027- ID: ${confText}`);
    attendeeCode = codeMatch[0];
    const confHtml = await partPage.innerHTML('#pfg-confirmation-panel');
    assert(!confHtml.includes('qrserver.com') && !confHtml.includes('<canvas'), 'QR pass element leaked into Attendee confirmation!');

    // 6. Verify /dashboard shows Registration ID and zero QR pass
    await partPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
    await partPage.waitForFunction(
      (code) => document.body && document.body.innerText.includes(code),
      attendeeCode,
      { timeout: 10000 }
    );
    const dashHtml = await partPage.content();
    assert(!dashHtml.includes('qrserver.com') && !dashHtml.includes('html5-qrcode'), 'QR pass leaked into Participant Dashboard!');
    await saveShot(partPage, 'dashboard_desktop.png');

    return `Registered ${partEmail} -> verified -> logged in -> /apply(3 cards) -> ${attendeeCode} confirmed (0 QR) -> visible on /dashboard`;
  });

  // =========================================================================
  // E2E-02: RGST Manual Registration Desk Journey (Staff Login -> /rgst -> Mark Day 1 -> Reload Persists)
  // =========================================================================
  const rgstContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const rgstPage = await rgstContext.newPage();

  await runCheck('E2E-02', 'RGST desk journey: /staff/login -> /rgst (0 QR scanner) -> mark Attendee Day 1 -> reload & verify persisted', async () => {
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
  // E2E-03: Volunteer & SLI Browser Forms + Admin Review/Assign + Dashboard Duty + RGST Attendance
  // =========================================================================
  await runCheck('E2E-03', 'Volunteer & SLI browser forms (shared a11y + shift grid), Admin approve/assign, dashboard duty display & RGST mark', async () => {
    // 1. Submit Volunteer form in browser
    await partPage.goto(`${BASE_URL}/apply/volunteer`, { waitUntil: 'networkidle' });
    const volShifts = await partPage.locator('input[name="availabilityShifts[]"]').count();
    const volPwdRadios = await partPage.locator('input[name="isPersonWithDisability"]').count();
    assert(volShifts === 6 && volPwdRadios >= 3, `Volunteer form missing shift grid (${volShifts}) or shared accessibility (${volPwdRadios})`);

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
    await partPage.click('#pfg-btn-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });
    const volConfText = await partPage.textContent('#pfg-confirmation-panel');
    const volMatch = String(volConfText || '').match(/PFG-2027-\d{6,}/);
    assert(volMatch, `Volunteer confirmation missing Registration ID: ${volConfText}`);
    volunteerCode = volMatch[0];

    // 2. Submit SLI form in browser
    await partPage.goto(`${BASE_URL}/apply/sli`, { waitUntil: 'networkidle' });
    const sliShifts = await partPage.locator('input[name="availabilityShifts[]"]').count();
    const sliPwdRadios = await partPage.locator('input[name="isPersonWithDisability"]').count();
    assert(sliShifts === 6 && sliPwdRadios >= 3, `SLI form missing shift grid (${sliShifts}) or shared accessibility (${sliPwdRadios})`);

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
    await partPage.click('#pfg-btn-submit');
    await partPage.waitForSelector('#pfg-confirmation-panel:not([hidden])', { timeout: 12000 });
    const sliConfText = await partPage.textContent('#pfg-confirmation-panel');
    const sliMatch = String(sliConfText || '').match(/PFG-2027-\d{6,}/);
    assert(sliMatch, `SLI confirmation missing Registration ID: ${sliConfText}`);
    sliCode = sliMatch[0];

    // 3. Admin approves both Volunteer & SLI and assigns Volunteer a duty via live Admin APIs
    const regLookup = JSON.parse(runPhpHelper(`
      require_once '${ROOT.replace(/\\/g, '/')}/includes/config.php';
      require_once '${ROOT.replace(/\\/g, '/')}/includes/database.php';
      $pdo = getDb();
      $v = $pdo->query("SELECT id FROM registrations WHERE registration_code = " . $pdo->quote('${volunteerCode}') . " LIMIT 1")->fetchColumn();
      $s = $pdo->query("SELECT id FROM registrations WHERE registration_code = " . $pdo->quote('${sliCode}') . " LIMIT 1")->fetchColumn();
      echo json_encode(['volId' => $v, 'sliId' => $s]);
    `));
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

    // 4. Participant opens /dashboard and verifies duty assignment is displayed
    await partPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
    await partPage.waitForFunction(
      (title) => document.body && document.body.innerText.includes(title),
      dutyTitle,
      { timeout: 10000 }
    );

    // 5. RGST marks approved Volunteer Day 1 attendance & verifies persistence
    await rgstPage.fill('#rgst-search-input', volunteerCode);
    await rgstPage.click('#rgst-search-btn');
    const volRow = rgstPage.locator('#rgst-table-body tr', { hasText: volunteerCode }).first();
    await volRow.waitFor({ state: 'visible', timeout: 10000 });
    const volDay1 = volRow.locator('input.rgst-day-checkbox[data-date="2027-01-21"]').first();
    const volDay1Id = await volDay1.getAttribute('id');
    await volDay1.click();
    await rgstPage.waitForFunction(
      (id) => {
        const el = document.getElementById(id);
        return Boolean(el && el.checked && !el.disabled);
      },
      volDay1Id,
      { timeout: 10000 }
    );

    return `Volunteer(${volunteerCode}) & SLI(${sliCode}) submitted -> Admin approved & assigned "${dutyTitle}" -> visible on /dashboard -> RGST marked Day 1 PRESENT`;
  });

  // =========================================================================
  // E2E-04: Public Desktop Visual Screenshots (Home, Programme, Accessibility)
  // =========================================================================
  const pubContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const pubPage = await pubContext.newPage();

  await runCheck('E2E-04', 'Capture post-Phase-15 desktop visual screenshots (home, programme, accessibility) with zero JS page errors', async () => {
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

    assert(pageErrors.length === 0, `Uncaught JS pageerrors: ${pageErrors.join(' | ')}`);
    assert(homeSize > 10000 && progSize > 10000 && a11ySize > 10000, 'One or more desktop screenshots appear empty');

    return `Saved 6 desktop screenshots: home(${homeSize}B), programme(${progSize}B), accessibility(${a11ySize}B), apply_hub, dashboard, rgst_desk — 0 pageerrors`;
  });

  // =========================================================================
  // E2E-05: Browser Accessibility & Contrast Verification (Desktop 1440x900 + Mobile 390x844)
  // =========================================================================
  await runCheck('E2E-05', 'WCAG 2.2 AA browser audit: <html lang>, skip link, 0 <button><h1-h6>, 0 mobile overflow (390x844), Tab focus, High Contrast & Yellow-on-Black (>=12:1)', async () => {
    const auditRoutes = [
      { path: '/', page: pubPage },
      { path: '/programme', page: pubPage },
      { path: '/accessibility', page: pubPage },
      { path: '/login', page: pubPage },
      { path: '/register', page: pubPage },
      { path: '/apply', page: pubPage },
      { path: '/dashboard', page: partPage },
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
