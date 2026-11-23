import React from "react";
import type { IllustrationProps } from "./StatueOfUnity";

/**
 * Adalaj Stepwell (Lower-Left Hero Background Illustration)
 * Renders the high-resolution transparent-alpha architectural engraving of
 * Adalaj ni Vav's carved Gujarati pillars, recessed vaults, jali balconies,
 * flanking foliage, and lower-left botanical sprig.
 */
export function AdalajStepwell({
  className = "",
  strokeColor = "#9783C5",
  secondaryStrokeColor = "#B5A4DF",
  opacity,
}: IllustrationProps) {
  return (
    <svg
      viewBox="0 0 720 572"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={opacity !== undefined ? { opacity } : undefined}
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/adalaj-stepwell.png"
        x="0"
        y="0"
        width="720"
        height="572"
        preserveAspectRatio="xMinYMax meet"
      />
      {/* Subtle vector horizon continuation */}
      <path
        d="M 320 404 Q 495 362 695 438"
        stroke={secondaryStrokeColor}
        strokeOpacity="0.32"
        strokeWidth="1.1"
        strokeLinecap="round"
      />
      <path
        d="M 0 448 Q 210 478 460 546"
        stroke={strokeColor}
        strokeOpacity="0.24"
        strokeWidth="0.95"
        strokeLinecap="round"
      />
    </svg>
  );
}
