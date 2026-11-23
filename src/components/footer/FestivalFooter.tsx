"use client";

import React, { useEffect, useState } from "react";
import { useAccessibility } from "../AccessibilityProvider";
import { FooterInformation } from "./FooterInformation";
import { InclusiveCrowd } from "./InclusiveCrowd";
import { FooterCopyright } from "./FooterCopyright";

export function FestivalFooter() {
  const { prefs } = useAccessibility();
  const [paused, setPaused] = useState(false);
  const [osReducedMotion, setOsReducedMotion] = useState(false);

  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const update = () => setOsReducedMotion(mq.matches);
    update();
    mq.addEventListener("change", update);
    return () => mq.removeEventListener("change", update);
  }, []);

  const reducedMotion = Boolean(prefs.reducedMotion || osReducedMotion);

  const handleTogglePause = () => {
    setPaused((prev) => !prev);
  };

  return (
    <footer className="w-full bg-[#17151C] text-[#F9F7FC]" data-surface="dark">
      {/* PART A — Charcoal Information Section & Finale Heading */}
      <FooterInformation
        paused={paused}
        reducedMotion={reducedMotion}
        onTogglePause={handleTogglePause}
      />

      {/* PART B — Full-Width White Animated Inclusive Crowd Strip */}
      <InclusiveCrowd
        paused={paused}
        reducedMotion={reducedMotion}
        onTogglePause={handleTogglePause}
      />

      {/* PART C — Bottom Charcoal Copyright Strip */}
      <FooterCopyright />
    </footer>
  );
}

