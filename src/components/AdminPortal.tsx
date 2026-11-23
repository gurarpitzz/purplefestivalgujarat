"use client";

import React, { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  LayoutDashboard,
  Users,
  HandHeart,
  Languages,
  Accessibility,
  QrCode,
  Ticket,
  FileEdit,
  ShieldCheck,
  ScrollText,
  LogOut,
  Search,
  Download,
  RefreshCw,
  CheckCircle2,
  XCircle,
  Clock,
  ExternalLink,
  Menu,
  X,
  Plus,
  Trash2,
  AlertCircle,
  ChevronLeft,
  ChevronRight,
  Table2,
  BarChart3,
  Eye,
  Save,
} from "lucide-react";

type AdminTab =
  | "OVERVIEW"
  | "REGISTRATIONS"
  | "VOLUNTEERS"
  | "SLI"
  | "ACCOMMODATIONS"
  | "ATTENDANCE"
  | "COUPONS"
  | "CMS"
  | "USERS"
  | "AUDIT_LOGS";

interface CurrentUserProps {
  id: string;
  fullName: string;
  email: string;
  roles: string[];
  permissions: string[];
}

interface OverviewData {
  generatedAt: string;
  todayIst: string;
  metrics: {
    uniquePeople: number;
    totalApplications: number;
    confirmedRegistrations: number;
    delegates: number;
    volunteerApplications: number;
    approvedVolunteers: number;
    sliApplications: number;
    approvedSli: number;
    alliesSupporters: number;
    participantsRequestingAccommodations: number;
    totalCheckins: number;
    uniqueCheckedInParticipants: number;
    notYetArrived: number;
    todayCheckins: number;
    pendingApprovals: number;
  };
  accommodationSummary: {
    totalRequests: number;
    uniqueParticipants: number;
    requested: number;
    inProgress: number;
    arranged: number;
    declined: number;
  };
  paymentReconciliation: {
    capturedOrders: number;
    capturedRevenueInr: number;
    pendingOrders: number;
    pendingAmountInr: number;
    refundedOrders: number;
    refundedAmountInr: number;
    totalDiscountInr: number;
  };
  dailyRegistrationTrends: Array<{ date: string; count: number }>;
  categoryDistribution: Array<{
    categoryCode: string;
    total: number;
    confirmed: number;
    pending: number;
    checkedIn: number;
  }>;
  attendanceByDay: Array<{ date: string; count: number }>;
  attendanceByVenue: Array<{ venueLabel: string; count: number }>;
  volunteerPipeline: {
    total: number;
    submitted: number;
    underReview: number;
    approved: number;
    waitlisted: number;
    rejected: number;
  };
  sliPipeline: {
    total: number;
    submitted: number;
    underReview: number;
    approved: number;
    waitlisted: number;
    rejected: number;
  };
  recentCheckins: Array<{
    id: string;
    registrationCode: string;
    participantName: string;
    categoryCode: string;
    venueLabel: string;
    checkinDateIst: string;
    method: string;
    checkedInAt: string;
  }>;
}

interface RegistrationListItem {
  id: string;
  registrationCode: string;
  categoryCode: string;
  status: string;
  paymentStatus: string;
  fullName: string;
  email: string;
  phone: string;
  country: string;
  occupation: string | null;
  organizationName: string | null;
  designation: string | null;
  attendingDays: string[];
  reviewNotes: string | null;
  submittedAt: string | null;
  createdAt: string;
  checkedIn: boolean;
  checkinCount: number;
  lastCheckinAt: string | null;
  lastCheckinVenue: string | null;
  credentialStatus: string | null;
  credentialCode: string | null;
  hasAccommodationRequest: boolean;
  accommodationStatus: string | null;
  volunteerDetails?: {
    institutionName?: string | null;
    isMsuStudentOrAlumni?: boolean | null;
    crrNumber?: string | null;
    preferredRoles?: string[] | null;
    availabilityDays?: string[] | null;
    languagesSpoken?: string[] | null;
    relevantExperience?: string | null;
    assignedDepartment?: string | null;
    assignedResponsibility?: string | null;
    assignedSupervisor?: string | null;
    assignedVenue?: string | null;
    assignedShift?: string | null;
    reportingTime?: string | null;
    assignmentNotes?: string | null;
  } | null;
  sliDetails?: {
    cityState?: string | null;
    basedInGujarat?: boolean | null;
    crrNumber?: string | null;
    qualifications?: string | null;
    trainingInstitute?: string | null;
    certificationLevel?: string | null;
    yearsOfExperience?: number | null;
    professionalAffiliation?: string | null;
    areasOfExpertise?: string[] | null;
    interpretationPreferences?: string[] | null;
    availabilityDays?: string[] | null;
    videoDemoUrl?: string | null;
    honorariumOrTravelNotes?: string | null;
    assignedSessions?: Array<{
      sessionTitle: string;
      venue: string;
      dutyTime: string;
      day: string;
    }> | null;
    assignedVenue?: string | null;
    dutyTimes?: string | null;
    assignmentNotes?: string | null;
  } | null;
}

interface AccommodationItem {
  id: string;
  registrationId: string;
  registrationCode: string;
  fullName: string;
  email: string;
  phone: string;
  categoryCode: string;
  registrationStatus: string;
  selectedTypes: string[];
  selectedTypeLabels: string[];
  freeTextDetails: string | null;
  status: string;
  coordinatorNotes: string;
  updatedAt: string;
}

interface CouponItem {
  id: string;
  code: string;
  description: string | null;
  discountType: "PERCENTAGE" | "FIXED_AMOUNT" | "FULL_WAIVER";
  discountValue: number;
  eligibleCategories: string[];
  maxUses: number | null;
  usedCount: number;
  redemptionCount: number;
  totalDiscountInr: number;
  isActive: boolean;
  validUntil: string | null;
}

interface CmsData {
  sections: Array<{
    id: string;
    sectionKey: string;
    title: string | null;
    subtitle: string | null;
    body: string | null;
    status: string;
    updatedAt: string;
  }>;
  programmeItems: Array<{
    id: string;
    dayNumber: number;
    timeSlot: string;
    title: string;
    description: string | null;
    category: string;
    venueName: string | null;
    status: string;
  }>;
  partners: Array<{
    id: string;
    organizationName: string;
    websiteUrl: string | null;
    category: string;
    consentVerified: boolean;
    status: string;
    displayOrder: number;
  }>;
  faqItems: Array<{
    id: string;
    category: string;
    question: string;
    answer: string;
    displayOrder: number;
    status: string;
  }>;
  announcements: Array<{
    id: string;
    title: string;
    message: string;
    severity: string;
    status: string;
    createdAt: string;
  }>;
  registrationCategories: Array<{
    id: string;
    code: string;
    name: string;
    description: string | null;
    requiresApproval: boolean;
    requiresPayment: boolean;
    feeAmountInr: number;
    isActive: boolean;
    closesAt: string | null;
  }>;
  recentRevisions: Array<{
    id: string;
    entityType: string;
    versionNumber: number;
    changeSummary: string | null;
    createdAt: string;
  }>;
}

interface StaffUserItem {
  id: string;
  email: string;
  fullName: string;
  phone: string | null;
  isActive: boolean;
  roles: string[];
  customPermissions: string[];
  lastLoginAt: string | null;
  createdAt: string;
}

interface AuditLogItem {
  id: string;
  action: string;
  resourceType: string;
  resourceId: string | null;
  actorName: string | null;
  actorEmail: string | null;
  metadata: Record<string, unknown> | null;
  createdAt: string;
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case "APPROVED":
    case "CONFIRMED":
    case "ARRANGED":
    case "PUBLISHED":
    case "ACTIVE":
    case "CAPTURED":
      return "bg-emerald-50 text-emerald-800 border-emerald-200";
    case "SUBMITTED":
    case "UNDER_REVIEW":
    case "REQUESTED":
    case "IN_PROGRESS":
    case "PENDING":
      return "bg-amber-50 text-amber-800 border-amber-200";
    case "WAITLISTED":
    case "ADDITIONAL_INFO_REQUIRED":
    case "DRAFT":
      return "bg-purple-50 text-[#5B3E87] border-purple-200";
    case "REJECTED":
    case "DECLINED":
    case "REVOKED":
    case "CANCELLED":
    case "FAILED":
      return "bg-rose-50 text-rose-800 border-rose-200";
    default:
      return "bg-slate-100 text-slate-700 border-slate-200";
  }
}

