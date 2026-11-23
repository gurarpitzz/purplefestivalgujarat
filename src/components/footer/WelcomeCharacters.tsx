"use client";

import React from "react";

interface WelcomeCharactersProps {
  className?: string;
}

/**
 * Stationary Central Welcoming Group (z-index: 10).
 * Dressed in signature Purple Festival Gujarat royal purple & lavender so they
 * stand out with immediate visual contrast against the black-and-white moving crowd,
 * surrounded by a subtle radial white backdrop glow and 7px white sticker contour.
 */
export function WelcomeCharacters({ className = "" }: WelcomeCharactersProps) {
  return (
    <div
      aria-hidden="true"
      className={`pointer-events-none select-none absolute bottom-0 left-1/2 z-[10] -translate-x-1/2 flex items-end justify-center ${className}`}
      style={{
        height: "min(368px, calc(100% - 4px))",
      }}
    >
      {/* Soft radial white stage halo behind the welcoming trio so crowd lines never clutter their faces */}
      <div
        className="pointer-events-none absolute inset-x-[-28px] bottom-0 top-[-12px] -z-10"
        style={{
          background:
            "radial-gradient(ellipse 54% 62% at 50% 56%, rgba(255,255,255,0.92) 0%, rgba(255,255,255,0.62) 55%, rgba(255,255,255,0) 100%)",
        }}
      />
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        id="pfg-welcome-trio-img"
        src="/assets/footer/welcoming-trio.png?v=4"
        alt=""
        width={680}
        height={480}
        loading="eager"
        decoding="async"
        className="h-full w-auto max-w-none object-contain object-bottom drop-shadow-[0_3px_10px_rgba(166,154,216,0.22)]"
      />
    </div>
  );
}
