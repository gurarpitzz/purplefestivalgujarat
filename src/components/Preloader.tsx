"use client";

import React, { useEffect, useRef, useState, useCallback } from "react";
import gsap from "gsap";
import { useAccessibility } from "./AccessibilityProvider";

const SESSION_KEY = "pfg27_intro_seen";

declare global {
  interface Window {
    __preloaderTimeline?: gsap.core.Timeline;
  }
}

export function Preloader() {
  const { prefs } = useAccessibility();
  const [visible, setVisible] = useState(false);
  const [announcement, setAnnouncement] = useState("");
  const containerRef = useRef<HTMLDivElement>(null);
  const backdropRef = useRef<HTMLSpanElement>(null);
  const contentWrapRef = useRef<HTMLDivElement>(null);
  const svgRef = useRef<SVGSVGElement>(null);
  const coreRef = useRef<SVGGElement>(null);
  const timelineRef = useRef<gsap.core.Timeline | null>(null);
  const finishedRef = useRef(false);

  const revealHeaderLogo = useCallback(() => {
    const headerLogo = document.querySelector<HTMLElement>("#header-logo, [data-header-logo]");
    if (headerLogo) {
      headerLogo.style.opacity = "1";
    }
  }, []);

  const finishIntro = useCallback(
    (focusMain = false) => {
      if (finishedRef.current) return;
      finishedRef.current = true;
      try {
        window.sessionStorage.setItem(SESSION_KEY, "1");
      } catch {
        // Ignore storage errors
      }
      document.body.style.overflow = "";
      revealHeaderLogo();
      window.dispatchEvent(new CustomEvent("pfg27:preloader-finished"));
      setAnnouncement("Purple Festival Gujarat 2027 loaded.");
      setVisible(false);

      if (focusMain) {
        const mainEl = document.getElementById("main-content");
        if (mainEl) {
          mainEl.focus({ preventScroll: true });
        }
      }
    },
    [revealHeaderLogo]
  );

  const handleSkip = useCallback(() => {
    if (timelineRef.current) {
      timelineRef.current.kill();
      timelineRef.current = null;
    }
    finishIntro(true);
  }, [finishIntro]);

  useEffect(() => {
    const prefersReduced =
      window.matchMedia("(prefers-reduced-motion: reduce)").matches || prefs.reducedMotion;

    // Play on fresh load or page reload; skip on in-session SPA returns
    const navEntries = performance.getEntriesByType("navigation") as PerformanceNavigationTiming[];
    const isReload = navEntries.length > 0 && navEntries[0].type === "reload";

    let alreadySeen = false;
    try {
      alreadySeen = !isReload && window.sessionStorage.getItem(SESSION_KEY) === "1";
    } catch {
      alreadySeen = false;
    }

    if (prefersReduced || alreadySeen) {
      finishIntro(false);
      return;
    }

    // Hide header logo briefly while preloader flies into it (matching original site)
    const headerLogo = document.querySelector<HTMLElement>("#header-logo, [data-header-logo]");
    if (headerLogo) {
      headerLogo.style.opacity = "0";
    }

    setVisible(true);
  }, [prefs.reducedMotion, finishIntro]);

  // Exact 3.53s GSAP choreography from purplefestivalvadodara-main.ajeetgraphics.com
  useEffect(() => {
    if (!visible) return;

    const container = containerRef.current;
    const backdrop = backdropRef.current;
    const wrap = contentWrapRef.current;
    const svg = svgRef.current;
    if (!container || !backdrop || !wrap || !svg) {
      finishIntro(false);
      return;
    }

    document.body.style.overflow = "hidden";

    const failsafeTimer = window.setTimeout(() => {
      finishIntro(false);
    }, 2600);

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        handleSkip();
      }
    };
    window.addEventListener("keydown", onKeyDown);

    try {
      const leftPath = svg.querySelector<SVGPathElement>('[data-draw="left-line"]');
      const ribbonPath = svg.querySelector<SVGPathElement>('[data-draw="emblem-ribbon"]');
      const rightPath = svg.querySelector<SVGPathElement>('[data-draw="right-line"]');
      const heart1 = svg.querySelector<SVGPathElement>('[data-heart="1"]');
      const heart2 = svg.querySelector<SVGPathElement>('[data-heart="2"]');
      const heart3 = svg.querySelector<SVGPathElement>('[data-heart="3"]');
      const titleEl = svg.querySelector<SVGTextElement>('[data-draw-text="title"]');
      const subtitleEl = svg.querySelector<SVGTextElement>('[data-draw-text="subtitle"]');

      const leftLen = leftPath ? Math.ceil(leftPath.getTotalLength()) || 680 : 680;
      const ribbonLen = ribbonPath ? Math.ceil(ribbonPath.getTotalLength()) || 1480 : 1480;
      const rightLen = rightPath ? Math.ceil(rightPath.getTotalLength()) || 680 : 680;

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
      const hearts = [heart1, heart2, heart3].filter(Boolean);
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
      const hEl = document.querySelector<HTMLElement>("#header-logo, [data-header-logo]");
      const yRect = hEl
        ? hEl.getBoundingClientRect()
        : { left: 40, top: 20, width: 186, height: 31 };
      const svgRect = svg.getBoundingClientRect();
      const coreRect = coreRef.current ? coreRef.current.getBoundingClientRect() : svgRect;

      const rawScale = Math.min(
        yRect.width / (coreRect.width || 300),
        (yRect.height * 1.65) / (coreRect.height || 240)
      );
      const scale = Number.isFinite(rawScale) && rawScale > 0.05 ? rawScale : 0.25;
      const offsetX = coreRect.left + coreRect.width / 2 - svgRect.left;
      const offsetY = coreRect.top + coreRect.height / 2 - svgRect.top;
      const tx = yRect.left + yRect.width / 2 - (svgRect.left + offsetX * scale);
      const ty = yRect.top + yRect.height / 2 - (svgRect.top + offsetY * scale);

      const tl = gsap.timeline({
        onComplete: () => {
          gsap.delayedCall(0.03, () => finishIntro(false));
        },
      });
      // Accelerate playback so the full choreography + page load finishes well under 3 seconds
      tl.timeScale(1.62);

      timelineRef.current = tl;
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
        () => {
          document.body.style.overflow = "";
          window.dispatchEvent(new CustomEvent("pfg27:preloader-finished"));
        },
        [],
        2.7
      );

      tl.to(
        svg,
        {
          x: tx,
          y: ty,
          scale,
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
      tl.call(() => revealHeaderLogo(), [], 3.22);

      // 3.25s -> 3.50s: Crossfade preloader SVG out
      tl.to(svg, { opacity: 0, duration: 0.25, ease: "power2.out" }, 3.25);
    } catch {
      finishIntro(false);
    }

    return () => {
      window.clearTimeout(failsafeTimer);
      window.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = "";
      if (timelineRef.current) {
        timelineRef.current.kill();
        timelineRef.current = null;
      }
    };
  }, [visible, finishIntro, handleSkip, revealHeaderLogo]);

  return (
    <>
      <div role="status" aria-live="polite" className="sr-only">
        {announcement}
      </div>

      {visible && (
        <div
          ref={containerRef}
          role="region"
          aria-label="Purple Festival Gujarat 2027 Introductory Animation"
          className="fixed left-0 top-0 z-[1001] flex h-full w-full items-center justify-center overflow-hidden text-[#202D59]"
        >
          {/* Exact #DDDDF9 Lavender-Periwinkle Curtain Backdrop */}
          <span
            ref={backdropRef}
            aria-hidden="true"
            className="fixed left-0 top-0 -z-10 h-screen w-full bg-[#DDDDF9]"
          />

          {/* Subtle, Accessible Skip Intro Button */}
          <button
            type="button"
            onClick={handleSkip}
            className="touch-target fixed bottom-6 right-6 z-[1002] inline-flex items-center gap-2 rounded border-2 border-[#202D59]/30 bg-[#DDDDF9]/90 px-4 py-2 text-sm font-semibold text-[#202D59] transition-colors hover:border-[#202D59] hover:bg-[#202D59] hover:text-white"
          >
            <span>Skip Intro</span>
            <span aria-hidden="true" className="text-xs opacity-70">
              ESC
            </span>
          </button>

          {/* Animated SVG Logo Container — Exact original #202D59 monochrome ink */}
          <div
            ref={contentWrapRef}
            aria-hidden="true"
            className="relative flex w-full items-center justify-center opacity-0"
          >
            <div className="relative flex w-full items-center justify-center overflow-visible">
              <svg
                ref={svgRef}
                width="900"
                height="320"
                viewBox="0 0 900 320"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                style={{ width: "min(88vw, 860px)", height: "auto", overflow: "visible" }}
                aria-hidden="true"
                focusable="false"
              >
                {/* 1. Left horizontal lead-in line */}
                <path
                  data-draw="left-line"
                  d="M -280 156 L 312 156 C 338 156 350 110 360.15 118.4"
                  fill="none"
                  stroke="#202D59"
                  strokeWidth="6.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />

                {/* 2. Core Logo Group */}
                <g ref={coreRef} data-logo-core="true">
                  {/* Continuous 3-figure calligraphic emblem ribbon */}
                  <path
                    data-draw="emblem-ribbon"
                    d="M 360.15 118.4 C 350.74 157.88 368.27 174.29 374.54 171.88 C 380.81 169.47 369.56 135.42 374.54 133.58 C 379.58 131.78 395.88 170.65 402.09 168.24 C 408.36 165.78 399.63 115.38 366.42 95.33 C 333.21 75.9 318.2 97.12 326.94 109.89 C 336.96 123.27 363.28 107.48 377.06 83.18 C 397.72 46.72 435.3 46.72 460.33 84.97 C 477.24 111.12 470.97 171.88 462.24 174.29 C 453.44 176.75 445.94 127.53 439.67 127.53 C 433.4 127.53 425.89 176.75 418.39 174.29 C 410.88 171.88 408.36 111.12 429.03 84.97 C 454.06 52.77 497.91 54.62 524.84 83.18 C 539.85 100.2 564.94 117.17 573.68 105.07 C 581.18 94.1 564.94 77.07 535.48 95.33 C 506.08 113.53 493.54 163.98 499.81 168.24 C 506.08 171.88 522.32 131.78 527.36 133.58 C 532.35 135.42 521.09 169.47 527.36 171.88 C 533.64 174.29 548.03 157.88 539.85 118.4"
                    fill="none"
                    stroke="#202D59"
                    strokeWidth="6.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />

                  {/* 3 Hearts above the 3 figures — exact #202D59 */}
                  <path
                    data-heart="1"
                    d="M 356.96 43.64 C 349.4 34.96 335.12 36.92 336.24 48.12 C 337.36 58.2 353.04 65.2 364.8 68.84 C 369.28 58.2 371.52 41.96 362.56 36.08 C 355.84 31.6 354.72 40.28 356.96 43.64 Z"
                    fill="#202D59"
                  />
                  <path
                    data-heart="2"
                    d="M 447.68 26.84 C 442.08 14.52 428.08 14.52 428.08 26.84 C 428.08 38.04 442.08 45.88 447.68 52.88 C 453.28 45.88 467.28 38.04 467.28 26.84 C 467.28 14.52 453.28 14.52 447.68 26.84 Z"
                    fill="#202D59"
                  />
                  <path
                    data-heart="3"
                    d="M 536.72 43.64 C 540.08 40.28 538.96 31.6 532.24 36.08 C 523.28 41.96 525.52 58.2 530 68.84 C 541.76 65.2 557.44 58.2 558.56 48.12 C 559.68 36.92 545.4 34.96 536.72 43.64 Z"
                    fill="#202D59"
                  />

                  {/* Rebranded Gujarat 2027 Wordmark — exact #202D59 geometric sans & scale */}
                  <g>
                    <text
                      data-draw-text="title"
                      x="450"
                      y="228"
                      textAnchor="middle"
                      fill="#202D59"
                      stroke="#202D59"
                      strokeWidth="1.2"
                      fontFamily="var(--font-dm-sans), Arial, sans-serif"
                      fontWeight="700"
                      fontSize="50"
                      letterSpacing="1.2"
                    >
                      PURPLE FESTIVAL
                    </text>
                    <text
                      data-draw-text="subtitle"
                      x="450"
                      y="274"
                      textAnchor="middle"
                      fill="#202D59"
                      stroke="#202D59"
                      strokeWidth="0.9"
                      fontFamily="var(--font-dm-sans), Arial, sans-serif"
                      fontWeight="500"
                      fontSize="36"
                      letterSpacing="0.5"
                    >
                      Gujarat 2027
                    </text>
                  </g>
                </g>

                {/* 3. Right horizontal exit line */}
                <path
                  data-draw="right-line"
                  d="M 539.85 118.4 C 550 110 562 156 588 156 L 1180 156"
                  fill="none"
                  stroke="#202D59"
                  strokeWidth="6.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
