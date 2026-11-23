"use client";

import React, { useEffect, useRef } from "react";
import Link from "next/link";
import {
  X,
  RotateCcw,
  Contrast,
  Type,
  Sparkles,
  AlignJustify,
  Link2,
  BookOpen,
  Minus,
  Plus,
} from "lucide-react";
import {
  useAccessibility,
  type ContrastMode,
  type TextScale,
} from "./AccessibilityProvider";

const TEXT_SCALES: TextScale[] = [90, 100, 115, 130, 150, 175, 200];

export function AccessibilityPanel() {
  const { prefs, panelOpen, setPanelOpen, updatePrefs, resetPrefs } = useAccessibility();
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeBtnRef = useRef<HTMLButtonElement>(null);

  // Manage focus & Escape key when dialog opens/closes
  useEffect(() => {
    if (!panelOpen) return;

    const previouslyFocused = document.activeElement as HTMLElement | null;
    closeBtnRef.current?.focus();

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        setPanelOpen(false);
        return;
      }

      if (e.key === "Tab" && dialogRef.current) {
        const focusable = dialogRef.current.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      if (previouslyFocused && typeof previouslyFocused.focus === "function") {
        previouslyFocused.focus();
      } else {
        const trigger = document.querySelector<HTMLElement>('[data-a11y-trigger="true"]');
        trigger?.focus();
      }
    };
  }, [panelOpen, setPanelOpen]);

  if (!panelOpen) return null;

  const currentScaleIndex = TEXT_SCALES.indexOf(prefs.textScale);
  const canDecrease = currentScaleIndex > 0;
  const canIncrease = currentScaleIndex < TEXT_SCALES.length - 1;

  const stepTextScale = (delta: number) => {
    const nextIdx = Math.min(
      TEXT_SCALES.length - 1,
      Math.max(0, currentScaleIndex + delta)
    );
    updatePrefs({ textScale: TEXT_SCALES[nextIdx] });
  };

  const contrastOptions: Array<{ value: ContrastMode; label: string; desc: string }> = [
    { value: "default", label: "Default", desc: "Dusty Purple & Charcoal" },
    { value: "high", label: "High Contrast", desc: "Pure Black & Crisp White" },
    { value: "yellow-black", label: "Yellow on Black", desc: "Maximum Low-Vision Contrast" },
  ];

  const toggleItems: Array<{
    key: "reducedMotion" | "wideSpacing" | "highlightLinks" | "readingGuide";
    label: string;
    description: string;
    icon: React.ReactNode;
    checked: boolean;
  }> = [
    {
      key: "reducedMotion",
      label: "Reduce Motion & Animations",
      description: "Skips intro preloader and disables page transitions.",
      icon: <Sparkles className="h-4 w-4 shrink-0" aria-hidden="true" />,
      checked: prefs.reducedMotion,
    },
    {
      key: "wideSpacing",
      label: "Increased Text & Line Spacing",
      description: "Expands line height, letter, and word spacing.",
      icon: <AlignJustify className="h-4 w-4 shrink-0" aria-hidden="true" />,
      checked: prefs.wideSpacing,
    },
    {
      key: "highlightLinks",
      label: "Highlight Links & Buttons",
      description: "Adds strong underlines and outlines to interactive elements.",
      icon: <Link2 className="h-4 w-4 shrink-0" aria-hidden="true" />,
      checked: prefs.highlightLinks,
    },
    {
      key: "readingGuide",
      label: "Reading Guide Band",
      description: "Displays a horizontal focus bar following pointer or keyboard.",
      icon: <BookOpen className="h-4 w-4 shrink-0" aria-hidden="true" />,
      checked: prefs.readingGuide,
    },
  ];

  return (
    <div
      className="fixed inset-0 z-[1100] flex justify-end bg-black/60 backdrop-blur-xs"
      onClick={(e) => {
        if (e.target === e.currentTarget) setPanelOpen(false);
      }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="a11y-panel-title"
        aria-describedby="a11y-panel-desc"
        className="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-[#383242] bg-[#17151C] text-[#F9F7FC] shadow-2xl"
        data-surface="dark"
      >
        {/* Header */}
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-[#383242] bg-[#17151C] px-6 py-5">
          <div>
            <p className="text-xs font-semibold tracking-[0.2em] uppercase text-[#D5B77B]">
              WCAG 2.2 AA+
            </p>
            <h2 id="a11y-panel-title" className="font-display text-xl font-semibold text-[#F9F7FC]">
              Accessibility Preferences
            </h2>
          </div>
          <button
            ref={closeBtnRef}
            type="button"
            onClick={() => setPanelOpen(false)}
            aria-label="Close accessibility preferences"
            className="touch-target inline-flex items-center justify-center rounded-lg border border-[#4D445C] bg-[#25212B] text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
          >
            <X className="h-5 w-5" aria-hidden="true" />
          </button>
        </div>

        <p id="a11y-panel-desc" className="px-6 pt-4 text-sm text-[#D7CCE2]">
          Personalize display, contrast, spacing, and motion settings. Your preferences are saved on
          this device.
        </p>

        <div className="flex-1 space-y-6 px-6 py-5">
          {/* Text Size */}
          <section aria-labelledby="a11y-text-size-heading">
            <div className="mb-3 flex items-center justify-between">
              <h3
                id="a11y-text-size-heading"
                className="flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
              >
                <Type className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
                Text Size
              </h3>
              <span className="rounded-full bg-[#25212B] px-3 py-1 text-xs font-bold text-[#D5B77B] border border-[#383242]">
                {prefs.textScale}%
              </span>
            </div>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => stepTextScale(-1)}
                disabled={!canDecrease}
                aria-label="Decrease text size"
                className="touch-target inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#4D445C] bg-[#25212B] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Minus className="h-4 w-4" aria-hidden="true" />
                A-
              </button>
              <button
                type="button"
                onClick={() => updatePrefs({ textScale: 100 })}
                className="touch-target inline-flex items-center justify-center rounded-lg border border-[#4D445C] bg-[#25212B] px-3 py-2.5 text-xs font-semibold text-[#D7CCE2] hover:border-[#D5B77B] hover:text-[#F9F7FC]"
              >
                100%
              </button>
              <button
                type="button"
                onClick={() => stepTextScale(1)}
                disabled={!canIncrease}
                aria-label="Increase text size"
                className="touch-target inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#4D445C] bg-[#25212B] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Plus className="h-4 w-4" aria-hidden="true" />
                A+
              </button>
            </div>
          </section>

          {/* Contrast Mode */}
          <fieldset>
            <legend className="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]">
              <Contrast className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
              Color Contrast Mode
            </legend>
            <div className="space-y-2.5" role="radiogroup" aria-label="Color contrast mode">
              {contrastOptions.map((opt) => {
                const selected = prefs.contrast === opt.value;
                return (
                  <button
                    key={opt.value}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    onClick={() => updatePrefs({ contrast: opt.value })}
                    className={`touch-target flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition-colors ${
                      selected
                        ? "border-[#D5B77B] bg-[#2D2538] text-[#F9F7FC]"
                        : "border-[#383242] bg-[#25212B] text-[#D7CCE2] hover:border-[#9273B5]"
                    }`}
                  >
                    <div>
                      <div className="text-sm font-semibold text-[#F9F7FC]">{opt.label}</div>
                      <div className="text-xs text-[#D7CCE2]">{opt.desc}</div>
                    </div>
                    <span
                      aria-hidden="true"
                      className={`h-4 w-4 rounded-full border-2 ${
                        selected
                          ? "border-[#D5B77B] bg-[#D5B77B]"
                          : "border-[#9273B5] bg-transparent"
                      }`}
                    />
                  </button>
                );
              })}
            </div>
          </fieldset>

          {/* Toggles */}
          <section aria-labelledby="a11y-toggles-heading" className="space-y-3">
            <h3
              id="a11y-toggles-heading"
              className="text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
            >
              Reading & Motion Support
            </h3>
            {toggleItems.map((item) => (
              <div
                key={item.key}
                className="flex items-start justify-between gap-4 rounded-xl border border-[#383242] bg-[#25212B] p-4"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2 text-sm font-semibold text-[#F9F7FC]">
                    <span className="text-[#D5B77B]">{item.icon}</span>
                    <span id={`a11y-label-${item.key}`}>{item.label}</span>
                  </div>
                  <p className="text-xs text-[#D7CCE2]">{item.description}</p>
                </div>
                <button
                  type="button"
                  role="switch"
                  aria-checked={item.checked}
                  aria-labelledby={`a11y-label-${item.key}`}
                  onClick={() => updatePrefs({ [item.key]: !item.checked })}
                  className={`relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition-colors ${
                    item.checked
                      ? "border-[#D5B77B] bg-[#9273B5]"
                      : "border-[#4D445C] bg-[#17151C]"
                  }`}
                >
                  <span
                    aria-hidden="true"
                    className={`inline-block h-5 w-5 rounded-full bg-[#F9F7FC] transition-transform ${
                      item.checked ? "translate-x-6" : "translate-x-1"
                    }`}
                  />
                </button>
              </div>
            ))}
          </section>

          {/* Quick Links */}
          <div className="rounded-xl border border-[#383242] bg-[#25212B]/70 p-4 text-xs text-[#D7CCE2]">
            <p className="mb-2 font-semibold text-[#F9F7FC]">Need on-site accommodations?</p>
            <div className="flex flex-wrap gap-3">
              <Link
                href="/accessibility"
                onClick={() => setPanelOpen(false)}
                className="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-[#F9F7FC]"
              >
                Festival Accessibility Guide
              </Link>
              <Link
                href="/legal/accessibility-statement"
                onClick={() => setPanelOpen(false)}
                className="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-[#F9F7FC]"
              >
                WCAG 2.2 Statement
              </Link>
            </div>
          </div>
        </div>

        {/* Footer Reset */}
        <div className="sticky bottom-0 border-t border-[#383242] bg-[#17151C] px-6 py-4">
          <button
            type="button"
            onClick={resetPrefs}
            className="touch-target flex w-full items-center justify-center gap-2 rounded-xl border border-[#4D445C] bg-[#25212B] px-4 py-3 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
          >
            <RotateCcw className="h-4 w-4" aria-hidden="true" />
            Reset All Accessibility Settings
          </button>
        </div>
      </div>
    </div>
  );
}
