/**
 * Purple Festival Gujarat 2027 — Accessibility Preferences & Navigation Controller
 * Exact Vanilla JS port of src/components/AccessibilityProvider.tsx & src/components/AccessibilityPanel.tsx
 */
(function () {
  "use strict";

  var STORAGE_KEY = "pfg27_a11y_prefs";
  var LEGACY_STORAGE_KEY = "pfg27_a11y_prefs_v1";
  var TEXT_SCALES = [90, 100, 115, 130, 150, 175, 200];

  var DEFAULT_PREFS = {
    textScale: 100,
    contrast: "default",
    reducedMotion: false,
    wideSpacing: false,
    highlightLinks: false,
    readingGuide: false,
  };

  var prefs = {
    textScale: 100,
    contrast: "default",
    reducedMotion: false,
    wideSpacing: false,
    highlightLinks: false,
    readingGuide: false,
  };

  var panelOpen = false;
  var previouslyFocused = null;
  var guideY = 240;

  function getOsReducedMotion() {
    try {
      return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (e) {
      return false;
    }
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

    return {
      textScale: scale,
      contrast: contrast,
      reducedMotion: reducedMotion,
      wideSpacing: Boolean(rawObj && rawObj.wideSpacing),
      highlightLinks: Boolean(rawObj && rawObj.highlightLinks),
      readingGuide: Boolean(rawObj && rawObj.readingGuide),
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
    root.style.fontSize = String(prefs.textScale) + "%";
  }

  function announce(message) {
    var liveEl = document.getElementById("a11y-live-status");
    if (liveEl) {
      liveEl.textContent = message;
    }
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

  function updatePrefs(patch) {
    prefs = sanitizePrefs(Object.assign({}, prefs, patch), getOsReducedMotion());
    applyPrefsToDocument();
    savePrefs();
    syncPanelUi();
    announce("Accessibility preferences updated.");
    dispatchChanged();
  }

  function resetPrefs() {
    prefs = Object.assign({}, DEFAULT_PREFS);
    applyPrefsToDocument();
    try {
      window.localStorage.removeItem(STORAGE_KEY);
      window.localStorage.removeItem(LEGACY_STORAGE_KEY);
    } catch (e) {
      // Ignore
    }
    syncPanelUi();
    announce("All accessibility preferences reset to default.");
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

  // Apply immediately on script load to avoid flash of unstyled preferences
  loadPrefs();
  applyPrefsToDocument();

  window.PFG27_A11Y = {
    getPrefs: function () {
      return Object.assign({}, prefs);
    },
    updatePrefs: updatePrefs,
    resetPrefs: resetPrefs,
    openPanel: function () {
      setPanelOpen(true);
    },
    closePanel: function () {
      setPanelOpen(false);
    },
    togglePanel: togglePanel,
  };

  function initDomBindings() {
    syncPanelUi();

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

    // Contrast Radio Buttons
    var contrastBtns = document.querySelectorAll("[data-a11y-contrast]");
    for (var c = 0; c < contrastBtns.length; c++) {
      contrastBtns[c].addEventListener("click", function () {
        var nextContrast = this.getAttribute("data-a11y-contrast");
        if (nextContrast) {
          updatePrefs({ contrast: nextContrast });
        }
      });
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
        var isReduced = prefs.reducedMotion || getOsReducedMotion();
        window.scrollTo({
          top: 0,
          behavior: isReduced ? "instant" : "smooth",
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
          'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
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
