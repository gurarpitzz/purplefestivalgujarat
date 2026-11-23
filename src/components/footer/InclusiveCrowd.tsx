"use client";

import React, { useId } from "react";
import { CrowdMarquee } from "./CrowdMarquee";

interface InclusiveCrowdProps {
  paused: boolean;
  reducedMotion: boolean;
  onTogglePause: () => void;
}

export function InclusiveCrowd({
  paused,
  reducedMotion,
  onTogglePause,
}: InclusiveCrowdProps) {
  const descriptionId = useId();

  return (
    <section
      aria-label="Inclusive Community Crowd Illustration"
      className="relative w-full bg-white"
    >
      {/* Single meaningful screen-reader description (keeps individual decorative sprites hidden) */}
      <p id={descriptionId} className="sr-only">
        An illustrated crowd of diverse people, including people with disabilities, moving
        together around three welcoming festival representatives: a young Indian woman in a
        traditional Gujarati outfit offering a Namaste gesture, a smiling man seated in a
        modern manual wheelchair, and a young man with a prosthetic lower limb.
      </p>

      <CrowdMarquee
        paused={paused}
        reducedMotion={reducedMotion}
        onTogglePause={onTogglePause}
        descriptionId={descriptionId}
      />
    </section>
  );
}

