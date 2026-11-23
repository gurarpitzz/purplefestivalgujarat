"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  AlertTriangle,
  Camera,
  CameraOff,
  CheckCircle2,
  Clock,
  Keyboard,
  LogOut,
  MapPin,
  QrCode,
  RefreshCw,
  ShieldCheck,
  UserCheck,
  XCircle,
} from "lucide-react";

interface Station {
  id: string;
  name: string;
  gateCode: string;
}

interface RecentCheckin {
  id: string;
  participantName: string;
  registrationCode: string;
  credentialCode: string;
  categoryCode: string;
  venueLabel: string;
  method: string;
  checkinDateIst: string;
  checkedInAt: string;
  checkedInAtIst: string;
}

interface VerificationResponse {
  valid: boolean;
  reason:
    | "VALID"
    | "ALREADY_CHECKED_IN"
    | "NOT_FOUND"
    | "REVOKED"
    | "INELIGIBLE_REGISTRATION"
    | "PAYMENT_PENDING";
  message: string;
  credentialId: string | null;
  credentialCode: string | null;
  registrationCode: string | null;
  participantName: string | null;
  categoryCode: string | null;
  credentialStatus: string | null;
  registrationStatus: string | null;
  attendanceStatus: "NOT_CHECKED_IN" | "ALREADY_CHECKED_IN" | "INELIGIBLE";
  entitlementScope: string | null;
  existingCheckin: {
    checkedInAt: string;
    checkedInAtIst: string;
    venueLabel: string;
    checkinDateIst: string;
  } | null;
}

interface ConfirmedBanner {
  participantName: string;
  registrationCode: string;
  categoryCode: string;
  venueLabel: string;
  checkedInAtIst: string;
}

interface RgstScannerPortalProps {
  operator: {
    id: string;
    fullName: string;
    email: string;
    roleLabel: string;
    isAdmin: boolean;
  };
  initialStations: Station[];
  initialRecentCheckins: RecentCheckin[];
  initialTodayCount: number;
  todayIst: string;
}

const FALLBACK_STATION: Station = {
  id: "",
  name: "Main Gate — Sama Indoor Stadium",
  gateCode: "MAIN-GATE",
};

function formatIstClock(now: Date): string {
  return (
    new Intl.DateTimeFormat("en-IN", {
      timeZone: "Asia/Kolkata",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: true,
    }).format(now) + " IST"
  );
}

