<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Staff Password Recovery';
$pageDescription = 'Request a single-use link to reset your authorized staff password for Purple Festival Gujarat 2027.';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" /><meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'forgot']) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="forgot-heading">
    <div class="pfg-auth-card">
      <p class="pfg-auth-eyebrow">Staff account recovery</p>
      <h1 id="forgot-heading" tabindex="-1">Reset staff password</h1>
      <p class="pfg-auth-lead">For authorized festival staff (Superadmin, Admin, and Registration Desk). Enter your staff email address and we will send a single-use password reset link if an eligible staff account exists.</p>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

      <form id="forgot-form" data-auth-form="forgot" novalidate>
        <div class="pfg-field">
          <label for="forgot-email">Staff email address</label>
          <input id="forgot-email" name="email" type="email" class="pfg-input" autocomplete="email" autocapitalize="none" spellcheck="false" required aria-describedby="forgot-email-error" />
          <p id="forgot-email-error" class="pfg-field-error" hidden></p>
        </div>
        <button id="forgot-submit" type="submit" class="pfg-btn">Send reset link</button>
      </form>

      <div class="pfg-links">
        <a href="/staff/login">Back to Staff Sign In</a>
        <a href="/apply">Public Registration</a>
      </div>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
