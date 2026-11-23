"use client";

import React, { useEffect, useRef } from "react";
import { WelcomeCharacters } from "./WelcomeCharacters";
import { CROWD_CHARACTER_REGISTRY } from "../illustrations/characters";

interface CrowdMarqueeProps {
  paused: boolean;
  reducedMotion: boolean;
  onTogglePause: () => void;
  descriptionId: string;
}

const CROWD_SHEETS = [
  "/assets/footer/crowd-sheet-01.png?v=3",
  "/assets/footer/crowd-sheet-02.png?v=3",
  "/assets/footer/crowd-sheet-03.png?v=3",
  "/assets/footer/crowd-sheet-04.png?v=3",
];

const COLS = 5;
const ROWS = 3;
const CHARS_PER_SHEET = COLS * ROWS; // 15
const TOTAL_CAST = CROWD_SHEETS.length * CHARS_PER_SHEET; // 60 unique illustrated characters
const CELL_ASPECT = 440 / 580;

interface CrowdActor {
  actorId: number;
  charIdx: number; // Strictly unique character index (0..59) — never shared with any other active actor!
  lastWrapCount: number;
  tier: 0 | 1 | 2; // 0 = Background (R->L), 1 = Midground (L->R), 2 = Foreground (R->L)
  canvasLayer: 0 | 1; // 0 = backCanvas (z-index 1), 1 = frontCanvas (z-index 2)
  h: number;
  w: number;
  baseline: number;
  speedPxPerSec: number;
  phase: number;
  direction: 1 | -1;
  bobAmp: number;
  bobFreq: number;
}

function seededRandom(seed: number): number {
  const x = Math.sin(seed * 127.1 + 311.7) * 43758.5453;
  return x - Math.floor(x);
}

/**
 * Deterministic Fisher-Yates shuffle of [0..TOTAL_CAST-1] so every active actor
 * receives a strictly unique character with zero duplicates on screen.
 */
function createShuffledCastPool(): number[] {
  const pool = Array.from({ length: TOTAL_CAST }, (_, i) => i);
  for (let i = pool.length - 1; i > 0; i--) {
    const j = Math.floor(seededRandom(i * 19 + 73) * (i + 1));
    const tmp = pool[i];
    pool[i] = pool[j];
    pool[j] = tmp;
  }
  return pool;
}

