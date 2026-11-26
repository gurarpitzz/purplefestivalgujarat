<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

// Legacy password-reset e-mails link to /login?resetToken=... — forward to the dedicated reset page.
$legacyToken = isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '';
if ($legacyToken !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $legacyToken) === 1) {
    header('Location: /reset-password?token=' . rawurlencode($legacyToken), true, 302);
    exit;
}

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');
$next = pfgValidateSafeRedirect($nextRaw, '');

// Staff-only destinations are signed into through the isolated Staff Portal.
if ($next !== '' && preg_match('#^/(admin|rgst|staff)(/|\?|$)#i', $next) === 1) {
    header('Location: /staff/login?next=' . rawurlencode($next), true, 302);
    exit;
}

$banner = null;
if (isset($_GET['verified'])) {
    $banner = 'Your email address is verified and your account is active. Please sign in.';
} elseif (isset($_GET['reset'])) {
    $banner = 'Your password was updated. Please sign in with your new password.';
}

$pageTitle = 'Sign In';
$pageDescription = 'Sign in to your Purple Festival Gujarat 2027 participant account to manage registrations and accessibility requests.';
$extraHeadHtml = '<meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'login', 'next' => substr(trim($nextRaw), 0, 500)]) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

$sponsors = [
    [
        'name' => 'Purple Festival Gujarat 2027',
        'src'  => '/assets/logos/purple-festival.jpg',
    ],
    [
        'name' => 'Brotherhood',
        'src'  => '/assets/logos/brotherhood.jpg',
    ],
    [
        'name' => 'Global Inclusive Education Network (GIEN)',
        'src'  => '/assets/logos/gien.jpg',
    ],
    [
        'name' => 'Praangan',
        'src'  => '/assets/logos/praangan.jpg',
    ],
];

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth pfg-auth--branded" style="background-image: url('/assets/login-bg.webp');" aria-labelledby="login-heading">
    <div class="pfg-auth-split">
      <div class="pfg-auth-split__card">
        <div class="pfg-auth-card">
          <p class="pfg-auth-eyebrow">Participant sign in</p>
          <h1 id="login-heading" tabindex="-1">Sign in</h1>
          <p class="pfg-auth-lead">Access your Purple Festival Gujarat 2027 registrations, accessibility arrangements and updates.</p>

          <?php if ($banner !== null): ?>
            <div class="pfg-alert pfg-alert-success" role="status"><span><strong>Done:</strong> <span><?= htmlspecialchars($banner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></span></div>
          <?php endif; ?>
          <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
          <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

          <form id="login-form" data-auth-form="login" novalidate>
            <div class="pfg-field">
              <label for="login-email">Email address</label>
              <input id="login-email" name="email" type="email" class="pfg-input" autocomplete="username" autocapitalize="none" spellcheck="false" required aria-describedby="login-email-error" />
              <p id="login-email-error" class="pfg-field-error" hidden></p>
            </div>

            <div class="pfg-field">
              <label for="login-password">Password</label>
              <div class="pfg-password-wrap">
                <input id="login-password" name="password" type="password" class="pfg-input" autocomplete="current-password" required aria-describedby="login-password-error" />
                <button type="button" class="pfg-password-toggle" data-toggle-password="login-password" aria-label="Show password">Show</button>
              </div>
              <p id="login-password-error" class="pfg-field-error" hidden></p>
            </div>

            <button id="login-submit" type="submit" class="pfg-btn">Sign in</button>

            <div id="login-resend-panel" class="pfg-panel" hidden>
              <p>Your email address is not verified yet. We can send you a new single-use verification link.</p>
              <button id="login-resend" type="button" class="pfg-btn pfg-btn--ghost">Resend verification email</button>
            </div>
          </form>

          <div class="pfg-links">
            <a href="/forgot-password">Forgot your password?</a>
            <a href="/register">Create an account</a>
          </div>
          <hr class="pfg-divider" />
          <div class="pfg-links" style="margin-top:0">
            <a href="/staff/login">Festival staff? Use the Staff Portal</a>
            <a href="/">Return to home page</a>
          </div>
        </div>
      </div>

      <section class="pfg-auth-split__sponsors" aria-label="Festival Organisers and Partners">
        <div class="pfg-auth-sponsors">
          <div class="pfg-auth-sponsors__head">
            <p class="pfg-auth-sponsors__eyebrow">Organisers &amp; Partners</p>
            <p class="pfg-auth-sponsors__caption">Purple Festival Gujarat 2027 &middot; 21&ndash;23 January 2027 &middot; Vadodara</p>
          </div>
          <ul class="pfg-sponsor-grid">
            <?php foreach ($sponsors as $sponsor): ?>
              <li class="pfg-sponsor-item">
                <img
                  src="<?= htmlspecialchars($sponsor['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($sponsor['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  width="120"
                  height="56"
                  loading="eager"
                  decoding="async"
                />
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
