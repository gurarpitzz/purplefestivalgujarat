"use client";

import React, { useState, useEffect, useRef } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, X, Accessibility } from "lucide-react";
import { useAccessibility } from "./AccessibilityProvider";

const NAV_LINKS = [
  { label: "Programme & Zones", href: "/programme" },
  { label: "About", href: "/#about" },
  { label: "Accessibility", href: "/accessibility" },
  { label: "Partners", href: "/#partners" },
  { label: "Gallery", href: "/#gallery" },
  { label: "Contact", href: "/#contact" },
] as const;

export function PurpleFestEmblemSvg({ className = "h-8 w-auto" }: { className?: string }) {
  return (
    <svg
      viewBox="315 10 270 175"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
      focusable="false"
    >
      <path
        d="M 360.15 118.4 C 350.74 157.88 368.27 174.29 374.54 171.88 C 380.81 169.47 369.56 135.42 374.54 133.58 C 379.58 131.78 395.88 170.65 402.09 168.24 C 408.36 165.78 399.63 115.38 366.42 95.33 C 333.21 75.9 318.2 97.12 326.94 109.89 C 336.96 123.27 363.28 107.48 377.06 83.18 C 397.72 46.72 435.3 46.72 460.33 84.97 C 477.24 111.12 470.97 171.88 462.24 174.29 C 453.44 176.75 445.94 127.53 439.67 127.53 C 433.4 127.53 425.89 176.75 418.39 174.29 C 410.88 171.88 408.36 111.12 429.03 84.97 C 454.06 52.77 497.91 54.62 524.84 83.18 C 539.85 100.2 564.94 117.17 573.68 105.07 C 581.18 94.1 564.94 77.07 535.48 95.33 C 506.08 113.53 493.54 163.98 499.81 168.24 C 506.08 171.88 522.32 131.78 527.36 133.58 C 532.35 135.42 521.09 169.47 527.36 171.88 C 533.64 174.29 548.03 157.88 539.85 118.4"
        stroke="currentColor"
        strokeWidth="9"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M 356.96 43.64 C 349.4 34.96 335.12 36.92 336.24 48.12 C 337.36 58.2 353.04 65.2 364.8 68.84 C 369.28 58.2 371.52 41.96 362.56 36.08 C 355.84 31.6 354.72 40.28 356.96 43.64 Z"
        fill="currentColor"
      />
      <path
        d="M 447.68 26.84 C 442.08 14.52 428.08 14.52 428.08 26.84 C 428.08 38.04 442.08 45.88 447.68 52.88 C 453.28 45.88 467.28 38.04 467.28 26.84 C 467.28 14.52 453.28 14.52 447.68 26.84 Z"
        fill="currentColor"
      />
      <path
        d="M 536.72 43.64 C 540.08 40.28 538.96 31.6 532.24 36.08 C 523.28 41.96 525.52 58.2 530 68.84 C 541.76 65.2 557.44 58.2 558.56 48.12 C 559.68 36.92 545.4 34.96 536.72 43.64 Z"
        fill="currentColor"
      />
    </svg>
  );
}

