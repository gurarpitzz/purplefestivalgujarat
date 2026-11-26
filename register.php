<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Create Your Account';
$pageDescription = 'Create a Purple Festival Gujarat 2027 participant account to register as an Attendee, Volunteer, or Sign Language Interpreter.';
$extraHeadHtml = '<meta name="referrer" content="no-referrer" /><link rel="stylesheet" href="/assets/css/auth-pages.css" />';
$extraFooterHtml = '<script type="application/json" id="auth-page-config">' . pfgSafeJsonForScript(['page' => 'register']) . '</script>'
    . '<script src="/assets/js/auth-pages.js" defer></script>';

require_once __DIR__ . '/includes/header.php';
?>
  <main id="main-content" tabindex="-1" class="pfg-auth" aria-labelledby="register-heading">
    <div class="pfg-auth-card pfg-auth-card--wide">
      <p class="pfg-auth-eyebrow">Participant account</p>
      <h1 id="register-heading" tabindex="-1">Create your account</h1>
      <p class="pfg-auth-lead">One account lets you apply as an Attendee, Volunteer or Sign Language Interpreter and track your registration. We only ask for basic contact details now &mdash; no medical or disability information.</p>

      <div id="auth-alert" class="pfg-alert pfg-alert-error" role="alert" tabindex="-1" hidden><span><strong>Error:</strong> <span data-text></span></span></div>
      <div id="auth-status" class="pfg-alert pfg-alert-success" role="status" aria-live="polite" tabindex="-1" hidden><span><strong>Done:</strong> <span data-text></span></span></div>

      <form id="register-form" data-auth-form="register" novalidate>
        <div class="pfg-field">
          <label for="register-name">Full name <span class="pfg-req" aria-hidden="true">*</span></label>
          <input id="register-name" name="fullName" type="text" class="pfg-input" autocomplete="name" required maxlength="200" aria-describedby="register-name-error" />
          <p id="register-name-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="register-email">Email address <span class="pfg-req" aria-hidden="true">*</span></label>
          <input id="register-email" name="email" type="email" class="pfg-input" autocomplete="email" autocapitalize="none" spellcheck="false" required maxlength="255" aria-describedby="register-email-hint register-email-error" />
          <p id="register-email-hint" class="pfg-hint">We will send a verification link to this address.</p>
          <p id="register-email-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="register-phone">Phone number <span class="pfg-req" aria-hidden="true">*</span></label>
          <input id="register-phone" name="phone" type="tel" class="pfg-input" autocomplete="tel" required maxlength="20" aria-describedby="register-phone-hint register-phone-error" />
          <p id="register-phone-hint" class="pfg-hint">Include the country code if outside India, e.g. +91 98765 43210.</p>
          <p id="register-phone-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="register-password">Password <span class="pfg-req" aria-hidden="true">*</span></label>
          <div class="pfg-password-wrap">
            <input id="register-password" name="password" type="password" class="pfg-input" autocomplete="new-password" required minlength="8" maxlength="128" aria-describedby="register-password-hint register-password-error" />
            <button type="button" class="pfg-password-toggle" data-toggle-password="register-password" aria-label="Show password">Show</button>
          </div>
          <p id="register-password-hint" class="pfg-hint">At least 8 characters, with at least one letter and one number.</p>
          <p id="register-password-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <label for="register-confirm">Confirm password <span class="pfg-req" aria-hidden="true">*</span></label>
          <div class="pfg-password-wrap">
            <input id="register-confirm" name="confirmPassword" type="password" class="pfg-input" autocomplete="new-password" required maxlength="128" aria-describedby="register-confirm-error" />
            <button type="button" class="pfg-password-toggle" data-toggle-password="register-confirm" data-label="confirmation password" aria-label="Show confirmation password">Show</button>
          </div>
          <p id="register-confirm-error" class="pfg-field-error" hidden></p>
        </div>

        <div class="pfg-field">
          <div class="pfg-check">
            <input id="register-consent" name="consentAccepted" type="checkbox" value="true" required aria-describedby="register-consent-error" />
            <label for="register-consent">I have read the <a href="/legal/privacy">Privacy Notice</a> and <a href="/legal/terms">Terms</a>, and agree to create a participant account.</label>
          </div>
          <p id="register-consent-error" class="pfg-field-error" hidden></p>
        </div>

        <button id="register-submit" type="submit" class="pfg-btn">Create account</button>

        <div class="pfg-links">
          <a href="/login">Already have an account? Sign in</a>
        </div>
      </form>

      <section id="register-success" hidden aria-labelledby="register-success-heading">
        <h2 id="register-success-heading" tabindex="-1" style="margin:0 0 .5rem;font-size:1.375rem;">Check your email</h2>
        <p class="pfg-auth-lead">If this email is eligible, a verification link has been sent to <strong id="register-success-email"></strong>. The link works once and expires in 30 minutes. Please also check your spam folder.</p>
        <div class="pfg-stack">
          <a class="pfg-btn" href="/verify-email">I have a verification link</a>
          <button id="register-resend" type="button" class="pfg-btn pfg-btn--ghost">Send a new verification link</button>
        </div>
        <div class="pfg-links"><a href="/login">Back to sign in</a></div>
      </section>
    </div>
  </main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
