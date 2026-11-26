/* Purple Festival Gujarat 2027 — Staff-only accessible authentication page controller (CAD-17).
   Handles: staff-login, forgot, reset.
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

  function byId(id) {
    return document.getElementById(id);
  }

  function isSafePath(p) {
    return typeof p === "string" && p.charAt(0) === "/" && p.charAt(1) !== "/" && p.indexOf("\\") === -1 && !/[\u0000-\u001f]/.test(p);
  }

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

  var handlers = {};

  handlers["staff-login"] = function (form) {
    var submit = byId("staff-submit");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearMessages();
      clearFieldErrors(form);

      var username = form.elements.username.value.trim();
      var password = form.elements.password.value;
      var errors = {};
      if (!username) errors.username = "Enter your staff username.";
      if (!password) errors.password = "Enter your password.";
      if (Object.keys(errors).length) {
        var first = applyFieldErrors(form, errors);
        showError("Please complete the required fields.");
        if (first) first.focus();
        return;
      }

      var payload = { username: username, password: password, portal: "staff", next: nextPath };

      setBusy(submit, true, "Signing in…");
      request("/api/auth/login", "POST", payload)
        .then(function (r) {
          if (r.ok && r.data && r.data.user) {
            go(nextPath ? nextPath : r.data.redirectTo, "/admin");
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
            showStatus("If an eligible staff account exists for that email address, password reset instructions have been processed. The single-use link expires in 1 hour.", true);
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

  var form = document.querySelector("[data-auth-form]");
  var pageKey = (form && form.getAttribute("data-auth-form")) || cfg.page || "";
  if (form && handlers[pageKey]) {
    handlers[pageKey](form);
  }
})();
