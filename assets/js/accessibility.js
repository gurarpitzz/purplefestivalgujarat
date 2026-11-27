/**
 * Purple Festival Gujarat 2027 — Accessibility Preferences & Navigation Controller
 * WCAG 2.2 AA+ Compliant Vanilla JS Controller (FND-20 & FND-46)
 */
(function () {
  "use strict";

  var STORAGE_KEY = "pfg27_a11y_prefs";
  var LEGACY_STORAGE_KEY = "pfg27_a11y_prefs_v1";
  var TEXT_SCALES = [90, 100, 115, 130, 150, 175, 200];
  var SPEECH_RATES = [0.8, 1, 1.25, 1.5];

  var DEFAULT_PREFS = {
    textScale: 100,
    contrast: "default",
    reducedMotion: false,
    wideSpacing: false,
    highlightLinks: false,
    readingGuide: false,
    dyslexiaFont: false,
    screenReader: false,
    speechRate: 1,
  };

  var prefs = {
    textScale: 100,
    contrast: "default",
    reducedMotion: false,
    wideSpacing: false,
    highlightLinks: false,
    readingGuide: false,
    dyslexiaFont: false,
    screenReader: false,
    speechRate: 1,
  };

  var panelOpen = false;
  var previouslyFocused = null;
  var guideY = 240;

  // Live-region state (prevents FND-46 duplicate announcement drops & page-load spam)
  var lastAnnounced = { polite: "", assertive: "" };
  var announceToggle = { polite: false, assertive: false };
  var announceRaf = { polite: 0, assertive: 0 };

  function getOsReducedMotion() {
    try {
      return Boolean(
        window.matchMedia &&
          window.matchMedia("(prefers-reduced-motion: reduce)").matches
      );
    } catch (e) {
      return false;
    }
  }

  function isReducedMotion() {
    var root = document.documentElement;
    var attrReduced = root && root.getAttribute("data-reduced-motion") === "true";
    var classReduced = root && root.classList.contains("reduced-motion");
    return Boolean(prefs.reducedMotion || attrReduced || classReduced || getOsReducedMotion());
  }

  function sanitizePrefs(rawObj, osReduced) {
    var scale = Number(rawObj && rawObj.textScale);
    if (TEXT_SCALES.indexOf(scale) === -1) {
      scale = 100;
    }
    var contrast =
      rawObj && (rawObj.contrast === "high" || rawObj.contrast === "yellow-black")
        ? rawObj.contrast
        : "default";
    var reducedMotion =
      rawObj && typeof rawObj.reducedMotion === "boolean"
        ? rawObj.reducedMotion
        : Boolean(osReduced);
    var speechRate = Number(rawObj && rawObj.speechRate);
    if (SPEECH_RATES.indexOf(speechRate) === -1) {
      speechRate = 1;
    }

    return {
      textScale: scale,
      contrast: contrast,
      reducedMotion: reducedMotion,
      wideSpacing: Boolean(rawObj && rawObj.wideSpacing),
      highlightLinks: Boolean(rawObj && rawObj.highlightLinks),
      readingGuide: Boolean(rawObj && rawObj.readingGuide),
      dyslexiaFont: Boolean(rawObj && rawObj.dyslexiaFont),
      screenReader: Boolean(rawObj && rawObj.screenReader),
      speechRate: speechRate,
    };
  }

  function loadPrefs() {
    var osReduced = getOsReducedMotion();
    try {
      var raw =
        window.localStorage.getItem(STORAGE_KEY) ||
        window.localStorage.getItem(LEGACY_STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        prefs = sanitizePrefs(parsed, osReduced);
      } else {
        prefs = sanitizePrefs({ reducedMotion: osReduced }, osReduced);
      }
    } catch (e) {
      prefs = sanitizePrefs({}, osReduced);
    }
  }

  function savePrefs() {
    try {
      var serialized = JSON.stringify(prefs);
      window.localStorage.setItem(STORAGE_KEY, serialized);
      window.localStorage.setItem(LEGACY_STORAGE_KEY, serialized);
    } catch (e) {
      // Ignore storage quota / private mode errors
    }
  }

  function applyPrefsToDocument() {
    var root = document.documentElement;
    if (!root) return;

    root.setAttribute("data-text-scale", String(prefs.textScale));
    root.setAttribute("data-contrast", prefs.contrast);
    root.setAttribute("data-reduced-motion", prefs.reducedMotion ? "true" : "false");
    root.setAttribute("data-spacing", prefs.wideSpacing ? "wide" : "normal");
    root.setAttribute("data-highlight-links", prefs.highlightLinks ? "true" : "false");
    root.setAttribute("data-dyslexia-font", prefs.dyslexiaFont ? "true" : "false");
    root.setAttribute("data-screen-reader", prefs.screenReader ? "true" : "false");
    root.style.fontSize = String(prefs.textScale) + "%";

    // Sync semantic utility classes alongside data attributes
    root.classList.toggle("high-contrast", prefs.contrast === "high");
    root.classList.toggle("yellow-black", prefs.contrast === "yellow-black");
    root.classList.toggle("reduced-motion", Boolean(prefs.reducedMotion));
    root.classList.toggle("dyslexia-font", Boolean(prefs.dyslexiaFont));
  }

  function ensureLiveRegion(politeness) {
    var isAssertive = politeness === "assertive";
    var id = isAssertive ? "a11y-live-assertive" : "a11y-live-status";
    var el = document.getElementById(id);

    if (!el && document.body) {
      el = document.createElement("div");
      el.id = id;
      el.className = "sr-only";
      el.setAttribute("role", isAssertive ? "alert" : "status");
      el.setAttribute("aria-live", isAssertive ? "assertive" : "polite");
      el.setAttribute("aria-atomic", "true");
      document.body.appendChild(el);
    } else if (el && !el.hasAttribute("aria-atomic")) {
      el.setAttribute("aria-atomic", "true");
    }

    return el;
  }

  /**
   * Announces a message cleanly to screen readers via polite or assertive live regions.
   * Fixes FND-46: clears and schedules updates via requestAnimationFrame with a zero-width
   * toggle when repeating identical strings so assistive tech reliably announces updates.
   */
  function announce(message, politeness) {
    var text = typeof message === "string" ? message.trim() : "";
    if (!text) return;

    var mode = politeness === "assertive" ? "assertive" : "polite";
    var liveEl = ensureLiveRegion(mode);
    if (!liveEl) return;

    var raf =
      typeof window.requestAnimationFrame === "function"
        ? window.requestAnimationFrame
        : function (cb) {
            return window.setTimeout(cb, 16);
          };
    var cancelRaf =
      typeof window.cancelAnimationFrame === "function"
        ? window.cancelAnimationFrame
        : window.clearTimeout;

    if (announceRaf[mode]) {
      cancelRaf(announceRaf[mode]);
      announceRaf[mode] = 0;
    }

    var isRepeat = lastAnnounced[mode] === text;
    if (isRepeat) {
      announceToggle[mode] = !announceToggle[mode];
    } else {
      announceToggle[mode] = false;
    }
    lastAnnounced[mode] = text;

    var payload = announceToggle[mode] ? text + "\u200B" : text;

    // Clear first, then write on the next animation frame so SR detects a fresh DOM mutation
    liveEl.textContent = "";
    announceRaf[mode] = raf(function () {
      announceRaf[mode] = raf(function () {
        announceRaf[mode] = 0;
        liveEl.textContent = payload;
      });
    });
  }

  function describePrefChange(patch) {
    if (!patch || typeof patch !== "object") {
      return "Accessibility preferences updated.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "textScale")) {
      return "Text size set to " + String(prefs.textScale) + "%.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "contrast")) {
      var contrastLabels = {
        default: "Default",
        high: "High Contrast",
        "yellow-black": "Yellow on Black",
      };
      return (
        "Color contrast mode set to " +
        (contrastLabels[prefs.contrast] || "Default") +
        "."
      );
    }
    if (Object.prototype.hasOwnProperty.call(patch, "reducedMotion")) {
      return prefs.reducedMotion
        ? "Reduce motion enabled."
        : "Reduce motion disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "wideSpacing")) {
      return prefs.wideSpacing
        ? "Increased text and line spacing enabled."
        : "Increased text and line spacing disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "highlightLinks")) {
      return prefs.highlightLinks
        ? "Highlight links and buttons enabled."
        : "Highlight links and buttons disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "readingGuide")) {
      return prefs.readingGuide
        ? "Reading guide band enabled."
        : "Reading guide band disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "dyslexiaFont")) {
      return prefs.dyslexiaFont
        ? "Dyslexia-friendly font enabled."
        : "Dyslexia-friendly font disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "screenReader")) {
      return prefs.screenReader
        ? "Screen reader TalkBack enabled."
        : "Screen reader TalkBack disabled.";
    }
    if (Object.prototype.hasOwnProperty.call(patch, "speechRate")) {
      return "Speech speed set to " + String(prefs.speechRate) + "x.";
    }
    return "Accessibility preferences updated.";
  }

  function dispatchChanged() {
    try {
      window.dispatchEvent(
        new CustomEvent("pfg27:a11y-changed", {
          detail: Object.assign({}, prefs),
        })
      );
    } catch (e) {
      // Ignore
    }
  }

  function syncPanelUi() {
    var badge = document.getElementById("a11y-text-scale-badge");
    if (badge) {
      badge.textContent = String(prefs.textScale) + "%";
    }

    var idx = TEXT_SCALES.indexOf(prefs.textScale);
    var decBtn = document.getElementById("a11y-scale-decrease");
    var incBtn = document.getElementById("a11y-scale-increase");
    if (decBtn) decBtn.disabled = idx <= 0;
    if (incBtn) incBtn.disabled = idx >= TEXT_SCALES.length - 1;

    // Contrast radio buttons
    var contrastBtns = document.querySelectorAll("[data-a11y-contrast]");
    for (var i = 0; i < contrastBtns.length; i++) {
      var btn = contrastBtns[i];
      var val = btn.getAttribute("data-a11y-contrast");
      var selected = val === prefs.contrast;
      btn.setAttribute("aria-checked", selected ? "true" : "false");
      btn.setAttribute("tabindex", selected ? "0" : "-1");
      btn.className =
        "touch-target flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition-colors " +
        (selected
          ? "border-[#D5B77B] bg-[#2D2538] text-[#F9F7FC]"
          : "border-[#383242] bg-[#25212B] text-[#D7CCE2] hover:border-[#9273B5]");

      var dot = btn.querySelector("[data-a11y-contrast-dot]");
      if (dot) {
        dot.className =
          "h-4 w-4 rounded-full border-2 " +
          (selected
            ? "border-[#D5B77B] bg-[#D5B77B]"
            : "border-[#9273B5] bg-transparent");
      }
    }

    // Switch toggles
    var switchBtns = document.querySelectorAll("[data-a11y-toggle]");
    for (var j = 0; j < switchBtns.length; j++) {
      var sw = switchBtns[j];
      var key = sw.getAttribute("data-a11y-toggle");
      var checked = Boolean(prefs[key]);
      sw.setAttribute("aria-checked", checked ? "true" : "false");
      sw.className =
        "relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition-colors " +
        (checked
          ? "border-[#D5B77B] bg-[#9273B5]"
          : "border-[#4D445C] bg-[#17151C]");
      var knob = sw.querySelector("[data-a11y-knob]");
      if (knob) {
        knob.className =
          "inline-block h-5 w-5 rounded-full bg-[#F9F7FC] transition-transform " +
          (checked ? "translate-x-6" : "translate-x-1");
      }
    }

    // TalkBack speech rate buttons
    var srRateBtns = document.querySelectorAll("[data-sr-rate]");
    for (var r = 0; r < srRateBtns.length; r++) {
      var rateBtn = srRateBtns[r];
      var rateVal = Number(rateBtn.getAttribute("data-sr-rate"));
      var isRateActive = rateVal === prefs.speechRate;
      rateBtn.setAttribute("aria-pressed", isRateActive ? "true" : "false");
      rateBtn.classList.toggle("is-active", isRateActive);
    }

    // Reading Guide Band
    var guideEl = document.getElementById("a11y-reading-guide");
    if (guideEl) {
      if (prefs.readingGuide) {
        guideEl.classList.remove("hidden");
        guideEl.style.top = String(guideY) + "px";
      } else {
        guideEl.classList.add("hidden");
      }
    }
  }

  function updatePrefs(patch, options) {
    var prevSerialized = JSON.stringify(prefs);
    prefs = sanitizePrefs(Object.assign({}, prefs, patch), getOsReducedMotion());
    var nextSerialized = JSON.stringify(prefs);

    applyPrefsToDocument();
    savePrefs();
    syncPanelUi();

    var silent = options && options.silent === true;
    if (!silent && nextSerialized !== prevSerialized) {
      announce(describePrefChange(patch), "polite");
    }
    dispatchChanged();
  }

  function resetPrefs() {
    prefs = Object.assign({}, DEFAULT_PREFS);
    if (window.PFG27_SR && typeof window.PFG27_SR.stop === "function") {
      window.PFG27_SR.stop();
    }
    applyPrefsToDocument();
    try {
      window.localStorage.removeItem(STORAGE_KEY);
      window.localStorage.removeItem(LEGACY_STORAGE_KEY);
    } catch (e) {
      // Ignore
    }
    syncPanelUi();
    announce("All accessibility preferences reset to default.", "polite");
    dispatchChanged();
  }

  function setPanelOpen(nextOpen) {
    panelOpen = Boolean(nextOpen);
    var modal = document.getElementById("a11y-preferences-modal");
    var triggers = document.querySelectorAll('[data-a11y-trigger="true"]');
    for (var i = 0; i < triggers.length; i++) {
      triggers[i].setAttribute("aria-expanded", panelOpen ? "true" : "false");
    }

    if (!modal) return;

    if (panelOpen) {
      previouslyFocused = document.activeElement;
      modal.classList.remove("hidden");
      modal.classList.add("flex");
      var closeBtn = document.getElementById("a11y-panel-close");
      if (closeBtn && typeof closeBtn.focus === "function") {
        closeBtn.focus();
      }
    } else {
      modal.classList.remove("flex");
      modal.classList.add("hidden");
      if (previouslyFocused && typeof previouslyFocused.focus === "function") {
        previouslyFocused.focus();
      } else if (triggers.length > 0 && typeof triggers[0].focus === "function") {
        triggers[0].focus();
      }
      previouslyFocused = null;
    }
  }

  function togglePanel() {
    setPanelOpen(!panelOpen);
  }

  // Apply immediately on script load to avoid flash of unstyled preferences (no FOUC)
  loadPrefs();
  applyPrefsToDocument();

  // Export clean global accessibility helpers
  window.pfgAnnounce = announce;
  window.pfgIsReducedMotion = isReducedMotion;

  window.PFG27_A11Y = {
    getPrefs: function () {
      return Object.assign({}, prefs);
    },
    updatePrefs: updatePrefs,
    resetPrefs: resetPrefs,
    announce: announce,
    isReducedMotion: isReducedMotion,
    openPanel: function () {
      setPanelOpen(true);
    },
    closePanel: function () {
      setPanelOpen(false);
    },
    togglePanel: togglePanel,
  };

  function initDomBindings() {
    // Ensure live regions exist silently without announcing on page load
    ensureLiveRegion("polite");
    syncPanelUi();

    // Watch OS prefers-reduced-motion changes dynamically
    try {
      if (window.matchMedia) {
        var mq = window.matchMedia("(prefers-reduced-motion: reduce)");
        var onOsMotionChange = function () {
          dispatchChanged();
        };
        if (typeof mq.addEventListener === "function") {
          mq.addEventListener("change", onOsMotionChange);
        } else if (typeof mq.addListener === "function") {
          mq.addListener(onOsMotionChange);
        }
      }
    } catch (e) {
      // Ignore
    }

    // Accessibility Trigger Buttons
    var triggers = document.querySelectorAll('[data-a11y-trigger="true"]');
    for (var i = 0; i < triggers.length; i++) {
      triggers[i].addEventListener("click", function (e) {
        e.preventDefault();
        togglePanel();
      });
    }

    // Modal Backdrop & Close Button
    var modal = document.getElementById("a11y-preferences-modal");
    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          setPanelOpen(false);
        }
      });
    }

    var closeBtn = document.getElementById("a11y-panel-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        setPanelOpen(false);
      });
    }

    var closeLinks = document.querySelectorAll('[data-a11y-close-link="true"]');
    for (var k = 0; k < closeLinks.length; k++) {
      closeLinks[k].addEventListener("click", function () {
        setPanelOpen(false);
      });
    }

    // Text Scale Controls
    var decBtn = document.getElementById("a11y-scale-decrease");
    if (decBtn) {
      decBtn.addEventListener("click", function () {
        var idx = TEXT_SCALES.indexOf(prefs.textScale);
        if (idx > 0) {
          updatePrefs({ textScale: TEXT_SCALES[idx - 1] });
        }
      });
    }

    var resetScaleBtn = document.getElementById("a11y-scale-reset");
    if (resetScaleBtn) {
      resetScaleBtn.addEventListener("click", function () {
        updatePrefs({ textScale: 100 });
      });
    }

    var incBtn = document.getElementById("a11y-scale-increase");
    if (incBtn) {
      incBtn.addEventListener("click", function () {
        var idx = TEXT_SCALES.indexOf(prefs.textScale);
        if (idx < TEXT_SCALES.length - 1) {
          updatePrefs({ textScale: TEXT_SCALES[idx + 1] });
        }
      });
    }

    // Contrast Radio Buttons (click + ARIA radiogroup Arrow key navigation)
    var contrastBtns = document.querySelectorAll("[data-a11y-contrast]");
    for (var c = 0; c < contrastBtns.length; c++) {
      (function (btnIndex) {
        var btn = contrastBtns[btnIndex];
        btn.addEventListener("click", function () {
          var nextContrast = this.getAttribute("data-a11y-contrast");
          if (nextContrast) {
            updatePrefs({ contrast: nextContrast });
          }
        });
        btn.addEventListener("keydown", function (e) {
          if (
            e.key === "ArrowDown" ||
            e.key === "ArrowRight" ||
            e.key === "ArrowUp" ||
            e.key === "ArrowLeft"
          ) {
            e.preventDefault();
            var dir = e.key === "ArrowDown" || e.key === "ArrowRight" ? 1 : -1;
            var nextIdx = (btnIndex + dir + contrastBtns.length) % contrastBtns.length;
            var nextBtn = contrastBtns[nextIdx];
            var nextContrast = nextBtn.getAttribute("data-a11y-contrast");
            if (nextContrast) {
              updatePrefs({ contrast: nextContrast });
            }
            if (typeof nextBtn.focus === "function") {
              nextBtn.focus();
            }
          }
        });
      })(c);
    }

    // Switch Toggles
    var switchBtns = document.querySelectorAll("[data-a11y-toggle]");
    for (var s = 0; s < switchBtns.length; s++) {
      switchBtns[s].addEventListener("click", function () {
        var key = this.getAttribute("data-a11y-toggle");
        if (key && Object.prototype.hasOwnProperty.call(prefs, key)) {
          var patch = {};
          patch[key] = !prefs[key];
          updatePrefs(patch);
        }
      });
    }

    // Reset All Button
    var resetAllBtn = document.getElementById("a11y-reset-all");
    if (resetAllBtn) {
      resetAllBtn.addEventListener("click", function () {
        resetPrefs();
      });
    }

    // Reading Guide Tracking
    window.addEventListener(
      "mousemove",
      function (e) {
        if (!prefs.readingGuide) return;
        guideY = e.clientY;
        var guideEl = document.getElementById("a11y-reading-guide");
        if (guideEl) {
          guideEl.style.top = String(guideY) + "px";
        }
      },
      { passive: true }
    );

    window.addEventListener(
      "focusin",
      function (e) {
        if (!prefs.readingGuide) return;
        var target = e.target;
        if (target && typeof target.getBoundingClientRect === "function") {
          var rect = target.getBoundingClientRect();
          if (rect.top >= 0 && rect.top <= window.innerHeight) {
            guideY = rect.top + rect.height / 2;
            var guideEl = document.getElementById("a11y-reading-guide");
            if (guideEl) {
              guideEl.style.top = String(guideY) + "px";
            }
          }
        }
      },
      { passive: true }
    );

    // Mobile Navigation Drawer Toggle
    var mobileToggle = document.getElementById("mobile-nav-toggle");
    var mobileDrawer = document.getElementById("mobile-nav-drawer");
    var iconOpen = document.getElementById("mobile-nav-icon-open");
    var iconClose = document.getElementById("mobile-nav-icon-close");
    var mobileOpen = false;

    function setMobileOpen(open) {
      mobileOpen = Boolean(open);
      if (!mobileToggle || !mobileDrawer) return;
      mobileToggle.setAttribute("aria-expanded", mobileOpen ? "true" : "false");
      mobileToggle.setAttribute(
        "aria-label",
        mobileOpen ? "Close navigation menu" : "Open navigation menu"
      );
      if (mobileOpen) {
        mobileDrawer.classList.remove("hidden");
        if (iconOpen) iconOpen.classList.add("hidden");
        if (iconClose) iconClose.classList.remove("hidden");
      } else {
        mobileDrawer.classList.add("hidden");
        if (iconOpen) iconOpen.classList.remove("hidden");
        if (iconClose) iconClose.classList.add("hidden");
      }
    }

    if (mobileToggle) {
      mobileToggle.addEventListener("click", function () {
        setMobileOpen(!mobileOpen);
      });
    }

    var mobileLinks = document.querySelectorAll('[data-mobile-nav-link="true"]');
    for (var m = 0; m < mobileLinks.length; m++) {
      mobileLinks[m].addEventListener("click", function () {
        setMobileOpen(false);
      });
    }

    // Back to Top Button
    var backToTopBtn = document.getElementById("pfg-back-to-top");
    if (backToTopBtn) {
      backToTopBtn.addEventListener("click", function (e) {
        e.preventDefault();
        window.scrollTo({
          top: 0,
          behavior: isReducedMotion() ? "instant" : "smooth",
        });
        var mainEl = document.getElementById("main-content");
        if (mainEl && typeof mainEl.focus === "function") {
          mainEl.focus({ preventScroll: true });
        }
      });
    }

    // Keyboard Escape & Tab Focus Trap for Accessibility Panel and Mobile Drawer
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        if (panelOpen) {
          e.preventDefault();
          setPanelOpen(false);
          return;
        }
        if (mobileOpen) {
          e.preventDefault();
          setMobileOpen(false);
          if (mobileToggle && typeof mobileToggle.focus === "function") {
            mobileToggle.focus();
          }
          return;
        }
      }

      if (e.key === "Tab" && panelOpen) {
        var dialog = document.getElementById("a11y-preferences-dialog");
        if (!dialog) return;
        var focusable = dialog.querySelectorAll(
          'a[href], button:not([disabled]):not([tabindex="-1"]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDomBindings);
  } else {
    initDomBindings();
  }
})();
