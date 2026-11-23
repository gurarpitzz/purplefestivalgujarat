import React from "react";
import type { IllustrationProps } from "./StatueOfUnity";

/**
 * Gujarati Lakeside Pavilion (Lower-Right Hero Background Illustration)
 * Renders the high-resolution transparent-alpha architectural engraving of
 * the domed chhatri pavilion, flanking trees, tranquil lake reflections,
 * and bottom-right botanical sprig.
 */
export function GujaratPavilion({
  className = "",
  strokeColor = "#9783C5",
  secondaryStrokeColor = "#B5A4DF",
  opacity,
}: IllustrationProps) {
  return (
    <svg
      viewBox="0 0 728 502"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={opacity !== undefined ? { opacity } : undefined}
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/gujarat-pavilion.png"
        x="0"
        y="0"
        width="728"
        height="502"
        preserveAspectRatio="xMaxYMax meet"
      />
      {/* Subtle vector shoreline continuation */}
      <path
        d="M 36 244 Q 260 226 520 228 T 728 232"
        stroke={secondaryStrokeColor}
        strokeOpacity="0.30"
        strokeWidth="1.05"
        strokeLinecap="round"
      />
      <path
        d="M 110 472 Q 360 398 640 422"
        stroke={strokeColor}
        strokeOpacity="0.24"
        strokeWidth="0.95"
        strokeLinecap="round"
      />
    </svg>
  );
}
