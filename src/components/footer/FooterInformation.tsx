"use client";

import React from "react";
import Link from "next/link";
import { ArrowUp, ArrowUpRight, Mail, Phone, MapPin, Calendar } from "lucide-react";
import { PurpleFestEmblemSvg } from "../SiteHeader";
import { AnimationControls } from "./AnimationControls";

interface FooterInformationProps {
  paused: boolean;
  reducedMotion: boolean;
  onTogglePause: () => void;
}

const EXPLORE_LINKS = [
  { label: "Home", href: "/" },
  { label: "About", href: "/#about" },
  { label: "Programme", href: "/programme" },
  { label: "Accessibility", href: "/accessibility" },
  { label: "Partners", href: "/#partners" },
  { label: "Gallery", href: "/#gallery" },
  { label: "Registration", href: "/apply" },
  { label: "Login", href: "/login" },
] as const;

export function FooterInformation({
  paused,
  reducedMotion,
  onTogglePause,
}: FooterInformationProps) {
  const handleBackToTop = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: reducedMotion ? "instant" : "smooth",
    });
    const main = document.getElementById("main-content");
    if (main) {
      main.focus({ preventScroll: true });
    }
  };

  return (
    <div className="bg-[#17151C] text-[#F9F7FC]" data-surface="dark">
      <div className="mx-auto max-w-[1380px] px-5 pt-12 pb-6 sm:px-8 sm:pt-16">
        {/* Top 3-Column Editorial Information Grid */}
        <div className="grid gap-10 md:grid-cols-12 lg:gap-12">
          {/* Left: Festival Identity */}
          <div className="space-y-3.5 md:col-span-4">
            <div className="flex items-center gap-3">
              <PurpleFestEmblemSvg className="h-9 w-auto text-[#E8DFF0]" />
              <div>
                <h2 className="text-xl font-extrabold tracking-tight text-[#F9F7FC] sm:text-2xl">
                  PURPLE FESTIVAL GUJARAT 2027
                </h2>
              </div>
            </div>
            <p className="max-w-sm text-sm leading-relaxed text-[#B6A1C8]">
              Celebrating accessibility, inclusion and diversity.
            </p>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-1 text-xs text-[#E8DFF0]/85">
              <span className="inline-flex items-center gap-1.5">
                <Calendar className="h-3.5 w-3.5 text-[#A459FF]" aria-hidden="true" />
                21–23 January 2027
              </span>
              <span className="inline-flex items-center gap-1.5">
                <MapPin className="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true" />
                Vadodara, Gujarat
              </span>
            </div>
          </div>

          {/* Center: Explore Navigation */}
          <nav
            aria-label="Footer navigation"
            className="space-y-3 md:col-span-4"
          >
            <p className="text-xs font-semibold tracking-[0.14em] uppercase text-[#B5B5B5]">
              Explore
            </p>
            <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3 md:grid-cols-2">
              {EXPLORE_LINKS.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="inline-flex min-h-[34px] items-center font-medium text-[#F8F7F4] transition-colors hover:text-[#B6A1C8] hover:underline"
                >
                  {item.label}
                </Link>
              ))}
            </div>
          </nav>

          {/* Right: Get in Touch & Policies */}
          <div className="space-y-3 md:col-span-4">
            <p className="text-xs font-semibold tracking-[0.14em] uppercase text-[#B5B5B5]">
              Get in touch
            </p>
            <div className="space-y-2 text-sm">
              <a
                href="mailto:purplefestivalvadodara@gmail.com"
                className="inline-flex min-h-[34px] items-center gap-1.5 font-medium text-[#F8F7F4] transition-colors hover:text-[#B6A1C8] hover:underline"
              >
                <Mail className="h-4 w-4 shrink-0 text-[#B6A1C8]" aria-hidden="true" />
                <span className="break-all">purplefestivalvadodara@gmail.com</span>
                <ArrowUpRight className="h-4 w-4 shrink-0 text-[#B5B5B5]" aria-hidden="true" />
              </a>

              <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#E8DFF0]/90">
                <a
                  href="tel:+919974383920"
                  className="inline-flex min-h-[32px] items-center gap-1.5 hover:text-white hover:underline"
                >
                  <Phone className="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true" />
                  <span>Radha Tandon: +91 99743 83920</span>
                </a>
                <a
                  href="tel:+919211760202"
                  className="inline-flex min-h-[32px] items-center gap-1.5 hover:text-white hover:underline"
                >
                  <Phone className="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true" />
                  <span>Satish Kapoor: +91 92117 60202</span>
                </a>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 text-xs text-[#B5B5B5]">
              <Link href="/legal/privacy" className="hover:text-white hover:underline">
                Privacy Policy
              </Link>
              <span aria-hidden="true">·</span>
              <Link href="/legal/terms" className="hover:text-white hover:underline">
                Terms
              </Link>
              <span aria-hidden="true">·</span>
              <Link
                href="/legal/accessibility-statement"
                className="hover:text-white hover:underline"
              >
                Accessibility Statement
              </Link>
            </div>
          </div>
        </div>

        {/* Finale Heading Bar right above the Animated Crowd Strip */}
        <div className="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6">
          <p className="text-[clamp(24px,3vw,40px)] font-medium leading-[1.15] tracking-[-0.03em] text-[#F8F7F4]">
            Everyone belongs here.
          </p>

          <div className="flex flex-wrap items-center gap-3">
            <AnimationControls
              paused={paused}
              reducedMotion={reducedMotion}
              onTogglePause={onTogglePause}
            />

            <a
              href="#main-content"
              onClick={handleBackToTop}
              className="touch-target inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs font-medium text-[#F8F7F4] transition-colors hover:border-white/40 hover:bg-white/5"
            >
              <span>Back to top</span>
              <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