export default function RgstScannerPortal({
  operator,
  initialStations,
  initialRecentCheckins,
  initialTodayCount,
  todayIst,
}: RgstScannerPortalProps) {
  const router = useRouter();
  const stations = initialStations.length > 0 ? initialStations : [FALLBACK_STATION];

  const [selectedStationId, setSelectedStationId] = useState<string>(stations[0].id);
  const [istClock, setIstClock] = useState<string>("");

  // Scanner & Input state
  const [manualInput, setManualInput] = useState<string>("");
  const [lastMethod, setLastMethod] = useState<"QR_CAMERA" | "USB_SCANNER" | "MANUAL_ID">(
    "MANUAL_ID"
  );
  const [cameraActive, setCameraActive] = useState<boolean>(false);
  const [cameraError, setCameraError] = useState<string | null>(null);

  // Verification & Check-in state
  const [verifying, setVerifying] = useState<boolean>(false);
  const [confirming, setConfirming] = useState<boolean>(false);
  const [verifyError, setVerifyError] = useState<string | null>(null);
  const [verification, setVerification] = useState<VerificationResponse | null>(null);
  const [confirmedBanner, setConfirmedBanner] = useState<ConfirmedBanner | null>(null);

  // Operator recent check-ins
  const [recentCheckins, setRecentCheckins] =
    useState<RecentCheckin[]>(initialRecentCheckins);
  const [todayCount, setTodayCount] = useState<number>(initialTodayCount);
  const [refreshingList, setRefreshingList] = useState<boolean>(false);
  const [loggingOut, setLoggingOut] = useState<boolean>(false);

  const inputRef = useRef<HTMLInputElement | null>(null);
  const confirmBtnRef = useRef<HTMLButtonElement | null>(null);
  const scannerRef = useRef<{
    stop: () => Promise<void>;
    clear: () => void;
    isScanning?: boolean;
  } | null>(null);
  const lastScannedRef = useRef<{ text: string; at: number }>({ text: "", at: 0 });

  const currentStation =
    stations.find((s) => s.id === selectedStationId) ?? stations[0];

  // Live IST clock
  useEffect(() => {
    setIstClock(formatIstClock(new Date()));
    const interval = setInterval(() => {
      setIstClock(formatIstClock(new Date()));
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  // Stop camera cleanly on unmount
  useEffect(() => {
    return () => {
      if (scannerRef.current) {
        scannerRef.current
          .stop()
          .then(() => scannerRef.current?.clear())
          .catch(() => {});
        scannerRef.current = null;
      }
    };
  }, []);

  const refreshRecentCheckins = useCallback(async () => {
    setRefreshingList(true);
    try {
      const res = await fetch("/api/checkins", { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data.recentCheckins)) {
          setRecentCheckins(data.recentCheckins);
        }
        if (typeof data.todayCount === "number") {
          setTodayCount(data.todayCount);
        }
      }
    } catch {
      // Keep existing list on transient network error
    } finally {
      setRefreshingList(false);
    }
  }, []);

  const verifyCredential = useCallback(
    async (rawCode: string, method: "QR_CAMERA" | "USB_SCANNER" | "MANUAL_ID") => {
      const trimmed = rawCode.trim();
      if (!trimmed) return;

      setVerifying(true);
      setVerifyError(null);
      setConfirmedBanner(null);
      setLastMethod(method);

      try {
        const res = await fetch("/api/qr/verify", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ input: trimmed }),
        });

        const data = await res.json().catch(() => null);
        if (!res.ok) {
          setVerification(null);
          setVerifyError(data?.error || "Unable to verify credential. Please retry.");
          return;
        }

        setVerification(data as VerificationResponse);
        if (data?.valid) {
          setTimeout(() => {
            confirmBtnRef.current?.focus();
          }, 50);
        }
      } catch {
        setVerification(null);
        setVerifyError("Network error while verifying credential. Check connection.");
      } finally {
        setVerifying(false);
      }
    },
    []
  );

  const stopCamera = useCallback(async () => {
    if (scannerRef.current) {
      try {
        await scannerRef.current.stop();
        scannerRef.current.clear();
      } catch {
        // Ignore stop errors if already stopped
      }
      scannerRef.current = null;
    }
    setCameraActive(false);
  }, []);

  const startCamera = useCallback(async () => {
    setCameraError(null);
    if (cameraActive) {
      await stopCamera();
      return;
    }

    try {
      const { Html5Qrcode } = await import("html5-qrcode");
      const scanner = new Html5Qrcode("rgst-qr-reader");
      scannerRef.current = scanner;

      await scanner.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 240, height: 240 },
          aspectRatio: 1.0,
        },
        (decodedText: string) => {
          const now = Date.now();
          if (
            decodedText === lastScannedRef.current.text &&
            now - lastScannedRef.current.at < 2500
          ) {
            return;
          }
          lastScannedRef.current = { text: decodedText, at: now };
          setManualInput(decodedText);
          void verifyCredential(decodedText, "QR_CAMERA");
        },
        () => {
          // Ignore per-frame decode misses
        }
      );
      setCameraActive(true);
    } catch (err) {
      scannerRef.current = null;
      setCameraActive(false);
      const msg = err instanceof Error ? err.message : String(err);
      if (
        msg.toLowerCase().includes("permission") ||
        msg.toLowerCase().includes("notallowed")
      ) {
        setCameraError(
          "Camera permission was denied. Allow camera access in your browser settings, or use the USB QR Scanner / Manual ID input."
        );
      } else {
        setCameraError(
          "Camera could not be started on this device. Use the USB QR Scanner or Manual Registration ID entry on the right."
        );
      }
    }
  }, [cameraActive, stopCamera, verifyCredential]);

  const handleManualSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleaned = manualInput.trim();
    if (!cleaned) return;
    const detectedMethod: "USB_SCANNER" | "MANUAL_ID" = cleaned.startsWith("PFG27.")
      ? "USB_SCANNER"
      : "MANUAL_ID";
    await verifyCredential(cleaned, detectedMethod);
  };

  const handleConfirmCheckin = async () => {
    if (!verification?.valid || !verification.credentialId) return;

    setConfirming(true);
    setVerifyError(null);

    try {
      const res = await fetch("/api/checkins/confirm", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          credentialId: verification.credentialId,
          venueLabel: currentStation.name,
          stationId: currentStation.id || null,
          method: lastMethod,
          scopeOverride: verification.entitlementScope || undefined,
        }),
      });

      const data = await res.json().catch(() => null);

      if (res.status === 409 && data?.duplicate) {
        setVerification({
          ...verification,
          valid: false,
          reason: "ALREADY_CHECKED_IN",
          message: data.message || "ALREADY CHECKED IN",
          attendanceStatus: "ALREADY_CHECKED_IN",
          existingCheckin: data.existingCheckin ?? verification.existingCheckin,
        });
        return;
      }

      if (!res.ok || !data?.success) {
        setVerifyError(data?.error || data?.message || "Check-in confirmation failed.");
        return;
      }

      const confirmed = data.checkin as RecentCheckin;
      setConfirmedBanner({
        participantName: confirmed.participantName,
        registrationCode: confirmed.registrationCode,
        categoryCode: confirmed.categoryCode,
        venueLabel: confirmed.venueLabel,
        checkedInAtIst: confirmed.checkedInAtIst,
      });
      setVerification(null);
      setManualInput("");
      setTodayCount((prev) => prev + 1);
      setRecentCheckins((prev) => [confirmed, ...prev.slice(0, 49)]);
    } catch {
      setVerifyError("Network error while confirming check-in. Please retry.");
    } finally {
      setConfirming(false);
    }
  };

  const handleResetForNext = () => {
    setVerification(null);
    setConfirmedBanner(null);
    setVerifyError(null);
    setManualInput("");
    inputRef.current?.focus();
  };

  const handleLogout = async () => {
    setLoggingOut(true);
    await stopCamera();
    try {
      await fetch("/api/auth/logout", { method: "POST" });
    } finally {
      router.push("/login");
      router.refresh();
    }
  };

  return (
    <div className="min-h-screen bg-[#17151C] text-[#F9F7FC] selection:bg-[#9273B5] selection:text-white">
      {/* Skip Link */}
      <a
        href="#rgst-main"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-[#D5B77B] focus:px-4 focus:py-2 focus:font-semibold focus:text-[#17151C]"
      >
        Skip to reception scanner
      </a>

      {/* Distraction-Free Reception Desk Header */}
      <header className="sticky top-0 z-30 border-b border-[#9273B5]/30 bg-[#17151C]/95 backdrop-blur">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
          <div className="flex items-center gap-3">
            <div
              className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#59436D] text-[#D5B77B] ring-1 ring-[#D5B77B]/40"
              aria-hidden="true"
            >
              <QrCode className="h-5 w-5" />
            </div>
            <div>
              <h1 className="text-base font-bold tracking-tight text-[#F9F7FC] sm:text-lg">
                Purple Festival Gujarat 2027 — RGST Desk
              </h1>
              <p className="text-xs text-[#C6B6D8]">
                Reception Verification &amp; Attendance Portal • Data-Minimized View
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            {/* Station Selector */}
            <div className="flex items-center gap-2 rounded-lg border border-[#9273B5]/40 bg-[#221E2A] px-3 py-1.5">
              <MapPin className="h-4 w-4 shrink-0 text-[#D5B77B]" aria-hidden="true" />
              <label htmlFor="rgst-station-select" className="sr-only">
                Active Check-in Station
              </label>
              <select
                id="rgst-station-select"
                value={selectedStationId}
                onChange={(e) => setSelectedStationId(e.target.value)}
                className="bg-transparent text-sm font-medium text-[#F9F7FC] focus:outline-none"
              >
                {stations.map((station) => (
                  <option
                    key={station.id || station.gateCode}
                    value={station.id}
                    className="bg-[#17151C] text-[#F9F7FC]"
                  >
                    {station.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Live IST Clock */}
            <div
              className="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs font-mono text-[#E8DFF2]"
              aria-label="Current Indian Standard Time"
            >
              <Clock className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
              <span>{istClock || `${todayIst} IST`}</span>
            </div>

            {/* Operator Badge */}
            <div className="flex items-center gap-2 rounded-lg border border-[#9273B5]/30 bg-[#221E2A] px-3 py-1.5 text-xs">
              <ShieldCheck className="h-4 w-4 text-[#9273B5]" aria-hidden="true" />
              <span className="font-semibold text-[#F9F7FC]">{operator.fullName}</span>
              <span className="rounded bg-[#59436D] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#F9F7FC]">
                {operator.roleLabel}
              </span>
            </div>

            {operator.isAdmin && (
              <Link
                href="/admin"
                className="rounded-lg border border-[#D5B77B]/50 px-3 py-1.5 text-xs font-semibold text-[#D5B77B] transition hover:bg-[#D5B77B] hover:text-[#17151C] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D5B77B]"
              >
                Admin Portal
              </Link>
            )}

            <button
              type="button"
              onClick={handleLogout}
              disabled={loggingOut}
              className="inline-flex items-center gap-1.5 rounded-lg border border-red-400/40 bg-red-950/40 px-3 py-1.5 text-xs font-semibold text-red-200 transition hover:bg-red-900/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400 disabled:opacity-50"
            >
              <LogOut className="h-3.5 w-3.5" aria-hidden="true" />
              {loggingOut ? "Signing out..." : "Logout"}
            </button>
          </div>
        </div>
      </header>

      <main id="rgst-main" className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
        {/* Top Row: Scanner + Manual Entry */}
        <section
          aria-label="Scan or enter participant credential"
          className="grid gap-6 lg:grid-cols-2"
        >
          {/* Camera QR Scanner Card */}
          <div className="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <Camera className="h-5 w-5 text-[#D5B77B]" aria-hidden="true" />
                <h2 className="text-base font-bold tracking-wide text-[#F9F7FC]">
                  SCAN PARTICIPANT QR
                </h2>
              </div>
              <button
                type="button"
                onClick={startCamera}
                className={`inline-flex min-h-[44px] items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D5B77B] ${
                  cameraActive
                    ? "bg-red-600 text-white hover:bg-red-500"
                    : "bg-[#9273B5] text-white hover:bg-[#A688C7]"
                }`}
              >
                {cameraActive ? (
                  <>
                    <CameraOff className="h-4 w-4" aria-hidden="true" />
                    Stop Camera
                  </>
                ) : (
                  <>
                    <Camera className="h-4 w-4" aria-hidden="true" />
                    Start Camera Scanner
                  </>
                )}
              </button>
            </div>

            <div className="relative overflow-hidden rounded-xl border border-dashed border-[#9273B5]/50 bg-[#141219] min-h-[260px] flex flex-col items-center justify-center">
              <div id="rgst-qr-reader" className="w-full max-w-[360px]" />
              {!cameraActive && (
                <div className="p-6 text-center">
                  <QrCode
                    className="mx-auto mb-3 h-12 w-12 text-[#9273B5]/60"
                    aria-hidden="true"
                  />
                  <p className="text-sm font-medium text-[#E8DFF2]">
                    Camera scanner is paused
                  </p>
                  <p className="mt-1 text-xs text-[#B6A5C9]">
                    Click <strong>Start Camera Scanner</strong> to scan a participant&apos;s
                    digital or printed QR pass. Works on mobile, tablet, and laptop cameras.
                  </p>
                </div>
              )}
            </div>

            {cameraError && (
              <div
                role="alert"
                className="mt-3 flex items-start gap-2.5 rounded-xl border border-amber-400/50 bg-amber-950/50 p-3 text-xs text-amber-100"
              >
                <AlertTriangle
                  className="mt-0.5 h-4 w-4 shrink-0 text-amber-300"
                  aria-hidden="true"
                />
                <span>{cameraError}</span>
              </div>
            )}
          </div>

          {/* USB QR Scanner / Manual ID Entry Card */}
          <div className="flex flex-col justify-between rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg">
            <div>
              <div className="mb-4 flex items-center gap-2.5">
                <Keyboard className="h-5 w-5 text-[#D5B77B]" aria-hidden="true" />
                <h2 className="text-base font-bold tracking-wide text-[#F9F7FC]">
                  USB QR SCANNER / MANUAL ID ENTRY
                </h2>
              </div>

              <p className="mb-4 text-sm text-[#C6B6D8]">
                Focus the field below to scan directly with a USB barcode/QR reader, or type
                the participant&apos;s Registration / Credential ID (e.g.{" "}
                <code className="rounded bg-[#17151C] px-1.5 py-0.5 font-mono text-[#D5B77B]">
                  PFG-2027-000001
                </code>
                ) and press <strong>Enter</strong>.
              </p>

              <form onSubmit={handleManualSubmit} className="space-y-4">
                <div>
                  <label
                    htmlFor="rgst-credential-input"
                    className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#D5B77B]"
                  >
                    Registration ID / Credential Code / QR Token
                  </label>
                  <input
                    ref={inputRef}
                    id="rgst-credential-input"
                    type="text"
                    value={manualInput}
                    onChange={(e) => setManualInput(e.target.value)}
                    placeholder="PFG-2027-000001 or scan QR token..."
                    autoComplete="off"
                    autoFocus
                    className="w-full min-h-[52px] rounded-xl border-2 border-[#9273B5]/50 bg-[#141219] px-4 py-3 font-mono text-base text-[#F9F7FC] placeholder-[#7B6E8C] focus:border-[#D5B77B] focus:outline-none"
                  />
                </div>

                <div className="flex flex-wrap gap-3">
                  <button
                    type="submit"
                    disabled={verifying || !manualInput.trim()}
                    className="inline-flex min-h-[48px] flex-1 items-center justify-center gap-2 rounded-xl bg-[#D5B77B] px-5 py-2.5 text-sm font-bold text-[#17151C] transition hover:bg-[#E2C78E] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D5B77B] disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <ShieldCheck className="h-4 w-4" aria-hidden="true" />
                    {verifying ? "Verifying..." : "Verify Credential"}
                  </button>
                  <button
                    type="button"
                    onClick={handleResetForNext}
                    className="min-h-[48px] rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-4 py-2.5 text-sm font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#9273B5]"
                  >
                    Clear
                  </button>
                </div>
              </form>
            </div>

            {/* Operator Desk Summary Strip */}
            <div className="mt-6 grid grid-cols-2 gap-3 border-t border-[#9273B5]/25 pt-4">
              <div className="rounded-xl bg-[#17151C] p-3">
                <p className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                  Your Check-ins Today
                </p>
                <p className="mt-1 text-2xl font-extrabold text-[#D5B77B]">{todayCount}</p>
              </div>
              <div className="rounded-xl bg-[#17151C] p-3">
                <p className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                  Active Gate / Station
                </p>
                <p className="mt-1 truncate text-sm font-semibold text-[#F9F7FC]">
                  {currentStation.name}
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Verification Result Panel (aria-live="polite") */}
        <section
          aria-label="Verification result"
          aria-live="polite"
          aria-atomic="true"
          className="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-6 shadow-lg"
        >
          {verifyError && (
            <div
              role="alert"
              className="flex items-start gap-3 rounded-xl border-2 border-red-500/70 bg-red-950/60 p-4 text-red-100"
            >
              <XCircle className="mt-0.5 h-6 w-6 shrink-0 text-red-400" aria-hidden="true" />
              <div className="flex-1">
                <p className="text-base font-bold">Verification Error</p>
                <p className="mt-1 text-sm text-red-200">{verifyError}</p>
              </div>
            </div>
          )}

          {confirmedBanner && (
            <div className="rounded-xl border-2 border-emerald-400 bg-emerald-950/60 p-5">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-start gap-3">
                  <CheckCircle2
                    className="mt-0.5 h-8 w-8 shrink-0 text-emerald-400"
                    aria-hidden="true"
                  />
                  <div>
                    <p className="text-lg font-extrabold tracking-wide text-emerald-300">
                      CHECK-IN CONFIRMED
                    </p>
                    <p className="mt-1 text-base font-bold text-white">
                      {confirmedBanner.participantName} ({confirmedBanner.registrationCode})
                    </p>
                    <p className="mt-1 text-sm text-emerald-100">
                      Category: <strong>{confirmedBanner.categoryCode}</strong> • Venue:{" "}
                      <strong>{confirmedBanner.venueLabel}</strong> • Time:{" "}
                      <strong>{confirmedBanner.checkedInAtIst}</strong>
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={handleResetForNext}
                  className="min-h-[48px] rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-[#0F1713] transition hover:bg-emerald-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300"
                >
                  Scan Next Participant
                </button>
              </div>
            </div>
          )}

          {!verifyError && !confirmedBanner && !verification && (
            <div className="flex flex-col items-center justify-center py-6 text-center">
              <UserCheck
                className="mb-2 h-10 w-10 text-[#9273B5]/70"
                aria-hidden="true"
              />
              <p className="text-base font-semibold text-[#E8DFF2]">
                Ready for Participant Verification
              </p>
              <p className="mt-1 max-w-xl text-sm text-[#B6A5C9]">
                Scan a participant&apos;s QR code or enter their Registration ID above. Check-in
                is never automatic — you will review the participant&apos;s identity and click{" "}
                <strong>VERIFY AND CHECK IN</strong> to record attendance.
              </p>
            </div>
          )}

          {/* VALID STATE */}
          {verification && verification.reason === "VALID" && (
            <div className="rounded-xl border-2 border-emerald-400 bg-emerald-950/45 p-5">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-emerald-400/30 pb-3">
                <div className="flex items-center gap-2.5">
                  <CheckCircle2 className="h-7 w-7 text-emerald-400" aria-hidden="true" />
                  <div>
                    <p className="text-lg font-extrabold tracking-wide text-emerald-300">
                      VALID CREDENTIAL — READY FOR ENTRY
                    </p>
                    <p className="text-xs text-emerald-100">
                      Confirm participant identity before pressing Verify and Check In.
                    </p>
                  </div>
                </div>
                <span className="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-200 ring-1 ring-emerald-400/50">
                  {verification.credentialStatus}
                </span>
              </div>

              <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Participant Name
                  </dt>
                  <dd className="mt-1 text-lg font-bold text-white">
                    {verification.participantName}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Registration ID
                  </dt>
                  <dd className="mt-1 font-mono text-base font-bold text-[#D5B77B]">
                    {verification.registrationCode}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Category
                  </dt>
                  <dd className="mt-1 text-base font-bold text-[#F9F7FC]">
                    {verification.categoryCode}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Status / Scope
                  </dt>
                  <dd className="mt-1 text-sm font-bold text-emerald-300">
                    {verification.registrationStatus} • {verification.entitlementScope}
                  </dd>
                </div>
              </dl>

              <div className="mt-5 flex flex-wrap items-center gap-3">
                <button
                  ref={confirmBtnRef}
                  type="button"
                  onClick={handleConfirmCheckin}
                  disabled={confirming}
                  className="inline-flex min-h-[54px] flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-base font-extrabold tracking-wide text-[#0B1711] shadow-lg transition hover:bg-emerald-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 sm:flex-none"
                >
                  <UserCheck className="h-5 w-5" aria-hidden="true" />
                  {confirming ? "Recording Check-in..." : "VERIFY AND CHECK IN"}
                </button>
                <button
                  type="button"
                  onClick={handleResetForNext}
                  className="min-h-[54px] rounded-xl border border-[#9273B5]/40 bg-[#17151C] px-5 py-3 text-sm font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435]"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {/* ALREADY CHECKED IN STATE */}
          {verification && verification.reason === "ALREADY_CHECKED_IN" && (
            <div className="rounded-xl border-2 border-amber-400 bg-amber-950/50 p-5">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-amber-400/30 pb-3">
                <div className="flex items-center gap-2.5">
                  <AlertTriangle className="h-7 w-7 text-amber-400" aria-hidden="true" />
                  <div>
                    <p className="text-lg font-extrabold tracking-wide text-amber-300">
                      ALREADY CHECKED IN
                    </p>
                    <p className="text-xs text-amber-100">
                      Duplicate entry is blocked for this attendance period (
                      {verification.entitlementScope}).
                    </p>
                  </div>
                </div>
                <span className="rounded-full bg-amber-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-200 ring-1 ring-amber-400/50">
                  DUPLICATE PREVENTED
                </span>
              </div>

              <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Participant Name
                  </dt>
                  <dd className="mt-1 text-base font-bold text-white">
                    {verification.participantName}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Registration ID &amp; Category
                  </dt>
                  <dd className="mt-1 font-mono text-sm font-bold text-[#D5B77B]">
                    {verification.registrationCode} • {verification.categoryCode}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Checked In At (IST)
                  </dt>
                  <dd className="mt-1 text-sm font-bold text-amber-200">
                    {verification.existingCheckin?.checkedInAtIst || "Recorded earlier"}
                  </dd>
                </div>
                <div className="rounded-lg bg-[#17151C]/80 p-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                    Check-in Venue
                  </dt>
                  <dd className="mt-1 text-sm font-bold text-amber-200">
                    {verification.existingCheckin?.venueLabel || "Festival Gate"}
                  </dd>
                </div>
              </dl>

              <div className="mt-5 flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  disabled
                  className="inline-flex min-h-[48px] cursor-not-allowed items-center gap-2 rounded-xl bg-amber-900/50 px-5 py-2.5 text-sm font-bold text-amber-200/70 ring-1 ring-amber-400/30"
                >
                  VERIFY AND CHECK IN (Disabled — Already Checked In)
                </button>
                <button
                  type="button"
                  onClick={handleResetForNext}
                  className="min-h-[48px] rounded-xl bg-[#9273B5] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#A688C7]"
                >
                  Scan Next Participant
                </button>
              </div>
            </div>
          )}

          {/* INVALID / REVOKED / INELIGIBLE / PAYMENT PENDING STATE */}
          {verification &&
            verification.reason !== "VALID" &&
            verification.reason !== "ALREADY_CHECKED_IN" && (
              <div className="rounded-xl border-2 border-red-500 bg-red-950/55 p-5">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-red-500/30 pb-3">
                  <div className="flex items-center gap-2.5">
                    <XCircle className="h-7 w-7 text-red-400" aria-hidden="true" />
                    <div>
                      <p className="text-lg font-extrabold tracking-wide text-red-300">
                        ENTRY NOT PERMITTED — {verification.reason.replace(/_/g, " ")}
                      </p>
                      <p className="text-sm text-red-100">{verification.message}</p>
                    </div>
                  </div>
                </div>

                {verification.participantName && (
                  <dl className="mt-3 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg bg-[#17151C]/80 p-3">
                      <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                        Participant
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-white">
                        {verification.participantName}
                      </dd>
                    </div>
                    <div className="rounded-lg bg-[#17151C]/80 p-3">
                      <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                        Registration ID
                      </dt>
                      <dd className="mt-1 font-mono text-sm font-bold text-[#D5B77B]">
                        {verification.registrationCode}
                      </dd>
                    </div>
                    <div className="rounded-lg bg-[#17151C]/80 p-3">
                      <dt className="text-[11px] font-semibold uppercase tracking-wider text-[#B6A5C9]">
                        Status
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-red-300">
                        Cred: {verification.credentialStatus} / Reg:{" "}
                        {verification.registrationStatus}
                      </dd>
                    </div>
                  </dl>
                )}

                <div className="mt-4">
                  <button
                    type="button"
                    onClick={handleResetForNext}
                    className="min-h-[44px] rounded-xl bg-[#9273B5] px-5 py-2 text-sm font-bold text-white transition hover:bg-[#A688C7]"
                  >
                    Scan Next Participant
                  </button>
                </div>
              </div>
            )}
        </section>

        {/* Operator's Own Recent Check-ins Table */}
        <section
          aria-label="Your recent check-ins"
          className="rounded-2xl border border-[#9273B5]/35 bg-[#201C27] p-5 shadow-lg"
        >
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-base font-bold text-[#F9F7FC]">
                Your Recent Check-ins
              </h2>
              <p className="text-xs text-[#B6A5C9]">
                Showing check-ins confirmed by {operator.fullName} (IST timestamps)
              </p>
            </div>
            <button
              type="button"
              onClick={refreshRecentCheckins}
              disabled={refreshingList}
              className="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-[#9273B5]/40 bg-[#17151C] px-3.5 py-1.5 text-xs font-semibold text-[#E8DFF2] transition hover:bg-[#2A2435] disabled:opacity-50"
            >
              <RefreshCw
                className={`h-3.5 w-3.5 ${refreshingList ? "animate-spin" : ""}`}
                aria-hidden="true"
              />
              Refresh
            </button>
          </div>

          {recentCheckins.length === 0 ? (
            <p className="rounded-xl bg-[#17151C] p-6 text-center text-sm text-[#B6A5C9]">
              No participants checked in by your station yet.
            </p>
          ) : (
            <div className="overflow-x-auto rounded-xl border border-[#9273B5]/25">
              <table className="min-w-full divide-y divide-[#9273B5]/20 text-left text-sm">
                <thead className="bg-[#17151C] text-xs uppercase tracking-wider text-[#C6B6D8]">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Time (IST)
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Participant
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Registration ID
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Category
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Venue / Station
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Method
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#9273B5]/15 bg-[#1C1823]">
                  {recentCheckins.map((row) => (
                    <tr key={row.id} className="hover:bg-[#262030]">
                      <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#E8DFF2]">
                        {row.checkedInAtIst}
                      </td>
                      <td className="px-4 py-3 font-semibold text-white">
                        {row.participantName}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#D5B77B]">
                        {row.registrationCode}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <span className="rounded-md bg-[#59436D]/60 px-2 py-0.5 text-xs font-semibold text-[#F9F7FC]">
                          {row.categoryCode}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs text-[#E8DFF2]">{row.venueLabel}</td>
                      <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#B6A5C9]">
                        {row.method}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>
    </div>
  );
}
