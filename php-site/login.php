<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

$resetTokenParam = isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '';
$nextParam = isset($_GET['next'])
    ? trim((string)$_GET['next'])
    : (isset($_GET['redirect']) ? trim((string)$_GET['redirect']) : '');
$modeParam = isset($_GET['mode']) ? trim((string)$_GET['mode']) : '';

$initialMode = 'login';
if ($resetTokenParam !== '') {
    $initialMode = 'reset';
} elseif ($modeParam === 'signup') {
    $initialMode = 'signup';
} elseif ($modeParam === 'forgot') {
    $initialMode = 'forgot';
}

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

$inputClass = 'w-full min-h-[46px] rounded-lg border border-[#DCE5F7] bg-[#EEF4FF] px-4 py-2.5 text-[15px] text-[#202D59] placeholder-[#64748B] transition-colors focus:border-[#202D59] focus:bg-white focus:outline-none';

function h_login(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#F2E9FE" />
  <title>Sign In | Purple Festival Gujarat 2027</title>
  <meta name="description" content="Sign in or create your participant account for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat)." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&display=swap" />
  <link rel="stylesheet" href="/assets/css/app.css" />
</head>
<body class="min-h-screen bg-white text-[#202D59] antialiased selection:bg-[#A459FF]/25 selection:text-[#202D59]">
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <main
    id="main-content"
    tabindex="-1"
    class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-[#16215C] bg-cover bg-center px-4 py-10 sm:px-8 focus:outline-none"
    style="background-image: url('/assets/login-bg.webp');"
  >
    <div class="relative z-10 mx-auto grid w-full max-w-5xl items-center gap-10 lg:grid-cols-12 lg:gap-12">
      <!-- Left: Clean Floating White Organiser / Sponsor Strip -->
      <section
        aria-label="Festival Organisers and Partners"
        class="order-2 flex justify-center lg:order-1 lg:col-span-6 lg:justify-start"
      >
        <div class="w-full max-w-[540px] rounded-2xl bg-white/95 px-5 py-5 shadow-[0_24px_60px_rgba(12,18,48,0.28)] backdrop-blur-sm sm:px-7 sm:py-6">
          <div class="grid grid-cols-2 items-center gap-y-4 divide-slate-200 sm:grid-cols-4 sm:divide-x">
            <?php foreach ($sponsors as $sponsor): ?>
              <div class="flex h-16 items-center justify-center px-3">
                <img
                  src="<?= h_login($sponsor['src']) ?>"
                  alt="<?= h_login($sponsor['name']) ?>"
                  width="120"
                  height="56"
                  class="max-h-13 w-auto object-contain"
                />
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- Right: Clean Floating White Sign-In Card -->
      <section
        aria-labelledby="login-heading"
        class="order-1 flex justify-center lg:order-2 lg:col-span-6 lg:justify-end"
      >
        <div class="w-full max-w-[400px] rounded-2xl bg-white p-8 text-[#202D59] shadow-[0_24px_60px_rgba(12,18,48,0.3)] sm:p-10">
          <div class="mb-6 text-center">
            <h1
              id="login-heading"
              class="text-2xl font-bold tracking-[-0.02em] text-[#111827] sm:text-[26px]"
            >
              <?php if ($initialMode === 'signup'): ?>Create Account
              <?php elseif ($initialMode === 'forgot'): ?>Reset Password
              <?php elseif ($initialMode === 'reset'): ?>Set New Password
              <?php else: ?>Sign In<?php endif; ?>
            </h1>
            <p id="login-subheading" class="mt-1 text-xs font-medium text-[#64748B]">
              <?php if ($initialMode === 'signup'): ?>Purple Festival Gujarat 2027 Portal
              <?php elseif ($initialMode === 'forgot'): ?>Enter your registered email address
              <?php elseif ($initialMode === 'reset'): ?>Choose a new password (min. 8 characters)
              <?php else: ?>With Your Credentials<?php endif; ?>
            </p>
          </div>

          <!-- Alert Banner -->
          <div
            id="auth-error-alert"
            role="alert"
            class="mb-4 hidden items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-800"
          >
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="auth-error-text"></span>
          </div>

          <!-- Success Banner -->
          <div
            id="auth-success-status"
            role="status"
            aria-live="polite"
            class="mb-4 hidden items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800"
          >
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span id="auth-success-text"></span>
          </div>

          <!-- MODE 1: SIGN IN -->
          <form
            id="form-login"
            class="space-y-4 <?= $initialMode === 'login' ? '' : 'hidden' ?>"
            novalidate
          >
            <div>
              <label for="login-email" class="sr-only">Email Address</label>
              <input
                id="login-email"
                name="email"
                type="email"
                required
                autocomplete="email"
                placeholder="Email address"
                class="<?= $inputClass ?>"
              />
            </div>

            <div class="relative">
              <label for="login-password" class="sr-only">Password</label>
              <input
                id="login-password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Password"
                class="<?= $inputClass ?> pr-11"
              />
              <button
                type="button"
                data-toggle-password="login-password"
                aria-label="Show password"
                aria-pressed="false"
                class="absolute right-1.5 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-md text-[#64748B] hover:text-[#202D59]"
              >
                <svg data-eye-open class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg data-eye-off class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              </button>
            </div>

            <div
              id="login-mfa-box"
              class="hidden space-y-1.5 rounded-lg border border-[#A459FF]/40 bg-[#F5F0FF] p-3"
            >
              <label
                for="login-mfa"
                class="flex items-center gap-1.5 text-xs font-semibold text-[#202D59]"
              >
                <svg class="h-4 w-4 text-[#A459FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                  <path d="m9 12 2 2 4-4"></path>
                </svg>
                6-Digit Authenticator Code
              </label>
              <input
                id="login-mfa"
                name="mfaCode"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="8"
                placeholder="000 000"
                class="w-full min-h-[42px] rounded-md border border-[#DCE5F7] bg-white px-3 text-center font-mono text-base tracking-[0.25em] text-[#202D59]"
              />
            </div>

            <button
              id="btn-login-submit"
              type="submit"
              class="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
            >
              Login
            </button>

            <div class="flex items-center justify-between pt-1 text-xs font-medium text-[#64748B]">
              <button
                type="button"
                data-switch-mode="signup"
                class="hover:text-[#202D59] hover:underline"
              >
                Create Account
              </button>
              <button
                type="button"
                data-switch-mode="forgot"
                class="hover:text-[#202D59] hover:underline"
              >
                Forgot password?
              </button>
            </div>
          </form>

          <!-- MODE 2: CREATE ACCOUNT -->
          <form
            id="form-signup"
            class="space-y-3.5 <?= $initialMode === 'signup' ? '' : 'hidden' ?>"
            novalidate
          >
            <div>
              <label for="signup-name" class="sr-only">Full Name</label>
              <input
                id="signup-name"
                type="text"
                required
                autocomplete="name"
                placeholder="Full Name"
                class="<?= $inputClass ?>"
              />
            </div>
            <div>
              <label for="signup-email" class="sr-only">Email Address</label>
              <input
                id="signup-email"
                type="email"
                required
                autocomplete="email"
                placeholder="Email address"
                class="<?= $inputClass ?>"
              />
            </div>
            <div class="grid grid-cols-2 gap-2.5">
              <div>
                <label for="signup-phone" class="sr-only">Phone</label>
                <input
                  id="signup-phone"
                  type="tel"
                  autocomplete="tel"
                  placeholder="Phone"
                  class="<?= $inputClass ?>"
                />
              </div>
              <div>
                <label for="signup-country" class="sr-only">Country</label>
                <input
                  id="signup-country"
                  type="text"
                  autocomplete="country-name"
                  value="India"
                  placeholder="Country"
                  class="<?= $inputClass ?>"
                />
              </div>
            </div>
            <div class="relative">
              <label for="signup-password" class="sr-only">Password</label>
              <input
                id="signup-password"
                type="password"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="Password (min. 8 chars)"
                class="<?= $inputClass ?> pr-11"
              />
              <button
                type="button"
                data-toggle-password="signup-password"
                aria-label="Show password"
                aria-pressed="false"
                class="absolute right-1.5 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-md text-[#64748B] hover:text-[#202D59]"
              >
                <svg data-eye-open class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg data-eye-off class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              </button>
            </div>

            <button
              id="btn-signup-submit"
              type="submit"
              class="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
            >
              Create Account
            </button>

            <div class="text-center text-xs font-medium text-[#64748B]">
              <button
                type="button"
                data-switch-mode="login"
                class="hover:text-[#202D59] hover:underline"
              >
                Already have an account? Sign In
              </button>
            </div>
          </form>

          <!-- MODE 3: FORGOT PASSWORD -->
          <form
            id="form-forgot"
            class="space-y-4 <?= $initialMode === 'forgot' ? '' : 'hidden' ?>"
            novalidate
          >
            <div>
              <label for="forgot-email" class="sr-only">Registered Email Address</label>
              <input
                id="forgot-email"
                type="email"
                required
                placeholder="Registered email address"
                class="<?= $inputClass ?>"
              />
            </div>
            <button
              id="btn-forgot-submit"
              type="submit"
              class="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
            >
              Request Reset
            </button>
            <div class="text-center text-xs font-medium text-[#64748B]">
              <button
                type="button"
                data-switch-mode="login"
                class="hover:text-[#202D59] hover:underline"
              >
                Back to Sign In
              </button>
            </div>
          </form>

          <!-- MODE 4: RESET PASSWORD -->
          <form
            id="form-reset"
            class="space-y-4 <?= $initialMode === 'reset' ? '' : 'hidden' ?>"
            novalidate
          >
            <div>
              <label for="reset-token" class="sr-only">Reset Token</label>
              <input
                id="reset-token"
                type="text"
                required
                value="<?= h_login($resetTokenParam) ?>"
                placeholder="Reset token"
                class="<?= $inputClass ?> font-mono text-sm"
              />
            </div>
            <div>
              <label for="reset-new-password" class="sr-only">New Password</label>
              <input
                id="reset-new-password"
                type="password"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="New password (min. 8 chars)"
                class="<?= $inputClass ?>"
              />
            </div>
            <button
              id="btn-reset-submit"
              type="submit"
              class="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
            >
              Update Password
            </button>
            <div class="text-center text-xs font-medium text-[#64748B]">
              <button
                type="button"
                data-switch-mode="login"
                class="hover:text-[#202D59] hover:underline"
              >
                Back to Sign In
              </button>
            </div>
          </form>

          <div class="mt-6 text-center">
            <a
              href="/"
              class="text-sm font-medium text-[#A459FF] transition-colors hover:text-[#202D59] hover:underline"
            >
              Return to Home Page
            </a>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script src="/assets/js/accessibility.js" defer></script>
  <script>
    (function () {
      const nextParam = <?= json_encode($nextParam, JSON_UNESCAPED_SLASHES) ?>;
      let currentMode = <?= json_encode($initialMode) ?>;
      let mfaRequired = false;

      const headingEl = document.getElementById("login-heading");
      const subheadingEl = document.getElementById("login-subheading");
      const errorAlert = document.getElementById("auth-error-alert");
      const errorText = document.getElementById("auth-error-text");
      const successStatus = document.getElementById("auth-success-status");
      const successText = document.getElementById("auth-success-text");

      const forms = {
        login: document.getElementById("form-login"),
        signup: document.getElementById("form-signup"),
        forgot: document.getElementById("form-forgot"),
        reset: document.getElementById("form-reset"),
      };

      const mfaBox = document.getElementById("login-mfa-box");
      const mfaInput = document.getElementById("login-mfa");
      const btnLoginSubmit = document.getElementById("btn-login-submit");
      const btnSignupSubmit = document.getElementById("btn-signup-submit");
      const btnForgotSubmit = document.getElementById("btn-forgot-submit");
      const btnResetSubmit = document.getElementById("btn-reset-submit");

      const MODE_COPY = {
        login: {
          title: "Sign In",
          subtitle: "With Your Credentials",
        },
        signup: {
          title: "Create Account",
          subtitle: "Purple Festival Gujarat 2027 Portal",
        },
        forgot: {
          title: "Reset Password",
          subtitle: "Enter your registered email address",
        },
        reset: {
          title: "Set New Password",
          subtitle: "Choose a new password (min. 8 characters)",
        },
      };

      function showError(msg) {
        if (!msg) {
          errorAlert.classList.add("hidden");
          errorAlert.classList.remove("flex");
          errorText.textContent = "";
          return;
        }
        errorText.textContent = msg;
        errorAlert.classList.remove("hidden");
        errorAlert.classList.add("flex");
      }

      function showSuccess(msg) {
        if (!msg) {
          successStatus.classList.add("hidden");
          successStatus.classList.remove("flex");
          successText.textContent = "";
          return;
        }
        successText.textContent = msg;
        successStatus.classList.remove("hidden");
        successStatus.classList.add("flex");
      }

      function setMfaState(required) {
        mfaRequired = Boolean(required);
        if (mfaRequired) {
          mfaBox.classList.remove("hidden");
          mfaInput.required = true;
          btnLoginSubmit.textContent = "Verify & Login";
        } else {
          mfaBox.classList.add("hidden");
          mfaInput.required = false;
          mfaInput.value = "";
          btnLoginSubmit.textContent = "Login";
        }
      }

      function switchMode(nextMode, keepBanner) {
        if (!MODE_COPY[nextMode]) return;
        currentMode = nextMode;
        headingEl.textContent = MODE_COPY[nextMode].title;
        subheadingEl.textContent = MODE_COPY[nextMode].subtitle;

        if (!keepBanner) {
          showError(null);
          showSuccess(null);
        }
        setMfaState(false);

        Object.keys(forms).forEach(function (key) {
          if (key === nextMode) {
            forms[key].classList.remove("hidden");
          } else {
            forms[key].classList.add("hidden");
          }
        });
      }

      document.querySelectorAll("[data-switch-mode]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const targetMode = btn.getAttribute("data-switch-mode");
          // Sync email across forms for user convenience
          const loginEmail = document.getElementById("login-email").value.trim();
          const signupEmail = document.getElementById("signup-email").value.trim();
          const forgotEmail = document.getElementById("forgot-email").value.trim();
          const activeEmail = loginEmail || signupEmail || forgotEmail;
          if (activeEmail) {
            document.getElementById("login-email").value = activeEmail;
            document.getElementById("signup-email").value = activeEmail;
            document.getElementById("forgot-email").value = activeEmail;
          }
          switchMode(targetMode, false);
        });
      });

      document.querySelectorAll("[data-toggle-password]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const inputId = btn.getAttribute("data-toggle-password");
          const input = document.getElementById(inputId);
          if (!input) return;
          const isPassword = input.type === "password";
          input.type = isPassword ? "text" : "password";
          btn.setAttribute("aria-pressed", isPassword ? "true" : "false");
          btn.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
          const openIcon = btn.querySelector("[data-eye-open]");
          const offIcon = btn.querySelector("[data-eye-off]");
          if (openIcon && offIcon) {
            if (isPassword) {
              openIcon.classList.add("hidden");
              offIcon.classList.remove("hidden");
            } else {
              openIcon.classList.remove("hidden");
              offIcon.classList.add("hidden");
            }
          }
        });
      });

      async function apiFetch(endpoint, options) {
        let res = await fetch(endpoint, options);
        const contentType = res.headers.get("content-type") || "";
        if (res.status === 404 && !contentType.includes("application/json") && !endpoint.endsWith(".php")) {
          res = await fetch(endpoint + ".php", options);
        }
        return res;
      }

      // Sign In submission
      forms.login.addEventListener("submit", async function (e) {
        e.preventDefault();
        showError(null);
        showSuccess(null);

        const email = document.getElementById("login-email").value.trim();
        const password = document.getElementById("login-password").value;
        const mfaCode = mfaInput.value.trim();

        btnLoginSubmit.disabled = true;
        btnLoginSubmit.textContent = "Signing in...";

        try {
          const payload = { email: email, password: password };
          if (mfaRequired && mfaCode) {
            payload.mfaCode = mfaCode;
          }

          const res = await apiFetch("/api/auth/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          });
          const data = await res.json().catch(function () { return {}; });

          if (data.mfaRequired && !res.ok) {
            setMfaState(true);
            showError(data.error || "Invalid authenticator code.");
            return;
          }

          if (data.mfaRequired && res.ok) {
            setMfaState(true);
            showSuccess(data.message || "Enter the 6-digit code from your authenticator app.");
            mfaInput.focus();
            return;
          }

          if (!res.ok) {
            showError(data.error || "Sign-in failed. Please check your credentials.");
            return;
          }

          const target =
            nextParam && nextParam.startsWith("/") && !nextParam.startsWith("//")
              ? nextParam
              : data.redirectTo || "/dashboard";
          window.location.href = target;
        } catch (err) {
          showError("Network error while signing in. Please try again.");
        } finally {
          btnLoginSubmit.disabled = false;
          btnLoginSubmit.textContent = mfaRequired ? "Verify & Login" : "Login";
        }
      });

      // Create Account submission
      forms.signup.addEventListener("submit", async function (e) {
        e.preventDefault();
        showError(null);
        showSuccess(null);

        const fullName = document.getElementById("signup-name").value.trim();
        const email = document.getElementById("signup-email").value.trim();
        const phone = document.getElementById("signup-phone").value.trim();
        const country = document.getElementById("signup-country").value.trim() || "India";
        const password = document.getElementById("signup-password").value;

        btnSignupSubmit.disabled = true;
        btnSignupSubmit.textContent = "Creating Account...";

        try {
          const res = await apiFetch("/api/auth/signup", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              fullName: fullName,
              email: email,
              phone: phone,
              country: country,
              password: password,
            }),
          });
          const data = await res.json().catch(function () { return {}; });

          if (!res.ok) {
            showError(data.error || "Unable to create account.");
            return;
          }

          window.location.href = data.redirectTo || "/dashboard";
        } catch (err) {
          showError("Network error while creating your account.");
        } finally {
          btnSignupSubmit.disabled = false;
          btnSignupSubmit.textContent = "Create Account";
        }
      });

      // Forgot Password submission
      forms.forgot.addEventListener("submit", async function (e) {
        e.preventDefault();
        showError(null);
        showSuccess(null);

        const email = document.getElementById("forgot-email").value.trim();
        btnForgotSubmit.disabled = true;
        btnForgotSubmit.textContent = "Sending...";

        try {
          const res = await apiFetch("/api/auth/password-reset", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: email }),
          });
          const data = await res.json().catch(function () { return {}; });

          if (!res.ok) {
            showError(data.error || "Unable to request password reset.");
            return;
          }

          showSuccess(
            data.message ||
              "If an account exists for that email, password reset instructions have been queued."
          );
        } catch (err) {
          showError("Network error while requesting password reset.");
        } finally {
          btnForgotSubmit.disabled = false;
          btnForgotSubmit.textContent = "Request Reset";
        }
      });

      // Reset Password Complete submission
      forms.reset.addEventListener("submit", async function (e) {
        e.preventDefault();
        showError(null);
        showSuccess(null);

        const token = document.getElementById("reset-token").value.trim();
        const newPassword = document.getElementById("reset-new-password").value;

        btnResetSubmit.disabled = true;
        btnResetSubmit.textContent = "Updating...";

        try {
          const res = await apiFetch("/api/auth/password-reset", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              token: token,
              newPassword: newPassword,
            }),
          });
          const data = await res.json().catch(function () { return {}; });

          if (!res.ok) {
            showError(data.error || "Unable to reset password.");
            return;
          }

          document.getElementById("reset-new-password").value = "";
          switchMode("login", true);
          showSuccess(
            data.message || "Password updated. You may now sign in with your new password."
          );
        } catch (err) {
          showError("Network error while updating password.");
        } finally {
          btnResetSubmit.disabled = false;
          btnResetSubmit.textContent = "Update Password";
        }
      });
    })();
  </script>
</body>
</html>
