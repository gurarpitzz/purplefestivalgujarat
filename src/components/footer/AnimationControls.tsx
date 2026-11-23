"use client";

import React from "react";
import { Pause, Play } from "lucide-react";

interface AnimationControlsProps {
  paused: boolean;
  reducedMotion: boolean;
  onTogglePause: () => void;
}

export function AnimationControls({
  paused,
  reducedMotion,
  onTogglePause,
}: AnimationControlsProps) {
  const isStopped = paused || reducedMotion;

  return (
    <button
      type="button"
      onClick={onTogglePause}
      disabled={reducedMotion}
      aria-pressed={isStopped}
      aria-label={
        reducedMotion
          ? "Crowd animation paused due to reduced motion preference"
          : isStopped
            ? "Resume animated footer crowd"
            : "Pause animated footer crowd"
      }
      className="touch-target inline-flex items-center gap-2 rounded-full border border-white/25 bg-[#23202B] px-4 py-2 text-xs font-semibold tracking-wide text-[#F9F7FC] transition-colors hover:border-[#B6A1C8] hover:bg-[#2E2A38] disabled:cursor-default disabled:opacity-60"
    >
      {isStopped ? (
        <>
          <Play className="h-3.5 w-3.5 fill-current text-[#D5B77B]" aria-hidden="true" />
          <span>{reducedMotion ? "Motion Reduced" : "Resume Animation"}</span>
        </>
      ) : (
        <>
          <Pause className="h-3.5 w-3.5 fill-current text-[#B6A1C8]" aria-hidden="true" />
          <span>Pause Animation</span>
        </>
      )}
    </button>
  );
}

