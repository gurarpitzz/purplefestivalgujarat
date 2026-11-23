import React from "react";

export interface IllustrationProps {
  className?: string;
  strokeColor?: string;
  secondaryStrokeColor?: string;
  opacity?: number;
}

/**
 * Statue of Unity (Upper-Left Hero Background Illustration)
 * Renders the high-resolution transparent-alpha architectural engraving of
 * Sardar Vallabhbhai Patel's Statue of Unity, colonnade base, and Vindhya hills.
 */
export function StatueOfUnity({
  className = "",
  strokeColor = "#9783C5",
  secondaryStrokeColor = "#B5A4DF",
  opacity,
}: IllustrationProps) {
  return (
    <svg
      viewBox="0 0 700 440"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={opacity !== undefined ? { opacity } : undefined}
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/statue-of-unity.png"
        x="0"
        y="0"
        width="700"
        height="440"
        preserveAspectRatio="xMinYMid meet"
      />
      {/* Subtle vector contour continuation */}
      <path
        d="M 0 382 Q 210 356 450 388 T 690 416"
        stroke={secondaryStrokeColor}
        strokeOpacity="0.35"
        strokeWidth="1.1"
        strokeLinecap="round"
      />
      <path
        d="M 118 332 L 264 312 L 442 354"
        stroke={strokeColor}
        strokeOpacity="0.22"
        strokeWidth="0.9"
        strokeLinecap="round"
      />
    </svg>
  );
}
