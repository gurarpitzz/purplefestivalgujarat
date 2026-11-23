import React from "react";
import type { IllustrationProps } from "./StatueOfUnity";

/**
 * Somnath Temple (Upper-Right Hero Background Illustration)
 * Renders the high-resolution transparent-alpha architectural engraving of
 * Somnath Temple's Chalukya Maha-Shikhara, fluttering flag, Sabha Mandapa,
 * coastal trees, and soaring birds.
 */
export function SomnathTemple({
  className = "",
  strokeColor = "#9783C5",
  secondaryStrokeColor = "#B5A4DF",
  opacity,
}: IllustrationProps) {
  return (
    <svg
      viewBox="0 0 728 430"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={opacity !== undefined ? { opacity } : undefined}
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/somnath-temple.png"
        x="0"
        y="0"
        width="728"
        height="430"
        preserveAspectRatio="xMaxYMid meet"
      />
      {/* Subtle vector coastal hill continuation */}
      <path
        d="M 24 388 Q 260 334 510 316 T 728 324"
        stroke={secondaryStrokeColor}
        strokeOpacity="0.32"
        strokeWidth="1.1"
        strokeLinecap="round"
      />
      <path
        d="M 160 398 Q 410 344 728 352"
        stroke={strokeColor}
        strokeOpacity="0.22"
        strokeWidth="0.95"
        strokeLinecap="round"
      />
    </svg>
  );
}
