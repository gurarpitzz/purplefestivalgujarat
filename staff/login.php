<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

$legacyToken = isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '';
if ($legacyToken !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $legacyToken) === 1) {
    header('Location: /reset-password?token=' . rawurlencode($legacyToken), true, 302);
    exit;
}

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');
$next = pfgValidateSafeRedirect($nextRaw, '');

$pageTitle = 'Staff Portal Sign In';
$pageDescription = 'Authorized staff sign-in for Purple Festival Gujarat 2027 (Superadmin, Admin and Registration Desk).';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" /><meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'staff-login', 'next' => substr(trim($nextRaw), 0, 500)]) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once dirname(__DIR__) . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="staff-heading">
    <div class="pfg-auth-card">
      <span class="pfg-staff-badge">Staff Portal</span>
      <h1 id="staff-heading" tabindex="-1">Staff sign in</h1>
      <p class="pfg-auth-lead">For authorized Superadmin, Admin and Registration Desk staff only. Sign in with your staff username and password.</p>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-info" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Notice:</strong> <span data-text></span></span></div>

      <form id="staff-form" data-auth-form="staff-login" novalidate>
        <div class="pfg-field">
          <label for="staff-username">Username</label>
          <input id="staff-username" name="username" type="text" class="pfg-input" autocomplete="username" autocapitalize="none" spellcheck="false" required aria-describedby="staff-username-error" />
          <p id="staff-username-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="staff-password">Password</label>
          <div class="pfg-password-wrap">
            <input id="staff-password" name="password" type="password" class="pfg-input" autocomplete="current-password" required aria-describedby="staff-password-error" />
            <button type="button" class="pfg-password-toggle" data-toggle-password="staff-password" aria-label="Show password">Show</button>
          </div>
          <p id="staff-password-error" class="pfg-field-error" hidden></p>
        </div>

        <button id="staff-submit" type="submit" class="pfg-btn">Sign in</button>
      </form>

      <div class="pfg-links">
        <a href="/forgot-password">Forgot your password?</a>
        <a href="/login">Participant sign in</a>
      </div>
    </div>
  </main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
