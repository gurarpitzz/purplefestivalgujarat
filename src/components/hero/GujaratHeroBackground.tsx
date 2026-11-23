import React from "react";
import { StatueOfUnity } from "@/components/illustrations/StatueOfUnity";
import { AdalajStepwell } from "@/components/illustrations/AdalajStepwell";
import { SomnathTemple } from "@/components/illustrations/SomnathTemple";
import { GujaratPavilion } from "@/components/illustrations/GujaratPavilion";

interface GujaratHeroBackgroundProps {
  strokeColor?: string;
  secondaryStrokeColor?: string;
}

/**
 * Purely presentational, non-interactive architectural landscape background
 * for the homepage hero section. Positions the four high-resolution
 * transparent-alpha Gujarat landmark illustrations and connecting horizon
 * waves around the outer perimeter while keeping the central 65–70%
 * typography, primary CTA, and festival emblem unobstructed.
 */
export function GujaratHeroBackground({
  strokeColor = "#9783C5",
  secondaryStrokeColor = "#B5A4DF",
}: GujaratHeroBackgroundProps) {
  return (
    <div
      aria-hidden="true"
      className="pointer-events-none select-none absolute inset-0 z-0 overflow-hidden"
    >
      {/* Subtle luminous radial center wash keeping headline & body copy spotless */}
      <div
        className="absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse 56% 52% at 50% 42%, rgba(246, 239, 255, 0.78) 0%, rgba(242, 233, 254, 0.35) 64%, rgba(235, 224, 252, 0.55) 100%)",
        }}
      />

      {/* CENTER-BOTTOM CONNECTING HORIZON WAVES */}
      <svg
        viewBox="0 0 1360 272"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        className="absolute bottom-0 left-[14%] h-auto w-[72%] opacity-85 md:opacity-95"
        aria-hidden="true"
        focusable="false"
      >
        <image
          href="/assets/illustrations/horizon-waves.png"
          x="0"
          y="0"
          width="1360"
          height="272"
          preserveAspectRatio="none"
        />
        <path
          d="M 40 218 Q 340 162 660 212 T 1320 174"
          stroke={secondaryStrokeColor}
          strokeOpacity="0.32"
          strokeWidth="1.2"
          strokeLinecap="round"
        />
      </svg>

      {/* UPPER LEFT — Statue of Unity */}
      <div className="absolute left-0 top-[1%] w-[155px] opacity-75 sm:top-[2%] sm:w-[225px] sm:opacity-90 md:top-[4%] md:w-[295px] md:opacity-95 lg:w-[375px] lg:opacity-100 xl:top-[5%] xl:w-[445px]">
        <StatueOfUnity
          className="h-auto w-full"
          strokeColor={strokeColor}
          secondaryStrokeColor={secondaryStrokeColor}
        />
      </div>

      {/* LOWER LEFT — Adalaj Stepwell */}
      <div className="hidden md:block md:absolute md:bottom-0 md:left-0 md:w-[310px] md:opacity-95 lg:w-[400px] lg:opacity-100 xl:w-[475px]">
        <AdalajStepwell
          className="h-auto w-full"
          strokeColor={strokeColor}
          secondaryStrokeColor={secondaryStrokeColor}
        />
      </div>

      {/* UPPER RIGHT — Somnath Temple */}
      <div className="absolute right-0 top-[2%] w-[165px] opacity-75 sm:top-[4%] sm:w-[235px] sm:opacity-90 md:top-[7%] md:w-[300px] md:opacity-95 lg:w-[385px] lg:opacity-100 xl:top-[9%] xl:w-[455px]">
        <SomnathTemple
          className="h-auto w-full"
          strokeColor={strokeColor}
          secondaryStrokeColor={secondaryStrokeColor}
        />
      </div>

      {/* LOWER RIGHT — Gujarati Lakeside Pavilion */}
      <div className="hidden md:block md:absolute md:bottom-0 md:right-0 md:w-[310px] md:opacity-95 lg:w-[400px] lg:opacity-100 xl:w-[480px]">
        <GujaratPavilion
          className="h-auto w-full"
          strokeColor={strokeColor}
          secondaryStrokeColor={secondaryStrokeColor}
        />
      </div>
    </div>
  );
}
