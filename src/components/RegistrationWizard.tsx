"use client";

import React, { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import {
  Accessibility,
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  Award,
  Check,
  CheckCircle2,
  Download,
  FileCheck2,
  HandHelping,
  HeartHandshake,
  Info,
  Languages,
  Save,
  ShieldCheck,
  Sparkles,
  Tag,
  Trash2,
  Users,
} from "lucide-react";
import {
  FESTIVAL_DATES_DISPLAY,
  FESTIVAL_DAYS,
  FESTIVAL_LOCATION_DISPLAY,
  FESTIVAL_NAME,
  OCCUPATION_OPTIONS,
} from "@/lib/constants";
import { SiteHeader } from "@/components/SiteHeader";

export interface CategoryOption {
  id: string;
  code: string;
  name: string;
  description: string | null;
  requiresApproval: boolean;
  requiresPayment: boolean;
  feeAmountInr: number;
}

export interface DisabilityOption {
  id: string;
  code: string;
  name: string;
  rpwdGroup: string;
  description: string;
}

export interface AccommodationOption {
  id: string;
  code: string;
  label: string;
  description: string | null;
}

export interface WizardProps {
  initialCategories: CategoryOption[];
  disabilityCategories: DisabilityOption[];
  accommodationTypes: AccommodationOption[];
  currentUser: {
    id: string;
    fullName: string;
    email: string;
    phone: string | null;
    country: string;
  } | null;
  initialCategoryParam: string | null;
}

interface WizardDraft {
  fullName: string;
  email: string;
  phone: string;
  country: string;
  categoryCode: string;
  occupation: string;
  organizationName: string;
  designation: string;
  attendingDays: string[];
  isPersonWithDisability: "YES" | "NO" | "PREFER_NOT_TO_SAY";
  disabilityCategoryCodes: string[];
  disabilitySelfDescription: string;
  accommodationTypes: string[];
  accommodationFreeText: string;
  couponCode: string;
  consentZeroTolerance: boolean;
  consentDataProcessing: boolean;
  consentMediaPhotography: boolean;
  // Delegate
  delegateGender: string;
  delegateAge: string;
  delegateCityState: string;
  delegateParaSports: string[];
  delegateOutstation: boolean;
  delegateAccommodation: boolean;
  delegateFood: string;
  delegateEmergencyName: string;
  delegateEmergencyPhone: string;
  delegateGuardianName: string;
  // Volunteer
  volAge: string;
  volGender: string;
  volCityState: string;
  volInstitution: string;
  volMsuAlumni: boolean;
  volCrrNumber: string;
  volPreferredRoles: string[];
  volLanguages: string[];
  volExperience: string;
  volEmergencyName: string;
  volEmergencyPhone: string;
  // SLI
  sliCityState: string;
  sliBasedInGujarat: boolean;
  sliCrrNumber: string;
  sliQualifications: string;
  sliTrainingInstitute: string;
  sliCertificationLevel: string;
  sliYearsExperience: string;
  sliAffiliation: string;
  sliAreasExpertise: string[];
  sliInterpretPrefs: string[];
  sliVideoDemoUrl: string;
  sliHonorariumNotes: string;
}

const DRAFT_STORAGE_KEY = "pfg2027_registration_draft_v1";

const VOLUNTEER_ROLE_OPTIONS = [
  "Accessibility & Inclusion Desk",
  "Stage & Cultural Coordination",
  "Para-Sports Support",
  "Registration & Hospitality",
  "Sign Language / Communication Support",
  "Media & Documentation",
];

const LANGUAGE_OPTIONS = ["Gujarati", "Hindi", "English", "Indian Sign Language (ISL)", "Marathi"];

const PARA_SPORTS_OPTIONS = [
  "Wheelchair Basketball",
  "Para Athletics",
  "Blind Cricket",
  "Para Badminton",
  "Boccia",
  "Para Table Tennis",
  "Chess",
];

const SLI_EXPERTISE_OPTIONS = [
  "Plenary & Keynote Sessions",
  "Panel Discussions & Policy",
  "Workshops & Education",
  "Cultural & Stage Performances",
  "Tactile / Deafblind Interpretation",
  "Para-Sports & Community Events",
];

const SLI_PREF_OPTIONS = [
  "ISL ↔ English",
  "ISL ↔ Gujarati",
  "ISL ↔ Hindi",
  "Team Interpreting (20-min rotations)",
  "Platform / Stage Interpreting",
];

const STEPS = [
  { num: 1, title: "Personal Details" },
  { num: 2, title: "Category & Role" },
  { num: 3, title: "Accessibility" },
  { num: 4, title: "Declarations" },
  { num: 5, title: "Review & Submit" },
];

function createEmptyDraft(user: WizardProps["currentUser"]): WizardDraft {
  return {
    fullName: user?.fullName || "",
    email: user?.email || "",
    phone: user?.phone || "",
    country: user?.country || "India",
    categoryCode: "ATTENDEE",
    occupation: "Student",
    organizationName: "",
    designation: "",
    attendingDays: ["DAY_1", "DAY_2", "DAY_3"],
    isPersonWithDisability: "PREFER_NOT_TO_SAY",
    disabilityCategoryCodes: [],
    disabilitySelfDescription: "",
    accommodationTypes: [],
    accommodationFreeText: "",
    couponCode: "",
    consentZeroTolerance: false,
    consentDataProcessing: false,
    consentMediaPhotography: false,
    delegateGender: "",
    delegateAge: "",
    delegateCityState: "",
    delegateParaSports: [],
    delegateOutstation: false,
    delegateAccommodation: false,
    delegateFood: "Vegetarian",
    delegateEmergencyName: "",
    delegateEmergencyPhone: "",
    delegateGuardianName: "",
    volAge: "",
    volGender: "",
    volCityState: "",
    volInstitution: "",
    volMsuAlumni: false,
    volCrrNumber: "",
    volPreferredRoles: [],
    volLanguages: ["English", "Gujarati"],
    volExperience: "",
    volEmergencyName: "",
    volEmergencyPhone: "",
    sliCityState: "",
    sliBasedInGujarat: true,
    sliCrrNumber: "",
    sliQualifications: "",
    sliTrainingInstitute: "",
    sliCertificationLevel: "",
    sliYearsExperience: "",
    sliAffiliation: "",
    sliAreasExpertise: [],
    sliInterpretPrefs: [],
    sliVideoDemoUrl: "",
    sliHonorariumNotes: "",
  };
}

interface SubmissionResult {
  registration: {
    id: string;
    registrationCode: string;
    categoryCode: string;
    status: string;
    paymentStatus: string;
    fullName: string;
    email: string;
  };
  category: {
    code: string;
    name: string;
    requiresApproval: boolean;
    requiresPayment: boolean;
  };
  pricing: {
    feeAmountInr: number;
    discountInr: number;
    netAmountInr: number;
    couponCode: string | null;
  };
  qrPass: {
    credentialCode: string;
    rawToken: string;
    qrDataUrl: string;
  } | null;
  sessionCreated: boolean;
}

export default function RegistrationWizard({
  initialCategories,
  disabilityCategories,
  accommodationTypes,
  currentUser,
  initialCategoryParam,
}: WizardProps) {
  const [showHub, setShowHub] = useState<boolean>(!initialCategoryParam);
  const [selectedPathwayId, setSelectedPathwayId] = useState<string>("ATTENDEE");
  const [step, setStep] = useState<number>(1);
  const [draft, setDraft] = useState<WizardDraft>(() =>
    createEmptyDraft(currentUser)
  );
  const [password, setPassword] = useState("");
  const [disabilitySearch, setDisabilitySearch] = useState("");
  const [draftNotice, setDraftNotice] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<SubmissionResult | null>(null);

  // Coupon state
  const [couponChecking, setCouponChecking] = useState(false);
  const [couponResult, setCouponResult] = useState<{
    valid: boolean;
    message: string;
    discountInr?: number;
    netAmountInr?: number;
    baseAmountInr?: number;
  } | null>(null);

  const errorBannerRef = useRef<HTMLDivElement>(null);

  // Restore saved draft on mount
  useEffect(() => {
    try {
      const saved = window.localStorage.getItem(DRAFT_STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved) as Partial<WizardDraft>;
        setDraft((prev) => ({
          ...prev,
          ...parsed,
          fullName: currentUser?.fullName || parsed.fullName || prev.fullName,
          email: currentUser?.email || parsed.email || prev.email,
        }));
        setDraftNotice("Saved registration draft restored.");
      }
    } catch {
      // Ignore storage errors
    }
  }, [currentUser]);

  // Apply URL param if provided
  useEffect(() => {
    if (!initialCategoryParam) return;
    const upper = initialCategoryParam.toUpperCase();
    if (upper === "ACCESSIBLE" || upper === "PWD") {
      setDraft((prev) => ({ ...prev, isPersonWithDisability: "YES" }));
      setShowHub(false);
    } else if (initialCategories.some((c) => c.code === upper)) {
      setDraft((prev) => ({ ...prev, categoryCode: upper }));
      setShowHub(false);
    }
  }, [initialCategoryParam, initialCategories]);

  // Auto-save draft to localStorage (NEVER saving password)
  useEffect(() => {
    if (result) return;
    try {
      window.localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
    } catch {
      // Ignore quota errors
    }
  }, [draft, result]);

  const selectedCategory = useMemo(
    () =>
      initialCategories.find((c) => c.code === draft.categoryCode) ||
      initialCategories[0],
    [initialCategories, draft.categoryCode]
  );

  const filteredDisabilities = useMemo(() => {
    const q = disabilitySearch.trim().toLowerCase();
    if (!q) return disabilityCategories;
    return disabilityCategories.filter(
      (d) =>
        d.name.toLowerCase().includes(q) ||
        d.rpwdGroup.toLowerCase().includes(q)
    );
  }, [disabilityCategories, disabilitySearch]);

  function updateField<K extends keyof WizardDraft>(key: K, value: WizardDraft[K]) {
    setDraft((prev) => ({ ...prev, [key]: value }));
    if (errors[key as string]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[key as string];
        return next;
      });
    }
  }

  function toggleArrayValue(key: keyof WizardDraft, value: string) {
    setDraft((prev) => {
      const current = (prev[key] as string[]) || [];
      const exists = current.includes(value);
      return {
        ...prev,
        [key]: exists
          ? current.filter((v) => v !== value)
          : [...current, value],
      };
    });
  }

  function startPathway(
    categoryCode: string,
    options?: { preselectPwd?: boolean }
  ) {
    setDraft((prev) => ({
      ...prev,
      categoryCode,
      ...(options?.preselectPwd ? { isPersonWithDisability: "YES" as const } : {}),
    }));
    setCouponResult(null);
    setShowHub(false);
    setStep(1);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function handleSaveLater() {
    try {
      window.localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
      setDraftNotice(
        "Your progress has been saved on this device. You can return anytime to finish."
      );
    } catch {
      setDraftNotice("Could not access browser storage to save draft.");
    }
  }

  function handleClearDraft() {
    try {
      window.localStorage.removeItem(DRAFT_STORAGE_KEY);
    } catch {
      // ignore
    }
    setDraft(createEmptyDraft(currentUser));
    setPassword("");
    setCouponResult(null);
    setErrors({});
    setDraftNotice("Draft cleared.");
  }

  function validateStep(currentStep: number): boolean {
    const nextErrors: Record<string, string> = {};

    if (currentStep === 1) {
      if (draft.fullName.trim().length < 2) {
        nextErrors.fullName = "Please enter your full name (at least 2 characters).";
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(draft.email.trim())) {
        nextErrors.email = "Please enter a valid email address.";
      }
      if (!/^\+?[0-9\s\-()]{7,20}$/.test(draft.phone.trim())) {
        nextErrors.phone =
          "Please enter a valid phone number (e.g., +91 98250 12345).";
      }
      if (draft.country.trim().length < 2) {
        nextErrors.country = "Please enter your country.";
      }
      if (!currentUser && password.length > 0 && password.length < 8) {
        nextErrors.password = "Password must be at least 8 characters long.";
      }
    }

    if (currentStep === 2) {
      if (!draft.categoryCode) {
        nextErrors.categoryCode = "Please choose a registration category.";
      }
      if (!draft.occupation) {
        nextErrors.occupation = "Please select your occupation or affiliation.";
      }
      if (draft.attendingDays.length === 0) {
        nextErrors.attendingDays = "Please select at least one festival day.";
      }
      if (
        draft.categoryCode === "SLI" &&
        draft.sliVideoDemoUrl.trim() &&
        !/^https?:\/\/.+/.test(draft.sliVideoDemoUrl.trim())
      ) {
        nextErrors.sliVideoDemoUrl =
          "Video demo link must start with https:// or http://.";
      }
    }

    if (currentStep === 4) {
      if (!draft.consentZeroTolerance) {
        nextErrors.consentZeroTolerance =
          "You must acknowledge the zero-tolerance policy towards harassment.";
      }
      if (!draft.consentDataProcessing) {
        nextErrors.consentDataProcessing =
          "You must consent to the processing of your registration data under the DPDP Act.";
      }
    }

    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      setTimeout(() => errorBannerRef.current?.focus(), 20);
      return false;
    }
    return true;
  }

  function handleNext() {
    if (validateStep(step)) {
      setStep((s) => Math.min(5, s + 1));
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }

  function handleBack() {
    setErrors({});
    setStep((s) => Math.max(1, s - 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function handleVerifyCoupon() {
    const code = draft.couponCode.trim();
    if (!code) {
      setCouponResult({ valid: false, message: "Please enter a coupon code first." });
      return;
    }
    setCouponChecking(true);
    setCouponResult(null);
    try {
      const res = await fetch("/api/coupons/validate", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          code,
          categoryCode: draft.categoryCode,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.valid) {
        setCouponResult({
          valid: false,
          message: data.error || "Coupon code is not valid.",
        });
        return;
      }
      setCouponResult({
        valid: true,
        message: `Coupon ${data.coupon.code} applied! Discount: ₹${data.pricing.discountInr} (Net payable: ₹${data.pricing.netAmountInr}).`,
        discountInr: data.pricing.discountInr,
        netAmountInr: data.pricing.netAmountInr,
        baseAmountInr: data.pricing.baseAmountInr,
      });
    } catch {
      setCouponResult({
        valid: false,
        message: "Unable to verify coupon right now.",
      });
    } finally {
      setCouponChecking(false);
    }
  }

  async function handleSubmit() {
    setSubmitError(null);
    if (!validateStep(1) || !validateStep(2) || !validateStep(4)) {
      setSubmitError("Please review the required fields before submitting.");
      return;
    }

    setSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        fullName: draft.fullName.trim(),
        email: draft.email.trim(),
        phone: draft.phone.trim(),
        country: draft.country.trim() || "India",
        ...(password.trim() ? { password: password.trim() } : {}),
        categoryCode: draft.categoryCode,
        occupation: draft.occupation,
        organizationName: draft.organizationName.trim(),
        designation: draft.designation.trim(),
        isPersonWithDisability: draft.isPersonWithDisability,
        disabilityCategoryCodes:
          draft.isPersonWithDisability === "YES"
            ? draft.disabilityCategoryCodes
            : [],
        disabilitySelfDescription:
          draft.isPersonWithDisability === "YES"
            ? draft.disabilitySelfDescription.trim()
            : "",
        accommodationTypes: draft.accommodationTypes,
        accommodationFreeText: draft.accommodationFreeText.trim(),
        attendingDays: draft.attendingDays,
        interestedTracks: [],
        couponCode: draft.couponCode.trim(),
        consentZeroTolerance: draft.consentZeroTolerance,
        consentDataProcessing: draft.consentDataProcessing,
        consentMediaPhotography: draft.consentMediaPhotography,
      };

      if (draft.categoryCode === "DELEGATE") {
        payload.delegateDetails = {
          gender: draft.delegateGender || undefined,
          age: draft.delegateAge ? Number(draft.delegateAge) : undefined,
          cityState: draft.delegateCityState || undefined,
          paraSportsInterests: draft.delegateParaSports,
          outstationTravelRequired: draft.delegateOutstation,
          accommodationNeeded: draft.delegateAccommodation,
          foodPreference: draft.delegateFood || undefined,
          emergencyContactName: draft.delegateEmergencyName || undefined,
          emergencyContactPhone: draft.delegateEmergencyPhone || undefined,
          guardianName: draft.delegateGuardianName || undefined,
        };
      }

      if (draft.categoryCode === "VOLUNTEER") {
        payload.volunteerDetails = {
          age: draft.volAge ? Number(draft.volAge) : undefined,
          gender: draft.volGender || undefined,
          cityState: draft.volCityState || undefined,
          institutionName: draft.volInstitution || undefined,
          isMsuStudentOrAlumni: draft.volMsuAlumni,
          crrNumber: draft.volCrrNumber || undefined,
          preferredRoles: draft.volPreferredRoles,
          availabilityDays: draft.attendingDays,
          languagesSpoken: draft.volLanguages,
          relevantExperience: draft.volExperience || undefined,
          emergencyContactName: draft.volEmergencyName || undefined,
          emergencyContactPhone: draft.volEmergencyPhone || undefined,
        };
      }

      if (draft.categoryCode === "SLI") {
        payload.sliDetails = {
          cityState: draft.sliCityState || undefined,
          basedInGujarat: draft.sliBasedInGujarat,
          crrNumber: draft.sliCrrNumber || undefined,
          qualifications: draft.sliQualifications || undefined,
          trainingInstitute: draft.sliTrainingInstitute || undefined,
          certificationLevel: draft.sliCertificationLevel || undefined,
          yearsOfExperience: draft.sliYearsExperience
            ? Number(draft.sliYearsExperience)
            : undefined,
          professionalAffiliation: draft.sliAffiliation || undefined,
          areasOfExpertise: draft.sliAreasExpertise,
          interpretationPreferences: draft.sliInterpretPrefs,
          availabilityDays: draft.attendingDays,
          videoDemoUrl: draft.sliVideoDemoUrl.trim() || "",
          honorariumOrTravelNotes: draft.sliHonorariumNotes || undefined,
        };
      }

      const res = await fetch("/api/registrations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        setSubmitError(
          data.error || "Registration could not be completed. Please review your details."
        );
        return;
      }

      try {
        window.localStorage.removeItem(DRAFT_STORAGE_KEY);
      } catch {
        // ignore
      }
      setResult(data as SubmissionResult);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch {
      setSubmitError("Network error while submitting registration. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }

  // ============================================================================
  // CONFIRMATION VIEW
  // ============================================================================
  if (result) {
    return (
      <div className="min-h-screen editorial-mesh-light py-10 px-4 sm:px-6 lg:px-8">
        <div className="max-w-3xl mx-auto">
          <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-xl overflow-hidden">
            <div className="bg-[#17151C] text-[#F9F7FC] p-6 sm:p-8">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#19734B]/25 border border-[#56C28E]/50 text-[#9FE8C4] text-xs font-semibold uppercase tracking-wider mb-3">
                <CheckCircle2 className="w-4 h-4" aria-hidden="true" />
                {result.qrPass
                  ? "Registration Confirmed"
                  : result.category.requiresApproval
                    ? "Application Submitted for Review"
                    : "Registration Received"}
              </div>
              <h1 className="font-display text-2xl sm:text-3xl font-semibold">
                {result.qrPass
                  ? `Welcome to ${FESTIVAL_NAME}!`
                  : `Thank you for applying as ${result.category.name}`}
              </h1>
              <p className="mt-2 text-sm text-[#D7CCE2]">
                {FESTIVAL_DATES_DISPLAY} • {FESTIVAL_LOCATION_DISPLAY}
              </p>
            </div>

            <div className="p-6 sm:p-8 space-y-6 text-[#211B29]">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-[#F6F1F8] border border-[#59436D]/15 p-4">
                <div>
                  <p className="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">
                    Registration Code
                  </p>
                  <p className="text-lg font-mono font-bold text-[#432F56]">
                    {result.registration.registrationCode}
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">
                    Category & Status
                  </p>
                  <p className="text-base font-semibold text-[#211B29]">
                    {result.category.name}{" "}
                    <span className="inline-block ml-1 px-2 py-0.5 rounded-md text-xs font-bold bg-[#E8DFF0] text-[#432F56]">
                      {result.registration.status}
                    </span>
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">
                    Participant
                  </p>
                  <p className="text-sm font-medium">{result.registration.fullName}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">
                    Email
                  </p>
                  <p className="text-sm font-medium">{result.registration.email}</p>
                </div>
              </div>

              {result.qrPass ? (
                <div className="rounded-2xl border-2 border-[#9273B5] bg-[#F9F7FC] p-6 flex flex-col sm:flex-row items-center gap-6">
                  <div className="shrink-0 bg-white p-3 rounded-xl border border-[#59436D]/20 shadow-sm">
                    <img
                      src={result.qrPass.qrDataUrl}
                      alt={`Digital QR entry pass for ${result.registration.fullName}, registration code ${result.qrPass.credentialCode}`}
                      className="w-48 h-48 object-contain"
                    />
                  </div>
                  <div className="space-y-3 text-center sm:text-left">
                    <h2 className="font-display text-xl font-semibold text-[#17151C]">
                      Your Digital QR Entry Pass
                    </h2>
                    <p className="text-sm text-[#4B4257]">
                      Present this QR code or your Registration ID{" "}
                      <strong className="font-mono text-[#211B29]">
                        {result.qrPass.credentialCode}
                      </strong>{" "}
                      at any festival reception desk. Your QR code contains zero personal
                      or medical information.
                    </p>
                    <div className="flex flex-wrap gap-3 justify-center sm:justify-start pt-1">
                      <a
                        href={`/api/qr/${result.registration.id}/pdf`}
                        download
                        className="btn-primary-pfg inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-semibold text-sm transition-colors"
                      >
                        <Download className="w-4 h-4" aria-hidden="true" />
                        Download Accessible PDF
                      </a>
                      <Link
                        href="/dashboard"
                        className="inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-xl border-2 border-[#59436D] text-[#432F56] font-semibold text-sm hover:bg-[#E8DFF0]"
                      >
                        Open Participant Dashboard
                      </Link>
                    </div>
                  </div>
                </div>
              ) : result.category.requiresApproval ? (
                <div className="rounded-xl bg-[#E8DFF0]/60 border border-[#9273B5] p-5 space-y-2">
                  <h2 className="font-semibold text-[#432F56] flex items-center gap-2">
                    <Info className="w-5 h-5" aria-hidden="true" />
                    Coordinator Review Required
                  </h2>
                  <p className="text-sm text-[#211B29]">
                    Volunteer and Indian Sign Language Interpreter applications are reviewed
                    by our festival coordinators before duty assignment. Once approved,
                    your official Digital QR Credential and duty schedule will appear
                    automatically in your Participant Dashboard.
                  </p>
                </div>
              ) : null}

              <div className="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-[#59436D]/15">
                <button
                  type="button"
                  onClick={() => {
                    setResult(null);
                    setShowHub(true);
                    setStep(1);
                    setDraft(createEmptyDraft(currentUser));
                  }}
                  className="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 text-sm font-semibold text-[#432F56] hover:bg-[#F6F1F8]"
                >
                  Register for Another Role
                </button>
                <Link
                  href={result.sessionCreated || currentUser ? "/dashboard" : "/login"}
                  className="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-semibold hover:bg-[#25212B] inline-flex items-center gap-2"
                >
                  {result.sessionCreated || currentUser
                    ? "Go to Participant Dashboard"
                    : "Sign In to Manage Registration"}
                  <ArrowRight className="w-4 h-4" aria-hidden="true" />
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // ============================================================================
  // REGISTRATION HUB VIEW
  // ============================================================================
  if (showHub) {
    const pathways = [
      {
        id: "ATTENDEE",
        title: "General Attendee",
        badge: "Instant QR Pass",
        description:
          "Open access to cultural performances, assistive tech showcase, para-sports, and inclusive public sessions.",
        icon: Users,
        onClick: () => startPathway("ATTENDEE"),
      },
      {
        id: "ACCESSIBLE",
        title: "Person with Disability / Accessible Registration",
        badge: "Any Role + Full Support",
        description:
          "Pre-opens our RPwD Act 2016 accessibility & accommodation planner (ISL, Braille, wheelchair, sensory sanctuary) while letting you choose ANY festival role.",
        icon: Accessibility,
        highlight: true,
        onClick: () => startPathway("ATTENDEE", { preselectPwd: true }),
      },
      {
        id: "DELEGATE",
        title: "Delegate (3-Day Conference)",
        badge: "All-Access Track",
        description:
          "Full access to plenaries, policy dialogues, workshops, para-sports, and outstation delegate coordination.",
        icon: Award,
        onClick: () => startPathway("DELEGATE"),
      },
      {
        id: "VOLUNTEER",
        title: "Festival Volunteer",
        badge: "Coordinator Review",
        description:
          "Support accessibility desks, hospitality, stage management, and para-sports. Open to MSU Baroda students, alumni, and community members.",
        icon: HandHelping,
        onClick: () => startPathway("VOLUNTEER"),
      },
      {
        id: "SLI",
        title: "SLI / Indian Sign Language Professional",
        badge: "Verification & Assignment",
        description:
          "Qualified Indian Sign Language interpreters supporting plenaries, panels, workshops, and cultural stages across Vadodara.",
        icon: Languages,
        onClick: () => startPathway("SLI"),
      },
    ];

    const activePathway =
      pathways.find((p) => p.id === selectedPathwayId) ?? pathways[0];

    return (
      <>
        <SiteHeader />
        <main
          id="main-content"
          tabIndex={-1}
          className="min-h-[calc(100vh-76px)] bg-white text-[#202D59] focus:outline-none"
        >
          <div className="mx-auto flex max-w-[560px] flex-col px-5 pb-20 pt-14 sm:pt-20">
            <h1 className="text-[clamp(1.65rem,3.4vw,2.3rem)] font-bold leading-[1.2] tracking-[-0.02em] text-[#202D59]">
              What type of registration are you interested in for Purple Fest Gujarat?
            </h1>

            <div
              role="radiogroup"
              aria-label="Registration pathway options"
              className="mt-8 flex flex-col gap-3"
            >
              {pathways.map((p) => {
                const isSelected = selectedPathwayId === p.id;
                return (
                  <button
                    key={p.id}
                    type="button"
                    role="radio"
                    aria-checked={isSelected}
                    onClick={() => setSelectedPathwayId(p.id)}
                    onDoubleClick={p.onClick}
                    className={`touch-target flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors ${
                      isSelected
                        ? "bg-[#CFCFF8] ring-2 ring-[#202D59]"
                        : "bg-[#DDDDF9] hover:bg-[#D2D2F7]"
                    }`}
                  >
                    <div className="min-w-0">
                      <span className="block text-[17px] font-semibold leading-snug text-[#202D59]">
                        {p.title}
                      </span>
                      <span className="mt-0.5 block text-xs font-medium text-[#46537C]">
                        {p.badge}
                      </span>
                    </div>
                    {isSelected && (
                      <Check
                        className="h-5 w-5 shrink-0 text-[#202D59]"
                        aria-hidden="true"
                      />
                    )}
                  </button>
                );
              })}
            </div>

            <div className="mt-6 flex items-center justify-between gap-4">
              <Link
                href="/"
                className="inline-flex items-center gap-1.5 text-sm font-medium text-[#46537C] hover:text-[#202D59] hover:underline"
              >
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back to Home
              </Link>

              <button
                type="button"
                onClick={() => activePathway?.onClick()}
                className="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-[#DDDDF9] px-7 py-2.5 text-[16px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
              >
                Next
              </button>
            </div>
          </div>
        </main>
      </>
    );
  }

  // ============================================================================
  // 5-STEP REGISTRATION WIZARD
  // ============================================================================
  const errorEntries = Object.entries(errors);

  return (
    <div className="min-h-screen editorial-mesh-light py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-3xl mx-auto space-y-6">
        {/* Top Bar */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            onClick={() => setShowHub(true)}
            className="inline-flex items-center gap-2 text-sm font-semibold text-[#432F56] hover:underline touch-target"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            Change Pathway
          </button>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleSaveLater}
              className="min-h-[44px] px-3.5 py-2 rounded-xl bg-white border border-[#59436D]/25 text-xs sm:text-sm font-semibold text-[#432F56] hover:bg-[#E8DFF0] inline-flex items-center gap-1.5"
            >
              <Save className="w-4 h-4" aria-hidden="true" />
              Save &amp; Continue Later
            </button>
            <button
              type="button"
              onClick={handleClearDraft}
              className="min-h-[44px] px-3 py-2 rounded-xl border border-[#59436D]/20 text-xs sm:text-sm font-medium text-[#4B4257] hover:bg-white inline-flex items-center gap-1"
              aria-label="Clear saved registration draft"
            >
              <Trash2 className="w-4 h-4" aria-hidden="true" />
              Clear
            </button>
          </div>
        </div>

        {/* Draft Status Live Region */}
        {draftNotice && (
          <div
            role="status"
            aria-live="polite"
            className="rounded-xl bg-[#E8DFF0] border border-[#9273B5]/40 px-4 py-2.5 text-sm text-[#432F56] flex items-center justify-between"
          >
            <span>{draftNotice}</span>
            <button
              type="button"
              onClick={() => setDraftNotice(null)}
              className="text-xs font-bold underline ml-4"
            >
              Dismiss
            </button>
          </div>
        )}

        {/* Progress Indicator */}
        <nav aria-label="Registration steps" className="surface-card rounded-2xl bg-[#17151C] text-[#F9F7FC] p-4 sm:p-5">
          <ol className="grid grid-cols-5 gap-2">
            {STEPS.map((s) => {
              const active = s.num === step;
              const completed = s.num < step;
              return (
                <li key={s.num} className="flex flex-col items-center text-center">
                  <button
                    type="button"
                    onClick={() => {
                      if (s.num < step) setStep(s.num);
                    }}
                    disabled={s.num > step}
                    aria-current={active ? "step" : undefined}
                    className={`w-9 h-9 rounded-full font-bold text-sm flex items-center justify-center transition-colors ${
                      active
                        ? "bg-[#D5B77B] text-[#17151C]"
                        : completed
                          ? "bg-[#9273B5] text-[#17151C] hover:bg-[#A58DBB]"
                          : "bg-[#25212B] text-[#D7CCE2] border border-[#383242]"
                    }`}
                  >
                    {completed ? "✓" : s.num}
                  </button>
                  <span
                    className={`mt-1.5 text-[11px] sm:text-xs font-medium ${
                      active ? "text-[#D5B77B] font-bold" : "text-[#D7CCE2]"
                    }`}
                  >
                    {s.title}
                  </span>
                </li>
              );
            })}
          </ol>
        </nav>

        {/* Main Card */}
        <div className="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-lg p-6 sm:p-8 text-[#211B29]">
          {/* Accessible Error Summary */}
          {(errorEntries.length > 0 || submitError) && (
            <div
              ref={errorBannerRef}
              tabIndex={-1}
              role="alert"
              className="mb-6 rounded-xl bg-[#B42332]/10 border-2 border-[#B42332] p-4 text-[#7A121D]"
            >
              <div className="flex items-center gap-2 font-bold text-sm">
                <AlertCircle className="w-5 h-5 shrink-0" aria-hidden="true" />
                Please correct the following before continuing:
              </div>
              {submitError && <p className="mt-1.5 text-sm">{submitError}</p>}
              {errorEntries.length > 0 && (
                <ul className="mt-2 list-disc list-inside text-sm space-y-1">
                  {errorEntries.map(([field, msg]) => (
                    <li key={field}>{msg}</li>
                  ))}
                </ul>
              )}
            </div>
          )}

          {/* ================= STEP 1: PERSONAL DETAILS ================= */}
          {step === 1 && (
            <div className="space-y-5">
              <div>
                <h1 className="font-display text-2xl font-semibold text-[#17151C]">
                  Step 1: Personal Details
                </h1>
                <p className="mt-1 text-sm text-[#4B4257]">
                  Enter your contact details for your official festival credential.
                </p>
              </div>

              {currentUser && (
                <div className="rounded-xl bg-[#E8DFF0]/70 border border-[#9273B5] p-3.5 text-sm text-[#432F56] flex items-center gap-2">
                  <ShieldCheck className="w-5 h-5 shrink-0" aria-hidden="true" />
                  <span>
                    Signed in as <strong>{currentUser.email}</strong>. This registration
                    will be linked to your single festival account.
                  </span>
                </div>
              )}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <label
                    htmlFor="reg-fullName"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Full Name <span aria-hidden="true" className="text-[#B42332]">*</span>
                  </label>
                  <input
                    id="reg-fullName"
                    type="text"
                    required
                    autoComplete="name"
                    aria-invalid={Boolean(errors.fullName)}
                    value={draft.fullName}
                    onChange={(e) => updateField("fullName", e.target.value)}
                    placeholder="e.g., Meera Deshmukh"
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  />
                  {errors.fullName && (
                    <p className="mt-1 text-xs font-medium text-[#B42332]">
                      {errors.fullName}
                    </p>
                  )}
                </div>

                <div>
                  <label
                    htmlFor="reg-email"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Email Address <span aria-hidden="true" className="text-[#B42332]">*</span>
                  </label>
                  <input
                    id="reg-email"
                    type="email"
                    required
                    autoComplete="email"
                    disabled={Boolean(currentUser)}
                    aria-invalid={Boolean(errors.email)}
                    value={draft.email}
                    onChange={(e) => updateField("email", e.target.value)}
                    placeholder="you@example.org"
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29] disabled:bg-[#F6F1F8]"
                  />
                  {errors.email && (
                    <p className="mt-1 text-xs font-medium text-[#B42332]">
                      {errors.email}
                    </p>
                  )}
                </div>

                <div>
                  <label
                    htmlFor="reg-phone"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Phone Number <span aria-hidden="true" className="text-[#B42332]">*</span>
                  </label>
                  <input
                    id="reg-phone"
                    type="tel"
                    required
                    autoComplete="tel"
                    aria-invalid={Boolean(errors.phone)}
                    value={draft.phone}
                    onChange={(e) => updateField("phone", e.target.value)}
                    placeholder="+91 98250 12345"
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  />
                  {errors.phone && (
                    <p className="mt-1 text-xs font-medium text-[#B42332]">
                      {errors.phone}
                    </p>
                  )}
                </div>

                <div>
                  <label
                    htmlFor="reg-country"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Country <span aria-hidden="true" className="text-[#B42332]">*</span>
                  </label>
                  <input
                    id="reg-country"
                    type="text"
                    required
                    autoComplete="country-name"
                    value={draft.country}
                    onChange={(e) => updateField("country", e.target.value)}
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  />
                </div>

                {!currentUser && (
                  <div>
                    <label
                      htmlFor="reg-password"
                      className="block text-sm font-semibold text-[#211B29] mb-1.5"
                    >
                      Create Portal Password (Optional)
                    </label>
                    <input
                      id="reg-password"
                      type="password"
                      autoComplete="new-password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Min. 8 characters"
                      className="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                    />
                    <p className="mt-1 text-xs text-[#4B4257]">
                      Set a password to immediately sign in to your Participant Dashboard
                      and download your QR pass.
                    </p>
                    {errors.password && (
                      <p className="mt-1 text-xs font-medium text-[#B42332]">
                        {errors.password}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* ================= STEP 2: CATEGORY & ROLE DETAILS ================= */}
          {step === 2 && (
            <div className="space-y-6">
              <div>
                <h1 className="font-display text-2xl font-semibold text-[#17151C]">
                  Step 2: Registration Category &amp; Role Details
                </h1>
                <p className="mt-1 text-sm text-[#4B4257]">
                  Select your participation category. Disability disclosure is handled
                  separately in Step 3 and never limits your category choice.
                </p>
              </div>

              <fieldset>
                <legend className="text-sm font-semibold text-[#211B29] mb-2">
                  Participation Category <span aria-hidden="true" className="text-[#B42332]">*</span>
                </legend>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {initialCategories.map((cat) => {
                    const checked = draft.categoryCode === cat.code;
                    return (
                      <label
                        key={cat.id}
                        className={`cursor-pointer rounded-xl border-2 p-3.5 flex items-start gap-3 transition-colors ${
                          checked
                            ? "border-[#59436D] bg-[#F6F1F8]"
                            : "border-[#59436D]/20 hover:border-[#9273B5]"
                        }`}
                      >
                        <input
                          type="radio"
                          name="categoryCode"
                          value={cat.code}
                          checked={checked}
                          onChange={() => {
                            updateField("categoryCode", cat.code);
                            setCouponResult(null);
                          }}
                          className="mt-1 w-4 h-4 accent-[#59436D]"
                        />
                        <div className="text-sm">
                          <div className="font-semibold text-[#17151C]">
                            {cat.name}
                          </div>
                          <div className="mt-0.5 flex flex-wrap gap-1.5 text-xs">
                            <span className="px-2 py-0.5 rounded bg-[#E8DFF0] text-[#432F56] font-semibold">
                              {cat.feeAmountInr > 0
                                ? `₹${cat.feeAmountInr}`
                                : "Free"}
                            </span>
                            {cat.requiresApproval && (
                              <span className="px-2 py-0.5 rounded bg-[#17151C] text-[#D5B77B] font-semibold">
                                Review Required
                              </span>
                            )}
                          </div>
                        </div>
                      </label>
                    );
                  })}
                </div>
              </fieldset>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label
                    htmlFor="reg-occupation"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Occupation <span aria-hidden="true" className="text-[#B42332]">*</span>
                  </label>
                  <select
                    id="reg-occupation"
                    value={draft.occupation}
                    onChange={(e) => updateField("occupation", e.target.value)}
                    className="w-full min-h-[44px] px-3 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  >
                    {OCCUPATION_OPTIONS.map((opt) => (
                      <option key={opt} value={opt}>
                        {opt}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label
                    htmlFor="reg-org"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Organization / Institution
                  </label>
                  <input
                    id="reg-org"
                    type="text"
                    value={draft.organizationName}
                    onChange={(e) =>
                      updateField("organizationName", e.target.value)
                    }
                    placeholder="University, NGO, or Company"
                    className="w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  />
                </div>

                <div>
                  <label
                    htmlFor="reg-designation"
                    className="block text-sm font-semibold text-[#211B29] mb-1.5"
                  >
                    Designation / Role
                  </label>
                  <input
                    id="reg-designation"
                    type="text"
                    value={draft.designation}
                    onChange={(e) => updateField("designation", e.target.value)}
                    placeholder="e.g., B.A. Student, Coordinator"
                    className="w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]"
                  />
                </div>
              </div>

              <fieldset>
                <legend className="text-sm font-semibold text-[#211B29] mb-2">
                  Festival Days Attending <span aria-hidden="true" className="text-[#B42332]">*</span>
                </legend>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {FESTIVAL_DAYS.map((day) => {
                    const checked = draft.attendingDays.includes(day.code);
                    return (
                      <label
                        key={day.code}
                        className={`cursor-pointer min-h-[44px] rounded-xl border p-3 flex items-center gap-2.5 text-sm font-medium ${
                          checked
                            ? "border-[#59436D] bg-[#F6F1F8] text-[#17151C]"
                            : "border-[#59436D]/25 text-[#4B4257]"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() =>
                            toggleArrayValue("attendingDays", day.code)
                          }
                          className="w-4 h-4 accent-[#59436D]"
                        />
                        {day.label}
                      </label>
                    );
                  })}
                </div>
              </fieldset>

              {/* Conditional Delegate Section */}
              {draft.categoryCode === "DELEGATE" && (
                <div className="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">
                  <h2 className="font-display text-lg font-semibold text-[#432F56]">
                    Delegate Conference Details
                  </h2>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label htmlFor="del-age" className="block text-xs font-semibold mb-1">
                        Age
                      </label>
                      <input
                        id="del-age"
                        type="number"
                        min={1}
                        max={120}
                        value={draft.delegateAge}
                        onChange={(e) => updateField("delegateAge", e.target.value)}
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="del-gender" className="block text-xs font-semibold mb-1">
                        Gender
                      </label>
                      <select
                        id="del-gender"
                        value={draft.delegateGender}
                        onChange={(e) => updateField("delegateGender", e.target.value)}
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      >
                        <option value="">Select</option>
                        <option value="Female">Female</option>
                        <option value="Male">Male</option>
                        <option value="Non-binary">Non-binary</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                      </select>
                    </div>
                    <div>
                      <label htmlFor="del-city" className="block text-xs font-semibold mb-1">
                        City &amp; State
                      </label>
                      <input
                        id="del-city"
                        type="text"
                        value={draft.delegateCityState}
                        onChange={(e) => updateField("delegateCityState", e.target.value)}
                        placeholder="Vadodara, Gujarat"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>

                  <div>
                    <p className="text-xs font-semibold mb-2">
                      Para-Sports Interests (Optional)
                    </p>
                    <div className="flex flex-wrap gap-2">
                      {PARA_SPORTS_OPTIONS.map((sport) => {
                        const selected = draft.delegateParaSports.includes(sport);
                        return (
                          <button
                            key={sport}
                            type="button"
                            aria-pressed={selected}
                            onClick={() =>
                              toggleArrayValue("delegateParaSports", sport)
                            }
                            className={`min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                              selected
                                ? "bg-[#59436D] text-white border-[#59436D]"
                                : "bg-white text-[#432F56] border-[#59436D]/30"
                            }`}
                          >
                            {sport}
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label className="flex items-center gap-2.5 text-sm font-medium cursor-pointer">
                      <input
                        type="checkbox"
                        checked={draft.delegateOutstation}
                        onChange={(e) =>
                          updateField("delegateOutstation", e.target.checked)
                        }
                        className="w-4 h-4 accent-[#59436D]"
                      />
                      Outstation travel required
                    </label>
                    <label className="flex items-center gap-2.5 text-sm font-medium cursor-pointer">
                      <input
                        type="checkbox"
                        checked={draft.delegateAccommodation}
                        onChange={(e) =>
                          updateField("delegateAccommodation", e.target.checked)
                        }
                        className="w-4 h-4 accent-[#59436D]"
                      />
                      Lodging / stay guidance needed
                    </label>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="del-emg-name" className="block text-xs font-semibold mb-1">
                        Emergency Contact Name
                      </label>
                      <input
                        id="del-emg-name"
                        type="text"
                        value={draft.delegateEmergencyName}
                        onChange={(e) =>
                          updateField("delegateEmergencyName", e.target.value)
                        }
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="del-emg-phone" className="block text-xs font-semibold mb-1">
                        Emergency Contact Phone
                      </label>
                      <input
                        id="del-emg-phone"
                        type="tel"
                        value={draft.delegateEmergencyPhone}
                        onChange={(e) =>
                          updateField("delegateEmergencyPhone", e.target.value)
                        }
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Conditional Volunteer Section */}
              {draft.categoryCode === "VOLUNTEER" && (
                <div className="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">
                  <h2 className="font-display text-lg font-semibold text-[#432F56]">
                    Volunteer Application Details
                  </h2>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label htmlFor="vol-age" className="block text-xs font-semibold mb-1">
                        Age (16+)
                      </label>
                      <input
                        id="vol-age"
                        type="number"
                        min={16}
                        max={100}
                        value={draft.volAge}
                        onChange={(e) => updateField("volAge", e.target.value)}
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="vol-city" className="block text-xs font-semibold mb-1">
                        City &amp; State
                      </label>
                      <input
                        id="vol-city"
                        type="text"
                        value={draft.volCityState}
                        onChange={(e) => updateField("volCityState", e.target.value)}
                        placeholder="Vadodara, Gujarat"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="vol-crr" className="block text-xs font-semibold mb-1">
                        RCI CRR Number (if applicable)
                      </label>
                      <input
                        id="vol-crr"
                        type="text"
                        value={draft.volCrrNumber}
                        onChange={(e) => updateField("volCrrNumber", e.target.value)}
                        placeholder="Optional"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>

                  <label className="flex items-center gap-2.5 text-sm font-medium cursor-pointer">
                    <input
                      type="checkbox"
                      checked={draft.volMsuAlumni}
                      onChange={(e) =>
                        updateField("volMsuAlumni", e.target.checked)
                      }
                      className="w-4 h-4 accent-[#59436D]"
                    />
                    I am a current student or alumni of The Maharaja Sayajirao
                    University of Baroda (MSU)
                  </label>

                  <div>
                    <p className="text-xs font-semibold mb-2">
                      Preferred Volunteer Roles
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {VOLUNTEER_ROLE_OPTIONS.map((role) => {
                        const checked = draft.volPreferredRoles.includes(role);
                        return (
                          <label
                            key={role}
                            className="flex items-center gap-2 text-sm bg-white p-2.5 rounded-lg border border-[#59436D]/20 cursor-pointer"
                          >
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() =>
                                toggleArrayValue("volPreferredRoles", role)
                              }
                              className="w-4 h-4 accent-[#59436D]"
                            />
                            {role}
                          </label>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <p className="text-xs font-semibold mb-2">Languages Spoken</p>
                    <div className="flex flex-wrap gap-2">
                      {LANGUAGE_OPTIONS.map((lang) => {
                        const selected = draft.volLanguages.includes(lang);
                        return (
                          <button
                            key={lang}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => toggleArrayValue("volLanguages", lang)}
                            className={`min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                              selected
                                ? "bg-[#59436D] text-white border-[#59436D]"
                                : "bg-white text-[#432F56] border-[#59436D]/30"
                            }`}
                          >
                            {lang}
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <label htmlFor="vol-exp" className="block text-xs font-semibold mb-1">
                      Relevant Experience or Motivation
                    </label>
                    <textarea
                      id="vol-exp"
                      rows={3}
                      value={draft.volExperience}
                      onChange={(e) => updateField("volExperience", e.target.value)}
                      placeholder="Share any prior volunteering, disability inclusion, or event coordination experience..."
                      className="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="vol-emg-name" className="block text-xs font-semibold mb-1">
                        Emergency Contact Name
                      </label>
                      <input
                        id="vol-emg-name"
                        type="text"
                        value={draft.volEmergencyName}
                        onChange={(e) =>
                          updateField("volEmergencyName", e.target.value)
                        }
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="vol-emg-phone" className="block text-xs font-semibold mb-1">
                        Emergency Contact Phone
                      </label>
                      <input
                        id="vol-emg-phone"
                        type="tel"
                        value={draft.volEmergencyPhone}
                        onChange={(e) =>
                          updateField("volEmergencyPhone", e.target.value)
                        }
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Conditional SLI Section */}
              {draft.categoryCode === "SLI" && (
                <div className="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">
                  <h2 className="font-display text-lg font-semibold text-[#432F56]">
                    Indian Sign Language Interpreter (SLI) Credentials
                  </h2>

                  <label className="flex items-center gap-2.5 text-sm font-medium cursor-pointer">
                    <input
                      type="checkbox"
                      checked={draft.sliBasedInGujarat}
                      onChange={(e) =>
                        updateField("sliBasedInGujarat", e.target.checked)
                      }
                      className="w-4 h-4 accent-[#59436D]"
                    />
                    I am currently based in Gujarat
                  </label>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="sli-city" className="block text-xs font-semibold mb-1">
                        City &amp; State
                      </label>
                      <input
                        id="sli-city"
                        type="text"
                        value={draft.sliCityState}
                        onChange={(e) => updateField("sliCityState", e.target.value)}
                        placeholder="Ahmedabad / Vadodara, Gujarat"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-crr" className="block text-xs font-semibold mb-1">
                        RCI CRR Number (Optional)
                      </label>
                      <input
                        id="sli-crr"
                        type="text"
                        value={draft.sliCrrNumber}
                        onChange={(e) => updateField("sliCrrNumber", e.target.value)}
                        placeholder="e.g., A12345"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-qual" className="block text-xs font-semibold mb-1">
                        Qualifications (DISLI / DTISL / Equivalent)
                      </label>
                      <input
                        id="sli-qual"
                        type="text"
                        value={draft.sliQualifications}
                        onChange={(e) =>
                          updateField("sliQualifications", e.target.value)
                        }
                        placeholder="e.g., DISLI (ISLRTC)"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-inst" className="block text-xs font-semibold mb-1">
                        Training Institute
                      </label>
                      <input
                        id="sli-inst"
                        type="text"
                        value={draft.sliTrainingInstitute}
                        onChange={(e) =>
                          updateField("sliTrainingInstitute", e.target.value)
                        }
                        placeholder="ISLRTC / AYJNISHD / Recognized Centre"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-cert" className="block text-xs font-semibold mb-1">
                        Certification Level
                      </label>
                      <input
                        id="sli-cert"
                        type="text"
                        value={draft.sliCertificationLevel}
                        onChange={(e) =>
                          updateField("sliCertificationLevel", e.target.value)
                        }
                        placeholder="Level A / B / C / Certified"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-exp" className="block text-xs font-semibold mb-1">
                        Years of Interpreting Experience
                      </label>
                      <input
                        id="sli-exp"
                        type="number"
                        min={0}
                        max={70}
                        value={draft.sliYearsExperience}
                        onChange={(e) =>
                          updateField("sliYearsExperience", e.target.value)
                        }
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>

                  <div>
                    <p className="text-xs font-semibold mb-2">Areas of Expertise</p>
                    <div className="flex flex-wrap gap-2">
                      {SLI_EXPERTISE_OPTIONS.map((area) => {
                        const selected = draft.sliAreasExpertise.includes(area);
                        return (
                          <button
                            key={area}
                            type="button"
                            aria-pressed={selected}
                            onClick={() =>
                              toggleArrayValue("sliAreasExpertise", area)
                            }
                            className={`min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                              selected
                                ? "bg-[#59436D] text-white border-[#59436D]"
                                : "bg-white text-[#432F56] border-[#59436D]/30"
                            }`}
                          >
                            {area}
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <p className="text-xs font-semibold mb-2">
                      Interpretation Preferences
                    </p>
                    <div className="flex flex-wrap gap-2">
                      {SLI_PREF_OPTIONS.map((pref) => {
                        const selected = draft.sliInterpretPrefs.includes(pref);
                        return (
                          <button
                            key={pref}
                            type="button"
                            aria-pressed={selected}
                            onClick={() =>
                              toggleArrayValue("sliInterpretPrefs", pref)
                            }
                            className={`min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                              selected
                                ? "bg-[#59436D] text-white border-[#59436D]"
                                : "bg-white text-[#432F56] border-[#59436D]/30"
                            }`}
                          >
                            {pref}
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="sli-video" className="block text-xs font-semibold mb-1">
                        Video Demo URL (Optional)
                      </label>
                      <input
                        id="sli-video"
                        type="url"
                        value={draft.sliVideoDemoUrl}
                        onChange={(e) =>
                          updateField("sliVideoDemoUrl", e.target.value)
                        }
                        placeholder="https://..."
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="sli-notes" className="block text-xs font-semibold mb-1">
                        Honorarium / Travel Support Notes
                      </label>
                      <input
                        id="sli-notes"
                        type="text"
                        value={draft.sliHonorariumNotes}
                        onChange={(e) =>
                          updateField("sliHonorariumNotes", e.target.value)
                        }
                        placeholder="Optional travel or scheduling notes"
                        className="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white"
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ================= STEP 3: ACCESSIBILITY & ACCOMMODATIONS ================= */}
          {step === 3 && (
            <div className="space-y-6">
              <div>
                <h1 className="font-display text-2xl font-semibold text-[#17151C]">
                  Step 3: Accessibility &amp; Accommodations
                </h1>
                <p className="mt-1 text-sm text-[#4B4257]">
                  Disclosure is completely optional and independent of your{" "}
                  <strong>{selectedCategory?.name}</strong> registration.
                </p>
              </div>

              <div className="rounded-xl bg-[#17151C] text-[#F9F7FC] p-4 flex items-start gap-3 text-sm">
                <ShieldCheck
                  className="w-5 h-5 text-[#D5B77B] shrink-0 mt-0.5"
                  aria-hidden="true"
                />
                <p>
                  <strong>Privacy Protection:</strong> Information shared in this step is
                  stored in restricted tables accessible only to authorized accessibility
                  coordinators — never displayed on QR passes, reception scanners, or
                  general exports.
                </p>
              </div>

              <fieldset>
                <legend className="text-sm font-semibold text-[#211B29] mb-2">
                  Are you a person with disability?
                </legend>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {[
                    { value: "YES" as const, label: "Yes" },
                    { value: "NO" as const, label: "No" },
                    {
                      value: "PREFER_NOT_TO_SAY" as const,
                      label: "Prefer not to say",
                    },
                  ].map((opt) => (
                    <label
                      key={opt.value}
                      className={`cursor-pointer min-h-[44px] rounded-xl border-2 px-4 py-2.5 flex items-center gap-2.5 text-sm font-semibold ${
                        draft.isPersonWithDisability === opt.value
                          ? "border-[#59436D] bg-[#F6F1F8] text-[#17151C]"
                          : "border-[#59436D]/20 text-[#4B4257]"
                      }`}
                    >
                      <input
                        type="radio"
                        name="isPersonWithDisability"
                        value={opt.value}
                        checked={draft.isPersonWithDisability === opt.value}
                        onChange={() =>
                          updateField("isPersonWithDisability", opt.value)
                        }
                        className="w-4 h-4 accent-[#59436D]"
                      />
                      {opt.label}
                    </label>
                  ))}
                </div>
              </fieldset>

              {draft.isPersonWithDisability === "YES" && (
                <div className="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-semibold text-[#432F56] text-base">
                      RPwD Act 2016 Specified Disabilities (Optional, Multi-Select)
                    </h2>
                    <span className="text-xs font-semibold text-[#59436D]">
                      {draft.disabilityCategoryCodes.length} selected
                    </span>
                  </div>

                  <div>
                    <label htmlFor="disability-filter" className="sr-only">
                      Filter the 21 RPwD disabilities
                    </label>
                    <input
                      id="disability-filter"
                      type="search"
                      value={disabilitySearch}
                      onChange={(e) => setDisabilitySearch(e.target.value)}
                      placeholder="Search 21 RPwD disabilities (e.g., Deaf, Low Vision, Autism)..."
                      className="w-full min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
                    {filteredDisabilities.map((d) => {
                      const checked = draft.disabilityCategoryCodes.includes(d.code);
                      return (
                        <label
                          key={d.id}
                          className={`cursor-pointer rounded-xl border p-2.5 flex items-start gap-2.5 text-sm ${
                            checked
                              ? "bg-white border-[#59436D] font-semibold"
                              : "bg-white/70 border-[#59436D]/20"
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() =>
                              toggleArrayValue("disabilityCategoryCodes", d.code)
                            }
                            className="mt-0.5 w-4 h-4 accent-[#59436D]"
                          />
                          <span>
                            {d.name}
                            <span className="block text-xs font-normal text-[#4B4257]">
                              {d.rpwdGroup}
                            </span>
                          </span>
                        </label>
                      );
                    })}
                  </div>

                  <div>
                    <label
                      htmlFor="disability-self-desc"
                      className="block text-xs font-semibold text-[#211B29] mb-1"
                    >
                      Self-Describe or Additional Context (Optional)
                    </label>
                    <textarea
                      id="disability-self-desc"
                      rows={2}
                      value={draft.disabilitySelfDescription}
                      onChange={(e) =>
                        updateField("disabilitySelfDescription", e.target.value)
                      }
                      placeholder="Optional — share how we can best support your participation"
                      className="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                    />
                  </div>
                </div>
              )}

              <fieldset className="space-y-3">
                <legend className="text-sm font-semibold text-[#211B29]">
                  Accessibility Accommodations Needed (Optional — open to all participants)
                </legend>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  {accommodationTypes.map((acc) => {
                    const checked = draft.accommodationTypes.includes(acc.code);
                    return (
                      <label
                        key={acc.id}
                        className={`cursor-pointer min-h-[44px] rounded-xl border p-3 flex items-center gap-2.5 text-sm ${
                          checked
                            ? "border-[#59436D] bg-[#F6F1F8] font-semibold text-[#17151C]"
                            : "border-[#59436D]/20 text-[#4B4257]"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() =>
                            toggleArrayValue("accommodationTypes", acc.code)
                          }
                          className="w-4 h-4 accent-[#59436D]"
                        />
                        {acc.label}
                      </label>
                    );
                  })}
                </div>
              </fieldset>

              <div>
                <label
                  htmlFor="accomm-free-text"
                  className="block text-sm font-semibold text-[#211B29] mb-1.5"
                >
                  Specific Accommodation Notes (Optional)
                </label>
                <textarea
                  id="accomm-free-text"
                  rows={3}
                  value={draft.accommodationFreeText}
                  onChange={(e) =>
                    updateField("accommodationFreeText", e.target.value)
                  }
                  placeholder="e.g., Front-row aisle seat near ISL interpreter, dietary allergies, companion seat..."
                  className="w-full p-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm"
                />
              </div>
            </div>
          )}

          {/* ================= STEP 4: DECLARATIONS & COUPON ================= */}
          {step === 4 && (
            <div className="space-y-6">
              <div>
                <h1 className="font-display text-2xl font-semibold text-[#17151C]">
                  Step 4: Declarations &amp; Optional Coupon
                </h1>
                <p className="mt-1 text-sm text-[#4B4257]">
                  Review our safety and DPDP privacy commitments. Photography consent is
                  completely optional and separate from registration.
                </p>
              </div>

              <div className="space-y-4">
                <label className="flex items-start gap-3 p-4 rounded-xl border-2 border-[#59436D]/25 bg-[#F6F1F8]/60 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={draft.consentZeroTolerance}
                    onChange={(e) =>
                      updateField("consentZeroTolerance", e.target.checked)
                    }
                    className="mt-1 w-5 h-5 accent-[#59436D] shrink-0"
                  />
                  <span className="text-sm text-[#211B29]">
                    <strong>Zero-Tolerance Harassment &amp; Respect Policy (Required):</strong>{" "}
                    I acknowledge and agree to uphold Purple Festival Gujarat 2027&apos;s
                    zero-tolerance policy towards harassment, discrimination, and
                    ableism across all physical and digital festival spaces.
                  </span>
                </label>

                <label className="flex items-start gap-3 p-4 rounded-xl border-2 border-[#59436D]/25 bg-[#F6F1F8]/60 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={draft.consentDataProcessing}
                    onChange={(e) =>
                      updateField("consentDataProcessing", e.target.checked)
                    }
                    className="mt-1 w-5 h-5 accent-[#59436D] shrink-0"
                  />
                  <span className="text-sm text-[#211B29]">
                    <strong>Data Processing Consent — DPDP Act, 2023 (Required):</strong>{" "}
                    I consent to the processing of my registration information solely for
                    festival credentialing, attendance management, and requested
                    accessibility coordination in accordance with the{" "}
                    <Link
                      href="/legal/privacy"
                      target="_blank"
                      className="text-[#59436D] font-semibold underline"
                    >
                      Privacy &amp; Data Protection Policy
                    </Link>
                    .
                  </span>
                </label>

                <label className="flex items-start gap-3 p-4 rounded-xl border border-[#59436D]/20 bg-white cursor-pointer">
                  <input
                    type="checkbox"
                    checked={draft.consentMediaPhotography}
                    onChange={(e) =>
                      updateField("consentMediaPhotography", e.target.checked)
                    }
                    className="mt-1 w-5 h-5 accent-[#59436D] shrink-0"
                  />
                  <span className="text-sm text-[#4B4257]">
                    <strong className="text-[#211B29]">
                      Photography &amp; Media Consent (Optional):
                    </strong>{" "}
                    I grant permission for non-commercial festival photography and video
                    documentation featuring me to be used in Purple Festival Gujarat
                    awareness materials. (Declining this does not affect your
                    registration.)
                  </span>
                </label>
              </div>

              {/* Optional Coupon Code */}
              <div className="rounded-2xl bg-[#F6F1F8] border border-[#59436D]/20 p-5 space-y-3">
                <div className="flex items-center gap-2 font-semibold text-sm text-[#432F56]">
                  <Tag className="w-4 h-4" aria-hidden="true" />
                  Institutional / Student Coupon Code (Optional)
                </div>
                <div className="flex flex-col sm:flex-row gap-2.5">
                  <label htmlFor="reg-coupon" className="sr-only">
                    Coupon Code
                  </label>
                  <input
                    id="reg-coupon"
                    type="text"
                    value={draft.couponCode}
                    onChange={(e) => {
                      updateField("couponCode", e.target.value.toUpperCase());
                      setCouponResult(null);
                    }}
                    placeholder="Enter coupon code (if issued)"
                    className="flex-1 min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white uppercase font-mono text-sm"
                  />
                  <button
                    type="button"
                    onClick={handleVerifyCoupon}
                    disabled={couponChecking}
                    className="min-h-[44px] px-5 py-2 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-semibold hover:bg-[#25212B] disabled:opacity-60"
                  >
                    {couponChecking ? "Verifying..." : "Verify Coupon"}
                  </button>
                </div>
                {couponResult && (
                  <p
                    role="status"
                    aria-live="polite"
                    className={`text-sm font-medium ${
                      couponResult.valid ? "text-[#19734B]" : "text-[#B42332]"
                    }`}
                  >
                    {couponResult.message}
                  </p>
                )}
              </div>
            </div>
          )}

          {/* ================= STEP 5: REVIEW & SUBMIT ================= */}
          {step === 5 && (
            <div className="space-y-6">
              <div>
                <h1 className="font-display text-2xl font-semibold text-[#17151C]">
                  Step 5: Review &amp; Submit
                </h1>
                <p className="mt-1 text-sm text-[#4B4257]">
                  Please confirm your details below before submitting.
                </p>
              </div>

              <div className="space-y-4">
                <div className="rounded-xl border border-[#59436D]/20 p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm uppercase tracking-wider text-[#59436D]">
                      Personal Details
                    </h2>
                    <button
                      type="button"
                      onClick={() => setStep(1)}
                      className="text-xs font-bold text-[#59436D] underline"
                    >
                      Edit
                    </button>
                  </div>
                  <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div>
                      <dt className="text-xs text-[#4B4257]">Full Name</dt>
                      <dd className="font-semibold">{draft.fullName}</dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Email</dt>
                      <dd className="font-semibold">{draft.email}</dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Phone</dt>
                      <dd className="font-semibold">{draft.phone}</dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Country</dt>
                      <dd className="font-semibold">{draft.country}</dd>
                    </div>
                  </dl>
                </div>

                <div className="rounded-xl border border-[#59436D]/20 p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm uppercase tracking-wider text-[#59436D]">
                      Category &amp; Role
                    </h2>
                    <button
                      type="button"
                      onClick={() => setStep(2)}
                      className="text-xs font-bold text-[#59436D] underline"
                    >
                      Edit
                    </button>
                  </div>
                  <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div>
                      <dt className="text-xs text-[#4B4257]">Category</dt>
                      <dd className="font-semibold">
                        {selectedCategory?.name || draft.categoryCode}
                      </dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Occupation</dt>
                      <dd className="font-semibold">{draft.occupation}</dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Attending Days</dt>
                      <dd className="font-semibold">
                        {draft.attendingDays.join(", ")}
                      </dd>
                    </div>
                    <div>
                      <dt className="text-xs text-[#4B4257]">Fee Summary</dt>
                      <dd className="font-semibold">
                        {couponResult?.valid
                          ? `₹${couponResult.netAmountInr} (after ₹${couponResult.discountInr} discount)`
                          : selectedCategory && selectedCategory.feeAmountInr > 0
                            ? `₹${selectedCategory.feeAmountInr}`
                            : "Free (₹0)"}
                      </dd>
                    </div>
                  </dl>
                </div>

                <div className="rounded-xl border border-[#59436D]/20 p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm uppercase tracking-wider text-[#59436D]">
                      Accessibility &amp; Accommodations
                    </h2>
                    <button
                      type="button"
                      onClick={() => setStep(3)}
                      className="text-xs font-bold text-[#59436D] underline"
                    >
                      Edit
                    </button>
                  </div>
                  <p className="text-sm">
                    <strong>Person with disability:</strong>{" "}
                    {draft.isPersonWithDisability === "YES"
                      ? "Yes"
                      : draft.isPersonWithDisability === "NO"
                        ? "No"
                        : "Prefer not to say"}
                  </p>
                  {draft.accommodationTypes.length > 0 && (
                    <p className="text-sm">
                      <strong>Requested accommodations:</strong>{" "}
                      {draft.accommodationTypes.length} selected
                    </p>
                  )}
                </div>
              </div>

              <div className="rounded-xl bg-[#E8DFF0]/60 border border-[#9273B5] p-4 flex items-start gap-3 text-sm text-[#432F56]">
                <Sparkles className="w-5 h-5 shrink-0 mt-0.5" aria-hidden="true" />
                <p>
                  {selectedCategory?.requiresApproval
                    ? "Your application will be submitted for coordinator review. You will receive your digital QR pass and duty assignment once approved."
                    : "Upon submission, your registration will be confirmed immediately and your unique Digital QR Entry Pass will be generated on screen."}
                </p>
              </div>
            </div>
          )}

          {/* Navigation Footer */}
          <div className="mt-8 pt-6 border-t border-[#59436D]/15 flex items-center justify-between gap-4">
            {step > 1 ? (
              <button
                type="button"
                onClick={handleBack}
                className="min-h-[48px] px-5 py-2.5 rounded-xl border-2 border-[#59436D]/30 text-[#432F56] font-semibold text-sm hover:bg-[#F6F1F8] inline-flex items-center gap-2"
              >
                <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                Back
              </button>
            ) : (
              <div />
            )}

            {step < 5 ? (
              <button
                type="button"
                onClick={handleNext}
                className="btn-primary-pfg min-h-[48px] px-6 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm inline-flex items-center gap-2 transition-colors"
              >
                Next Step
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </button>
            ) : (
              <button
                type="button"
                onClick={handleSubmit}
                disabled={submitting}
                className="btn-primary-pfg min-h-[48px] px-7 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm inline-flex items-center gap-2 transition-colors disabled:opacity-60"
              >
                <FileCheck2 className="w-4 h-4" aria-hidden="true" />
                {submitting ? "Submitting..." : "Submit Registration"}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
