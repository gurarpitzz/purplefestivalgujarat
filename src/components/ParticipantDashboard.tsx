"use client";

import React, { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Accessibility,
  AlertCircle,
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  Download,
  ExternalLink,
  KeyRound,
  LogOut,
  PlusCircle,
  QrCode,
  ShieldCheck,
  User,
} from "lucide-react";
import {
  FESTIVAL_DATES_DISPLAY,
  FESTIVAL_LOCATION_DISPLAY,
  FESTIVAL_NAME,
} from "@/lib/constants";

export interface DashboardRegistration {
  id: string;
  registrationCode: string;
  categoryCode: string;
  categoryName: string;
  status: string;
  paymentStatus: string;
  fullName: string;
  email: string;
  attendingDays: string[];
  reviewNotes: string | null;
  createdAt: string;
  activeCredential: {
    id: string;
    credentialCode: string;
    status: string;
    issuedAt: string;
    qrDataUrl: string | null;
  } | null;
  volunteerAssignment: {
    assignedDepartment: string | null;
    assignedResponsibility: string | null;
    assignedSupervisor: string | null;
    assignedVenue: string | null;
    assignedShift: string | null;
    reportingTime: string | null;
    assignmentNotes: string | null;
  } | null;
  sliAssignment: {
    assignedVenue: string | null;
    dutyTimes: string | null;
    assignedSessions: Array<{
      sessionTitle: string;
      venue: string;
      dutyTime: string;
      day: string;
    }>;
    assignmentNotes: string | null;
  } | null;
  checkins: Array<{
    id: string;
    venueLabel: string;
    checkinDateIst: string;
    checkedInAt: string;
  }>;
}

interface AccommodationItem {
  id: string;
  registrationId: string;
  registrationCode: string;
  categoryCode: string;
  selectedTypes: string[];
  freeTextDetails: string | null;
  status: string;
  createdAt: string;
}

interface ParticipantDashboardProps {
  user: {
    id: string;
    fullName: string;
    email: string;
    phone: string | null;
    country: string;
    roles: string[];
    mfaEnabled: boolean;
  };
  registrations: DashboardRegistration[];
  accommodationTypes: Array<{ code: string; label: string }>;
  initialAccommodations: AccommodationItem[];
}

type TabId = "passes" | "accommodations" | "settings";

function statusBadgeClasses(status: string): string {
  switch (status) {
    case "CONFIRMED":
    case "APPROVED":
    case "ARRANGED":
      return "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30";
    case "SUBMITTED":
    case "UNDER_REVIEW":
    case "REQUESTED":
    case "IN_PROGRESS":
      return "bg-[#59436D]/15 text-[#432F56] border-[#59436D]/30";
    case "WAITLISTED":
    case "ADDITIONAL_INFO_REQUIRED":
      return "bg-[#7A5C1F]/15 text-[#5E4514] border-[#7A5C1F]/30";
    case "REJECTED":
    case "CANCELLED":
    case "DECLINED":
      return "bg-[#B42332]/15 text-[#8A1824] border-[#B42332]/30";
    default:
      return "bg-[#E8DFF0] text-[#432F56] border-[#59436D]/20";
  }
}

