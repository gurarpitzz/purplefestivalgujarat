/**
 * Purple Festival Gujarat 2027 — Introductory Preloader Animation
 * Exact Vanilla JS + GSAP port of src/components/Preloader.tsx
 */
(function () {
  "use strict";

  var SESSION_KEY = "pfg27_intro_seen";

  function initPreloader() {
    var container = document.getElementById("pfg-preloader");
    if (!container) return;

    var backdrop = document.getElementById("pfg-preloader-backdrop");
    var wrap = document.getElementById("pfg-preloader-wrap");
    var svg = document.getElementById("pfg-preloader-svg");
    var core = svg ? svg.querySelector('[data-logo-core="true"]') : null;
    var skipBtn = document.getElementById("pfg-preloader-skip");
    var liveStatus = document.getElementById("pfg-preloader-status");

    var finished = false;
    var tl = null;
    var failsafeTimer = 0;

    function revealHeaderLogo() {
      var headerLogo = document.querySelector("#header-logo, [data-header-logo]");
      if (headerLogo) {
        headerLogo.style.opacity = "1";
      }
    }

    function finishIntro(focusMain) {
      if (finished) return;
      finished = true;
      if (failsafeTimer) {
        window.clearTimeout(failsafeTimer);
        failsafeTimer = 0;
      }
      window.removeEventListener("keydown", onKeyDown);

      try {
        window.sessionStorage.setItem(SESSION_KEY, "1");
      } catch (e) {
        // Ignore storage errors
      }

      document.body.style.overflow = "";
      revealHeaderLogo();

      if (container) {
        container.classList.add("hidden");
        container.setAttribute("aria-hidden", "true");
      }

      if (liveStatus) {
        liveStatus.textContent = "Purple Festival Gujarat 2027 loaded.";
      }

      try {
        window.dispatchEvent(new CustomEvent("pfg27:preloader-finished"));
      } catch (e) {
        // Ignore
      }

      if (focusMain) {
        var mainEl = document.getElementById("main-content");
        if (mainEl && typeof mainEl.focus === "function") {
          mainEl.focus({ preventScroll: true });
        }
      }
    }

    function handleSkip() {
      if (tl) {
        tl.kill();
        tl = null;
      }
      finishIntro(true);
    }

    function onKeyDown(e) {
      if (e.key === "Escape") {
        e.preventDefault();
        handleSkip();
      }
    }

    if (skipBtn) {
      skipBtn.addEventListener("click", function (e) {
        e.preventDefault();
        handleSkip();
      });
    }

    var osReduced = false;
    try {
      osReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (e) {
      osReduced = false;
    }

    var a11yReduced =
      document.documentElement.getAttribute("data-reduced-motion") === "true" ||
      (window.PFG27_A11Y && window.PFG27_A11Y.getPrefs().reducedMotion);

    var prefersReduced = Boolean(osReduced || a11yReduced);

    var isReload = false;
    try {
      var navEntries = performance.getEntriesByType("navigation");
      isReload = Boolean(navEntries && navEntries.length > 0 && navEntries[0].type === "reload");
    } catch (e) {
      isReload = false;
    }

    var alreadySeen = false;
    try {
      alreadySeen = !isReload && window.sessionStorage.getItem(SESSION_KEY) === "1";
    } catch (e) {
      alreadySeen = false;
    }

    if (prefersReduced || alreadySeen || typeof window.gsap === "undefined" || !backdrop || !wrap || !svg) {
      finishIntro(false);
      return;
    }

    var gsap = window.gsap;

    // Show preloader container and temporarily hide #header-logo while preloader flies into it
    container.classList.remove("hidden");
    container.classList.add("flex");
    var headerLogo = document.querySelector("#header-logo, [data-header-logo]");
    if (headerLogo) {
      headerLogo.style.opacity = "0";
    }

    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", onKeyDown);

    failsafeTimer = window.setTimeout(function () {
      finishIntro(false);
    }, 3800);

    try {
      var leftPath = svg.querySelector('[data-draw="left-line"]');
      var ribbonPath = svg.querySelector('[data-draw="emblem-ribbon"]');
      var rightPath = svg.querySelector('[data-draw="right-line"]');
      var heart1 = svg.querySelector('[data-heart="1"]');
      var heart2 = svg.querySelector('[data-heart="2"]');
      var heart3 = svg.querySelector('[data-heart="3"]');
      var titleEl = svg.querySelector('[data-draw-text="title"]');
      var subtitleEl = svg.querySelector('[data-draw-text="subtitle"]');

      var leftLen = leftPath && leftPath.getTotalLength ? Math.ceil(leftPath.getTotalLength()) || 680 : 680;
      var ribbonLen = ribbonPath && ribbonPath.getTotalLength ? Math.ceil(ribbonPath.getTotalLength()) || 1480 : 1480;
      var rightLen = rightPath && rightPath.getTotalLength ? Math.ceil(rightPath.getTotalLength()) || 680 : 680;

      gsap.set(wrap, { opacity: 1 });
      if (leftPath) {
        gsap.set(leftPath, {
          strokeDasharray: leftLen,
          strokeDashoffset: leftLen,
          opacity: 1,
        });
      }
      if (ribbonPath) {
        gsap.set(ribbonPath, {
          strokeDasharray: ribbonLen,
          strokeDashoffset: ribbonLen,
          opacity: 0,
        });
      }
      if (rightPath) {
        gsap.set(rightPath, {
          strokeDasharray: rightLen,
          strokeDashoffset: rightLen,
          opacity: 0,
        });
      }

      var hearts = [heart1, heart2, heart3].filter(Boolean);
      if (hearts.length > 0) {
        gsap.set(hearts, {
          scale: 0,
          opacity: 0,
          transformOrigin: "50% 85%",
        });
      }

      if (titleEl) {
        gsap.set(titleEl, {
          strokeDasharray: 340,
          strokeDashoffset: 340,
          fillOpacity: 0,
          opacity: 0,
          y: 8,
        });
      }
      if (subtitleEl) {
        gsap.set(subtitleEl, {
          strokeDasharray: 240,
          strokeDashoffset: 240,
          fillOpacity: 0,
          opacity: 0,
          y: 8,
        });
      }

      // Dynamic Header-Docking Math matching original site
      var hEl = document.querySelector("#header-logo, [data-header-logo]");
      var yRect = hEl
        ? hEl.getBoundingClientRect()
        : { left: 40, top: 20, width: 186, height: 31 };
      var svgRect = svg.getBoundingClientRect();
      var coreRect = core ? core.getBoundingClientRect() : svgRect;

      var rawScale = Math.min(
        yRect.width / (coreRect.width || 300),
        (yRect.height * 1.65) / (coreRect.height || 240)
      );
      var scale = Number.isFinite(rawScale) && rawScale > 0.05 ? rawScale : 0.25;
      var offsetX = coreRect.left + coreRect.width / 2 - svgRect.left;
      var offsetY = coreRect.top + coreRect.height / 2 - svgRect.top;
      var tx = yRect.left + yRect.width / 2 - (svgRect.left + offsetX * scale);
      var ty = yRect.top + yRect.height / 2 - (svgRect.top + offsetY * scale);

      tl = gsap.timeline({
        onComplete: function () {
          gsap.delayedCall(0.06, function () {
            finishIntro(false);
          });
        },
      });

      // Smooth, unhurried playback (~2.95s total) while keeping load + intro right around 3s
      tl.timeScale(1.18);
      window.__preloaderTimeline = tl;

      // 0.00s -> 0.55s: Left horizontal lead-in line
      if (leftPath) {
        tl.to(leftPath, { strokeDashoffset: 0, duration: 0.55, ease: "power2.in" }, 0.0);
      }

      // 0.55s -> 1.85s: Continuous 3-figure calligraphic emblem ribbon
      if (ribbonPath) {
        tl.set(ribbonPath, { opacity: 1 }, 0.55);
        tl.to(ribbonPath, { strokeDashoffset: 0, duration: 1.3, ease: "none" }, 0.55);
      }

      // 0.85s, 1.25s, 1.65s: Three hearts pop in with spring overshoot
      if (heart1) {
        tl.to(heart1, { scale: 1, opacity: 1, duration: 0.32, ease: "back.out(2.2)" }, 0.85);
      }
      if (heart2) {
        tl.to(heart2, { scale: 1, opacity: 1, duration: 0.32, ease: "back.out(2.2)" }, 1.25);
      }
      if (heart3) {
        tl.to(heart3, { scale: 1, opacity: 1, duration: 0.32, ease: "back.out(2.2)" }, 1.65);
      }

      // 1.35s -> 1.93s: Title ("PURPLE FESTIVAL") outline draw + solid fill
      if (titleEl) {
        tl.to(
          titleEl,
          { opacity: 1, y: 0, strokeDashoffset: 0, duration: 0.55, ease: "power2.out" },
          1.35
        );
        tl.to(
          titleEl,
          { fillOpacity: 1, strokeWidth: 0, duration: 0.35, ease: "power2.out" },
          1.58
        );
      }

      // 1.85s -> 2.40s: Right exit line + Subtitle ("Gujarat 2027")
      if (rightPath) {
        tl.set(rightPath, { opacity: 1 }, 1.85);
        tl.to(rightPath, { strokeDashoffset: 0, duration: 0.55, ease: "power2.out" }, 1.85);
      }
      if (subtitleEl) {
        tl.to(
          subtitleEl,
          { opacity: 1, y: 0, strokeDashoffset: 0, duration: 0.5, ease: "power2.out" },
          1.85
        );
        tl.to(
          subtitleEl,
          { fillOpacity: 1, strokeWidth: 0, duration: 0.35, ease: "power2.out" },
          2.05
        );
      }

      // 2.50s -> 2.78s: Both side lead-in/out lines wipe away cleanly
      if (leftPath) {
        tl.to(
          leftPath,
          { strokeDashoffset: -leftLen, opacity: 0, duration: 0.28, ease: "power2.inOut" },
          2.5
        );
      }
      if (rightPath) {
        tl.to(
          rightPath,
          { strokeDashoffset: -rightLen, opacity: 0, duration: 0.28, ease: "power2.inOut" },
          2.5
        );
      }

      // 2.70s: Unlock scroll & fly SVG into #header-logo
      tl.call(
        function () {
          document.body.style.overflow = "";
          try {
            window.dispatchEvent(new CustomEvent("pfg27:preloader-finished"));
          } catch (e) {
            // Ignore
          }
        },
        [],
        2.7
      );

      tl.to(
        svg,
        {
          x: tx,
          y: ty,
          scale: scale,
          transformOrigin: "top left",
          duration: 0.82,
          ease: "power3.inOut",
        },
        2.7
      );

      // 3.05s -> 3.53s: Exact #DDDDF9 lavender curtain lifts upward
      tl.to(
        backdrop,
        {
          yPercent: -100,
          opacity: 0,
          duration: 0.48,
          ease: "power3.inOut",
        },
        3.05
      );

      // 3.22s: Reveal #header-logo
      tl.call(
        function () {
          revealHeaderLogo();
        },
        [],
        3.22
      );

      // 3.25s -> 3.50s: Crossfade preloader SVG out
      tl.to(svg, { opacity: 0, duration: 0.25, ease: "power2.out" }, 3.25);
    } catch (e) {
      finishIntro(false);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPreloader);
  } else {
    initPreloader();
  }
})();
