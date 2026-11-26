<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if (preg_match('/^[A-Za-z0-9_\-]{20,200}$/', $token) !== 1) {
    $token = '';
}

$pageTitle = 'Verify Your Email';
$pageDescription = 'Confirm your email address to activate your Purple Festival Gujarat 2027 account.';
$extraHeadHtml = '<meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'verify', 'token' => $token]) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="verify-heading">
    <div class="pfg-auth-card">
      <p class="pfg-auth-eyebrow">Account verification</p>
      <h1 id="verify-heading" tabindex="-1"><?= $token !== '' ? 'Confirm your email address' : 'Verify your email address' ?></h1>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

      <?php if ($token !== ''): ?>
        <p class="pfg-auth-lead">Select the button below to confirm this email address and activate your account. The link can only be used once.</p>
        <button id="verify-confirm" type="button" class="pfg-btn">Confirm my email address</button>
        <div id="verify-done" hidden class="pfg-stack" style="margin-top:1rem">
          <a id="verify-done-link" class="pfg-btn" href="/login?verified=1">Continue to sign in</a>
        </div>
      <?php else: ?>
        <p class="pfg-auth-lead">Open the verification link we emailed you to activate your account. Links work once and expire after 30 minutes. If yours has expired or you cannot find it, request a new one below.</p>
      <?php endif; ?>

      <section id="verify-resend-panel" class="pfg-panel" aria-labelledby="verify-resend-heading"<?= $token !== '' ? ' hidden' : '' ?>>
        <h2 id="verify-resend-heading" style="margin:0 0 .5rem;font-size:1.125rem;">Send a new verification link</h2>
        <form id="verify-form" data-auth-form="verify" novalidate>
          <div class="pfg-field">
            <label for="verify-email">Email address</label>
            <input id="verify-email" name="email" type="email" class="pfg-input" autocomplete="email" autocapitalize="none" spellcheck="false" required aria-describedby="verify-email-error" />
            <p id="verify-email-error" class="pfg-field-error" hidden></p>
          </div>
          <button id="verify-resend" type="submit" class="pfg-btn pfg-btn--ghost">Send new link</button>
        </form>
      </section>

      <div class="pfg-links">
        <a href="/login">Back to sign in</a>
        <a href="/register">Create an account</a>
      </div>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