export default function ParticipantDashboard({
  user: initialUser,
  registrations,
  accommodationTypes,
  initialAccommodations,
}: ParticipantDashboardProps) {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<TabId>("passes");
  const [user, setUser] = useState(initialUser);
  const [loggingOut, setLoggingOut] = useState(false);

  // Accommodations state
  const [accommodations, setAccommodations] = useState<AccommodationItem[]>(
    initialAccommodations
  );
  const [accRegId, setAccRegId] = useState(registrations[0]?.id || "");
  const [accSelected, setAccSelected] = useState<string[]>([]);
  const [accFreeText, setAccFreeText] = useState("");
  const [accSaving, setAccSaving] = useState(false);
  const [accMessage, setAccMessage] = useState<{
    type: "success" | "error";
    text: string;
  } | null>(null);

  // Profile state
  const [fullName, setFullName] = useState(initialUser.fullName);
  const [phone, setPhone] = useState(initialUser.phone || "");
  const [country, setCountry] = useState(initialUser.country || "India");
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileMessage, setProfileMessage] = useState<{
    type: "success" | "error";
    text: string;
  } | null>(null);

  // MFA state
  const [mfaSetup, setMfaSetup] = useState<{
    secret: string;
    qrDataUrl: string;
  } | null>(null);
  const [mfaCode, setMfaCode] = useState("");
  const [mfaLoading, setMfaLoading] = useState(false);
  const [mfaMessage, setMfaMessage] = useState<{
    type: "success" | "error";
    text: string;
  } | null>(null);

  const isStaff =
    user.roles.includes("SUPERADMIN") ||
    user.roles.includes("ADMIN") ||
    user.roles.includes("RGST");

  const staffPortalHref =
    user.roles.includes("SUPERADMIN") || user.roles.includes("ADMIN")
      ? "/admin"
      : "/rgst";

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await fetch("/api/auth/logout", { method: "POST" });
      router.push("/login");
      router.refresh();
    } finally {
      setLoggingOut(false);
    }
  }

  function toggleAccType(code: string) {
    setAccSelected((prev) =>
      prev.includes(code) ? prev.filter((c) => c !== code) : [...prev, code]
    );
  }

  async function handleAccommodationSubmit(e: React.FormEvent) {
    e.preventDefault();
    setAccMessage(null);
    if (!accRegId) {
      setAccMessage({
        type: "error",
        text: "Please select a registration first.",
      });
      return;
    }
    setAccSaving(true);
    try {
      const res = await fetch("/api/account/accommodations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          registrationId: accRegId,
          selectedTypes: accSelected,
          freeTextDetails: accFreeText.trim(),
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setAccMessage({
          type: "error",
          text: data.error || "Unable to submit accommodation request.",
        });
        return;
      }
      setAccommodations((prev) => [
        {
          id: data.request.id,
          registrationId: data.request.registrationId,
          registrationCode: data.request.registrationCode,
          categoryCode: data.request.categoryCode,
          selectedTypes: data.request.selectedTypes || [],
          freeTextDetails: data.request.freeTextDetails,
          status: data.request.status,
          createdAt: data.request.createdAt,
        },
        ...prev,
      ]);
      setAccSelected([]);
      setAccFreeText("");
      setAccMessage({
        type: "success",
        text:
          data.message ||
          "Accommodation request submitted to our accessibility coordinators.",
      });
    } catch {
      setAccMessage({
        type: "error",
        text: "Network error while submitting request.",
      });
    } finally {
      setAccSaving(false);
    }
  }

  async function handleProfileSave(e: React.FormEvent) {
    e.preventDefault();
    setProfileMessage(null);
    setProfileSaving(true);
    try {
      const res = await fetch("/api/account/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          fullName: fullName.trim(),
          phone: phone.trim(),
          country: country.trim() || "India",
          ...(newPassword
            ? { currentPassword, newPassword }
            : {}),
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setProfileMessage({
          type: "error",
          text: data.error || "Could not update profile.",
        });
        return;
      }
      setUser((prev) => ({
        ...prev,
        fullName: data.user.fullName,
        phone: data.user.phone,
        country: data.user.country,
      }));
      setCurrentPassword("");
      setNewPassword("");
      setProfileMessage({
        type: "success",
        text: data.message || "Profile updated successfully.",
      });
    } catch {
      setProfileMessage({
        type: "error",
        text: "Network error while saving profile.",
      });
    } finally {
      setProfileSaving(false);
    }
  }

  async function handleStartMfa() {
    setMfaMessage(null);
    setMfaLoading(true);
    try {
      const res = await fetch("/api/auth/mfa");
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setMfaMessage({
          type: "error",
          text: data.error || "Could not initialize MFA.",
        });
        return;
      }
      setMfaSetup({ secret: data.secret, qrDataUrl: data.qrDataUrl });
    } catch {
      setMfaMessage({ type: "error", text: "Network error starting MFA." });
    } finally {
      setMfaLoading(false);
    }
  }

  async function handleVerifyMfa(action: "enable" | "disable") {
    setMfaMessage(null);
    setMfaLoading(true);
    try {
      const res = await fetch("/api/auth/mfa", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ code: mfaCode.trim(), action }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setMfaMessage({
          type: "error",
          text: data.error || "Verification failed.",
        });
        return;
      }
      setUser((prev) => ({ ...prev, mfaEnabled: Boolean(data.mfaEnabled) }));
      setMfaSetup(null);
      setMfaCode("");
      setMfaMessage({
        type: "success",
        text:
          action === "enable"
            ? "Two-factor authentication is now enabled on your account."
            : "Two-factor authentication has been disabled.",
      });
    } catch {
      setMfaMessage({ type: "error", text: "Network error verifying MFA." });
    } finally {
      setMfaLoading(false);
    }
  }

  const accLabelMap = new Map(accommodationTypes.map((a) => [a.code, a.label]));

  return (
    <div className="min-h-screen editorial-mesh-light">
      {/* Top Charcoal Header */}
      <header className="bg-[#17151C] text-[#F9F7FC] border-b border-[#383242]">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#D5B77B]">
                {FESTIVAL_NAME} • {FESTIVAL_DATES_DISPLAY}
              </p>
              <h1 className="mt-1 font-display text-2xl sm:text-3xl font-semibold">
                Welcome, {user.fullName}
              </h1>
              <p className="text-sm text-[#D7CCE2] mt-0.5">
                {user.email} • {FESTIVAL_LOCATION_DISPLAY}
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
              {isStaff && (
                <Link
                  href={staffPortalHref}
                  className="min-h-[44px] px-4 py-2 rounded-xl bg-[#D5B77B] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5"
                >
                  Open Staff Portal
                  <ExternalLink className="w-4 h-4" aria-hidden="true" />
                </Link>
              )}
              <Link
                href="/apply"
                className="min-h-[44px] px-4 py-2 rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
              >
                <PlusCircle className="w-4 h-4" aria-hidden="true" />
                Add Role / Registration
              </Link>
              <button
                type="button"
                onClick={handleLogout}
                disabled={loggingOut}
                className="min-h-[44px] px-4 py-2 rounded-xl border border-[#383242] bg-[#25212B] hover:bg-[#383242] text-[#F9F7FC] text-sm font-semibold inline-flex items-center gap-1.5"
              >
                <LogOut className="w-4 h-4" aria-hidden="true" />
                {loggingOut ? "Signing out..." : "Sign Out"}
              </button>
            </div>
          </div>

          {/* Navigation Tabs */}
          <nav
            aria-label="Participant dashboard sections"
            className="mt-6 flex flex-wrap gap-2"
            role="tablist"
          >
            {[
              {
                id: "passes" as const,
                label: `Registrations & QR Passes (${registrations.length})`,
                icon: QrCode,
              },
              {
                id: "accommodations" as const,
                label: `Accommodations (${accommodations.length})`,
                icon: Accessibility,
              },
              {
                id: "settings" as const,
                label: "Profile & Security (MFA)",
                icon: User,
              },
            ].map((tab) => {
              const Icon = tab.icon;
              const selected = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  type="button"
                  role="tab"
                  aria-selected={selected}
                  onClick={() => setActiveTab(tab.id)}
                  className={`min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors ${
                    selected
                      ? "bg-[#F6F1F8] text-[#17151C]"
                      : "bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
                  }`}
                >
                  <Icon className="w-4 h-4" aria-hidden="true" />
                  {tab.label}
                </button>
              );
            })}
          </nav>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {/* ================= TAB 1: REGISTRATIONS & QR PASSES ================= */}
        {activeTab === "passes" && (
          <section aria-label="Your registrations and digital QR passes" className="space-y-6">
            {registrations.length === 0 ? (
              <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-8 text-center space-y-4">
                <ClipboardList
                  className="w-10 h-10 text-[#9273B5] mx-auto"
                  aria-hidden="true"
                />
                <h2 className="font-display text-2xl font-semibold text-[#17151C]">
                  No Registrations Yet
                </h2>
                <p className="text-sm text-[#4B4257] max-w-md mx-auto">
                  Register as an Attendee, Delegate, Volunteer, or Indian Sign Language
                  Interpreter for Purple Festival Gujarat 2027.
                </p>
                <Link
                  href="/apply"
                  className="btn-primary-pfg inline-flex items-center gap-2 min-h-[48px] px-6 py-2.5 rounded-xl bg-[#59436D] text-[#F9F7FC] font-bold text-sm"
                >
                  <PlusCircle className="w-4 h-4" aria-hidden="true" />
                  Start Registration
                </Link>
              </div>
            ) : (
              registrations.map((reg) => {
                const hasVolDuty =
                  reg.volunteerAssignment &&
                  (reg.volunteerAssignment.assignedDepartment ||
                    reg.volunteerAssignment.assignedVenue ||
                    reg.volunteerAssignment.assignedShift);

                const hasSliDuty =
                  reg.sliAssignment &&
                  (reg.sliAssignment.assignedVenue ||
                    reg.sliAssignment.dutyTimes ||
                    (reg.sliAssignment.assignedSessions &&
                      reg.sliAssignment.assignedSessions.length > 0));

                return (
                  <article
                    key={reg.id}
                    className="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-sm overflow-hidden"
                  >
                    <div className="p-6 border-b border-[#59436D]/15 flex flex-wrap items-start justify-between gap-4">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-mono text-sm font-bold text-[#432F56] bg-[#E8DFF0] px-2.5 py-1 rounded-lg">
                            {reg.registrationCode}
                          </span>
                          <span
                            className={`px-2.5 py-1 rounded-lg text-xs font-bold border ${statusBadgeClasses(
                              reg.status
                            )}`}
                          >
                            {reg.status.replace(/_/g, " ")}
                          </span>
                          {reg.paymentStatus !== "NOT_REQUIRED" && (
                            <span className="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#F6F1F8] text-[#4B4257] border border-[#59436D]/20">
                              Payment: {reg.paymentStatus}
                            </span>
                          )}
                        </div>
                        <h2 className="mt-2 font-display text-xl font-semibold text-[#17151C]">
                          {reg.categoryName}
                        </h2>
                        <p className="text-xs text-[#4B4257] mt-1 flex items-center gap-1.5">
                          <CalendarDays className="w-3.5 h-3.5" aria-hidden="true" />
                          Days:{" "}
                          {reg.attendingDays.length > 0
                            ? reg.attendingDays.join(", ")
                            : "All Festival Days"}
                        </p>
                      </div>

                      {reg.activeCredential && (
                        <a
                          href={`/api/qr/${reg.id}/pdf`}
                          download
                          className="btn-primary-pfg inline-flex items-center gap-2 min-h-[44px] px-4 py-2 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] text-sm font-semibold transition-colors"
                        >
                          <Download className="w-4 h-4" aria-hidden="true" />
                          Download Accessible PDF
                        </a>
                      )}
                    </div>

                    <div className="p-6 space-y-5">
                      {/* Digital QR Pass Card */}
                      {reg.activeCredential ? (
                        <div className="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 flex flex-col md:flex-row items-center gap-6">
                          {reg.activeCredential.qrDataUrl && (
                            <div className="bg-white p-3 rounded-xl border border-[#59436D]/20 shrink-0 shadow-sm">
                              <img
                                src={reg.activeCredential.qrDataUrl}
                                alt={`Digital QR pass for ${reg.fullName}, credential code ${reg.activeCredential.credentialCode}`}
                                className="w-44 h-44 object-contain"
                              />
                            </div>
                          )}
                          <div className="space-y-2 text-sm text-[#211B29] w-full">
                            <h3 className="font-display text-lg font-semibold text-[#17151C]">
                              Active Digital QR Entry Pass
                            </h3>
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                              <div>
                                <dt className="text-xs text-[#4B4257]">
                                  Accessible Text Pass ID
                                </dt>
                                <dd className="font-mono font-bold text-[#432F56]">
                                  {reg.activeCredential.credentialCode}
                                </dd>
                              </div>
                              <div>
                                <dt className="text-xs text-[#4B4257]">
                                  Credential Status
                                </dt>
                                <dd className="font-semibold text-[#19734B]">
                                  {reg.activeCredential.status}
                                </dd>
                              </div>
                              <div>
                                <dt className="text-xs text-[#4B4257]">
                                  Participant Name
                                </dt>
                                <dd className="font-semibold">{reg.fullName}</dd>
                              </div>
                              <div>
                                <dt className="text-xs text-[#4B4257]">
                                  Verified Check-ins
                                </dt>
                                <dd className="font-semibold">
                                  {reg.checkins.length > 0
                                    ? `${reg.checkins.length} recorded`
                                    : "Not yet scanned at venue"}
                                </dd>
                              </div>
                            </dl>
                            <p className="text-xs text-[#4B4257] pt-1">
                              Screen-reader &amp; manual fallback: Staff at reception can
                              verify your entry using Pass ID{" "}
                              <strong className="font-mono">
                                {reg.activeCredential.credentialCode}
                              </strong>{" "}
                              without scanning the image.
                            </p>
                          </div>
                        </div>
                      ) : (
                        <div className="rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 text-sm text-[#4B4257]">
                          {reg.status === "SUBMITTED" ||
                          reg.status === "UNDER_REVIEW"
                            ? "Your application is currently under coordinator review. Your digital QR entry pass will appear here automatically once approved."
                            : `Status: ${reg.status.replace(/_/g, " ")}. No active QR credential issued.`}
                        </div>
                      )}

                      {/* Volunteer Duty Assignment */}
                      {hasVolDuty && reg.volunteerAssignment && (
                        <div className="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-2">
                          <h3 className="font-display text-base font-semibold text-[#D5B77B]">
                            Volunteer Duty Assignment
                          </h3>
                          <dl className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            {reg.volunteerAssignment.assignedDepartment && (
                              <div>
                                <dt className="text-xs text-[#D7CCE2]">Department</dt>
                                <dd className="font-semibold">
                                  {reg.volunteerAssignment.assignedDepartment}
                                </dd>
                              </div>
                            )}
                            {reg.volunteerAssignment.assignedVenue && (
                              <div>
                                <dt className="text-xs text-[#D7CCE2]">Venue</dt>
                                <dd className="font-semibold">
                                  {reg.volunteerAssignment.assignedVenue}
                                </dd>
                              </div>
                            )}
                            {reg.volunteerAssignment.assignedShift && (
                              <div>
                                <dt className="text-xs text-[#D7CCE2]">Shift / Reporting</dt>
                                <dd className="font-semibold">
                                  {reg.volunteerAssignment.assignedShift}
                                  {reg.volunteerAssignment.reportingTime
                                    ? ` (${reg.volunteerAssignment.reportingTime})`
                                    : ""}
                                </dd>
                              </div>
                            )}
                            {reg.volunteerAssignment.assignedSupervisor && (
                              <div>
                                <dt className="text-xs text-[#D7CCE2]">Supervisor</dt>
                                <dd className="font-semibold">
                                  {reg.volunteerAssignment.assignedSupervisor}
                                </dd>
                              </div>
                            )}
                            {reg.volunteerAssignment.assignedResponsibility && (
                              <div className="sm:col-span-2">
                                <dt className="text-xs text-[#D7CCE2]">
                                  Responsibility
                                </dt>
                                <dd className="font-semibold">
                                  {reg.volunteerAssignment.assignedResponsibility}
                                </dd>
                              </div>
                            )}
                          </dl>
                        </div>
                      )}

                      {/* SLI Duty Assignment */}
                      {hasSliDuty && reg.sliAssignment && (
                        <div className="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-3">
                          <h3 className="font-display text-base font-semibold text-[#D5B77B]">
                            SLI Interpretation Assignment
                          </h3>
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            {reg.sliAssignment.assignedVenue && (
                              <div>
                                <p className="text-xs text-[#D7CCE2]">Assigned Venue</p>
                                <p className="font-semibold">
                                  {reg.sliAssignment.assignedVenue}
                                </p>
                              </div>
                            )}
                            {reg.sliAssignment.dutyTimes && (
                              <div>
                                <p className="text-xs text-[#D7CCE2]">Duty Schedule</p>
                                <p className="font-semibold">
                                  {reg.sliAssignment.dutyTimes}
                                </p>
                              </div>
                            )}
                          </div>
                          {reg.sliAssignment.assignedSessions.length > 0 && (
                            <ul className="space-y-1.5 text-sm border-t border-[#383242] pt-3">
                              {reg.sliAssignment.assignedSessions.map((s, idx) => (
                                <li key={idx} className="text-[#E8DFF0]">
                                  <strong>{s.day}:</strong> {s.sessionTitle} —{" "}
                                  {s.venue} ({s.dutyTime})
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      )}
                    </div>
                  </article>
                );
              })
            )}
          </section>
        )}

        {/* ================= TAB 2: ACCOMMODATIONS ================= */}
        {activeTab === "accommodations" && (
          <section
            aria-label="Accessibility and accommodation requests"
            className="grid grid-cols-1 lg:grid-cols-2 gap-6"
          >
            <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
              <h2 className="font-display text-xl font-semibold text-[#17151C]">
                Submit Accommodation Request
              </h2>
              <p className="text-sm text-[#4B4257]">
                Need ISL interpretation, Braille materials, wheelchair seating, or
                sensory sanctuary access? Requests are visible only to authorized
                accessibility coordinators.
              </p>

              {accMessage && (
                <div
                  role={accMessage.type === "error" ? "alert" : "status"}
                  className={`rounded-xl p-3.5 text-sm flex items-start gap-2.5 ${
                    accMessage.type === "error"
                      ? "bg-[#B42332]/10 border border-[#B42332] text-[#7A121D]"
                      : "bg-[#19734B]/10 border border-[#19734B] text-[#145C3B]"
                  }`}
                >
                  {accMessage.type === "error" ? (
                    <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
                  ) : (
                    <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
                  )}
                  <span>{accMessage.text}</span>
                </div>
              )}

              {registrations.length === 0 ? (
                <p className="text-sm text-[#4B4257]">
                  Please complete a registration first to link an accommodation
                  request.
                </p>
              ) : (
                <form onSubmit={handleAccommodationSubmit} className="space-y-4">
                  <div>
                    <label
                      htmlFor="acc-reg-select"
                      className="block text-sm font-semibold text-[#211B29] mb-1.5"
                    >
                      Select Registration
                    </label>
                    <select
                      id="acc-reg-select"
                      value={accRegId}
                      onChange={(e) => setAccRegId(e.target.value)}
                      className="w-full min-h-[44px] px-3 py-2 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                    >
                      {registrations.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.registrationCode} — {r.categoryName}
                        </option>
                      ))}
                    </select>
                  </div>

                  <fieldset>
                    <legend className="text-sm font-semibold text-[#211B29] mb-2">
                      Select Accommodations Needed
                    </legend>
                    <div className="grid grid-cols-1 gap-2 max-h-56 overflow-y-auto pr-1">
                      {accommodationTypes.map((a) => {
                        const checked = accSelected.includes(a.code);
                        return (
                          <label
                            key={a.code}
                            className={`cursor-pointer rounded-xl border p-2.5 flex items-center gap-2.5 text-sm ${
                              checked
                                ? "border-[#59436D] bg-[#F6F1F8] font-semibold"
                                : "border-[#59436D]/20"
                            }`}
                          >
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() => toggleAccType(a.code)}
                              className="w-4 h-4 accent-[#59436D]"
                            />
                            {a.label}
                          </label>
                        );
                      })}
                    </div>
                  </fieldset>

                  <div>
                    <label
                      htmlFor="acc-notes"
                      className="block text-sm font-semibold text-[#211B29] mb-1.5"
                    >
                      Additional Details (Optional)
                    </label>
                    <textarea
                      id="acc-notes"
                      rows={3}
                      value={accFreeText}
                      onChange={(e) => setAccFreeText(e.target.value)}
                      placeholder="Share specific timing, seating, or communication preferences..."
                      className="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={accSaving}
                    className="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm disabled:opacity-60"
                  >
                    {accSaving ? "Submitting..." : "Submit Accommodation Request"}
                  </button>
                </form>
              )}
            </div>

            <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
              <h2 className="font-display text-xl font-semibold text-[#17151C]">
                Your Submitted Requests
              </h2>
              {accommodations.length === 0 ? (
                <p className="text-sm text-[#4B4257]">
                  No accommodation requests submitted yet.
                </p>
              ) : (
                <div className="space-y-3">
                  {accommodations.map((req) => (
                    <div
                      key={req.id}
                      className="rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-4 space-y-2"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-mono text-xs font-bold text-[#432F56]">
                          {req.registrationCode}
                        </span>
                        <span
                          className={`px-2.5 py-0.5 rounded-md text-xs font-bold border ${statusBadgeClasses(
                            req.status
                          )}`}
                        >
                          {req.status.replace(/_/g, " ")}
                        </span>
                      </div>
                      {req.selectedTypes.length > 0 && (
                        <ul className="flex flex-wrap gap-1.5">
                          {req.selectedTypes.map((code) => (
                            <li
                              key={code}
                              className="px-2 py-0.5 rounded bg-white border border-[#59436D]/20 text-xs font-medium text-[#211B29]"
                            >
                              {accLabelMap.get(code) || code}
                            </li>
                          ))}
                        </ul>
                      )}
                      {req.freeTextDetails && (
                        <p className="text-sm text-[#4B4257]">
                          {req.freeTextDetails}
                        </p>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </section>
        )}

        {/* ================= TAB 3: PROFILE & SECURITY (MFA) ================= */}
        {activeTab === "settings" && (
          <section
            aria-label="Profile and account security settings"
            className="grid grid-cols-1 lg:grid-cols-2 gap-6"
          >
            {/* Profile & Password Form */}
            <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
              <h2 className="font-display text-xl font-semibold text-[#17151C]">
                Profile &amp; Password
              </h2>

              {profileMessage && (
                <div
                  role={profileMessage.type === "error" ? "alert" : "status"}
                  className={`rounded-xl p-3.5 text-sm ${
                    profileMessage.type === "error"
                      ? "bg-[#B42332]/10 border border-[#B42332] text-[#7A121D]"
                      : "bg-[#19734B]/10 border border-[#19734B] text-[#145C3B]"
                  }`}
                >
                  {profileMessage.text}
                </div>
              )}

              <form onSubmit={handleProfileSave} className="space-y-4">
                <div>
                  <label
                    htmlFor="prof-name"
                    className="block text-sm font-semibold mb-1"
                  >
                    Full Name
                  </label>
                  <input
                    id="prof-name"
                    type="text"
                    required
                    value={fullName}
                    onChange={(e) => setFullName(e.target.value)}
                    className="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
                  />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label
                      htmlFor="prof-phone"
                      className="block text-sm font-semibold mb-1"
                    >
                      Phone Number
                    </label>
                    <input
                      id="prof-phone"
                      type="tel"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      className="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
                    />
                  </div>
                  <div>
                    <label
                      htmlFor="prof-country"
                      className="block text-sm font-semibold mb-1"
                    >
                      Country
                    </label>
                    <input
                      id="prof-country"
                      type="text"
                      value={country}
                      onChange={(e) => setCountry(e.target.value)}
                      className="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
                    />
                  </div>
                </div>

                <div className="pt-3 border-t border-[#59436D]/15 space-y-3">
                  <p className="text-sm font-semibold text-[#432F56] flex items-center gap-1.5">
                    <KeyRound className="w-4 h-4" aria-hidden="true" />
                    Change Password (Optional)
                  </p>
                  <div>
                    <label
                      htmlFor="prof-curr-pw"
                      className="block text-xs font-semibold mb-1"
                    >
                      Current Password
                    </label>
                    <input
                      id="prof-curr-pw"
                      type="password"
                      autoComplete="current-password"
                      value={currentPassword}
                      onChange={(e) => setCurrentPassword(e.target.value)}
                      className="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
                    />
                  </div>
                  <div>
                    <label
                      htmlFor="prof-new-pw"
                      className="block text-xs font-semibold mb-1"
                    >
                      New Password (minimum 8 characters)
                    </label>
                    <input
                      id="prof-new-pw"
                      type="password"
                      autoComplete="new-password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      className="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={profileSaving}
                  className="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm disabled:opacity-60"
                >
                  {profileSaving ? "Saving..." : "Save Changes"}
                </button>
              </form>
            </div>

            {/* MFA Setup Card */}
            <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
              <div className="flex items-center justify-between gap-2">
                <h2 className="font-display text-xl font-semibold text-[#17151C] flex items-center gap-2">
                  <ShieldCheck className="w-5 h-5 text-[#59436D]" aria-hidden="true" />
                  Two-Factor Authentication (MFA)
                </h2>
                <span
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold border ${
                    user.mfaEnabled
                      ? "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30"
                      : "bg-[#F6F1F8] text-[#4B4257] border-[#59436D]/20"
                  }`}
                >
                  {user.mfaEnabled ? "Enabled" : "Not Enabled"}
                </span>
              </div>

              <p className="text-sm text-[#4B4257]">
                Protect your account with a 6-digit TOTP authenticator app (Google
                Authenticator, Authy, 1Password, or Microsoft Authenticator).
              </p>

              {mfaMessage && (
                <div
                  role={mfaMessage.type === "error" ? "alert" : "status"}
                  className={`rounded-xl p-3.5 text-sm ${
                    mfaMessage.type === "error"
                      ? "bg-[#B42332]/10 border border-[#B42332] text-[#7A121D]"
                      : "bg-[#19734B]/10 border border-[#19734B] text-[#145C3B]"
                  }`}
                >
                  {mfaMessage.text}
                </div>
              )}

              {!user.mfaEnabled && !mfaSetup && (
                <button
                  type="button"
                  onClick={handleStartMfa}
                  disabled={mfaLoading}
                  className="min-h-[46px] px-5 py-2.5 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-bold hover:bg-[#25212B]"
                >
                  {mfaLoading ? "Generating QR Code..." : "Set Up Authenticator App"}
                </button>
              )}

              {mfaSetup && !user.mfaEnabled && (
                <div className="rounded-xl bg-[#F6F1F8] border border-[#9273B5]/40 p-4 space-y-4">
                  <div className="flex flex-col sm:flex-row items-center gap-4">
                    <img
                      src={mfaSetup.qrDataUrl}
                      alt="TOTP authenticator setup QR code"
                      className="w-40 h-40 bg-white p-2 rounded-xl border border-[#59436D]/20"
                    />
                    <div className="text-xs space-y-1.5">
                      <p className="font-semibold text-[#17151C]">
                        1. Scan this QR code in your authenticator app
                      </p>
                      <p className="text-[#4B4257]">
                        Or enter this manual secret key:
                      </p>
                      <code className="block p-2 rounded bg-white border border-[#59436D]/20 font-mono text-xs break-all select-all">
                        {mfaSetup.secret}
                      </code>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label
                      htmlFor="mfa-verify-code"
                      className="block text-sm font-semibold text-[#211B29]"
                    >
                      2. Enter the 6-digit code to activate MFA
                    </label>
                    <div className="flex gap-2">
                      <input
                        id="mfa-verify-code"
                        type="text"
                        inputMode="numeric"
                        maxLength={8}
                        value={mfaCode}
                        onChange={(e) => setMfaCode(e.target.value)}
                        placeholder="000000"
                        className="flex-1 min-h-[44px] px-4 rounded-xl border border-[#59436D]/30 bg-white font-mono text-center tracking-widest"
                      />
                      <button
                        type="button"
                        onClick={() => handleVerifyMfa("enable")}
                        disabled={mfaLoading}
                        className="min-h-[44px] px-5 rounded-xl bg-[#59436D] text-white font-bold text-sm"
                      >
                        Verify &amp; Enable
                      </button>
                    </div>
                  </div>
                </div>
              )}

              {user.mfaEnabled && (
                <div className="rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 space-y-3">
                  <p className="text-xs text-[#4B4257]">
                    To disable MFA, enter a current 6-digit code from your authenticator
                    app:
                  </p>
                  <div className="flex gap-2">
                    <label htmlFor="mfa-disable-code" className="sr-only">
                      6-digit authenticator code to disable MFA
                    </label>
                    <input
                      id="mfa-disable-code"
                      type="text"
                      inputMode="numeric"
                      maxLength={8}
                      value={mfaCode}
                      onChange={(e) => setMfaCode(e.target.value)}
                      placeholder="000000"
                      className="flex-1 min-h-[44px] px-4 rounded-xl border border-[#59436D]/30 bg-white font-mono text-center tracking-widest"
                    />
                    <button
                      type="button"
                      onClick={() => handleVerifyMfa("disable")}
                      disabled={mfaLoading}
                      className="min-h-[44px] px-4 rounded-xl border border-[#B42332] text-[#B42332] font-bold text-sm hover:bg-[#B42332]/10"
                    >
                      Disable MFA
                    </button>
                  </div>
                </div>
              )}
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
