<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

$nextParam = isset($_GET['next'])
    ? trim((string)$_GET['next'])
    : (isset($_GET['redirect']) ? trim((string)$_GET['redirect']) : '');

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
  <title>Staff Sign In | Purple Festival Gujarat 2027</title>
  <meta name="description" content="Authorized staff sign-in portal for Purple Festival Gujarat 2027 (Superadmin, Admin, and Registration Desk)." />
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

      <!-- Right: Clean Floating White Staff Sign-In Card -->
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
              Sign In
            </h1>
            <p id="login-subheading" class="mt-1 text-xs font-medium text-[#64748B]">
              With Your Credentials
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

          <!-- STAFF SIGN IN FORM -->
          <form id="form-login" class="space-y-4" novalidate>
            <div>
              <label for="login-username" class="sr-only">Username</label>
              <input
                id="login-username"
                name="username"
                type="text"
                required
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                placeholder="Username"
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
                6-Digit Authenticator Code or Recovery Code
              </label>
              <input
                id="login-mfa"
                name="mfaCode"
                type="text"
                autocomplete="one-time-code"
                maxlength="12"
                placeholder="000000 or XXXX-XXXX"
                class="w-full min-h-[42px] rounded-md border border-[#DCE5F7] bg-white px-3 text-center font-mono text-base tracking-[0.15em] text-[#202D59]"
              />
            </div>

            <button
              id="btn-login-submit"
              type="submit"
              class="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
            >
              Login
            </button>
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
      const nextParam = <?= pfgSafeJsonForScript($nextParam) ?>;
      let mfaRequired = false;

      const errorAlert = document.getElementById("auth-error-alert");
      const errorText = document.getElementById("auth-error-text");
      const successStatus = document.getElementById("auth-success-status");
      const successText = document.getElementById("auth-success-text");

      const loginForm = document.getElementById("form-login");
      const mfaBox = document.getElementById("login-mfa-box");
      const mfaInput = document.getElementById("login-mfa");
      const btnLoginSubmit = document.getElementById("btn-login-submit");

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

      loginForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        showError(null);
        showSuccess(null);

        const username = document.getElementById("login-username").value.trim();
        const password = document.getElementById("login-password").value;
        const mfaCode = mfaInput.value.trim();

        btnLoginSubmit.disabled = true;
        btnLoginSubmit.textContent = "Signing in...";

        try {
          const payload = { username: username, password: password };
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

          if (data.mfaEnrollmentRequired) {
            window.location.href = data.redirectTo || "/admin?mfa_setup=1";
            return;
          }

          const isSafeNext =
            typeof nextParam === "string" &&
            nextParam.startsWith("/") &&
            !nextParam.startsWith("//") &&
            !nextParam.includes("\\");
          const target = isSafeNext ? nextParam : data.redirectTo || "/admin";
          window.location.href = target;
        } catch (err) {
          showError("Network error while signing in. Please try again.");
        } finally {
          btnLoginSubmit.disabled = false;
          btnLoginSubmit.textContent = mfaRequired ? "Verify & Login" : "Login";
        }
      });
    })();
  </script>
</body>
</html>