function formatDateShort(iso: string | null | undefined): string {
  if (!iso) return "—";
  try {
    return new Intl.DateTimeFormat("en-IN", {
      timeZone: "Asia/Kolkata",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export default function AdminPortal({ currentUser }: { currentUser: CurrentUserProps }) {
  const router = useRouter();
  const isSuperadmin = currentUser.roles.includes("SUPERADMIN");
  const can = useCallback(
    (perm: string) => isSuperadmin || currentUser.permissions.includes(perm),
    [isSuperadmin, currentUser.permissions]
  );

  const [activeTab, setActiveTab] = useState<AdminTab>("OVERVIEW");
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [toast, setToast] = useState<{ type: "success" | "error"; message: string } | null>(null);

  const notify = useCallback((type: "success" | "error", message: string) => {
    setToast({ type, message });
    setTimeout(() => {
      setToast((prev) => (prev?.message === message ? null : prev));
    }, 5000);
  }, []);

  // Overview state
  const [overview, setOverview] = useState<OverviewData | null>(null);
  const [overviewLoading, setOverviewLoading] = useState(true);
  const [chartViewMode, setChartViewMode] = useState<"chart" | "table">("chart");

  // Registrations state
  const [regSearch, setRegSearch] = useState("");
  const [regCategory, setRegCategory] = useState("");
  const [regStatus, setRegStatus] = useState("");
  const [regCheckin, setRegCheckin] = useState("");
  const [regPage, setRegPage] = useState(1);
  const [regItems, setRegItems] = useState<RegistrationListItem[]>([]);
  const [regPagination, setRegPagination] = useState({ page: 1, pageSize: 25, total: 0, totalPages: 1 });
  const [regLoading, setRegLoading] = useState(false);

  // Detail modal state
  const [selectedRegId, setSelectedRegId] = useState<string | null>(null);
  const [regDetail, setRegDetail] = useState<Record<string, unknown> | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailStatus, setDetailStatus] = useState("");
  const [detailNotes, setDetailNotes] = useState("");
  const [savingDetail, setSavingDetail] = useState(false);

  // Volunteers queue state
  const [volItems, setVolItems] = useState<RegistrationListItem[]>([]);
  const [volLoading, setVolLoading] = useState(false);
  const [activeVolAssign, setActiveVolAssign] = useState<string | null>(null);
  const [volForm, setVolForm] = useState({
    assignedDepartment: "",
    assignedResponsibility: "",
    assignedSupervisor: "",
    assignedVenue: "",
    assignedShift: "",
    reportingTime: "",
    assignmentNotes: "",
  });

  // SLI queue state
  const [sliItems, setSliItems] = useState<RegistrationListItem[]>([]);
  const [sliLoading, setSliLoading] = useState(false);
  const [activeSliAssign, setActiveSliAssign] = useState<string | null>(null);
  const [sliForm, setSliForm] = useState({
    assignedVenue: "",
    dutyTimes: "",
    sessionTitle: "",
    sessionDay: "Day 1 (21 Jan 2027)",
    assignmentNotes: "",
  });

  // Accommodations state
  const [accommItems, setAccommItems] = useState<AccommodationItem[]>([]);
  const [accommLoading, setAccommLoading] = useState(false);
  const [accommStatusFilter, setAccommStatusFilter] = useState("");
  const [accommNotesDraft, setAccommNotesDraft] = useState<Record<string, string>>({});

  // Coupons state
  const [coupons, setCoupons] = useState<CouponItem[]>([]);
  const [couponsLoading, setCouponsLoading] = useState(false);
  const [newCoupon, setNewCoupon] = useState({
    code: "",
    description: "",
    discountType: "PERCENTAGE" as "PERCENTAGE" | "FIXED_AMOUNT" | "FULL_WAIVER",
    discountValue: 100,
    maxUses: "",
    eligibleCategories: ["DELEGATE"],
  });

  // CMS state
  const [cmsData, setCmsData] = useState<CmsData | null>(null);
  const [cmsLoading, setCmsLoading] = useState(false);
  const [cmsSubTab, setCmsSubTab] = useState<
    "sections" | "programme" | "partners" | "faqs" | "announcements" | "categories"
  >("sections");
  const [sectionDrafts, setSectionDrafts] = useState<
    Record<string, { title: string; subtitle: string; body: string; status: string }>
  >({});
  const [newProgramme, setNewProgramme] = useState({
    dayNumber: 1,
    timeSlot: "10:00 – 11:30 IST",
    title: "",
    category: "Plenary",
    venueName: "Sama Indoor Stadium",
    description: "",
  });
  const [newPartner, setNewPartner] = useState({
    organizationName: "",
    category: "Knowledge Partners",
    websiteUrl: "",
    consentVerified: false,
    status: "DRAFT" as "DRAFT" | "PUBLISHED",
  });
  const [newFaq, setNewFaq] = useState({ category: "General", question: "", answer: "" });
  const [newAnnouncement, setNewAnnouncement] = useState({
    title: "",
    message: "",
    severity: "INFO" as "INFO" | "IMPORTANT" | "ALERT",
  });

  // Users state (Superadmin)
  const [staffUsers, setStaffUsers] = useState<StaffUserItem[]>([]);
  const [availablePermissions, setAvailablePermissions] = useState<string[]>([]);
  const [usersLoading, setUsersLoading] = useState(false);
  const [newStaff, setNewStaff] = useState({
    fullName: "",
    email: "",
    phone: "",
    password: "",
    roleCode: "ADMIN" as "ADMIN" | "RGST",
    grantCms: false,
  });
  const [editingPermsUser, setEditingPermsUser] = useState<StaffUserItem | null>(null);
  const [permsDraft, setPermsDraft] = useState<string[]>([]);

  // Audit logs state (Superadmin)
  const [auditLogs, setAuditLogs] = useState<AuditLogItem[]>([]);
  const [auditPagination, setAuditPagination] = useState({ page: 1, pageSize: 30, total: 0, totalPages: 1 });
  const [auditSearch, setAuditSearch] = useState("");
  const [auditLoading, setAuditLoading] = useState(false);

  // =========================================================================
  // DATA FETCHERS
  // =========================================================================

  const fetchOverview = useCallback(async () => {
    setOverviewLoading(true);
    try {
      const res = await fetch("/api/admin/overview", { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        setOverview(data);
      }
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  const fetchRegistrations = useCallback(
    async (opts?: { page?: number; category?: string; status?: string; checkinStatus?: string; search?: string }) => {
      setRegLoading(true);
      try {
        const params = new URLSearchParams();
        const p = opts?.page ?? regPage;
        const cat = opts?.category !== undefined ? opts.category : regCategory;
        const st = opts?.status !== undefined ? opts.status : regStatus;
        const ck = opts?.checkinStatus !== undefined ? opts.checkinStatus : regCheckin;
        const sr = opts?.search !== undefined ? opts.search : regSearch;

        params.set("page", String(p));
        params.set("pageSize", "25");
        if (sr) params.set("search", sr);
        if (cat) params.set("category", cat);
        if (st) params.set("status", st);
        if (ck) params.set("checkinStatus", ck);

        const res = await fetch(`/api/admin/registrations?${params.toString()}`, {
          cache: "no-store",
        });
        if (res.ok) {
          const data = await res.json();
          setRegItems(data.items || []);
          setRegPagination(data.pagination || { page: 1, pageSize: 25, total: 0, totalPages: 1 });
        }
      } finally {
        setRegLoading(false);
      }
    },
    [regPage, regCategory, regStatus, regCheckin, regSearch]
  );

  const fetchVolunteers = useCallback(async () => {
    setVolLoading(true);
    try {
      const res = await fetch("/api/admin/registrations?category=VOLUNTEER&pageSize=100", {
        cache: "no-store",
      });
      if (res.ok) {
        const data = await res.json();
        setVolItems(data.items || []);
      }
    } finally {
      setVolLoading(false);
    }
  }, []);

  const fetchSli = useCallback(async () => {
    setSliLoading(true);
    try {
      const res = await fetch("/api/admin/registrations?category=SLI&pageSize=100", {
        cache: "no-store",
      });
      if (res.ok) {
        const data = await res.json();
        setSliItems(data.items || []);
      }
    } finally {
      setSliLoading(false);
    }
  }, []);

  const fetchAccommodations = useCallback(async () => {
    if (!can("accommodations.read")) return;
    setAccommLoading(true);
    try {
      const qs = accommStatusFilter ? `?status=${accommStatusFilter}` : "";
      const res = await fetch(`/api/admin/accommodations${qs}`, { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        const items: AccommodationItem[] = data.items || [];
        setAccommItems(items);
        const drafts: Record<string, string> = {};
        for (const it of items) drafts[it.id] = it.coordinatorNotes || "";
        setAccommNotesDraft(drafts);
      }
    } finally {
      setAccommLoading(false);
    }
  }, [can, accommStatusFilter]);

  const fetchCoupons = useCallback(async () => {
    if (!can("coupons.manage")) return;
    setCouponsLoading(true);
    try {
      const res = await fetch("/api/admin/coupons", { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        setCoupons(data.items || []);
      }
    } finally {
      setCouponsLoading(false);
    }
  }, [can]);

  const fetchCms = useCallback(async () => {
    if (!can("cms.manage")) return;
    setCmsLoading(true);
    try {
      const res = await fetch("/api/admin/cms", { cache: "no-store" });
      if (res.ok) {
        const data: CmsData = await res.json();
        setCmsData(data);
        const drafts: Record<string, { title: string; subtitle: string; body: string; status: string }> = {};
        for (const s of data.sections || []) {
          drafts[s.id] = {
            title: s.title || "",
            subtitle: s.subtitle || "",
            body: s.body || "",
            status: s.status || "PUBLISHED",
          };
        }
        setSectionDrafts(drafts);
      }
    } finally {
      setCmsLoading(false);
    }
  }, [can]);

  const fetchUsers = useCallback(async () => {
    if (!isSuperadmin) return;
    setUsersLoading(true);
    try {
      const res = await fetch("/api/admin/users", { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        setStaffUsers(data.items || []);
        setAvailablePermissions(data.availablePermissions || []);
      }
    } finally {
      setUsersLoading(false);
    }
  }, [isSuperadmin]);

  const fetchAuditLogs = useCallback(
    async (pageNum = 1) => {
      if (!can("audit_logs.read")) return;
      setAuditLoading(true);
      try {
        const params = new URLSearchParams({ page: String(pageNum), pageSize: "30" });
        if (auditSearch.trim()) params.set("search", auditSearch.trim());
        const res = await fetch(`/api/admin/audit-logs?${params.toString()}`, {
          cache: "no-store",
        });
        if (res.ok) {
          const data = await res.json();
          setAuditLogs(data.items || []);
          setAuditPagination(data.pagination || { page: 1, pageSize: 30, total: 0, totalPages: 1 });
        }
      } finally {
        setAuditLoading(false);
      }
    },
    [can, auditSearch]
  );

  // Trigger fetches when tab changes
  useEffect(() => {
    if (activeTab === "OVERVIEW" || activeTab === "ATTENDANCE") fetchOverview();
    if (activeTab === "REGISTRATIONS") fetchRegistrations();
    if (activeTab === "VOLUNTEERS") fetchVolunteers();
    if (activeTab === "SLI") fetchSli();
    if (activeTab === "ACCOMMODATIONS") fetchAccommodations();
    if (activeTab === "COUPONS") fetchCoupons();
    if (activeTab === "CMS") fetchCms();
    if (activeTab === "USERS") fetchUsers();
    if (activeTab === "AUDIT_LOGS") fetchAuditLogs(1);
  }, [
    activeTab,
    fetchOverview,
    fetchRegistrations,
    fetchVolunteers,
    fetchSli,
    fetchAccommodations,
    fetchCoupons,
    fetchCms,
    fetchUsers,
    fetchAuditLogs,
  ]);

  // =========================================================================
  // ACTIONS & MUTATIONS
  // =========================================================================

  const jumpToFilteredRegistrations = (filters: {
    category?: string;
    status?: string;
    checkinStatus?: string;
  }) => {
    setRegCategory(filters.category ?? "");
    setRegStatus(filters.status ?? "");
    setRegCheckin(filters.checkinStatus ?? "");
    setRegSearch("");
    setRegPage(1);
    setActiveTab("REGISTRATIONS");
    fetchRegistrations({
      page: 1,
      category: filters.category ?? "",
      status: filters.status ?? "",
      checkinStatus: filters.checkinStatus ?? "",
      search: "",
    });
  };

  const openRegistrationDetail = async (id: string) => {
    setSelectedRegId(id);
    setDetailLoading(true);
    try {
      const res = await fetch(`/api/admin/registrations/${id}`, { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        setRegDetail(data);
        setDetailStatus(data.registration?.status || "");
        setDetailNotes(data.registration?.reviewNotes || "");
      }
    } finally {
      setDetailLoading(false);
    }
  };

  const saveRegistrationReview = async (credentialAction?: "ISSUE" | "REISSUE" | "REVOKE") => {
    if (!selectedRegId) return;
    setSavingDetail(true);
    try {
      const payload: Record<string, unknown> = {
        status: detailStatus,
        reviewNotes: detailNotes,
      };
      if (credentialAction) {
        payload.credentialAction = credentialAction;
        if (credentialAction === "REVOKE") {
          payload.revocationReason = detailNotes || "Revoked by administrator";
        }
      }
      const res = await fetch(`/api/admin/registrations/${selectedRegId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok) {
        notify("error", data.error || "Update failed.");
        return;
      }
      notify(
        "success",
        data.credentialResult?.credentialCode
          ? `Saved! QR Credential ${data.credentialResult.credentialCode} issued.`
          : "Registration updated successfully."
      );
      await openRegistrationDetail(selectedRegId);
      fetchRegistrations();
      fetchOverview();
    } finally {
      setSavingDetail(false);
    }
  };

  const quickUpdateStatus = async (
    id: string,
    status: string,
    source: "VOLUNTEERS" | "SLI"
  ) => {
    const res = await fetch(`/api/admin/registrations/${id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ status }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Failed to update status.");
      return;
    }
    notify(
      "success",
      status === "APPROVED"
        ? `Approved! ${data.credentialResult?.credentialCode ? `QR Pass ${data.credentialResult.credentialCode} issued.` : ""}`
        : `Status updated to ${status}.`
    );
    if (source === "VOLUNTEERS") fetchVolunteers();
    else fetchSli();
    fetchOverview();
  };

  const saveVolunteerAssignment = async (regId: string) => {
    const res = await fetch(`/api/admin/registrations/${regId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ volunteerAssignment: volForm }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      notify("error", err.error || "Failed to save volunteer duty assignment.");
      return;
    }
    notify("success", "Volunteer duty assignment saved.");
    setActiveVolAssign(null);
    fetchVolunteers();
  };

  const saveSliAssignment = async (regId: string, existingItem: RegistrationListItem) => {
    const currentSessions = existingItem.sliDetails?.assignedSessions || [];
    const nextSessions = sliForm.sessionTitle.trim()
      ? [
          ...currentSessions,
          {
            sessionTitle: sliForm.sessionTitle.trim(),
            venue: sliForm.assignedVenue || "Main Auditorium",
            dutyTime: sliForm.dutyTimes || "10:00 – 13:00 IST",
            day: sliForm.sessionDay,
          },
        ]
      : currentSessions;

    const res = await fetch(`/api/admin/registrations/${regId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        sliAssignment: {
          assignedVenue: sliForm.assignedVenue,
          dutyTimes: sliForm.dutyTimes,
          assignmentNotes: sliForm.assignmentNotes,
          assignedSessions: nextSessions,
        },
      }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      notify("error", err.error || "Failed to save SLI assignment.");
      return;
    }
    notify("success", "SLI session & venue assignment saved.");
    setActiveSliAssign(null);
    fetchSli();
  };

  const updateAccommodation = async (requestId: string, status: string) => {
    const res = await fetch("/api/admin/accommodations", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        requestId,
        status,
        coordinatorNotes: accommNotesDraft[requestId] || "",
      }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      notify("error", err.error || "Failed to update accommodation request.");
      return;
    }
    notify("success", `Accommodation status updated to ${status}.`);
    fetchAccommodations();
    fetchOverview();
  };

  const handleCreateCoupon = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await fetch("/api/admin/coupons", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ...newCoupon,
        maxUses: newCoupon.maxUses ? Number(newCoupon.maxUses) : null,
      }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Failed to create coupon.");
      return;
    }
    notify("success", `Coupon ${data.item.code} created.`);
    setNewCoupon({
      code: "",
      description: "",
      discountType: "PERCENTAGE",
      discountValue: 100,
      maxUses: "",
      eligibleCategories: ["DELEGATE"],
    });
    fetchCoupons();
  };

  const toggleCouponActive = async (coupon: CouponItem) => {
    const res = await fetch("/api/admin/coupons", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: coupon.id, isActive: !coupon.isActive }),
    });
    if (res.ok) {
      notify("success", `Coupon ${coupon.code} ${!coupon.isActive ? "activated" : "deactivated"}.`);
      fetchCoupons();
    }
  };

  const saveCmsSection = async (sectionId: string) => {
    const draft = sectionDrafts[sectionId];
    if (!draft) return;
    const res = await fetch("/api/admin/cms", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "section", id: sectionId, payload: draft }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      notify("error", err.error || "Failed to save section.");
      return;
    }
    notify("success", "CMS section saved and revision recorded.");
    fetchCms();
  };

  const handleCreateProgramme = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await fetch("/api/admin/cms", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "programme", payload: newProgramme }),
    });
    if (!res.ok) {
      notify("error", "Failed to add programme item.");
      return;
    }
    notify("success", "Programme item added.");
    setNewProgramme({
      dayNumber: 1,
      timeSlot: "10:00 – 11:30 IST",
      title: "",
      category: "Plenary",
      venueName: "Sama Indoor Stadium",
      description: "",
    });
    fetchCms();
  };

  const handleCreatePartner = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await fetch("/api/admin/cms", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "partner", payload: newPartner }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Failed to add partner.");
      return;
    }
    notify("success", "Partner saved.");
    setNewPartner({
      organizationName: "",
      category: "Knowledge Partners",
      websiteUrl: "",
      consentVerified: false,
      status: "DRAFT",
    });
    fetchCms();
  };

  const updatePartnerConsentAndPublish = async (
    partnerId: string,
    consentVerified: boolean,
    status: string
  ) => {
    const res = await fetch("/api/admin/cms", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        entityType: "partner",
        id: partnerId,
        payload: { consentVerified, status },
      }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Could not update partner.");
      return;
    }
    notify("success", `Partner updated (${status}).`);
    fetchCms();
  };

  const handleCreateFaq = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await fetch("/api/admin/cms", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "faq", payload: newFaq }),
    });
    if (!res.ok) {
      notify("error", "Failed to create FAQ.");
      return;
    }
    notify("success", "FAQ added.");
    setNewFaq({ category: "General", question: "", answer: "" });
    fetchCms();
  };

  const handleCreateAnnouncement = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await fetch("/api/admin/cms", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "announcement", payload: newAnnouncement }),
    });
    if (!res.ok) {
      notify("error", "Failed to post announcement.");
      return;
    }
    notify("success", "Announcement published.");
    setNewAnnouncement({ title: "", message: "", severity: "INFO" });
    fetchCms();
  };

  const deleteCmsItem = async (entityType: string, id: string) => {
    const res = await fetch(`/api/admin/cms?entityType=${entityType}&id=${id}`, {
      method: "DELETE",
    });
    if (res.ok) {
      notify("success", "Item deleted and revision logged.");
      fetchCms();
    }
  };

  const updateCategorySetting = async (
    catId: string,
    patch: { isActive?: boolean; feeAmountInr?: number; requiresPayment?: boolean }
  ) => {
    const res = await fetch("/api/admin/cms", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entityType: "category", id: catId, payload: patch }),
    });
    if (!res.ok) {
      notify("error", "Failed to update registration category.");
      return;
    }
    notify("success", "Registration category updated.");
    fetchCms();
  };

  const handleCreateStaff = async (e: React.FormEvent) => {
    e.preventDefault();
    const customPermissions =
      newStaff.roleCode === "ADMIN" && newStaff.grantCms ? ["cms.manage"] : [];
    const res = await fetch("/api/admin/users", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        fullName: newStaff.fullName,
        email: newStaff.email,
        phone: newStaff.phone,
        password: newStaff.password,
        roleCode: newStaff.roleCode,
        customPermissions,
      }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Failed to create staff account.");
      return;
    }
    notify("success", `${newStaff.roleCode} account created for ${data.user.email}.`);
    setNewStaff({
      fullName: "",
      email: "",
      phone: "",
      password: "",
      roleCode: "ADMIN",
      grantCms: false,
    });
    fetchUsers();
  };

  const toggleUserActive = async (u: StaffUserItem) => {
    const res = await fetch("/api/admin/users", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ userId: u.id, isActive: !u.isActive }),
    });
    const data = await res.json();
    if (!res.ok) {
      notify("error", data.error || "Failed to update account.");
      return;
    }
    notify("success", `User ${u.email} ${!u.isActive ? "activated" : "deactivated"}.`);
    fetchUsers();
  };

  const saveUserCustomPermissions = async () => {
    if (!editingPermsUser) return;
    const res = await fetch("/api/admin/users", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        userId: editingPermsUser.id,
        customPermissions: permsDraft,
      }),
    });
    if (!res.ok) {
      notify("error", "Failed to update permissions.");
      return;
    }
    notify("success", `Permissions updated for ${editingPermsUser.email}.`);
    setEditingPermsUser(null);
    fetchUsers();
  };

  const handleLogout = async () => {
    await fetch("/api/auth/logout", { method: "POST" }).catch(() => null);
    router.push("/login");
    router.refresh();
  };

  const buildExportUrl = (format: "csv" | "xlsx", includeAccommodations = false) => {
    const params = new URLSearchParams({ format });
    if (regSearch) params.set("search", regSearch);
    if (regCategory) params.set("category", regCategory);
    if (regStatus) params.set("status", regStatus);
    if (regCheckin) params.set("checkinStatus", regCheckin);
    if (includeAccommodations) params.set("includeAccommodations", "true");
    return `/api/admin/registrations?${params.toString()}`;
  };

  // Role-aware navigation items
  const navItems = useMemo(() => {
    const items: Array<{ id: AdminTab; label: string; icon: React.ComponentType<{ className?: string }>; visible: boolean }> = [
      { id: "OVERVIEW", label: "Overview", icon: LayoutDashboard, visible: true },
      { id: "REGISTRATIONS", label: "Registrations", icon: Users, visible: can("registrations.read.all") },
      { id: "VOLUNTEERS", label: "Volunteers", icon: HandHeart, visible: can("volunteers.review") },
      { id: "SLI", label: "SLI Professionals", icon: Languages, visible: can("sli.review") },
      { id: "ACCOMMODATIONS", label: "Accommodations", icon: Accessibility, visible: can("accommodations.read") },
      { id: "ATTENDANCE", label: "Attendance / Check-In", icon: QrCode, visible: can("attendance.read.full") },
      { id: "COUPONS", label: "Coupons", icon: Ticket, visible: can("coupons.manage") },
      { id: "CMS", label: "Content (CMS)", icon: FileEdit, visible: can("cms.manage") },
      { id: "USERS", label: "User Management", icon: ShieldCheck, visible: isSuperadmin && can("admin_accounts.manage") },
      { id: "AUDIT_LOGS", label: "Audit Logs", icon: ScrollText, visible: can("audit_logs.read") },
    ];
    return items.filter((i) => i.visible);
  }, [can, isSuperadmin]);

  // 12 Real-DB Metric Cards for Overview
  const metricCards = useMemo(() => {
    const m = overview?.metrics;
    return [
      {
        label: "Unique People",
        value: m?.uniquePeople ?? 0,
        sub: "Distinct registered accounts",
        onClick: () => jumpToFilteredRegistrations({}),
      },
      {
        label: "Total Applications",
        value: m?.totalApplications ?? 0,
        sub: "All active submissions",
        onClick: () => jumpToFilteredRegistrations({}),
      },
      {
        label: "Confirmed Registrations",
        value: m?.confirmedRegistrations ?? 0,
        sub: "Confirmed & approved passes",
        onClick: () => jumpToFilteredRegistrations({ status: "CONFIRMED,APPROVED" }),
      },
      {
        label: "Pending Approvals",
        value: m?.pendingApprovals ?? 0,
        sub: "Awaiting organizer review",
        onClick: () => jumpToFilteredRegistrations({ status: "SUBMITTED,UNDER_REVIEW,ADDITIONAL_INFO_REQUIRED" }),
      },
      {
        label: "Delegates",
        value: m?.delegates ?? 0,
        sub: "3-Day conference track",
        onClick: () => jumpToFilteredRegistrations({ category: "DELEGATE" }),
      },
      {
        label: "Volunteer Applications",
        value: m?.volunteerApplications ?? 0,
        sub: "Total volunteer applicants",
        onClick: () => setActiveTab("VOLUNTEERS"),
      },
      {
        label: "Approved Volunteers",
        value: m?.approvedVolunteers ?? 0,
        sub: "Cleared for duty",
        onClick: () => jumpToFilteredRegistrations({ category: "VOLUNTEER", status: "APPROVED,CONFIRMED" }),
      },
      {
        label: "SLI Applications",
        value: m?.sliApplications ?? 0,
        sub: "Sign language interpreters",
        onClick: () => setActiveTab("SLI"),
      },
      {
        label: "Approved SLI Pros",
        value: m?.approvedSli ?? 0,
        sub: "Verified ISL interpreters",
        onClick: () => jumpToFilteredRegistrations({ category: "SLI", status: "APPROVED,CONFIRMED" }),
      },
      {
        label: "Allies & Supporters",
        value: m?.alliesSupporters ?? 0,
        sub: "Community ally track",
        onClick: () => jumpToFilteredRegistrations({ category: "ALLY" }),
      },
      {
        label: "Accommodation Requests",
        value: m?.participantsRequestingAccommodations ?? 0,
        sub: "ISL, Braille, mobility & sensory",
        onClick: () => (can("accommodations.read") ? setActiveTab("ACCOMMODATIONS") : undefined),
      },
      {
        label: "Total Check-Ins",
        value: m?.totalCheckins ?? 0,
        sub: `${m?.todayCheckins ?? 0} today (IST)`,
        onClick: () => setActiveTab("ATTENDANCE"),
      },
    ];
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [overview, can]);

  return (
    <div className="min-h-screen bg-[#F6F4FA] text-[#17151C] flex">
      <a
        href="#admin-main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-[#6B4E96] focus:text-white focus:px-4 focus:py-2 focus:rounded-lg"
      >
        Skip to main content
      </a>

      {/* Mobile Sidebar Backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Charcoal-Black Sidebar with Dusty-Purple Highlights */}
      <aside
        aria-label="Admin navigation"
        className={`fixed lg:sticky top-0 left-0 z-40 h-screen w-68 shrink-0 bg-[#15121B] text-[#F2EFF8] border-r border-[#2B2538] flex flex-col transition-transform duration-200 ${
          sidebarOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
        }`}
      >
        <div className="px-5 py-5 border-b border-[#2B2538] flex items-center justify-between">
          <div>
            <p className="text-[11px] uppercase tracking-[0.2em] text-[#B79CE0] font-semibold">
              Purple Festival 2027
            </p>
            <h1 className="text-base font-bold text-white mt-0.5">
              {isSuperadmin ? "Superadmin Portal" : "Admin Operations"}
            </h1>
          </div>
          <button
            type="button"
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden p-1.5 rounded-md text-[#B79CE0] hover:bg-white/10"
            aria-label="Close navigation menu"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
          {navItems.map((item) => {
            const Icon = item.icon;
            const active = activeTab === item.id;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => {
                  setActiveTab(item.id);
                  setSidebarOpen(false);
                }}
                aria-current={active ? "page" : undefined}
                className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors text-left focus-visible:outline-2 focus-visible:outline-[#B79CE0] ${
                  active
                    ? "bg-[#7E61A8]/30 text-white border-l-2 border-[#B79CE0]"
                    : "text-[#C4BCD4] hover:bg-white/5 hover:text-white"
                }`}
              >
                <Icon className={`w-4 h-4 shrink-0 ${active ? "text-[#CBB5EC]" : "text-[#8E83A6]"}`} />
                <span>{item.label}</span>
              </button>
            );
          })}
        </nav>

        <div className="p-4 border-t border-[#2B2538] space-y-3 bg-[#110E16]">
          <div className="flex items-center gap-2">
            <Link
              href="/rgst"
              className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-[#272133] hover:bg-[#342C45] text-[#E6DCF7] transition-colors"
            >
              <QrCode className="w-3.5 h-3.5" />
              RGST Desk
            </Link>
            <Link
              href="/"
              className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-[#272133] hover:bg-[#342C45] text-[#E6DCF7] transition-colors"
            >
              <ExternalLink className="w-3.5 h-3.5" />
              Public Site
            </Link>
          </div>
          <div className="flex items-center justify-between pt-2 border-t border-[#262033]">
            <div className="min-w-0">
              <p className="text-xs font-semibold text-white truncate">{currentUser.fullName}</p>
              <p className="text-[11px] text-[#9E93B5] truncate">{currentUser.email}</p>
            </div>
            <button
              type="button"
              onClick={handleLogout}
              className="p-2 rounded-lg text-[#C4BCD4] hover:text-white hover:bg-rose-500/20 transition-colors"
              title="Sign out"
              aria-label="Sign out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 min-w-0 flex flex-col">
        {/* Top Bar */}
        <header className="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#E5DFEF] px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 rounded-lg border border-[#DDD5EA] text-[#3B2E52] hover:bg-[#F3EEF9]"
              aria-label="Open navigation menu"
            >
              <Menu className="w-5 h-5" />
            </button>
            <div>
              <h2 className="text-lg font-bold text-[#1B1724]">
                {navItems.find((i) => i.id === activeTab)?.label || "Overview"}
              </h2>
              <p className="text-xs text-[#645C75]">
                Vadodara, Gujarat • 21–23 January 2027 • Live PostgreSQL Telemetry
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#EFE9F8] text-[#563A80] border border-[#DDD1F0]">
              {currentUser.roles.join(" / ")}
            </span>
            <button
              type="button"
              onClick={() => {
                if (activeTab === "OVERVIEW" || activeTab === "ATTENDANCE") fetchOverview();
                else if (activeTab === "REGISTRATIONS") fetchRegistrations();
                else if (activeTab === "VOLUNTEERS") fetchVolunteers();
                else if (activeTab === "SLI") fetchSli();
                else if (activeTab === "ACCOMMODATIONS") fetchAccommodations();
                else if (activeTab === "COUPONS") fetchCoupons();
                else if (activeTab === "CMS") fetchCms();
                else if (activeTab === "USERS") fetchUsers();
                else if (activeTab === "AUDIT_LOGS") fetchAuditLogs(auditPagination.page);
              }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-[#DDD5EA] bg-white hover:bg-[#F5F1FA] text-[#3B2E52]"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              Refresh
            </button>
          </div>
        </header>

        {/* Live Toast Region */}
        <div aria-live="polite" className="px-4 sm:px-6 lg:px-8 pt-3">
          {toast && (
            <div
              role="status"
              className={`flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-xs ${
                toast.type === "success"
                  ? "bg-emerald-50 border-emerald-200 text-emerald-900"
                  : "bg-rose-50 border-rose-200 text-rose-900"
              }`}
            >
              <div className="flex items-center gap-2">
                {toast.type === "success" ? (
                  <CheckCircle2 className="w-4 h-4 shrink-0" />
                ) : (
                  <AlertCircle className="w-4 h-4 shrink-0" />
                )}
                <span>{toast.message}</span>
              </div>
              <button
                type="button"
                onClick={() => setToast(null)}
                className="p-1 rounded hover:bg-black/5"
                aria-label="Dismiss notification"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>

        <main id="admin-main-content" className="flex-1 p-4 sm:p-6 lg:p-8 space-y-6">
          {/* =============================================================== */}
          {/* TAB 1: OVERVIEW                                                 */}
          {/* =============================================================== */}
          {activeTab === "OVERVIEW" && (
            <div className="space-y-6">
              {/* 12 Clickable Real-DB Metric Cards */}
              <section aria-label="Key festival metrics">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                  {metricCards.map((card) => (
                    <button
                      key={card.label}
                      type="button"
                      onClick={card.onClick}
                      className="text-left bg-white border border-[#E5DFEF] hover:border-[#8D6FBC] hover:shadow-sm rounded-xl p-4 transition-all focus-visible:outline-2 focus-visible:outline-[#6B4E96] group"
                    >
                      <p className="text-xs font-semibold uppercase tracking-wide text-[#665B7A] group-hover:text-[#5B3E87]">
                        {card.label}
                      </p>
                      <p className="text-3xl font-bold text-[#17151C] mt-1.5 tabular-nums">
                        {overviewLoading ? "…" : card.value.toLocaleString("en-IN")}
                      </p>
                      <p className="text-xs text-[#6E6580] mt-1">{card.sub}</p>
                    </button>
                  ))}
                </div>
              </section>

              {/* Accessible Charts with Table Toggle */}
              <section className="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <h3 className="text-base font-bold text-[#17151C]">
                      Festival Analytics & Pipelines
                    </h3>
                    <p className="text-xs text-[#645C75]">
                      Switch between visual bar charts and accessible tabular views at any time.
                    </p>
                  </div>
                  <div className="inline-flex rounded-lg border border-[#DDD5EA] p-0.5 bg-[#F7F4FC]">
                    <button
                      type="button"
                      onClick={() => setChartViewMode("chart")}
                      aria-pressed={chartViewMode === "chart"}
                      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold ${
                        chartViewMode === "chart"
                          ? "bg-[#6B4E96] text-white"
                          : "text-[#4D4263] hover:text-[#17151C]"
                      }`}
                    >
                      <BarChart3 className="w-3.5 h-3.5" />
                      Bar Charts
                    </button>
                    <button
                      type="button"
                      onClick={() => setChartViewMode("table")}
                      aria-pressed={chartViewMode === "table"}
                      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold ${
                        chartViewMode === "table"
                          ? "bg-[#6B4E96] text-white"
                          : "text-[#4D4263] hover:text-[#17151C]"
                      }`}
                    >
                      <Table2 className="w-3.5 h-3.5" />
                      Data Tables
                    </button>
                  </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  {/* Chart 1: Registration by Category */}
                  <div className="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">
                    <h4 className="text-sm font-bold text-[#1E1929] mb-3">
                      Registration by Category
                    </h4>
                    {chartViewMode === "chart" ? (
                      <div className="space-y-3">
                        {(overview?.categoryDistribution || []).length === 0 ? (
                          <p className="text-xs text-[#6E6580]">No registrations recorded yet.</p>
                        ) : (
                          overview!.categoryDistribution.map((c) => {
                            const max = Math.max(
                              1,
                              ...overview!.categoryDistribution.map((x) => x.total)
                            );
                            const pct = Math.round((c.total / max) * 100);
                            return (
                              <div key={c.categoryCode} className="space-y-1">
                                <div className="flex justify-between text-xs font-medium">
                                  <span>{c.categoryCode}</span>
                                  <span className="tabular-nums text-[#544A68]">
                                    {c.total} ({c.confirmed} confirmed)
                                  </span>
                                </div>
                                <div className="h-2.5 w-full bg-[#EAE3F5] rounded-full overflow-hidden">
                                  <div
                                    className="h-full bg-[#6B4E96] rounded-full"
                                    style={{ width: `${pct}%` }}
                                  />
                                </div>
                              </div>
                            );
                          })
                        )}
                      </div>
                    ) : (
                      <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                          <caption className="sr-only">Registration counts by category</caption>
                          <thead>
                            <tr className="border-b border-[#E5DFEF] text-[#5F5573]">
                              <th scope="col" className="py-2 pr-2">Category</th>
                              <th scope="col" className="py-2 px-2 text-right">Total</th>
                              <th scope="col" className="py-2 px-2 text-right">Confirmed</th>
                              <th scope="col" className="py-2 pl-2 text-right">Checked In</th>
                            </tr>
                          </thead>
                          <tbody>
                            {(overview?.categoryDistribution || []).map((c) => (
                              <tr key={c.categoryCode} className="border-b border-[#F2EEF9]">
                                <td className="py-2 pr-2 font-medium">{c.categoryCode}</td>
                                <td className="py-2 px-2 text-right tabular-nums">{c.total}</td>
                                <td className="py-2 px-2 text-right tabular-nums">{c.confirmed}</td>
                                <td className="py-2 pl-2 text-right tabular-nums">{c.checkedIn}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </div>

                  {/* Chart 2: Attendance by Day */}
                  <div className="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">
                    <h4 className="text-sm font-bold text-[#1E1929] mb-3">
                      Attendance by Day (IST)
                    </h4>
                    {chartViewMode === "chart" ? (
                      <div className="space-y-3">
                        {(overview?.attendanceByDay || []).length === 0 ? (
                          <p className="text-xs text-[#6E6580]">No check-ins recorded yet.</p>
                        ) : (
                          overview!.attendanceByDay.map((d) => {
                            const max = Math.max(
                              1,
                              ...overview!.attendanceByDay.map((x) => x.count)
                            );
                            const pct = Math.round((d.count / max) * 100);
                            return (
                              <div key={d.date} className="space-y-1">
                                <div className="flex justify-between text-xs font-medium">
                                  <span>{d.date}</span>
                                  <span className="tabular-nums text-[#544A68]">{d.count}</span>
                                </div>
                                <div className="h-2.5 w-full bg-[#EAE3F5] rounded-full overflow-hidden">
                                  <div
                                    className="h-full bg-[#4E7B6B] rounded-full"
                                    style={{ width: `${pct}%` }}
                                  />
                                </div>
                              </div>
                            );
                          })
                        )}
                      </div>
                    ) : (
                      <table className="w-full text-xs text-left">
                        <caption className="sr-only">Attendance check-ins by IST date</caption>
                        <thead>
                          <tr className="border-b border-[#E5DFEF] text-[#5F5573]">
                            <th scope="col" className="py-2">Date (IST)</th>
                            <th scope="col" className="py-2 text-right">Check-Ins</th>
                          </tr>
                        </thead>
                        <tbody>
                          {(overview?.attendanceByDay || []).map((d) => (
                            <tr key={d.date} className="border-b border-[#F2EEF9]">
                              <td className="py-2 font-medium">{d.date}</td>
                              <td className="py-2 text-right tabular-nums">{d.count}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>

                  {/* Chart 3: Volunteer & SLI Pipeline */}
                  <div className="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">
                    <h4 className="text-sm font-bold text-[#1E1929] mb-3">
                      Volunteer & SLI Review Pipeline
                    </h4>
                    {chartViewMode === "chart" ? (
                      <div className="space-y-4">
                        {[
                          { label: "Volunteers", p: overview?.volunteerPipeline },
                          { label: "SLI Professionals", p: overview?.sliPipeline },
                        ].map(({ label, p }) => {
                          const total = Math.max(1, p?.total ?? 0);
                          return (
                            <div key={label} className="space-y-1.5">
                              <div className="flex justify-between text-xs font-semibold">
                                <span>{label}</span>
                                <span className="tabular-nums">{p?.total ?? 0} total</span>
                              </div>
                              <div className="h-3 w-full bg-[#EAE3F5] rounded-full overflow-hidden flex">
                                <div
                                  className="bg-emerald-600 h-full"
                                  style={{ width: `${((p?.approved ?? 0) / total) * 100}%` }}
                                  title={`Approved: ${p?.approved ?? 0}`}
                                />
                                <div
                                  className="bg-amber-500 h-full"
                                  style={{
                                    width: `${(((p?.submitted ?? 0) + (p?.underReview ?? 0)) / total) * 100}%`,
                                  }}
                                  title={`Pending: ${(p?.submitted ?? 0) + (p?.underReview ?? 0)}`}
                                />
                                <div
                                  className="bg-purple-500 h-full"
                                  style={{ width: `${((p?.waitlisted ?? 0) / total) * 100}%` }}
                                  title={`Waitlisted: ${p?.waitlisted ?? 0}`}
                                />
                              </div>
                              <div className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-[#5C536E]">
                                <span>Approved: {p?.approved ?? 0}</span>
                                <span>Pending: {(p?.submitted ?? 0) + (p?.underReview ?? 0)}</span>
                                <span>Waitlisted: {p?.waitlisted ?? 0}</span>
                                <span>Rejected: {p?.rejected ?? 0}</span>
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    ) : (
                      <table className="w-full text-xs text-left">
                        <caption className="sr-only">Volunteer and SLI review pipeline</caption>
                        <thead>
                          <tr className="border-b border-[#E5DFEF] text-[#5F5573]">
                            <th scope="col" className="py-2">Track</th>
                            <th scope="col" className="py-2 text-right">Total</th>
                            <th scope="col" className="py-2 text-right">Approved</th>
                            <th scope="col" className="py-2 text-right">Pending</th>
                            <th scope="col" className="py-2 text-right">Waitlist</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr className="border-b border-[#F2EEF9]">
                            <td className="py-2 font-medium">Volunteers</td>
                            <td className="py-2 text-right tabular-nums">{overview?.volunteerPipeline.total ?? 0}</td>
                            <td className="py-2 text-right tabular-nums">{overview?.volunteerPipeline.approved ?? 0}</td>
                            <td className="py-2 text-right tabular-nums">
                              {(overview?.volunteerPipeline.submitted ?? 0) + (overview?.volunteerPipeline.underReview ?? 0)}
                            </td>
                            <td className="py-2 text-right tabular-nums">{overview?.volunteerPipeline.waitlisted ?? 0}</td>
                          </tr>
                          <tr>
                            <td className="py-2 font-medium">SLI</td>
                            <td className="py-2 text-right tabular-nums">{overview?.sliPipeline.total ?? 0}</td>
                            <td className="py-2 text-right tabular-nums">{overview?.sliPipeline.approved ?? 0}</td>
                            <td className="py-2 text-right tabular-nums">
                              {(overview?.sliPipeline.submitted ?? 0) + (overview?.sliPipeline.underReview ?? 0)}
                            </td>
                            <td className="py-2 text-right tabular-nums">{overview?.sliPipeline.waitlisted ?? 0}</td>
                          </tr>
                        </tbody>
                      </table>
                    )}
                  </div>
                </div>
              </section>

              {/* Payment Reconciliation Summary */}
              {can("payments.read") && overview?.paymentReconciliation && (
                <section className="bg-white border border-[#E5DFEF] rounded-xl p-5">
                  <h3 className="text-sm font-bold text-[#17151C] mb-3">
                    Payment Reconciliation (INR)
                  </h3>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div className="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]">
                      <p className="text-xs text-[#645C75]">Captured Revenue</p>
                      <p className="text-xl font-bold text-emerald-800 mt-0.5 tabular-nums">
                        ₹{overview.paymentReconciliation.capturedRevenueInr.toLocaleString("en-IN")}
                      </p>
                      <p className="text-[11px] text-[#6E6580]">
                        {overview.paymentReconciliation.capturedOrders} orders
                      </p>
                    </div>
                    <div className="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]">
                      <p className="text-xs text-[#645C75]">Pending / Authorized</p>
                      <p className="text-xl font-bold text-amber-800 mt-0.5 tabular-nums">
                        ₹{overview.paymentReconciliation.pendingAmountInr.toLocaleString("en-IN")}
                      </p>
                      <p className="text-[11px] text-[#6E6580]">
                        {overview.paymentReconciliation.pendingOrders} orders
                      </p>
                    </div>
                    <div className="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]">
                      <p className="text-xs text-[#645C75]">Coupon Discounts</p>
                      <p className="text-xl font-bold text-[#5B3E87] mt-0.5 tabular-nums">
                        ₹{overview.paymentReconciliation.totalDiscountInr.toLocaleString("en-IN")}
                      </p>
                    </div>
                    <div className="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]">
                      <p className="text-xs text-[#645C75]">Refunded</p>
                      <p className="text-xl font-bold text-rose-800 mt-0.5 tabular-nums">
                        ₹{overview.paymentReconciliation.refundedAmountInr.toLocaleString("en-IN")}
                      </p>
                      <p className="text-[11px] text-[#6E6580]">
                        {overview.paymentReconciliation.refundedOrders} orders
                      </p>
                    </div>
                  </div>
                </section>
              )}
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 2: REGISTRATIONS                                            */}
          {/* =============================================================== */}
          {activeTab === "REGISTRATIONS" && (
            <div className="space-y-4">
              {/* Filters & Exports */}
              <div className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">
                <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                  <div className="md:col-span-2 relative">
                    <Search className="w-4 h-4 text-[#7D7393] absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                      type="search"
                      value={regSearch}
                      onChange={(e) => setRegSearch(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === "Enter") {
                          setRegPage(1);
                          fetchRegistrations({ page: 1 });
                        }
                      }}
                      placeholder="Search name, email, phone, or PFG code…"
                      aria-label="Search registrations"
                      className="w-full pl-9 pr-3 py-2 text-sm border border-[#DDD5EA] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6B4E96]"
                    />
                  </div>
                  <select
                    value={regCategory}
                    onChange={(e) => {
                      setRegCategory(e.target.value);
                      setRegPage(1);
                      fetchRegistrations({ page: 1, category: e.target.value });
                    }}
                    aria-label="Filter by category"
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                  >
                    <option value="">All Categories</option>
                    <option value="ATTENDEE">Attendee</option>
                    <option value="DELEGATE">Delegate</option>
                    <option value="VOLUNTEER">Volunteer</option>
                    <option value="SLI">SLI Professional</option>
                    <option value="STUDENT">Student</option>
                    <option value="ALLY">Ally / Supporter</option>
                    <option value="INSTITUTIONAL">Institutional</option>
                  </select>
                  <select
                    value={regStatus}
                    onChange={(e) => {
                      setRegStatus(e.target.value);
                      setRegPage(1);
                      fetchRegistrations({ page: 1, status: e.target.value });
                    }}
                    aria-label="Filter by status"
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                  >
                    <option value="">All Statuses</option>
                    <option value="CONFIRMED">Confirmed</option>
                    <option value="APPROVED">Approved</option>
                    <option value="SUBMITTED">Submitted</option>
                    <option value="UNDER_REVIEW">Under Review</option>
                    <option value="ADDITIONAL_INFO_REQUIRED">Info Required</option>
                    <option value="WAITLISTED">Waitlisted</option>
                    <option value="REJECTED">Rejected</option>
                  </select>
                  <select
                    value={regCheckin}
                    onChange={(e) => {
                      setRegCheckin(e.target.value);
                      setRegPage(1);
                      fetchRegistrations({ page: 1, checkinStatus: e.target.value });
                    }}
                    aria-label="Filter by check-in status"
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                  >
                    <option value="">All Check-in States</option>
                    <option value="CHECKED_IN">Checked In</option>
                    <option value="NOT_CHECKED_IN">Not Checked In</option>
                  </select>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#F0EBF8]">
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => {
                        setRegPage(1);
                        fetchRegistrations({ page: 1 });
                      }}
                      className="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#583D80] text-white"
                    >
                      Apply Filters
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setRegSearch("");
                        setRegCategory("");
                        setRegStatus("");
                        setRegCheckin("");
                        setRegPage(1);
                        fetchRegistrations({
                          page: 1,
                          search: "",
                          category: "",
                          status: "",
                          checkinStatus: "",
                        });
                      }}
                      className="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#DDD5EA] text-[#4E4466] hover:bg-[#F5F1FA]"
                    >
                      Reset
                    </button>
                    <span className="text-xs text-[#645C75] ml-1">
                      {regPagination.total} records
                    </span>
                  </div>

                  {can("registrations.export") && (
                    <div className="flex flex-wrap items-center gap-2">
                      <a
                        href={buildExportUrl("csv", false)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] bg-[#F7F3FC] hover:bg-[#ECE4F8] text-[#4C3273]"
                      >
                        <Download className="w-3.5 h-3.5" />
                        Export CSV
                      </a>
                      <a
                        href={buildExportUrl("xlsx", false)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] bg-[#F7F3FC] hover:bg-[#ECE4F8] text-[#4C3273]"
                      >
                        <Download className="w-3.5 h-3.5" />
                        Export XLSX
                      </a>
                      {can("accommodations.read") && (
                        <a
                          href={buildExportUrl("xlsx", true)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900"
                          title="Includes accommodation details — audit logged"
                        >
                          <Accessibility className="w-3.5 h-3.5" />
                          Accommodation XLSX
                        </a>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Table */}
              <div className="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm text-left">
                    <caption className="sr-only">Festival registrations table</caption>
                    <thead className="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]">
                      <tr>
                        <th scope="col" className="px-4 py-3">Reg Code</th>
                        <th scope="col" className="px-4 py-3">Participant</th>
                        <th scope="col" className="px-4 py-3">Category</th>
                        <th scope="col" className="px-4 py-3">Status</th>
                        <th scope="col" className="px-4 py-3">QR / Check-in</th>
                        <th scope="col" className="px-4 py-3 text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0EBF8]">
                      {regLoading ? (
                        <tr>
                          <td colSpan={6} className="px-4 py-8 text-center text-sm text-[#6E6580]">
                            Loading registrations…
                          </td>
                        </tr>
                      ) : regItems.length === 0 ? (
                        <tr>
                          <td colSpan={6} className="px-4 py-8 text-center text-sm text-[#6E6580]">
                            No registrations match the current filters.
                          </td>
                        </tr>
                      ) : (
                        regItems.map((r) => (
                          <tr key={r.id} className="hover:bg-[#FAF8FD]">
                            <td className="px-4 py-3 font-mono text-xs font-semibold text-[#4B3470]">
                              {r.registrationCode}
                            </td>
                            <td className="px-4 py-3">
                              <p className="font-semibold text-[#17151C]">{r.fullName}</p>
                              <p className="text-xs text-[#645C75]">
                                {r.email} • {r.phone}
                              </p>
                            </td>
                            <td className="px-4 py-3">
                              <span className="inline-block px-2 py-0.5 rounded-md text-xs font-semibold bg-[#F2ECF9] text-[#51377A]">
                                {r.categoryCode}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              <span
                                className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                  r.status
                                )}`}
                              >
                                {r.status}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-xs">
                              {r.checkedIn ? (
                                <span className="text-emerald-800 font-semibold">
                                  Checked In ({r.checkinCount})
                                </span>
                              ) : r.credentialStatus === "ACTIVE" ? (
                                <span className="text-[#5B3E87]">Pass Ready</span>
                              ) : (
                                <span className="text-[#7C738E]">No Pass</span>
                              )}
                            </td>
                            <td className="px-4 py-3 text-right">
                              <button
                                type="button"
                                onClick={() => openRegistrationDetail(r.id)}
                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D8CCE9] text-[#4C3273] hover:bg-[#F1EBF9]"
                              >
                                <Eye className="w-3.5 h-3.5" />
                                Review
                              </button>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                <div className="px-4 py-3 border-t border-[#ECE7F5] flex items-center justify-between text-xs text-[#5E5470]">
                  <span>
                    Page {regPagination.page} of {regPagination.totalPages} ({regPagination.total} total)
                  </span>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      disabled={regPagination.page <= 1}
                      onClick={() => {
                        const next = regPagination.page - 1;
                        setRegPage(next);
                        fetchRegistrations({ page: next });
                      }}
                      className="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40"
                    >
                      <ChevronLeft className="w-3.5 h-3.5" /> Prev
                    </button>
                    <button
                      type="button"
                      disabled={regPagination.page >= regPagination.totalPages}
                      onClick={() => {
                        const next = regPagination.page + 1;
                        setRegPage(next);
                        fetchRegistrations({ page: next });
                      }}
                      className="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40"
                    >
                      Next <ChevronRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 3: VOLUNTEERS                                               */}
          {/* =============================================================== */}
          {activeTab === "VOLUNTEERS" && (
            <div className="space-y-4">
              <div className="bg-white border border-[#E5DFEF] rounded-xl p-4 flex items-center justify-between">
                <div>
                  <h3 className="text-sm font-bold text-[#17151C]">Volunteer Applications Queue</h3>
                  <p className="text-xs text-[#645C75]">
                    Approve, waitlist, or reject applicants and assign festival departments, shifts, and venues.
                  </p>
                </div>
                <span className="text-xs font-semibold px-3 py-1 rounded-full bg-[#F1EBF9] text-[#51377A]">
                  {volItems.length} applicants
                </span>
              </div>

              {volLoading ? (
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">
                  Loading volunteer queue…
                </div>
              ) : volItems.length === 0 ? (
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">
                  No volunteer applications submitted yet.
                </div>
              ) : (
                <div className="space-y-3">
                  {volItems.map((v) => {
                    const vd = v.volunteerDetails;
                    const isEditing = activeVolAssign === v.id;
                    return (
                      <div
                        key={v.id}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3"
                      >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="font-mono text-xs font-bold text-[#563A80]">
                                {v.registrationCode}
                              </span>
                              <h4 className="font-bold text-[#17151C]">{v.fullName}</h4>
                              <span
                                className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                  v.status
                                )}`}
                              >
                                {v.status}
                              </span>
                              {vd?.isMsuStudentOrAlumni && (
                                <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200">
                                  MSU Baroda
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-[#645C75] mt-1">
                              {v.email} • {v.phone} • {vd?.institutionName || v.organizationName || "Independent"}
                            </p>
                            {vd?.preferredRoles && vd.preferredRoles.length > 0 && (
                              <p className="text-xs text-[#4C4263] mt-1">
                                <strong>Preferred Roles:</strong> {vd.preferredRoles.join(", ")}
                              </p>
                            )}
                            {vd?.assignedDepartment && (
                              <p className="text-xs text-emerald-800 mt-1 font-medium">
                                Assigned: {vd.assignedDepartment} • {vd.assignedVenue || "Venue TBD"} • Shift:{" "}
                                {vd.assignedShift || "TBD"} (Report: {vd.reportingTime || "TBD"})
                              </p>
                            )}
                          </div>

                          <div className="flex flex-wrap items-center gap-2">
                            <button
                              type="button"
                              onClick={() => quickUpdateStatus(v.id, "APPROVED", "VOLUNTEERS")}
                              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white"
                            >
                              <CheckCircle2 className="w-3.5 h-3.5" /> Approve & Issue Pass
                            </button>
                            <button
                              type="button"
                              onClick={() => quickUpdateStatus(v.id, "WAITLISTED", "VOLUNTEERS")}
                              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-100 hover:bg-amber-200 text-amber-900"
                            >
                              <Clock className="w-3.5 h-3.5" /> Waitlist
                            </button>
                            <button
                              type="button"
                              onClick={() => quickUpdateStatus(v.id, "REJECTED", "VOLUNTEERS")}
                              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900"
                            >
                              <XCircle className="w-3.5 h-3.5" /> Reject
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                if (isEditing) {
                                  setActiveVolAssign(null);
                                } else {
                                  setActiveVolAssign(v.id);
                                  setVolForm({
                                    assignedDepartment: vd?.assignedDepartment || "",
                                    assignedResponsibility: vd?.assignedResponsibility || "",
                                    assignedSupervisor: vd?.assignedSupervisor || "",
                                    assignedVenue: vd?.assignedVenue || "",
                                    assignedShift: vd?.assignedShift || "",
                                    reportingTime: vd?.reportingTime || "",
                                    assignmentNotes: vd?.assignmentNotes || "",
                                  });
                                }
                              }}
                              className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]"
                            >
                              {isEditing ? "Close Duty Form" : "Assign Duty"}
                            </button>
                          </div>
                        </div>

                        {isEditing && (
                          <div className="pt-3 border-t border-[#ECE7F5] grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <input
                              type="text"
                              placeholder="Department (e.g. Accessibility Desk)"
                              value={volForm.assignedDepartment}
                              onChange={(e) =>
                                setVolForm({ ...volForm, assignedDepartment: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Supervisor Name"
                              value={volForm.assignedSupervisor}
                              onChange={(e) =>
                                setVolForm({ ...volForm, assignedSupervisor: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Venue (e.g. Sama Indoor Stadium)"
                              value={volForm.assignedVenue}
                              onChange={(e) =>
                                setVolForm({ ...volForm, assignedVenue: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Shift (e.g. Morning 08:30–13:30)"
                              value={volForm.assignedShift}
                              onChange={(e) =>
                                setVolForm({ ...volForm, assignedShift: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Reporting Time (e.g. 08:00 IST)"
                              value={volForm.reportingTime}
                              onChange={(e) =>
                                setVolForm({ ...volForm, reportingTime: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Responsibility"
                              value={volForm.assignedResponsibility}
                              onChange={(e) =>
                                setVolForm({ ...volForm, assignedResponsibility: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <div className="sm:col-span-3 flex items-center gap-2">
                              <input
                                type="text"
                                placeholder="Additional assignment notes…"
                                value={volForm.assignmentNotes}
                                onChange={(e) =>
                                  setVolForm({ ...volForm, assignmentNotes: e.target.value })
                                }
                                className="flex-1 px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                              />
                              <button
                                type="button"
                                onClick={() => saveVolunteerAssignment(v.id)}
                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                              >
                                <Save className="w-3.5 h-3.5" /> Save Assignment
                              </button>
                            </div>
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 4: SLI PROFESSIONALS                                        */}
          {/* =============================================================== */}
          {activeTab === "SLI" && (
            <div className="space-y-4">
              <div className="bg-white border border-[#E5DFEF] rounded-xl p-4 flex items-center justify-between">
                <div>
                  <h3 className="text-sm font-bold text-[#17151C]">
                    Sign Language Interpreter (SLI) Verification & Assignment
                  </h3>
                  <p className="text-xs text-[#645C75]">
                    Review RCI CRR numbers, Gujarat availability, qualifications, and video demos before assigning sessions.
                  </p>
                </div>
                <span className="text-xs font-semibold px-3 py-1 rounded-full bg-[#F1EBF9] text-[#51377A]">
                  {sliItems.length} applicants
                </span>
              </div>

              {sliLoading ? (
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">
                  Loading SLI applications…
                </div>
              ) : sliItems.length === 0 ? (
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">
                  No SLI applications submitted yet.
                </div>
              ) : (
                <div className="space-y-3">
                  {sliItems.map((s) => {
                    const sd = s.sliDetails;
                    const isEditing = activeSliAssign === s.id;
                    return (
                      <div
                        key={s.id}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3"
                      >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div className="space-y-1">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="font-mono text-xs font-bold text-[#563A80]">
                                {s.registrationCode}
                              </span>
                              <h4 className="font-bold text-[#17151C]">{s.fullName}</h4>
                              <span
                                className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                  s.status
                                )}`}
                              >
                                {s.status}
                              </span>
                              <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F3EEF9] text-[#4C3273]">
                                {sd?.basedInGujarat ? "Based in Gujarat" : "Outstation"}
                              </span>
                              {sd?.crrNumber && (
                                <span className="px-2 py-0.5 rounded-full text-xs font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                                  CRR: {sd.crrNumber}
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-[#645C75]">
                              {s.email} • {s.phone} • Experience: {sd?.yearsOfExperience ?? 0} yrs •{" "}
                              {sd?.qualifications || "Qualifications not listed"}
                            </p>
                            {sd?.videoDemoUrl && (
                              <a
                                href={sd.videoDemoUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-xs font-semibold text-[#6B4E96] hover:underline"
                              >
                                <ExternalLink className="w-3.5 h-3.5" /> View ISL Video Demo
                              </a>
                            )}
                            {sd?.assignedVenue && (
                              <p className="text-xs text-emerald-800 font-medium">
                                Assigned Venue: {sd.assignedVenue} • Duty: {sd.dutyTimes || "TBD"} (
                                {(sd.assignedSessions || []).length} sessions)
                              </p>
                            )}
                          </div>

                          <div className="flex flex-wrap items-center gap-2">
                            <button
                              type="button"
                              onClick={() => quickUpdateStatus(s.id, "APPROVED", "SLI")}
                              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white"
                            >
                              <CheckCircle2 className="w-3.5 h-3.5" /> Approve & Issue Pass
                            </button>
                            <button
                              type="button"
                              onClick={() => quickUpdateStatus(s.id, "REJECTED", "SLI")}
                              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900"
                            >
                              <XCircle className="w-3.5 h-3.5" /> Reject
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                if (isEditing) {
                                  setActiveSliAssign(null);
                                } else {
                                  setActiveSliAssign(s.id);
                                  setSliForm({
                                    assignedVenue: sd?.assignedVenue || "",
                                    dutyTimes: sd?.dutyTimes || "",
                                    sessionTitle: "",
                                    sessionDay: "Day 1 (21 Jan 2027)",
                                    assignmentNotes: sd?.assignmentNotes || "",
                                  });
                                }
                              }}
                              className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]"
                            >
                              {isEditing ? "Close Assignment" : "Assign Session / Venue"}
                            </button>
                          </div>
                        </div>

                        {isEditing && (
                          <div className="pt-3 border-t border-[#ECE7F5] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <input
                              type="text"
                              placeholder="Assigned Venue"
                              value={sliForm.assignedVenue}
                              onChange={(e) =>
                                setSliForm({ ...sliForm, assignedVenue: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Duty Times (e.g. 09:30–13:00 IST)"
                              value={sliForm.dutyTimes}
                              onChange={(e) => setSliForm({ ...sliForm, dutyTimes: e.target.value })}
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Add Session Title (optional)"
                              value={sliForm.sessionTitle}
                              onChange={(e) =>
                                setSliForm({ ...sliForm, sessionTitle: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <input
                              type="text"
                              placeholder="Session Day"
                              value={sliForm.sessionDay}
                              onChange={(e) =>
                                setSliForm({ ...sliForm, sessionDay: e.target.value })
                              }
                              className="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                            />
                            <div className="sm:col-span-2 lg:col-span-4 flex items-center gap-2">
                              <input
                                type="text"
                                placeholder="Coordinator notes / honorarium / travel notes…"
                                value={sliForm.assignmentNotes}
                                onChange={(e) =>
                                  setSliForm({ ...sliForm, assignmentNotes: e.target.value })
                                }
                                className="flex-1 px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg"
                              />
                              <button
                                type="button"
                                onClick={() => saveSliAssignment(s.id, s)}
                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                              >
                                <Save className="w-3.5 h-3.5" /> Save Assignment
                              </button>
                            </div>
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 5: ACCOMMODATION REQUESTS                                   */}
          {/* =============================================================== */}
          {activeTab === "ACCOMMODATIONS" && (
            <div className="space-y-4">
              <div className="bg-white border border-[#E5DFEF] rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h3 className="text-sm font-bold text-[#17151C]">
                    Accessibility & Accommodation Coordination (Permission Protected)
                  </h3>
                  <p className="text-xs text-[#645C75]">
                    Coordinate ISL interpreters, Braille schedules, wheelchair seating, and sensory sanctuary access.
                  </p>
                </div>
                <select
                  value={accommStatusFilter}
                  onChange={(e) => setAccommStatusFilter(e.target.value)}
                  aria-label="Filter accommodation requests by status"
                  className="px-3 py-1.5 text-xs border border-[#DDD5EA] rounded-lg bg-white"
                >
                  <option value="">All Statuses</option>
                  <option value="REQUESTED">Requested</option>
                  <option value="IN_PROGRESS">In Progress</option>
                  <option value="ARRANGED">Arranged</option>
                  <option value="DECLINED">Declined</option>
                </select>
              </div>

              <div className="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm text-left">
                    <caption className="sr-only">Participant accommodation requests</caption>
                    <thead className="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]">
                      <tr>
                        <th scope="col" className="px-4 py-3">Participant</th>
                        <th scope="col" className="px-4 py-3">Requested Accommodations</th>
                        <th scope="col" className="px-4 py-3">Status</th>
                        <th scope="col" className="px-4 py-3">Coordinator Notes & Action</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0EBF8]">
                      {accommLoading ? (
                        <tr>
                          <td colSpan={4} className="px-4 py-8 text-center text-sm text-[#6E6580]">
                            Loading accommodation requests…
                          </td>
                        </tr>
                      ) : accommItems.length === 0 ? (
                        <tr>
                          <td colSpan={4} className="px-4 py-8 text-center text-sm text-[#6E6580]">
                            No accommodation requests found.
                          </td>
                        </tr>
                      ) : (
                        accommItems.map((a) => (
                          <tr key={a.id} className="align-top">
                            <td className="px-4 py-3">
                              <p className="font-mono text-xs font-bold text-[#563A80]">
                                {a.registrationCode}
                              </p>
                              <p className="font-semibold text-[#17151C]">{a.fullName}</p>
                              <p className="text-xs text-[#645C75]">
                                {a.email} • {a.phone}
                              </p>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex flex-wrap gap-1.5">
                                {a.selectedTypeLabels.map((lbl) => (
                                  <span
                                    key={lbl}
                                    className="px-2 py-0.5 rounded-md text-xs font-medium bg-[#F2ECF9] text-[#4C3273]"
                                  >
                                    {lbl}
                                  </span>
                                ))}
                              </div>
                              {a.freeTextDetails && (
                                <p className="text-xs text-[#4A435C] mt-2 bg-[#FAF8FD] p-2 rounded border border-[#ECE7F5]">
                                  {a.freeTextDetails}
                                </p>
                              )}
                            </td>
                            <td className="px-4 py-3">
                              <span
                                className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                  a.status
                                )}`}
                              >
                                {a.status}
                              </span>
                            </td>
                            <td className="px-4 py-3 space-y-2 min-w-[260px]">
                              <input
                                type="text"
                                value={accommNotesDraft[a.id] ?? ""}
                                onChange={(e) =>
                                  setAccommNotesDraft({
                                    ...accommNotesDraft,
                                    [a.id]: e.target.value,
                                  })
                                }
                                placeholder="Coordinator notes…"
                                aria-label={`Coordinator notes for ${a.fullName}`}
                                className="w-full px-2.5 py-1.5 text-xs border border-[#DDD5EA] rounded-lg"
                              />
                              <div className="flex flex-wrap gap-1.5">
                                {(["IN_PROGRESS", "ARRANGED", "DECLINED"] as const).map((st) => (
                                  <button
                                    key={st}
                                    type="button"
                                    onClick={() => updateAccommodation(a.id, st)}
                                    className="px-2.5 py-1 rounded text-[11px] font-semibold border border-[#DDD5EA] hover:bg-[#F3EEF9] text-[#3E2B5E]"
                                  >
                                    Mark {st.replace("_", " ")}
                                  </button>
                                ))}
                              </div>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 6: ATTENDANCE / CHECK-IN                                    */}
          {/* =============================================================== */}
          {activeTab === "ATTENDANCE" && (
            <div className="space-y-6">
              <div className="bg-[#181421] text-white rounded-xl p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                  <p className="text-xs uppercase tracking-wider text-[#C4ACEB] font-semibold">
                    Live Reception Operations
                  </p>
                  <h3 className="text-lg font-bold mt-0.5">
                    Real-Time Attendance & Gate Check-In Monitor
                  </h3>
                  <p className="text-xs text-[#C8C0D8] mt-1">
                    Today (IST): {overview?.todayIst || "—"} • Duplicate entry prevented at database level
                  </p>
                </div>
                <Link
                  href="/rgst"
                  className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-[#7E61A8] hover:bg-[#6D5096] text-white"
                >
                  <QrCode className="w-4 h-4" />
                  Open RGST QR Scanner
                </Link>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <p className="text-xs font-semibold text-[#645C75] uppercase">Confirmed Eligible</p>
                  <p className="text-3xl font-bold mt-1 tabular-nums">
                    {overview?.metrics.confirmedRegistrations ?? 0}
                  </p>
                </div>
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <p className="text-xs font-semibold text-emerald-700 uppercase">Unique Checked-In</p>
                  <p className="text-3xl font-bold text-emerald-800 mt-1 tabular-nums">
                    {overview?.metrics.uniqueCheckedInParticipants ?? 0}
                  </p>
                </div>
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <p className="text-xs font-semibold text-amber-700 uppercase">Not Yet Arrived</p>
                  <p className="text-3xl font-bold text-amber-800 mt-1 tabular-nums">
                    {overview?.metrics.notYetArrived ?? 0}
                  </p>
                </div>
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <p className="text-xs font-semibold text-[#5B3E87] uppercase">Today&apos;s Arrivals (IST)</p>
                  <p className="text-3xl font-bold text-[#5B3E87] mt-1 tabular-nums">
                    {overview?.metrics.todayCheckins ?? 0}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <h4 className="text-sm font-bold mb-3">Attendance by Venue / Gate</h4>
                  <table className="w-full text-xs text-left">
                    <caption className="sr-only">Check-in counts by venue</caption>
                    <thead>
                      <tr className="border-b border-[#E5DFEF] text-[#5C536E]">
                        <th scope="col" className="py-2">Venue / Gate</th>
                        <th scope="col" className="py-2 text-right">Check-Ins</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(overview?.attendanceByVenue || []).length === 0 ? (
                        <tr>
                          <td colSpan={2} className="py-4 text-center text-[#6E6580]">
                            No venue check-ins recorded yet.
                          </td>
                        </tr>
                      ) : (
                        overview!.attendanceByVenue.map((v) => (
                          <tr key={v.venueLabel} className="border-b border-[#F2EEF9]">
                            <td className="py-2 font-medium">{v.venueLabel}</td>
                            <td className="py-2 text-right tabular-nums font-semibold">{v.count}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>

                <div className="bg-white border border-[#E5DFEF] rounded-xl p-4">
                  <h4 className="text-sm font-bold mb-3">Recent Check-Ins</h4>
                  <div className="space-y-2 max-h-72 overflow-y-auto">
                    {(overview?.recentCheckins || []).length === 0 ? (
                      <p className="text-xs text-[#6E6580]">No recent check-ins.</p>
                    ) : (
                      overview!.recentCheckins.map((c) => (
                        <div
                          key={c.id}
                          className="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5] text-xs"
                        >
                          <div>
                            <p className="font-semibold text-[#17151C]">
                              {c.participantName}{" "}
                              <span className="font-mono text-[#5B3E87]">({c.registrationCode})</span>
                            </p>
                            <p className="text-[#645C75]">
                              {c.categoryCode} • {c.venueLabel} • {c.method}
                            </p>
                          </div>
                          <span className="text-[11px] text-[#5C536E] shrink-0">
                            {formatDateShort(c.checkedInAt)}
                          </span>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 7: COUPONS                                                  */}
          {/* =============================================================== */}
          {activeTab === "COUPONS" && (
            <div className="space-y-6">
              <form
                onSubmit={handleCreateCoupon}
                className="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-4"
              >
                <h3 className="text-sm font-bold text-[#17151C]">
                  Create Institutional / Student Coupon Code
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                  <input
                    required
                    type="text"
                    placeholder="CODE (e.g. MSU2027)"
                    value={newCoupon.code}
                    onChange={(e) =>
                      setNewCoupon({ ...newCoupon, code: e.target.value.toUpperCase() })
                    }
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg font-mono uppercase"
                  />
                  <select
                    value={newCoupon.discountType}
                    onChange={(e) =>
                      setNewCoupon({
                        ...newCoupon,
                        discountType: e.target.value as "PERCENTAGE" | "FIXED_AMOUNT" | "FULL_WAIVER",
                      })
                    }
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                  >
                    <option value="PERCENTAGE">Percentage (%)</option>
                    <option value="FIXED_AMOUNT">Fixed Amount (₹)</option>
                    <option value="FULL_WAIVER">100% Full Waiver</option>
                  </select>
                  <input
                    type="number"
                    min={0}
                    max={100000}
                    disabled={newCoupon.discountType === "FULL_WAIVER"}
                    value={newCoupon.discountType === "FULL_WAIVER" ? 100 : newCoupon.discountValue}
                    onChange={(e) =>
                      setNewCoupon({ ...newCoupon, discountValue: Number(e.target.value) })
                    }
                    placeholder="Value"
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg disabled:bg-slate-100"
                  />
                  <input
                    type="number"
                    min={1}
                    placeholder="Max Uses (optional)"
                    value={newCoupon.maxUses}
                    onChange={(e) => setNewCoupon({ ...newCoupon, maxUses: e.target.value })}
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                  />
                  <button
                    type="submit"
                    className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                  >
                    <Plus className="w-4 h-4" /> Create Coupon
                  </button>
                </div>
                <input
                  type="text"
                  placeholder="Description (e.g. MSU Baroda faculty & student waiver)"
                  value={newCoupon.description}
                  onChange={(e) => setNewCoupon({ ...newCoupon, description: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                />
              </form>

              <div className="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">
                <table className="w-full text-sm text-left">
                  <caption className="sr-only">Coupon codes list</caption>
                  <thead className="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]">
                    <tr>
                      <th scope="col" className="px-4 py-3">Code</th>
                      <th scope="col" className="px-4 py-3">Discount</th>
                      <th scope="col" className="px-4 py-3">Redemptions</th>
                      <th scope="col" className="px-4 py-3">Status</th>
                      <th scope="col" className="px-4 py-3 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#F0EBF8]">
                    {couponsLoading ? (
                      <tr>
                        <td colSpan={5} className="px-4 py-6 text-center text-[#6E6580]">
                          Loading coupons…
                        </td>
                      </tr>
                    ) : coupons.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="px-4 py-6 text-center text-[#6E6580]">
                          No coupon codes created yet.
                        </td>
                      </tr>
                    ) : (
                      coupons.map((c) => (
                        <tr key={c.id}>
                          <td className="px-4 py-3">
                            <p className="font-mono font-bold text-[#4B3470]">{c.code}</p>
                            {c.description && <p className="text-xs text-[#645C75]">{c.description}</p>}
                          </td>
                          <td className="px-4 py-3 text-xs font-semibold">
                            {c.discountType === "FULL_WAIVER"
                              ? "100% Waiver"
                              : c.discountType === "PERCENTAGE"
                                ? `${c.discountValue}% Off`
                                : `₹${c.discountValue} Off`}
                          </td>
                          <td className="px-4 py-3 text-xs tabular-nums">
                            {c.redemptionCount} / {c.maxUses ?? "∞"}
                          </td>
                          <td className="px-4 py-3">
                            <span
                              className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                c.isActive ? "ACTIVE" : "REVOKED"
                              )}`}
                            >
                              {c.isActive ? "Active" : "Inactive"}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-right">
                            <button
                              type="button"
                              onClick={() => toggleCouponActive(c)}
                              className="px-3 py-1 rounded-lg text-xs font-semibold border border-[#DDD5EA] hover:bg-[#F3EEF9]"
                            >
                              {c.isActive ? "Disable" : "Enable"}
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 8: CONTENT MANAGEMENT (CMS)                                 */}
          {/* =============================================================== */}
          {activeTab === "CMS" && (
            <div className="space-y-4">
              <div className="flex flex-wrap gap-2 bg-white border border-[#E5DFEF] rounded-xl p-2">
                {(
                  [
                    ["sections", "Page Sections"],
                    ["programme", "Programme"],
                    ["partners", "Partners"],
                    ["faqs", "FAQs"],
                    ["announcements", "Announcements"],
                    ["categories", "Registration Categories"],
                  ] as const
                ).map(([key, label]) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setCmsSubTab(key)}
                    className={`px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors ${
                      cmsSubTab === key
                        ? "bg-[#6B4E96] text-white"
                        : "text-[#4D4263] hover:bg-[#F3EEF9]"
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </div>

              {cmsLoading || !cmsData ? (
                <div className="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">
                  Loading CMS content…
                </div>
              ) : (
                <>
                  {/* CMS: Page Sections */}
                  {cmsSubTab === "sections" && (
                    <div className="space-y-4">
                      {cmsData.sections.map((s) => {
                        const d = sectionDrafts[s.id] || {
                          title: s.title || "",
                          subtitle: s.subtitle || "",
                          body: s.body || "",
                          status: s.status,
                        };
                        return (
                          <div
                            key={s.id}
                            className="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-3"
                          >
                            <div className="flex items-center justify-between">
                              <span className="font-mono text-xs uppercase font-bold px-2.5 py-1 rounded bg-[#F1EBF9] text-[#51377A]">
                                {s.sectionKey}
                              </span>
                              <button
                                type="button"
                                onClick={() => saveCmsSection(s.id)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                              >
                                <Save className="w-3.5 h-3.5" /> Save & Record Revision
                              </button>
                            </div>
                            <input
                              type="text"
                              value={d.title}
                              onChange={(e) =>
                                setSectionDrafts({
                                  ...sectionDrafts,
                                  [s.id]: { ...d, title: e.target.value },
                                })
                              }
                              aria-label={`${s.sectionKey} title`}
                              className="w-full px-3 py-2 text-sm font-semibold border border-[#DDD5EA] rounded-lg"
                              placeholder="Section Title"
                            />
                            <input
                              type="text"
                              value={d.subtitle}
                              onChange={(e) =>
                                setSectionDrafts({
                                  ...sectionDrafts,
                                  [s.id]: { ...d, subtitle: e.target.value },
                                })
                              }
                              aria-label={`${s.sectionKey} subtitle`}
                              className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                              placeholder="Subtitle"
                            />
                            <textarea
                              rows={3}
                              value={d.body}
                              onChange={(e) =>
                                setSectionDrafts({
                                  ...sectionDrafts,
                                  [s.id]: { ...d, body: e.target.value },
                                })
                              }
                              aria-label={`${s.sectionKey} body`}
                              className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                              placeholder="Section Body Copy"
                            />
                          </div>
                        );
                      })}
                    </div>
                  )}

                  {/* CMS: Programme */}
                  {cmsSubTab === "programme" && (
                    <div className="space-y-4">
                      <form
                        onSubmit={handleCreateProgramme}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 grid grid-cols-1 sm:grid-cols-3 gap-3"
                      >
                        <input
                          required
                          type="text"
                          placeholder="Session Title"
                          value={newProgramme.title}
                          onChange={(e) =>
                            setNewProgramme({ ...newProgramme, title: e.target.value })
                          }
                          className="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <select
                          value={newProgramme.dayNumber}
                          onChange={(e) =>
                            setNewProgramme({ ...newProgramme, dayNumber: Number(e.target.value) })
                          }
                          className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                        >
                          <option value={1}>Day 1 — 21 Jan 2027</option>
                          <option value={2}>Day 2 — 22 Jan 2027</option>
                          <option value={3}>Day 3 — 23 Jan 2027</option>
                        </select>
                        <input
                          required
                          type="text"
                          placeholder="Time Slot"
                          value={newProgramme.timeSlot}
                          onChange={(e) =>
                            setNewProgramme({ ...newProgramme, timeSlot: e.target.value })
                          }
                          className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <input
                          required
                          type="text"
                          placeholder="Category (e.g. Para Sports, Plenary)"
                          value={newProgramme.category}
                          onChange={(e) =>
                            setNewProgramme({ ...newProgramme, category: e.target.value })
                          }
                          className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <input
                          type="text"
                          placeholder="Venue Name"
                          value={newProgramme.venueName}
                          onChange={(e) =>
                            setNewProgramme({ ...newProgramme, venueName: e.target.value })
                          }
                          className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <button
                          type="submit"
                          className="sm:col-span-3 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                        >
                          <Plus className="w-4 h-4" /> Add Programme Session
                        </button>
                      </form>

                      <div className="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">
                        {cmsData.programmeItems.map((p) => (
                          <div key={p.id} className="p-4 flex items-center justify-between gap-3">
                            <div>
                              <p className="text-xs font-semibold text-[#5B3E87]">
                                Day {p.dayNumber} • {p.timeSlot} • {p.category}
                              </p>
                              <p className="font-bold text-[#17151C]">{p.title}</p>
                              {p.venueName && (
                                <p className="text-xs text-[#645C75]">{p.venueName}</p>
                              )}
                            </div>
                            <button
                              type="button"
                              onClick={() => deleteCmsItem("programme", p.id)}
                              className="p-2 rounded-lg text-rose-700 hover:bg-rose-50"
                              aria-label={`Delete ${p.title}`}
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* CMS: Partners (with Consent Gate) */}
                  {cmsSubTab === "partners" && (
                    <div className="space-y-4">
                      <form
                        onSubmit={handleCreatePartner}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3"
                      >
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                          <input
                            required
                            type="text"
                            placeholder="Organization Name"
                            value={newPartner.organizationName}
                            onChange={(e) =>
                              setNewPartner({ ...newPartner, organizationName: e.target.value })
                            }
                            className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                          <input
                            required
                            type="text"
                            placeholder="Category (e.g. Knowledge Partners)"
                            value={newPartner.category}
                            onChange={(e) =>
                              setNewPartner({ ...newPartner, category: e.target.value })
                            }
                            className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                          <input
                            type="url"
                            placeholder="Website URL (optional)"
                            value={newPartner.websiteUrl}
                            onChange={(e) =>
                              setNewPartner({ ...newPartner, websiteUrl: e.target.value })
                            }
                            className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                          <label className="inline-flex items-center gap-2 text-xs font-medium text-[#3C334E]">
                            <input
                              type="checkbox"
                              checked={newPartner.consentVerified}
                              onChange={(e) =>
                                setNewPartner({
                                  ...newPartner,
                                  consentVerified: e.target.checked,
                                })
                              }
                            />
                            Written partner logo/name consent verified
                          </label>
                          <button
                            type="submit"
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                          >
                            <Plus className="w-4 h-4" /> Add Partner (Draft)
                          </button>
                        </div>
                      </form>

                      <div className="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">
                        {cmsData.partners.map((pt) => (
                          <div
                            key={pt.id}
                            className="p-4 flex flex-wrap items-center justify-between gap-3"
                          >
                            <div>
                              <div className="flex items-center gap-2">
                                <p className="font-bold text-[#17151C]">{pt.organizationName}</p>
                                <span
                                  className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                    pt.status
                                  )}`}
                                >
                                  {pt.status}
                                </span>
                              </div>
                              <p className="text-xs text-[#645C75]">
                                {pt.category} • Consent:{" "}
                                {pt.consentVerified ? "Verified" : "Unverified"}
                              </p>
                            </div>
                            <div className="flex items-center gap-2">
                              <label className="inline-flex items-center gap-1.5 text-xs">
                                <input
                                  type="checkbox"
                                  checked={pt.consentVerified}
                                  onChange={(e) =>
                                    updatePartnerConsentAndPublish(
                                      pt.id,
                                      e.target.checked,
                                      e.target.checked ? pt.status : "DRAFT"
                                    )
                                  }
                                />
                                Consent
                              </label>
                              <button
                                type="button"
                                disabled={!pt.consentVerified && pt.status !== "PUBLISHED"}
                                onClick={() =>
                                  updatePartnerConsentAndPublish(
                                    pt.id,
                                    pt.consentVerified,
                                    pt.status === "PUBLISHED" ? "DRAFT" : "PUBLISHED"
                                  )
                                }
                                className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white disabled:opacity-40"
                              >
                                {pt.status === "PUBLISHED" ? "Unpublish" : "Publish"}
                              </button>
                              <button
                                type="button"
                                onClick={() => deleteCmsItem("partner", pt.id)}
                                className="p-1.5 rounded-lg text-rose-700 hover:bg-rose-50"
                                aria-label={`Delete ${pt.organizationName}`}
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* CMS: FAQs */}
                  {cmsSubTab === "faqs" && (
                    <div className="space-y-4">
                      <form
                        onSubmit={handleCreateFaq}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3"
                      >
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                          <input
                            required
                            type="text"
                            placeholder="Category"
                            value={newFaq.category}
                            onChange={(e) => setNewFaq({ ...newFaq, category: e.target.value })}
                            className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                          <input
                            required
                            type="text"
                            placeholder="Question"
                            value={newFaq.question}
                            onChange={(e) => setNewFaq({ ...newFaq, question: e.target.value })}
                            className="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                        </div>
                        <textarea
                          required
                          rows={2}
                          placeholder="Answer"
                          value={newFaq.answer}
                          onChange={(e) => setNewFaq({ ...newFaq, answer: e.target.value })}
                          className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <button
                          type="submit"
                          className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white"
                        >
                          <Plus className="w-4 h-4" /> Add FAQ
                        </button>
                      </form>

                      <div className="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">
                        {cmsData.faqItems.map((f) => (
                          <div key={f.id} className="p-4 flex items-start justify-between gap-3">
                            <div>
                              <span className="text-[11px] font-semibold uppercase text-[#5B3E87]">
                                {f.category}
                              </span>
                              <p className="font-bold text-sm text-[#17151C]">{f.question}</p>
                              <p className="text-xs text-[#544A68] mt-1">{f.answer}</p>
                            </div>
                            <button
                              type="button"
                              onClick={() => deleteCmsItem("faq", f.id)}
                              className="p-1.5 rounded-lg text-rose-700 hover:bg-rose-50"
                              aria-label="Delete FAQ"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* CMS: Announcements */}
                  {cmsSubTab === "announcements" && (
                    <div className="space-y-4">
                      <form
                        onSubmit={handleCreateAnnouncement}
                        className="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3"
                      >
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                          <input
                            required
                            type="text"
                            placeholder="Announcement Title"
                            value={newAnnouncement.title}
                            onChange={(e) =>
                              setNewAnnouncement({ ...newAnnouncement, title: e.target.value })
                            }
                            className="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                          />
                          <select
                            value={newAnnouncement.severity}
                            onChange={(e) =>
                              setNewAnnouncement({
                                ...newAnnouncement,
                                severity: e.target.value as "INFO" | "IMPORTANT" | "ALERT",
                              })
                            }
                            className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                          >
                            <option value="INFO">Info</option>
                            <option value="IMPORTANT">Important</option>
                            <option value="ALERT">Alert</option>
                          </select>
                        </div>
                        <textarea
                          required
                          rows={2}
                          placeholder="Announcement message…"
                          value={newAnnouncement.message}
                          onChange={(e) =>
                            setNewAnnouncement({ ...newAnnouncement, message: e.target.value })
                          }
                          className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        />
                        <button
                          type="submit"
                          className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white"
                        >
                          <Plus className="w-4 h-4" /> Publish Announcement
                        </button>
                      </form>

                      <div className="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">
                        {cmsData.announcements.length === 0 ? (
                          <p className="p-6 text-center text-xs text-[#6E6580]">
                            No announcements posted yet.
                          </p>
                        ) : (
                          cmsData.announcements.map((a) => (
                            <div key={a.id} className="p-4 flex items-start justify-between gap-3">
                              <div>
                                <p className="font-bold text-sm">{a.title}</p>
                                <p className="text-xs text-[#544A68] mt-1">{a.message}</p>
                              </div>
                              <button
                                type="button"
                                onClick={() => deleteCmsItem("announcement", a.id)}
                                className="p-1.5 rounded-lg text-rose-700 hover:bg-rose-50"
                                aria-label="Delete announcement"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </div>
                          ))
                        )}
                      </div>
                    </div>
                  )}

                  {/* CMS: Registration Categories */}
                  {cmsSubTab === "categories" && (
                    <div className="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">
                      {cmsData.registrationCategories.map((cat) => (
                        <div
                          key={cat.id}
                          className="p-4 flex flex-wrap items-center justify-between gap-4"
                        >
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-mono text-xs font-bold text-[#563A80]">
                                {cat.code}
                              </span>
                              <p className="font-bold text-sm text-[#17151C]">{cat.name}</p>
                            </div>
                            <p className="text-xs text-[#645C75] mt-0.5">
                              {cat.requiresApproval ? "Requires organizer approval" : "Instant confirmation"}
                            </p>
                          </div>
                          <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-1.5 text-xs">
                              <span>Fee (₹):</span>
                              <input
                                type="number"
                                min={0}
                                defaultValue={cat.feeAmountInr}
                                onBlur={(e) => {
                                  const val = Number(e.target.value) || 0;
                                  if (val !== cat.feeAmountInr) {
                                    updateCategorySetting(cat.id, {
                                      feeAmountInr: val,
                                      requiresPayment: val > 0,
                                    });
                                  }
                                }}
                                className="w-24 px-2 py-1 border border-[#DDD5EA] rounded text-xs"
                              />
                            </label>
                            <button
                              type="button"
                              onClick={() =>
                                updateCategorySetting(cat.id, { isActive: !cat.isActive })
                              }
                              className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${
                                cat.isActive
                                  ? "bg-emerald-700 text-white"
                                  : "bg-slate-200 text-slate-700"
                              }`}
                            >
                              {cat.isActive ? "Open" : "Closed"}
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 9: USER MANAGEMENT (SUPERADMIN)                             */}
          {/* =============================================================== */}
          {activeTab === "USERS" && isSuperadmin && (
            <div className="space-y-6">
              <form
                onSubmit={handleCreateStaff}
                className="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-4"
              >
                <h3 className="text-sm font-bold text-[#17151C]">
                  Create Staff Account (Admin or RGST Reception Operator)
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                  <input
                    required
                    type="text"
                    placeholder="Full Name"
                    value={newStaff.fullName}
                    onChange={(e) => setNewStaff({ ...newStaff, fullName: e.target.value })}
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                  />
                  <input
                    required
                    type="email"
                    placeholder="Email Address"
                    value={newStaff.email}
                    onChange={(e) => setNewStaff({ ...newStaff, email: e.target.value })}
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                  />
                  <input
                    type="text"
                    placeholder="Phone (optional)"
                    value={newStaff.phone}
                    onChange={(e) => setNewStaff({ ...newStaff, phone: e.target.value })}
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                  />
                  <input
                    required
                    type="password"
                    minLength={8}
                    placeholder="Initial Password (min 8)"
                    value={newStaff.password}
                    onChange={(e) => setNewStaff({ ...newStaff, password: e.target.value })}
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                  />
                  <select
                    value={newStaff.roleCode}
                    onChange={(e) =>
                      setNewStaff({
                        ...newStaff,
                        roleCode: e.target.value as "ADMIN" | "RGST",
                      })
                    }
                    className="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                  >
                    <option value="ADMIN">ADMIN (Operations)</option>
                    <option value="RGST">RGST (QR Desk Only)</option>
                  </select>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  {newStaff.roleCode === "ADMIN" ? (
                    <label className="inline-flex items-center gap-2 text-xs font-medium text-[#3C334E]">
                      <input
                        type="checkbox"
                        checked={newStaff.grantCms}
                        onChange={(e) => setNewStaff({ ...newStaff, grantCms: e.target.checked })}
                      />
                      Grant custom CMS permission (cms.manage) to this Admin
                    </label>
                  ) : (
                    <span className="text-xs text-[#645C75]">
                      RGST accounts are strictly restricted to reception QR check-in.
                    </span>
                  )}
                  <button
                    type="submit"
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white"
                  >
                    <Plus className="w-4 h-4" /> Create Staff Account
                  </button>
                </div>
              </form>

              <div className="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm text-left">
                    <caption className="sr-only">Staff and user accounts</caption>
                    <thead className="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]">
                      <tr>
                        <th scope="col" className="px-4 py-3">User</th>
                        <th scope="col" className="px-4 py-3">Roles</th>
                        <th scope="col" className="px-4 py-3">Custom Permissions</th>
                        <th scope="col" className="px-4 py-3">Status</th>
                        <th scope="col" className="px-4 py-3 text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0EBF8]">
                      {usersLoading ? (
                        <tr>
                          <td colSpan={5} className="px-4 py-6 text-center text-[#6E6580]">
                            Loading users…
                          </td>
                        </tr>
                      ) : (
                        staffUsers.map((u) => (
                          <tr key={u.id}>
                            <td className="px-4 py-3">
                              <p className="font-semibold text-[#17151C]">{u.fullName}</p>
                              <p className="text-xs text-[#645C75]">{u.email}</p>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex flex-wrap gap-1">
                                {u.roles.map((r) => (
                                  <span
                                    key={r}
                                    className="px-2 py-0.5 rounded text-xs font-semibold bg-[#F1EBF9] text-[#4C3273]"
                                  >
                                    {r}
                                  </span>
                                ))}
                              </div>
                            </td>
                            <td className="px-4 py-3 text-xs text-[#5C536E]">
                              {u.customPermissions.length > 0
                                ? u.customPermissions.join(", ")
                                : "Default role set"}
                            </td>
                            <td className="px-4 py-3">
                              <span
                                className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusBadgeClass(
                                  u.isActive ? "ACTIVE" : "REVOKED"
                                )}`}
                              >
                                {u.isActive ? "Active" : "Disabled"}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-right space-x-2">
                              {u.roles.includes("ADMIN") && (
                                <button
                                  type="button"
                                  onClick={() => {
                                    setEditingPermsUser(u);
                                    setPermsDraft(u.customPermissions);
                                  }}
                                  className="px-2.5 py-1 rounded text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]"
                                >
                                  Permissions
                                </button>
                              )}
                              {u.id !== currentUser.id && (
                                <button
                                  type="button"
                                  onClick={() => toggleUserActive(u)}
                                  className="px-2.5 py-1 rounded text-xs font-semibold border border-[#DDD5EA] hover:bg-slate-50"
                                >
                                  {u.isActive ? "Deactivate" : "Activate"}
                                </button>
                              )}
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* =============================================================== */}
          {/* TAB 10: AUDIT LOGS (SUPERADMIN)                                 */}
          {/* =============================================================== */}
          {activeTab === "AUDIT_LOGS" && (
            <div className="space-y-4">
              <div className="bg-white border border-[#E5DFEF] rounded-xl p-4 flex flex-wrap items-center gap-3">
                <input
                  type="search"
                  value={auditSearch}
                  onChange={(e) => setAuditSearch(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") fetchAuditLogs(1);
                  }}
                  placeholder="Filter by action, resource type, or actor email…"
                  aria-label="Filter audit logs"
                  className="flex-1 min-w-[220px] px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                />
                <button
                  type="button"
                  onClick={() => fetchAuditLogs(1)}
                  className="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white"
                >
                  Filter Logs
                </button>
              </div>

              <div className="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-xs text-left">
                    <caption className="sr-only">Immutable security and admin audit trail</caption>
                    <thead className="bg-[#F8F6FC] border-b border-[#E5DFEF] uppercase text-[#5C536E]">
                      <tr>
                        <th scope="col" className="px-4 py-3">Timestamp (IST)</th>
                        <th scope="col" className="px-4 py-3">Actor</th>
                        <th scope="col" className="px-4 py-3">Action</th>
                        <th scope="col" className="px-4 py-3">Resource</th>
                        <th scope="col" className="px-4 py-3">Metadata</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0EBF8]">
                      {auditLoading ? (
                        <tr>
                          <td colSpan={5} className="px-4 py-8 text-center text-[#6E6580]">
                            Loading audit trail…
                          </td>
                        </tr>
                      ) : auditLogs.length === 0 ? (
                        <tr>
                          <td colSpan={5} className="px-4 py-8 text-center text-[#6E6580]">
                            No audit entries found.
                          </td>
                        </tr>
                      ) : (
                        auditLogs.map((log) => (
                          <tr key={log.id}>
                            <td className="px-4 py-3 whitespace-nowrap text-[#544A68]">
                              {formatDateShort(log.createdAt)}
                            </td>
                            <td className="px-4 py-3">
                              <p className="font-semibold text-[#17151C]">
                                {log.actorName || "System"}
                              </p>
                              <p className="text-[11px] text-[#645C75]">{log.actorEmail || "—"}</p>
                            </td>
                            <td className="px-4 py-3 font-mono font-semibold text-[#4C3273]">
                              {log.action}
                            </td>
                            <td className="px-4 py-3">
                              <span className="font-medium">{log.resourceType}</span>
                              {log.resourceId && (
                                <p className="font-mono text-[10px] text-[#6E6580]">
                                  {log.resourceId.slice(0, 12)}…
                                </p>
                              )}
                            </td>
                            <td className="px-4 py-3 max-w-xs truncate font-mono text-[11px] text-[#5C536E]">
                              {log.metadata ? JSON.stringify(log.metadata) : "—"}
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>

                <div className="px-4 py-3 border-t border-[#ECE7F5] flex items-center justify-between text-xs text-[#5E5470]">
                  <span>
                    Page {auditPagination.page} of {auditPagination.totalPages} ({auditPagination.total} entries)
                  </span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      disabled={auditPagination.page <= 1}
                      onClick={() => fetchAuditLogs(auditPagination.page - 1)}
                      className="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40"
                    >
                      Prev
                    </button>
                    <button
                      type="button"
                      disabled={auditPagination.page >= auditPagination.totalPages}
                      onClick={() => fetchAuditLogs(auditPagination.page + 1)}
                      className="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40"
                    >
                      Next
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </main>
      </div>

      {/* ================================================================= */}
      {/* REGISTRATION DETAIL MODAL                                         */}
      {/* ================================================================= */}
      {selectedRegId && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Registration review modal"
          className="fixed inset-0 z-50 bg-black/55 flex items-center justify-end"
        >
          <div className="bg-white w-full max-w-xl h-full overflow-y-auto shadow-2xl p-6 space-y-5">
            <div className="flex items-center justify-between border-b border-[#ECE7F5] pb-3">
              <h3 className="text-base font-bold text-[#17151C]">Registration Detail & Review</h3>
              <button
                type="button"
                onClick={() => {
                  setSelectedRegId(null);
                  setRegDetail(null);
                }}
                className="p-1.5 rounded-lg hover:bg-[#F3EEF9]"
                aria-label="Close modal"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {detailLoading || !regDetail ? (
              <p className="text-sm text-[#6E6580]">Loading full record…</p>
            ) : (
              (() => {
                const reg = regDetail.registration as Record<string, unknown>;
                const creds = (regDetail.credentials as Array<Record<string, unknown>>) || [];
                const accomms = (regDetail.accommodations as Array<Record<string, unknown>>) || [];
                return (
                  <div className="space-y-4 text-sm">
                    <div className="p-4 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] space-y-1">
                      <p className="font-mono text-xs font-bold text-[#563A80]">
                        {String(reg.registrationCode)}
                      </p>
                      <p className="text-base font-bold text-[#17151C]">{String(reg.fullName)}</p>
                      <p className="text-xs text-[#5C536E]">
                        {String(reg.email)} • {String(reg.phone)} • {String(reg.country)}
                      </p>
                      <p className="text-xs text-[#5C536E]">
                        Category: <strong>{String(reg.categoryCode)}</strong> • Payment:{" "}
                        <strong>{String(reg.paymentStatus)}</strong>
                      </p>
                    </div>

                    {accomms.length > 0 && (
                      <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1">
                        <p className="font-bold text-amber-900">Accommodation Requests</p>
                        {accomms.map((a) => (
                          <p key={String(a.id)} className="text-amber-900">
                            {Array.isArray(a.selectedTypes)
                              ? (a.selectedTypes as string[]).join(", ")
                              : ""}{" "}
                            {a.freeTextDetails ? `— ${String(a.freeTextDetails)}` : ""} (
                            {String(a.status)})
                          </p>
                        ))}
                      </div>
                    )}

                    <div className="space-y-2">
                      <label className="block text-xs font-semibold text-[#4A415C]">
                        Application Status
                      </label>
                      <select
                        value={detailStatus}
                        onChange={(e) => setDetailStatus(e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"
                      >
                        <option value="SUBMITTED">SUBMITTED</option>
                        <option value="UNDER_REVIEW">UNDER_REVIEW</option>
                        <option value="ADDITIONAL_INFO_REQUIRED">ADDITIONAL_INFO_REQUIRED</option>
                        <option value="APPROVED">APPROVED (Auto-issues QR Pass)</option>
                        <option value="CONFIRMED">CONFIRMED (Auto-issues QR Pass)</option>
                        <option value="WAITLISTED">WAITLISTED</option>
                        <option value="REJECTED">REJECTED</option>
                      </select>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-xs font-semibold text-[#4A415C]">
                        Reviewer Notes
                      </label>
                      <textarea
                        rows={3}
                        value={detailNotes}
                        onChange={(e) => setDetailNotes(e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"
                        placeholder="Add review notes or reason…"
                      />
                    </div>

                    <div className="flex flex-wrap gap-2">
                      <button
                        type="button"
                        disabled={savingDetail}
                        onClick={() => saveRegistrationReview()}
                        className="flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white disabled:opacity-50"
                      >
                        {savingDetail ? "Saving…" : "Save Review"}
                      </button>
                      {can("credentials.issue") && (
                        <button
                          type="button"
                          disabled={savingDetail}
                          onClick={() => saveRegistrationReview("REISSUE")}
                          className="px-3 py-2 rounded-lg text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]"
                        >
                          Reissue QR Pass
                        </button>
                      )}
                      {can("credentials.revoke") && (
                        <button
                          type="button"
                          disabled={savingDetail}
                          onClick={() => saveRegistrationReview("REVOKE")}
                          className="px-3 py-2 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900"
                        >
                          Revoke Pass
                        </button>
                      )}
                    </div>

                    {creds.length > 0 && (
                      <div className="pt-3 border-t border-[#ECE7F5] space-y-2">
                        <p className="text-xs font-bold text-[#4A415C]">QR Credentials History</p>
                        {creds.map((cr) => (
                          <div
                            key={String(cr.id)}
                            className="flex items-center justify-between text-xs p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]"
                          >
                            <span className="font-mono font-semibold">
                              {String(cr.credentialCode)}
                            </span>
                            <span
                              className={`px-2 py-0.5 rounded-full text-[11px] font-semibold border ${statusBadgeClass(
                                String(cr.status)
                              )}`}
                            >
                              {String(cr.status)}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })()
            )}
          </div>
        </div>
      )}

      {/* ================================================================= */}
      {/* ADMIN CUSTOM PERMISSIONS MODAL (SUPERADMIN)                       */}
      {/* ================================================================= */}
      {editingPermsUser && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Edit admin custom permissions"
          className="fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4"
        >
          <div className="bg-white rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold">
                Custom Permissions — {editingPermsUser.fullName}
              </h3>
              <button
                type="button"
                onClick={() => setEditingPermsUser(null)}
                aria-label="Close permissions dialog"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <p className="text-xs text-[#645C75]">
              Grant additional permissions (such as <code>cms.manage</code> or{" "}
              <code>audit_logs.read</code>) on top of default ADMIN permissions.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto p-2 border border-[#ECE7F5] rounded-lg">
              {availablePermissions.map((perm) => {
                const checked = permsDraft.includes(perm);
                return (
                  <label key={perm} className="flex items-center gap-2 text-xs font-mono">
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={(e) => {
                        if (e.target.checked) setPermsDraft([...permsDraft, perm]);
                        else setPermsDraft(permsDraft.filter((p) => p !== perm));
                      }}
                    />
                    {perm}
                  </label>
                );
              })}
            </div>
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setEditingPermsUser(null)}
                className="px-4 py-2 rounded-lg text-xs font-semibold border border-[#DDD5EA]"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={saveUserCustomPermissions}
                className="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white"
              >
                Save Permissions
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
