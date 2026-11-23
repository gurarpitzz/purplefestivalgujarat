"use client";

import React, { createContext, useContext, useEffect, useState, useCallback } from "react";

export type ContrastMode = "default" | "high" | "yellow-black";
export type TextScale = 90 | 100 | 115 | 130 | 150 | 175 | 200;

export interface AccessibilityPrefs {
  textScale: TextScale;
  contrast: ContrastMode;
  reducedMotion: boolean;
  wideSpacing: boolean;
  highlightLinks: boolean;
  readingGuide: boolean;
}

export const DEFAULT_A11Y_PREFS: AccessibilityPrefs = {
  textScale: 100,
  contrast: "default",
  reducedMotion: false,
  wideSpacing: false,
  highlightLinks: false,
  readingGuide: false,
};

const STORAGE_KEY = "pfg27_a11y_prefs_v1";

interface AccessibilityContextValue {
  prefs: AccessibilityPrefs;
  panelOpen: boolean;
  setPanelOpen: (open: boolean) => void;
  togglePanel: () => void;
  updatePrefs: (patch: Partial<AccessibilityPrefs>) => void;
  resetPrefs: () => void;
  statusMessage: string;
}

const AccessibilityContext = createContext<AccessibilityContextValue | null>(null);

export function useAccessibility(): AccessibilityContextValue {
  const ctx = useContext(AccessibilityContext);
  if (!ctx) {
    throw new Error("useAccessibility must be used within AccessibilityProvider");
  }
  return ctx;
}

function applyPrefsToDocument(prefs: AccessibilityPrefs) {
  if (typeof document === "undefined") return;
  const root = document.documentElement;
  root.setAttribute("data-text-scale", String(prefs.textScale));
  root.setAttribute("data-contrast", prefs.contrast);
  root.setAttribute("data-reduced-motion", prefs.reducedMotion ? "true" : "false");
  root.setAttribute("data-spacing", prefs.wideSpacing ? "wide" : "normal");
  root.setAttribute("data-highlight-links", prefs.highlightLinks ? "true" : "false");
}

export function AccessibilityProvider({ children }: { children: React.ReactNode }) {
  const [prefs, setPrefs] = useState<AccessibilityPrefs>(DEFAULT_A11Y_PREFS);
  const [panelOpen, setPanelOpen] = useState(false);
  const [hydrated, setHydrated] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [guideY, setGuideY] = useState<number>(240);

  // Load persisted preferences + OS reduced motion on mount
  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      const osReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (raw) {
        const parsed = JSON.parse(raw) as Partial<AccessibilityPrefs>;
        const merged: AccessibilityPrefs = {
          textScale: ([90, 100, 115, 130, 150, 175, 200] as TextScale[]).includes(
            parsed.textScale as TextScale
          )
            ? (parsed.textScale as TextScale)
            : 100,
          contrast:
            parsed.contrast === "high" || parsed.contrast === "yellow-black"
              ? parsed.contrast
              : "default",
          reducedMotion:
            typeof parsed.reducedMotion === "boolean" ? parsed.reducedMotion : osReduced,
          wideSpacing: Boolean(parsed.wideSpacing),
          highlightLinks: Boolean(parsed.highlightLinks),
          readingGuide: Boolean(parsed.readingGuide),
        };
        setPrefs(merged);
        applyPrefsToDocument(merged);
      } else {
        const initial = { ...DEFAULT_A11Y_PREFS, reducedMotion: osReduced };
        setPrefs(initial);
        applyPrefsToDocument(initial);
      }
    } catch {
      applyPrefsToDocument(DEFAULT_A11Y_PREFS);
    } finally {
      setHydrated(true);
    }
  }, []);

  // Persist changes
  useEffect(() => {
    if (!hydrated) return;
    applyPrefsToDocument(prefs);
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch {
      // Ignore storage quota / private mode errors
    }
  }, [prefs, hydrated]);

  // Reading Guide pointer & focus tracking
  useEffect(() => {
    if (!prefs.readingGuide) return;
    const onMouseMove = (e: MouseEvent) => setGuideY(e.clientY);
    const onFocusIn = (e: FocusEvent) => {
      const target = e.target as HTMLElement | null;
      if (target && typeof target.getBoundingClientRect === "function") {
        const rect = target.getBoundingClientRect();
        if (rect.top >= 0 && rect.top <= window.innerHeight) {
          setGuideY(rect.top + rect.height / 2);
        }
      }
    };
    window.addEventListener("mousemove", onMouseMove, { passive: true });
    window.addEventListener("focusin", onFocusIn, { passive: true });
    return () => {
      window.removeEventListener("mousemove", onMouseMove);
      window.removeEventListener("focusin", onFocusIn);
    };
  }, [prefs.readingGuide]);

  const updatePrefs = useCallback((patch: Partial<AccessibilityPrefs>) => {
    setPrefs((prev) => {
      const next = { ...prev, ...patch };
      return next;
    });
    setStatusMessage("Accessibility preferences updated.");
  }, []);

  const resetPrefs = useCallback(() => {
    setPrefs(DEFAULT_A11Y_PREFS);
    applyPrefsToDocument(DEFAULT_A11Y_PREFS);
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Ignore
    }
    setStatusMessage("All accessibility preferences reset to default.");
  }, []);

  const togglePanel = useCallback(() => {
    setPanelOpen((prev) => !prev);
  }, []);

  return (
    <AccessibilityContext.Provider
      value={{
        prefs,
        panelOpen,
        setPanelOpen,
        togglePanel,
        updatePrefs,
        resetPrefs,
        statusMessage,
      }}
    >
      {children}
      {prefs.readingGuide && (
        <div
          aria-hidden="true"
          className="reading-guide-bar"
          style={{ top: `${guideY}px` }}
        />
      )}
      <div role="status" aria-live="polite" className="sr-only">
        {statusMessage}
      </div>
    </AccessibilityContext.Provider>
  );
}
