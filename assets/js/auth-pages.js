/* Purple Festival Gujarat 2027 — shared accessible authentication page controller.
   Handles: register, login, staff-login, forgot, reset, verify, resend.
   Pages embed config as <script type="application/json" id="auth-page-config"> (no inline JS). */
(function () {
  "use strict";

  var cfg = {};
  try {
    var cfgNode = document.getElementById("auth-page-config");
    if (cfgNode) cfg = JSON.parse(cfgNode.textContent || "{}") || {};
  } catch (e) {
    cfg = {};
  }

  var alertBox = document.getElementById("auth-alert");
  var statusBox = document.getElementById("auth-status");

  /* ---------- helpers ---------- */

  function byId(id) {
    return document.getElementById(id);
  }

  function isSafePath(p) {
    return typeof p === "string" && p.charAt(0) === "/" && p.charAt(1) !== "/" && p.indexOf("\\") === -1 && !/[\u0000-\u001f]/.test(p);
  }

  // Raw `next` is embedded via JSON_HEX_* escaping and re-validated here and again server-side.
  var nextPath = isSafePath(cfg.next) ? cfg.next : "";

  function go(target, fallback) {
    window.location.assign(isSafePath(target) ? target : fallback);
  }

  function setBox(box, message, focus) {
    if (!box) return;
    var textEl = box.querySelector("[data-text]");
    if (!message) {
      box.hidden = true;
      if (textEl) textEl.textContent = "";
      return;
    }
    box.hidden = false;
    if (textEl) textEl.textContent = message;
    if (focus) box.focus();
  }

  function showError(message) {
    setBox(statusBox, "");
    setBox(alertBox, message, true);
  }

  function showStatus(message, focus) {
    setBox(alertBox, "");
    setBox(statusBox, message, focus === true);
  }

  function clearMessages() {
    setBox(alertBox, "");
    setBox(statusBox, "");
  }

  function clearFieldErrors(form) {
    form.querySelectorAll("[aria-invalid]").forEach(function (el) {
      el.removeAttribute("aria-invalid");
    });
    form.querySelectorAll(".pfg-field-error").forEach(function (el) {
      el.textContent = "";
      el.hidden = true;
    });
  }

  function fieldError(input, message) {
    if (!input) return;
    input.setAttribute("aria-invalid", "true");
    var err = byId(input.id + "-error");
    if (err) {
      err.textContent = message;
      err.hidden = false;
    }
  }

  /** Applies {field: message} errors to inputs named after the keys; focuses the first invalid input. */
  function applyFieldErrors(form, errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (name) {
      var input = form.elements[name];
      if (input && input.id) {
        fieldError(input, errors[name]);
        if (!first) first = input;
      }
    });
    return first;
  }

  function setBusy(btn, busy, busyLabel) {
    if (!btn) return;
    if (busy) {
      btn.dataset.label = btn.textContent;
      btn.textContent = busyLabel || "Please wait…";
      btn.disabled = true;
      btn.setAttribute("aria-busy", "true");
    } else {
      if (btn.dataset.label) btn.textContent = btn.dataset.label;
      btn.disabled = false;
      btn.removeAttribute("aria-busy");
    }
  }

  function request(path, method, body) {
    var opts = {
      method: method || "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/json" }
    };
    if (body !== undefined) opts.body = JSON.stringify(body);
    return fetch(path, opts)
      .then(function (res) {
        var ct = res.headers.get("content-type") || "";
        if (res.status === 404 && ct.indexOf("application/json") === -1 && !/\.php$/.test(path)) {
          return fetch(path + ".php", opts);
        }
        return res;
      })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { status: res.status, ok: res.ok, data: data || {} };
        });
      });
  }

  function describeFailure(r, fallback) {
    if (r.status === 429) {
      var wait = r.data && r.data.retryAfter ? Math.ceil(Number(r.data.retryAfter) / 60) : 0;
      return "Too many attempts. Please wait" + (wait > 0 ? " about " + wait + " minute" + (wait === 1 ? "" : "s") : " a little") + " before trying again.";
    }
    return (r.data && r.data.error) || fallback;
  }

  var NETWORK_ERROR = "We could not reach the server. Please check your connection and try again.";

  function validEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) && v.length <= 255;
  }

  function passwordProblem(pw) {
    if (pw.length < 8) return "Password must be at least 8 characters long.";
    if (pw.length > 128) return "Password must not exceed 128 characters.";
    if (!/[A-Za-z]/.test(pw) || !/[0-9]/.test(pw)) return "Password must include at least one letter and one number.";
    return null;
  }

  /* ---------- show / hide password ---------- */

  document.querySelectorAll("[data-toggle-password]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = byId(btn.getAttribute("data-toggle-password"));
      if (!input) return;
      var reveal = input.type === "password";
      input.type = reveal ? "text" : "password";
      btn.textContent = reveal ? "Hide" : "Show";
      btn.setAttribute("aria-label", (reveal ? "Hide " : "Show ") + (btn.getAttribute("data-label") || "password"));
    });
  });

  /* ---------- resend verification (shared by register/login/verify) ---------- */

  function resendVerification(email, btn) {
    if (!validEmail(email)) {
      showError("Enter a valid email address to receive a new verification link.");
      return;
    }
    setBusy(btn, true, "Sending…");
    request("/api/auth/resend-verification", "POST", { email: email })
      .then(function (r) {
        if (r.ok) {
          showStatus((r.data && r.data.message) || "If this email is eligible, a new verification link has been sent.", true);
        } else {
          showError(describeFailure(r, "Could not send a new verification link. Please try again later."));
        }
      })
      .catch(function () { showError(NETWORK_ERROR); })
      .then(function () { setBusy(btn, false); });
  }

  /* ---------- page handlers ---------- */

  var handlers = {};

  handlers.register = function (form) {
    var submit = byId("register-submit");
    var successPanel = byId("register-success");
    var resendBtn = byId("register-resend");
    var registeredEmail = "";

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);

      var v = {
        fullName: form.elements.fullName.value.trim(),
        email: form.elements.email.value.trim().toLowerCase(),
        phone: form.elements.phone.value.trim(),
        password: form.elements.password.value,
        confirmPassword: form.elements.confirmPassword.value,
        consentAccepted: form.elements.consentAccepted.checked
      };
      var errors = {};
      if (v.fullName.length < 2) errors.fullName = "Please enter your full name.";
      if (!validEmail(v.email)) errors.email = "Please enter a valid email address.";
      if (!/^\+?[0-9\s\-()]{7,20}$/.test(v.phone)) errors.phone = "Please enter a valid phone number.";
      var pwp = passwordProblem(v.password);
      if (pwp) errors.password = pwp;
      if (v.password !== v.confirmPassword) errors.confirmPassword = "The two passwords do not match.";
      if (!v.consentAccepted) errors.consentAccepted = "You must accept the privacy notice and terms to continue.";

      if (Object.keys(errors).length) {
        var first = applyFieldErrors(form, errors);
        showError("Please correct the " + Object.keys(errors).length + " highlighted field" + (Object.keys(errors).length === 1 ? "" : "s") + ".");
        if (first) first.focus();
        return;
      }

      setBusy(submit, true, "Creating account…");
      request("/api/auth/signup", "POST", v)
        .then(function (r) {
          if (r.status === 201 || r.status === 202) {
            registeredEmail = v.email;
            form.hidden = true;
            var target = byId("register-success-email");
            if (target) target.textContent = v.email;
            if (successPanel) {
              successPanel.hidden = false;
              var h = successPanel.querySelector("h2");
              if (h) h.focus();
            }
            return;
          }
          if (r.status === 400 && r.data && r.data.errors) {
            var f = applyFieldErrors(form, r.data.errors);
            showError(r.data.error || "Please correct the highlighted fields.");
            if (f) f.focus();
            return;
          }
          showError(describeFailure(r, "We could not create your account right now. Please try again."));
        })
        .catch(function () { showError(NETWORK_ERROR); })
        .then(function () { setBusy(submit, false); });
    });

    if (resendBtn) {
      resendBtn.addEventListener("click", function () {
        resendVerification(registeredEmail, resendBtn);
      });
    }
  };

  handlers.login = function (form) {
    var submit = byId("login-submit");
    var resendPanel = byId("login-resend-panel");
    var resendBtn = byId("login-resend");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);
      if (resendPanel) resendPanel.hidden = true;

      var email = form.elements.email.value.trim();
      var password = form.elements.password.value;
      var errors = {};
      if (!email) errors.email = "Enter your email address.";
      if (!password) errors.password = "Enter your password.";
      if (Object.keys(errors).length) {
        var first = applyFieldErrors(form, errors);
        showError("Enter your email address and password to sign in.");
        if (first) first.focus();
        return;
      }

      setBusy(submit, true, "Signing in…");
      request("/api/auth/login", "POST", { username: email, password: password, portal: "participant", next: nextPath })
        .then(function (r) {
          if (r.ok && r.data && r.data.user) {
            go(r.data.redirectTo, "/dashboard");
            return;
          }
          if (r.status === 403 && r.data && r.data.emailVerificationRequired) {
            showError(r.data.error);
            if (resendPanel) resendPanel.hidden = false;
            return;
          }
          if (r.status === 403 && r.data && r.data.staffLogin) {
            showError(r.data.error + " Use the Staff Portal link below.");
            return;
          }
          showError(describeFailure(r, "Sign-in failed. Please check your email and password."));
        })
        .catch(function () { showError(NETWORK_ERROR); })
        .then(function () { setBusy(submit, false); });
    });

    if (resendBtn) {
      resendBtn.addEventListener("click", function () {
        resendVerification(form.elements.email.value.trim().toLowerCase(), resendBtn);
      });
    }
  };

  handlers["staff-login"] = function (form) {
    var submit = byId("staff-submit");
    var mfaBox = byId("staff-mfa-box");
    var mfaInput = byId("staff-mfa");
    var mfaLabel = byId("staff-mfa-label");
    var mfaHint = byId("staff-mfa-hint");
    var mfaToggle = byId("staff-mfa-toggle");
    var mfaNeeded = false;
    var useRecovery = false;

    function renderMfaMode() {
      if (useRecovery) {
        mfaLabel.textContent = "Recovery code";
        mfaHint.textContent = "Enter one of your single-use 8-character recovery codes, for example ABCD-2345.";
        mfaInput.setAttribute("inputmode", "text");
        mfaInput.setAttribute("maxlength", "12");
        mfaInput.setAttribute("autocomplete", "off");
        mfaToggle.textContent = "Use my authenticator app code instead";
      } else {
        mfaLabel.textContent = "6-digit authenticator code";
        mfaHint.textContent = "Open your authenticator app and enter the current 6-digit code.";
        mfaInput.setAttribute("inputmode", "numeric");
        mfaInput.setAttribute("maxlength", "7");
        mfaInput.setAttribute("autocomplete", "one-time-code");
        mfaToggle.textContent = "Use a recovery code instead";
      }
      mfaInput.value = "";
    }

    function revealMfa() {
      mfaNeeded = true;
      mfaBox.hidden = false;
      mfaInput.required = true;
      renderMfaMode();
      mfaInput.focus();
    }

    if (mfaToggle) {
      mfaToggle.addEventListener("click", function () {
        useRecovery = !useRecovery;
        renderMfaMode();
        mfaInput.focus();
      });
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);

      var username = form.elements.username.value.trim();
      var password = form.elements.password.value;
      var errors = {};
      if (!username) errors.username = "Enter your staff username or email.";
      if (!password) errors.password = "Enter your password.";
      var code = mfaNeeded ? mfaInput.value.trim() : "";
      if (mfaNeeded && !code) errors.mfaCode = useRecovery ? "Enter a recovery code." : "Enter the 6-digit code.";
      if (Object.keys(errors).length) {
        var first = applyFieldErrors(form, errors);
        showError("Please complete the required fields.");
        if (first) first.focus();
        return;
      }

      var payload = { username: username, password: password, portal: "staff", next: nextPath };
      if (mfaNeeded) {
        if (useRecovery) payload.recoveryCode = code;
        else payload.mfaCode = code.replace(/\s+/g, "");
      }

      setBusy(submit, true, "Signing in…");
      request("/api/auth/login", "POST", payload)
        .then(function (r) {
          if (r.ok && r.data && r.data.mfaRequired && !r.data.user) {
            revealMfa();
            showStatus(r.data.message || "Enter the code from your authenticator app to continue.", false);
            return;
          }
          if (r.ok && r.data && r.data.user) {
            if (r.data.mfaEnrollmentRequired) {
              go(r.data.redirectTo, "/admin?mfa_setup=1");
              return;
            }
            go(nextPath ? nextPath : r.data.redirectTo, "/admin");
            return;
          }
          if (r.data && r.data.mfaRequired) {
            revealMfa();
            showError(r.data.error || "Invalid authenticator code.");
            return;
          }
          showError(describeFailure(r, "Sign-in failed. Please check your credentials."));
        })
        .catch(function () { showError(NETWORK_ERROR); })
        .then(function () { setBusy(submit, false); });
    });
  };

  handlers.forgot = function (form) {
    var submit = byId("forgot-submit");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);
      var email = form.elements.email.value.trim().toLowerCase();
      if (!validEmail(email)) {
        fieldError(form.elements.email, "Please enter a valid email address.");
        showError("Please enter a valid email address.");
        form.elements.email.focus();
        return;
      }
      setBusy(submit, true, "Sending…");
      request("/api/auth/password-reset", "POST", { email: email })
        .then(function (r) {
          if (r.ok) {
            showStatus("If an account exists for that email address, password reset instructions are on their way. Check your inbox and spam folder; the link works once and expires in 1 hour.", true);
            form.reset();
          } else {
            showError(describeFailure(r, "We could not process your request. Please try again."));
          }
        })
        .catch(function () { showError(NETWORK_ERROR); })
        .then(function () { setBusy(submit, false); });
    });
  };

  handlers.reset = function (form) {
    var submit = byId("reset-submit");
    var tokenInput = form.elements.token;
    var done = byId("reset-done");
    if (cfg.token && tokenInput) tokenInput.value = cfg.token;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);
      var token = tokenInput ? tokenInput.value.trim() : "";
      var pw = form.elements.newPassword.value;
      var errors = {};
      if (token.length < 10) errors.token = "Enter the reset code from your email, or open the link in the email again.";
      var pwp = passwordProblem(pw);
      if (pwp) errors.newPassword = pwp;
      if (pw !== form.elements.confirmPassword.value) errors.confirmPassword = "The two passwords do not match.";
      if (Object.keys(errors).length) {
        var first = applyFieldErrors(form, errors);
        showError("Please correct the highlighted fields.");
        if (first) first.focus();
        return;
      }
      setBusy(submit, true, "Updating password…");
      request("/api/auth/password-reset", "POST", { token: token, newPassword: pw })
        .then(function (r) {
          if (r.ok) {
            form.hidden = true;
            setBox(alertBox, "");
            if (done) {
              done.hidden = false;
              var link = done.querySelector("a");
              if (link) link.focus();
            }
            showStatus((r.data && r.data.message) || "Your password has been updated. Please sign in with your new password.", false);
          } else {
            showError(describeFailure(r, "This password reset link is invalid or has expired. Please request a new one."));
          }
        })
        .catch(function () { showError(NETWORK_ERROR); })
        .then(function () { setBusy(submit, false); });
    });
  };

  handlers.verify = function (form) {
    var confirmBtn = byId("verify-confirm");
    var done = byId("verify-done");
    var resendPanel = byId("verify-resend-panel");

    if (confirmBtn) {
      confirmBtn.addEventListener("click", function () {
        clearMessages();
        setBusy(confirmBtn, true, "Verifying…");
        request("/api/auth/verify-email", "POST", { token: cfg.token || "" })
          .then(function (r) {
            if (r.ok) {
              confirmBtn.hidden = true;
              if (done) {
                done.hidden = false;
                var link = done.querySelector("a");
                if (link) link.focus();
              }
              showStatus((r.data && r.data.message) || "Your email address is verified.", false);
              var back = byId("verify-done-link");
              if (back && r.data && isSafePath(r.data.redirectTo)) back.setAttribute("href", r.data.redirectTo);
            } else {
              showError(describeFailure(r, "This verification link is invalid or has expired."));
              if (resendPanel) resendPanel.hidden = false;
              setBusy(confirmBtn, false);
            }
          })
          .catch(function () {
            showError(NETWORK_ERROR);
            setBusy(confirmBtn, false);
          });
      });
    }

    var resendBtn = byId("verify-resend");
    if (form && resendBtn) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        clearMessages();
        clearFieldErrors(form);
        var email = form.elements.email.value.trim().toLowerCase();
        if (!validEmail(email)) {
          fieldError(form.elements.email, "Please enter a valid email address.");
          form.elements.email.focus();
          showError("Please enter a valid email address.");
          return;
        }
        resendVerification(email, resendBtn);
      });
    }
  };

  /* ---------- bootstrap ---------- */

  // Remove one-time tokens from the address bar / history once read.
  if (cfg.token && window.history && window.history.replaceState) {
    window.history.replaceState(null, "", window.location.pathname);
  }

  var formEl = document.querySelector("form[data-auth-form]");
  var page = cfg.page || (formEl ? formEl.getAttribute("data-auth-form") : "");
  if (handlers[page]) {
    handlers[page](formEl);
  }
})();