export function CrowdMarquee({
  paused,
  reducedMotion,
  onTogglePause,
  descriptionId,
}: CrowdMarqueeProps) {
  const sceneRef = useRef<HTMLButtonElement>(null);
  const backCanvasRef = useRef<HTMLCanvasElement>(null);
  const frontCanvasRef = useRef<HTMLCanvasElement>(null);
  const stateRef = useRef({
    paused,
    reducedMotion,
    sync: () => {},
  });

  useEffect(() => {
    stateRef.current.paused = paused;
    stateRef.current.reducedMotion = reducedMotion;
    stateRef.current.sync();
  }, [paused, reducedMotion]);

  useEffect(() => {
    const sceneEl = sceneRef.current;
    const backCanvas = backCanvasRef.current;
    const frontCanvas = frontCanvasRef.current;
    if (!sceneEl || !backCanvas || !frontCanvas) return;

    const backCtx = backCanvas.getContext("2d");
    const frontCtx = frontCanvas.getContext("2d");
    if (!backCtx || !frontCtx) return;

    const ctxs = [backCtx, frontCtx];
    const imgs = CROWD_SHEETS.map((src) => {
      const im = new Image();
      im.decoding = "async";
      im.src = src;
      return im;
    });

    let unmounted = false;
    let assetsReady = false;
    let inViewport = false;
    let width = 0;
    let height = 0;
    let dpr = 1;
    let elapsedSec = 0;
    let lastTimestamp = 0;
    let rafId = 0;
    let maxActorWidth = 0;
    let totalTrackSpan = 0;
    let actors: CrowdActor[] = [];
    let reserveQueue: number[] = [];

    const drawFrame = () => {
      ctxs.forEach((ctx) => {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
      });

      if (!assetsReady || totalTrackSpan <= 0) return;

      const centerX = width / 2;
      const centerClearanceRadius = Math.min(240, width * 0.24);

      for (let i = 0; i < actors.length; i++) {
        const actor = actors[i];
        const rawProgress =
          actor.phase + (elapsedSec * actor.speedPxPerSec) / totalTrackSpan;
        const wrapCount = Math.floor(rawProgress);
        const norm = rawProgress - wrapCount;

        // When an actor wraps off-screen, swap its character with an off-screen reserve character
        // so two actors on screen NEVER share the same character!
        if (wrapCount !== actor.lastWrapCount) {
          actor.lastWrapCount = wrapCount;
          if (reserveQueue.length > 0) {
            const nextChar = reserveQueue.shift()!;
            reserveQueue.push(actor.charIdx);
            actor.charIdx = nextChar;
          }
        }

        const charIdx = actor.charIdx;
        const meta = CROWD_CHARACTER_REGISTRY[charIdx];
        const charScale = meta?.scale ?? 1.0;

        const sheetIdx = Math.floor(charIdx / CHARS_PER_SHEET);
        const sheet = imgs[sheetIdx];
        if (!sheet || !sheet.naturalWidth) continue;

        const cellW = sheet.naturalWidth / COLS;
        const cellH = sheet.naturalHeight / ROWS;
        const localIdx = charIdx % CHARS_PER_SHEET;
        const sx = (localIdx % COLS) * cellW;
        const sy = Math.floor(localIdx / COLS) * cellH;

        const x =
          (actor.direction === 1 ? norm : 1 - norm) * totalTrackSpan -
          maxActorWidth;

        const bob = stateRef.current.reducedMotion
          ? 0
          : (1 -
              Math.cos(
                (elapsedSec * actor.bobFreq + actor.phase * 10) * Math.PI * 2
              )) *
            actor.bobAmp;

        // Smooth courtesy dip behind the central welcoming trio so crowd heads don't photobomb right behind the hosts' faces
        const distFromCenter = Math.abs(x - centerX);
        const centerDip =
          distFromCenter < centerClearanceRadius
            ? Math.cos((distFromCenter / centerClearanceRadius) * (Math.PI / 2)) *
              34
            : 0;

        const drawW = actor.w * charScale;
        const drawH = actor.h * charScale;
        const yPos = actor.baseline + (actor.h - drawH) + centerDip - bob;

        const ctx = ctxs[actor.canvasLayer];
        ctx.save();
        ctx.translate(x, yPos);
        ctx.scale(actor.direction, 1);
        ctx.drawImage(
          sheet,
          sx,
          sy,
          cellW,
          cellH,
          -drawW / 2,
          0,
          drawW,
          drawH
        );
        ctx.restore();
      }
    };

    const tick = (now: number) => {
      if (lastTimestamp) {
        elapsedSec += Math.min((now - lastTimestamp) / 1000, 0.05);
      }
      lastTimestamp = now;
      drawFrame();
      rafId = requestAnimationFrame(tick);
    };

    const syncAnimation = () => {
      if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = 0;
      }
      lastTimestamp = 0;
      if (!assetsReady || unmounted) return;

      drawFrame();

      const shouldAnimate =
        inViewport &&
        !document.hidden &&
        !stateRef.current.paused &&
        !stateRef.current.reducedMotion;

      if (shouldAnimate) {
        rafId = requestAnimationFrame(tick);
      }
    };

    stateRef.current.sync = syncAnimation;

    const rebuildLayout = () => {
      if (!assetsReady || unmounted) return;
      const newW = sceneEl.clientWidth;
      const newH = sceneEl.clientHeight;
      if (!newW || !newH) return;
      if (newW === width && newH === height) return;

      width = newW;
      height = newH;
      dpr = Math.min(window.devicePixelRatio || 1, 2);

      [backCanvas, frontCanvas].forEach((canvas) => {
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
      });

      const isDesktop = width >= 960;
      const isTablet = width >= 600 && width < 960;
      const scale = isDesktop ? 1 : isTablet ? 0.82 : 0.66;

      // Total active actors is always <= 34 (well below TOTAL_CAST = 60), guaranteeing 100% unique characters on screen!
      const counts: [number, number, number] = isDesktop
        ? [11, 11, 12]
        : isTablet
          ? [8, 8, 8]
          : [5, 5, 5];

      const shuffledPool = createShuffledCastPool();
      const totalActive = counts[0] + counts[1] + counts[2];
      reserveQueue = shuffledPool.slice(totalActive);

      const nextActors: CrowdActor[] = [];
      let globalIdx = 0;

      for (let tier = 0 as 0 | 1 | 2; tier < 3; tier = (tier + 1) as 0 | 1 | 2) {
        const tierCount = counts[tier];
        for (let i = 0; i < tierCount; i++) {
          const r1 = seededRandom(globalIdx + 7);
          const r2 = seededRandom(globalIdx + 23);
          const r3 = seededRandom(globalIdx + 59);
          const r4 = seededRandom(globalIdx + 97);

          let charH: number;
          let charW: number;
          let baseline: number;
          let speedPxPerSec: number;
          let direction: 1 | -1;
          let canvasLayer: 0 | 1;
          let bobAmp: number;

          // Every cell is 440x580 (with upper 415px original bust and lower 165px continuous torso extension).
          // Setting baseline so baseline + charH > height ensures every character's body reaches the bottom floor (zero mid-air cut-offs!).
          if (tier === 0) {
            // BACKGROUND CROWD: Smaller head scale, higher head position, moves slowly Right -> Left (17–24 px/s)
            charH = (325 + 18 * r1) * scale;
            charW = charH * CELL_ASPECT * 0.86;
            baseline = height - charH + (26 + 12 * r2) * scale;
            speedPxPerSec = 17 + 7 * r3;
            direction = -1;
            canvasLayer = 0;
            bobAmp = 2.2 * scale;
          } else if (tier === 1) {
            // MIDGROUND CROWD: Medium head scale, middle head position, moves Left -> Right (27–36 px/s)
            charH = (318 + 18 * r1) * scale;
            charW = charH * CELL_ASPECT * 0.94;
            baseline = height - charH + (64 + 12 * r2) * scale;
            speedPxPerSec = 27 + 9 * r3;
            direction = 1;
            canvasLayer = 0;
            bobAmp = 3.2 * scale;
          } else {
            // FOREGROUND CROWD: Larger head scale, front row position, moves Right -> Left (38–48 px/s)
            charH = (312 + 20 * r1) * scale;
            charW = charH * CELL_ASPECT * 1.02;
            baseline = height - charH + (104 + 10 * r2) * scale;
            speedPxPerSec = 38 + 10 * r3;
            direction = -1;
            canvasLayer = 1;
            bobAmp = 4.0 * scale;
          }

          nextActors.push({
            actorId: globalIdx,
            charIdx: shuffledPool[globalIdx],
            lastWrapCount: 0,
            tier,
            canvasLayer,
            h: charH,
            w: charW,
            baseline,
            speedPxPerSec,
            phase: (i + 0.45 * r4) / tierCount,
            direction,
            bobAmp,
            bobFreq: 0.72 + 0.32 * r3,
          });
          globalIdx++;
        }
      }

      nextActors.sort((a, b) => a.baseline - b.baseline);
      actors = nextActors;
      maxActorWidth = Math.max(...actors.map((a) => a.w), 180);
      totalTrackSpan = width + 2 * maxActorWidth;
      syncAnimation();
    };

    const io = new IntersectionObserver(
      (entries) => {
        inViewport = Boolean(entries[0]?.isIntersecting);
        syncAnimation();
      },
      { rootMargin: "100px" }
    );
    io.observe(sceneEl);

    const ro = new ResizeObserver(rebuildLayout);
    ro.observe(sceneEl);

    document.addEventListener("visibilitychange", syncAnimation);

    Promise.all(
      imgs.map((im) =>
        (im.decode ? im.decode().catch(() => {}) : Promise.resolve()).then(() => {
          if (unmounted) return;
          if (!assetsReady) {
            assetsReady = true;
            rebuildLayout();
          } else {
            syncAnimation();
          }
        })
      )
    ).then(() => {
      if (unmounted) return;
      assetsReady = true;
      rebuildLayout();
    });

    return () => {
      unmounted = true;
      if (rafId) cancelAnimationFrame(rafId);
      io.disconnect();
      ro.disconnect();
      document.removeEventListener("visibilitychange", syncAnimation);
      stateRef.current.sync = () => {};
    };
  }, []);

  const isStopped = paused || reducedMotion;

  return (
    <button
      ref={sceneRef}
      type="button"
      onClick={onTogglePause}
      disabled={reducedMotion}
      aria-label={
        isStopped
          ? "PURPLE FESTIVAL. Resume inclusive crowd animation"
          : "PURPLE FESTIVAL. Pause inclusive crowd animation"
      }
      aria-describedby={descriptionId}
      aria-pressed={isStopped}
      data-cast-size={TOTAL_CAST}
      className="group relative block h-[270px] w-full cursor-pointer overflow-hidden border-0 bg-white p-0 text-left text-[#141414] isolation-isolate sm:h-[330px] lg:h-[395px] disabled:cursor-default focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-[#141414]"
    >
      {/* Large Faint Editorial Background Typography (5–9% opacity equivalence behind crowd) */}
      <span
        aria-hidden="true"
        className="pointer-events-none select-none absolute left-0 top-2 z-0 w-full text-center font-extrabold leading-none tracking-[-0.04em] text-[#EFEFEF] text-[clamp(56px,11.8vw,210px)]"
      >
        EVERYONE BELONGS
      </span>

      {/* Layer 1: Background & Midground Crowd Canvas (R->L and L->R behind foreground) */}
      <canvas
        ref={backCanvasRef}
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 z-[1] h-full w-full"
      />

      {/* Layer 2: Foreground Crowd Canvas (R->L flush with bottom border) */}
      <canvas
        ref={frontCanvasRef}
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 z-[2] h-full w-full"
      />

      {/* Layer 3 (z-index 10): High-Contrast Purple-Attire Stationary Central Welcoming Trio */}
      <WelcomeCharacters />
    </button>
  );
}
