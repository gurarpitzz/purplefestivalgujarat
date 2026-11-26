<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : (isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '');
if (preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $token) !== 1) {
    $token = '';
}

$pageTitle = 'Reset Staff Password';
$pageDescription = 'Choose a new password for your Purple Festival Gujarat 2027 staff account.';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" /><meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'reset', 'token' => $token]) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="reset-heading">
    <div class="pfg-auth-card">
      <p class="pfg-auth-eyebrow">Account recovery</p>
      <h1 id="reset-heading" tabindex="-1">Choose a new password</h1>
      <p class="pfg-auth-lead">Your new password must be at least 8 characters with at least one letter and one number. After the change, all other signed-in devices are signed out.</p>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

      <form id="reset-form" data-auth-form="reset" novalidate>
        <?php if ($token === ''): ?>
          <div class="pfg-field">
            <label for="reset-token">Reset code from your email</label>
            <input id="reset-token" name="token" type="text" class="pfg-input" autocomplete="off" autocapitalize="none" spellcheck="false" required aria-describedby="reset-token-hint reset-token-error" />
            <p id="reset-token-hint" class="pfg-hint">Opening the link in your email fills this in automatically. You can also <a href="/forgot-password">request a new link</a>.</p>
            <p id="reset-token-error" class="pfg-field-error" hidden></p>
          </div>
        <?php else: ?>
          <input id="reset-token" name="token" type="hidden" value="" />
        <?php endif; ?>

        <div class="pfg-field">
          <label for="reset-password">New password</label>
          <div class="pfg-password-wrap">
            <input id="reset-password" name="newPassword" type="password" class="pfg-input" autocomplete="new-password" required minlength="8" maxlength="128" aria-describedby="reset-password-error" />
            <button type="button" class="pfg-password-toggle" data-toggle-password="reset-password" aria-label="Show password">Show</button>
          </div>
          <p id="reset-password-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="reset-confirm">Confirm new password</label>
          <div class="pfg-password-wrap">
            <input id="reset-confirm" name="confirmPassword" type="password" class="pfg-input" autocomplete="new-password" required maxlength="128" aria-describedby="reset-confirm-error" />
            <button type="button" class="pfg-password-toggle" data-toggle-password="reset-confirm" data-label="confirmation password" aria-label="Show confirmation password">Show</button>
          </div>
          <p id="reset-confirm-error" class="pfg-field-error" hidden></p>
        </div>

        <button id="reset-submit" type="submit" class="pfg-btn">Update password</button>
      </form>

      <div id="reset-done" hidden class="pfg-stack">
        <a class="pfg-btn" href="/staff/login?reset=1">Continue to Staff Sign In</a>
      </div>

      <div class="pfg-links">
        <a href="/forgot-password">Request a new link</a>
        <a href="/staff/login">Back to Staff Sign In</a>
      </div>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
