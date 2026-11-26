<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

$legacyToken = isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '';
if ($legacyToken !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $legacyToken) === 1) {
    header('Location: /reset-password?token=' . rawurlencode($legacyToken), true, 302);
    exit;
}

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');

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
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow, noarchive" />
  <meta name="referrer" content="no-referrer" />
  <title>Staff Login | Purple Festival Gujarat 2027</title>
  <script>
    (function () {
      try {
        var raw = localStorage.getItem('pfg27_a11y_prefs');
        if (!raw) return;
        var p = JSON.parse(raw);
        if (!p || typeof p !== 'object') return;
        var h = document.documentElement;
        if (p.contrast && p.contrast !== 'default') h.setAttribute('data-contrast', String(p.contrast));
        if (p.textScale) h.setAttribute('data-text-scale', String(p.textScale));
        if (p.spacing === 'wide') h.setAttribute('data-spacing', 'wide');
        if (p.reducedMotion) h.setAttribute('data-reduced-motion', 'true');
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/app.css" />
  <link rel="stylesheet" href="/assets/css/auth-pages.css" />
</head>
<body class="pfg-auth-body">
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <main
    id="main-content"
    tabindex="-1"
    class="pfg-auth pfg-auth--branded pfg-auth--standalone pfg-auth--staff"
    style="background-image: url('/assets/staff-login-bg.jpg');"
    aria-labelledby="staff-heading"
  >
    <div class="pfg-auth-split pfg-auth-split--standalone">
      <div class="pfg-auth-split__card">
        <div class="pfg-auth-card pfg-auth-card--standalone">
          <h1 id="staff-heading" tabindex="-1">Sign In</h1>
          <p class="pfg-auth-lead pfg-auth-lead--center">With Your Credentials</p>

          <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
          <div id="auth-status" class="pfg-alert pfg-alert-info" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Notice:</strong> <span data-text></span></span></div>

          <form id="staff-form" data-auth-form="staff-login" novalidate>
            <!-- Accessible anti-script honeypot (hidden from users and assistive technology) -->
            <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
              <label for="staff-hp-website">Leave blank</label>
              <input id="staff-hp-website" name="websiteHp" type="text" tabindex="-1" autocomplete="off" value="" />
            </div>

            <div class="pfg-field">
              <label for="staff-username" class="sr-only">Username</label>
              <input
                id="staff-username"
                name="username"
                type="text"
                class="pfg-input pfg-input--clean"
                placeholder="User"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                required
                aria-describedby="staff-username-error"
              />
              <p id="staff-username-error" class="pfg-field-error" hidden></p>
            </div>

            <div class="pfg-field">
              <label for="staff-password" class="sr-only">Password</label>
              <div class="pfg-password-wrap">
                <input
                  id="staff-password"
                  name="password"
                  type="password"
                  class="pfg-input pfg-input--clean"
                  placeholder="Password"
                  autocomplete="current-password"
                  required
                  aria-describedby="staff-password-error"
                />
                <button type="button" class="pfg-password-toggle" data-toggle-password="staff-password" aria-label="Show password">Show</button>
              </div>
              <p id="staff-password-error" class="pfg-field-error" hidden></p>
            </div>

            <button id="staff-submit" type="submit" class="pfg-btn pfg-btn--standalone">Login</button>
          </form>

          <div class="pfg-links pfg-links--center">
            <a href="/">Return to Home Page</a>
          </div>
        </div>
      </div>

      <section class="pfg-auth-split__sponsors" aria-label="Festival Organisers and Partners">
        <div class="pfg-auth-sponsors pfg-auth-sponsors--pill">
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

  <script type="application/json" id="auth-page-config"><?= pfgSafeJsonForScript(['page' => 'staff-login', 'next' => substr(trim($nextRaw), 0, 500)]) ?></script>
  <script src="/assets/js/auth-pages.js" defer></script>
</body>
</html>