export function SiteHeader() {
  const pathname = usePathname();
  const { togglePanel, panelOpen } = useAccessibility();
  const [mobileOpen, setMobileOpen] = useState(false);
  const drawerRef = useRef<HTMLDivElement>(null);
  const menuBtnRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!mobileOpen) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        setMobileOpen(false);
        menuBtnRef.current?.focus();
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [mobileOpen]);

  return (
    <header className="sticky top-0 z-50 bg-[#F2E9FE]/95 text-[#202D59] backdrop-blur-md">
      <div className="mx-auto flex w-full max-w-[1440px] items-center justify-between gap-6 px-6 py-4 sm:px-10">
        {/* Flat Crisp PurpleFest Logo (#header-logo docking target for Preloader) */}
        <Link
          id="header-logo"
          data-header-logo="true"
          href="/"
          aria-label="Purple Festival Gujarat 2027 Home"
          className="group flex shrink-0 items-center gap-2.5 text-[#202D59] transition-opacity"
        >
          <PurpleFestEmblemSvg className="h-9 w-auto text-[#202D59]" />
          <span className="text-[23px] font-bold tracking-[-0.02em] text-[#202D59]">
            PurpleFest
          </span>
        </Link>

        {/* Primary Desktop Navigation — exact scale & spacing */}
        <nav aria-label="Primary Navigation" className="hidden items-center gap-8 lg:flex">
          {NAV_LINKS.map((item) => {
            const isActive =
              !item.href.startsWith("/#") && pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                aria-current={isActive ? "page" : undefined}
                className={`text-[16px] font-normal tracking-[-0.01em] transition-colors xl:text-[17px] ${
                  isActive
                    ? "font-semibold text-[#A459FF]"
                    : "text-[#202D59] hover:text-[#A459FF]"
                }`}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>

        {/* Right Controls: Outlined Login (replaces Book Stall), Solid #A459FF Apply Now, Circular Accessibility */}
        <div className="flex items-center gap-3">
          <Link
            href="/login"
            className="touch-target hidden items-center justify-center border-2 border-[#202D59] bg-transparent px-6 py-2 text-[16px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white sm:inline-flex"
          >
            Login
          </Link>

          <Link
            href="/apply"
            className="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-6 py-2 text-[16px] font-semibold text-white transition-colors hover:bg-[#8D3BF2]"
          >
            Apply Now
          </Link>

          <button
            type="button"
            data-a11y-trigger="true"
            onClick={togglePanel}
            aria-expanded={panelOpen}
            aria-label="Open Accessibility Preferences"
            title="Accessibility Preferences"
            className="touch-target inline-flex h-11 w-11 items-center justify-center rounded-full bg-[#1C0D30] text-white shadow-sm transition-transform hover:scale-105 hover:bg-[#202D59]"
          >
            <Accessibility className="h-5 w-5" aria-hidden="true" />
          </button>

          <button
            ref={menuBtnRef}
            type="button"
            onClick={() => setMobileOpen((v) => !v)}
            aria-expanded={mobileOpen}
            aria-controls="mobile-nav-drawer"
            aria-label={mobileOpen ? "Close navigation menu" : "Open navigation menu"}
            className="touch-target inline-flex items-center justify-center border border-[#202D59]/30 bg-white/60 text-[#202D59] hover:border-[#202D59] lg:hidden"
          >
            {mobileOpen ? (
              <X className="h-5 w-5" aria-hidden="true" />
            ) : (
              <Menu className="h-5 w-5" aria-hidden="true" />
            )}
          </button>
        </div>
      </div>

      {/* Responsive Mobile Navigation Drawer */}
      {mobileOpen && (
        <div
          id="mobile-nav-drawer"
          ref={drawerRef}
          className="border-t border-[#202D59]/15 bg-[#DDDDF9] px-5 pb-6 pt-4 lg:hidden"
        >
          <nav aria-label="Mobile Navigation" className="flex flex-col space-y-1">
            {NAV_LINKS.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setMobileOpen(false)}
                className="touch-target flex items-center rounded px-3 py-2.5 text-base font-semibold text-[#202D59] hover:bg-white/60"
              >
                {item.label}
              </Link>
            ))}
          </nav>
          <div className="mt-4 flex flex-col gap-2.5 border-t border-[#202D59]/15 pt-4 sm:hidden">
            <Link
              href="/login"
              onClick={() => setMobileOpen(false)}
              className="touch-target flex items-center justify-center border-2 border-[#202D59] px-4 py-2.5 text-sm font-bold text-[#202D59]"
            >
              Participant & Staff Login
            </Link>
            <Link
              href="/apply"
              onClick={() => setMobileOpen(false)}
              className="btn-primary-pfg touch-target flex items-center justify-center bg-[#A459FF] px-4 py-2.5 text-sm font-bold text-white"
            >
              Apply Now
            </Link>
          </div>
        </div>
      )}
    </header>
  );
}
