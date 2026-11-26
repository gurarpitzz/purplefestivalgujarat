<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Forgot Password';
$pageDescription = 'Request a single-use link to reset your Purple Festival Gujarat 2027 account password.';
$extraHeadHtml = '<meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'forgot']) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="forgot-heading">
    <div class="pfg-auth-card">
      <p class="pfg-auth-eyebrow">Account recovery</p>
      <h1 id="forgot-heading" tabindex="-1">Forgot your password?</h1>
      <p class="pfg-auth-lead">Enter the email address on your account and we will send a single-use link to choose a new password. For your privacy we show the same confirmation whether or not an account exists.</p>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

      <form id="forgot-form" data-auth-form="forgot" novalidate>
        <div class="pfg-field">
          <label for="forgot-email">Email address</label>
          <input id="forgot-email" name="email" type="email" class="pfg-input" autocomplete="email" autocapitalize="none" spellcheck="false" required aria-describedby="forgot-email-error" />
          <p id="forgot-email-error" class="pfg-field-error" hidden></p>
        </div>
        <button id="forgot-submit" type="submit" class="pfg-btn">Send reset link</button>
      </form>

      <div class="pfg-links">
        <a href="/login">Back to sign in</a>
        <a href="/register">Create an account</a>
      </div>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
