/**
 * Purple Festival Gujarat 2027 — Admin & Superadmin CMS Portal Client Controller
 * Exact visual and functional Vanilla JS port of src/components/AdminPortal.tsx
 */
(function () {
  "use strict";

  const currentUser = window.PFG_ADMIN_USER || {
    id: "",
    fullName: "Administrator",
    email: "",
    roles: ["ADMIN"],
    permissions: [],
    mfaEnabled: false,
  };

  const isSuperadmin = Array.isArray(currentUser.roles) && currentUser.roles.includes("SUPERADMIN");

  function can(perm) {
    if (isSuperadmin) return true;
    return Array.isArray(currentUser.permissions) && currentUser.permissions.includes(perm);
  }

  function esc(val) {
    if (val === null || val === undefined) return "";
    return String(val)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function safeHref(url) {
    if (!url || typeof url !== "string") return "";
    const trimmed = url.trim();
    if (/^https?:\/\//i.test(trimmed)) {
      return trimmed;
    }
    return "";
  }

  function statusBadgeClass(status) {
    switch (String(status || "").toUpperCase()) {
      case "APPROVED":
      case "CONFIRMED":
      case "ARRANGED":
      case "COMPLETED":
      case "PUBLISHED":
      case "ACTIVE":
      case "CAPTURED":
        return "bg-emerald-50 text-emerald-800 border-emerald-200";
      case "SUBMITTED":
      case "UNDER_REVIEW":
      case "REQUESTED":
      case "IN_PROGRESS":
      case "ASSIGNED":
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

  function formatDateShort(iso) {
    if (!iso) return "—";
    try {
      const normalized = String(iso).includes("T") ? String(iso) : String(iso).replace(" ", "T") + "+05:30";
      return new Intl.DateTimeFormat("en-IN", {
        timeZone: "Asia/Kolkata",
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(normalized));
    } catch (e) {
      return String(iso);
    }
  }

  async function apiFetch(path, options) {
    const opts = Object.assign({ credentials: "same-origin" }, options || {});
    opts.headers = Object.assign({}, opts.headers || {});
    if (window.PFG_CSRF_TOKEN) {
      opts.headers["X-CSRF-Token"] = window.PFG_CSRF_TOKEN;
    }
    let res = await fetch(path, opts);
    const ct = res.headers.get("content-type") || "";
    if (res.status === 404 && !ct.includes("application/json")) {
      const qIdx = path.indexOf("?");
      const basePath = qIdx >= 0 ? path.slice(0, qIdx) : path;
      const qs = qIdx >= 0 ? path.slice(qIdx) : "";
      if (!basePath.endsWith(".php")) {
        res = await fetch(basePath + ".php" + qs, opts);
      }
    }
    return res;
  }

  // SVG Icons
  const ICONS = {
    OVERVIEW: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
    REGISTRATIONS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    VOLUNTEERS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 14h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"/><path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 15 6 6"/><path d="M19.5 8.5c.7-.7 1.5-1.6 1.5-2.7A2.73 2.73 0 0 0 16 4a2.78 2.78 0 0 0-5 1.8c0 1.2.8 2 1.5 2.8L16 12Z"/></svg>',
    SLI: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>',
    ACCOMMODATIONS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="16" cy="4" r="1"/><path d="m18 19 1-7-6 1"/><path d="m5 8 3-3 5.5 3-2.36 3.5"/><path d="M4.24 14.5a5 5 0 0 0 6.88 6"/><path d="M13.76 17.5a5 5 0 0 0-6.88-6"/></svg>',
    ATTENDANCE: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/></svg>',
    COUPONS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
    CMS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
    USERS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>',
    SECURITY: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
    AUDIT_LOGS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/></svg>',
  };

  const NAV_ITEMS = [
    { id: "OVERVIEW", label: "Overview & Analytics", visible: true },
    { id: "REGISTRATIONS", label: "Registrations", visible: can("registrations.read.all") },
    { id: "VOLUNTEERS", label: "Volunteers & Duty", visible: can("volunteers.review") },
    { id: "SLI", label: "SLI Professionals", visible: can("sli.review") },
    { id: "ACCOMMODATIONS", label: "Accommodations", visible: can("accommodations.read") },
    { id: "ATTENDANCE", label: "Attendance / Check-In", visible: can("attendance.read.full") },
    { id: "CMS", label: "Programme & CMS", visible: can("cms.manage") },
    { id: "COUPONS", label: "Coupons", visible: can("coupons.manage") },
    { id: "USERS", label: "Staff & Roles", visible: isSuperadmin },
    { id: "SECURITY", label: "Security & MFA", visible: true },
    { id: "AUDIT_LOGS", label: "Audit Logs", visible: can("audit_logs.read") },
  ].filter(function (i) {
    return i.visible;
  });

  // Portal State
  const mustEnrollMfa = Boolean(currentUser.mfaEnrollmentRequired);
  const state = {
    activeTab: mustEnrollMfa ? "SECURITY" : "OVERVIEW",
    mfaEnrollmentRequired: mustEnrollMfa,
    mfaRecoveryCodes: null,
    chartViewMode: "chart",
    overview: null,
    overviewLoading: false,

    // Registrations
    regSearch: "",
    regCategory: "",
    regStatus: "",
    regCheckin: "",
    regPage: 1,
    regItems: [],
    regPagination: { page: 1, pageSize: 25, total: 0, totalPages: 1 },
    regLoading: false,

    // Detail modal
    selectedRegId: null,
    regDetail: null,
    detailLoading: false,
    detailStatus: "",
    detailNotes: "",
    savingDetail: false,

    // Volunteers
    volItems: [],
    volLoading: false,
    activeVolAssign: null,
    volForm: {
      assignedDepartment: "",
      assignedResponsibility: "",
      assignedSupervisor: "",
      assignedVenue: "",
      assignedShift: "",
      reportingTime: "",
      assignmentNotes: "",
    },

    // SLI
    sliItems: [],
    sliLoading: false,
    activeSliAssign: null,
    sliForm: {
      assignedVenue: "",
      dutyTimes: "",
      sessionTitle: "",
      sessionDay: "Day 1 (21 Jan 2027)",
      assignmentNotes: "",
    },

    // Accommodations
    accommItems: [],
    accommLoading: false,
    accommStatusFilter: "",
    accommNotesDraft: {},

    // Coupons
    coupons: [],
    couponsLoading: false,
    newCoupon: {
      code: "",
      description: "",
      discountType: "PERCENTAGE",
      discountValue: 100,
      maxUses: "",
      eligibleCategories: ["DELEGATE"],
    },

    // CMS
    cmsData: null,
    cmsLoading: false,
    cmsSubTab: "sections",
    sectionDrafts: {},

    // Users (Superadmin)
    staffUsers: [],
    availablePermissions: [],
    usersLoading: false,
    editingPermsUser: null,
    permsDraft: [],

    // Security & MFA
    mfaEnabled: Boolean(currentUser.mfaEnabled),
    mfaSetupData: null,
    mfaLoading: false,

    // Audit Logs
    auditLogs: [],
    auditPagination: { page: 1, pageSize: 30, total: 0, totalPages: 1 },
    auditSearch: "",
    auditLoading: false,
  };

  const navListEl = document.getElementById("admin-nav-list");
  const mainEl = document.getElementById("admin-main-content");
  const tabTitleEl = document.getElementById("admin-active-tab-title");
  const toastRegionEl = document.getElementById("admin-toast-region");
  const modalRootEl = document.getElementById("admin-modal-root");
  const sidebarEl = document.getElementById("admin-sidebar");
  const backdropEl = document.getElementById("admin-sidebar-backdrop");

  function notify(type, message) {
    if (!toastRegionEl) return;
    const isSuccess = type === "success";
    toastRegionEl.innerHTML =
      '<div role="status" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-xs ' +
      (isSuccess
        ? "bg-emerald-50 border-emerald-200 text-emerald-900"
        : "bg-rose-50 border-rose-200 text-rose-900") +
      '">' +
      '<div class="flex items-center gap-2">' +
      '<span>' + esc(message) + "</span>" +
      "</div>" +
      '<button type="button" id="admin-toast-dismiss" class="p-1 rounded hover:bg-black/5" aria-label="Dismiss notification">&times;</button>' +
      "</div>";
    const dismissBtn = document.getElementById("admin-toast-dismiss");
    if (dismissBtn) {
      dismissBtn.addEventListener("click", function () {
        toastRegionEl.innerHTML = "";
      });
    }
    setTimeout(function () {
      if (toastRegionEl && toastRegionEl.textContent.includes(message)) {
        toastRegionEl.innerHTML = "";
      }
    }, 5500);
  }

  function closeMobileSidebar() {
    if (sidebarEl) sidebarEl.classList.add("-translate-x-full");
    if (backdropEl) backdropEl.classList.add("hidden");
  }

  function openMobileSidebar() {
    if (sidebarEl) sidebarEl.classList.remove("-translate-x-full");
    if (backdropEl) backdropEl.classList.remove("hidden");
  }

  function renderNav() {
    if (!navListEl) return;
    navListEl.innerHTML = NAV_ITEMS.map(function (item) {
      const active = state.activeTab === item.id;
      return (
        '<button type="button" data-nav-tab="' +
        esc(item.id) +
        '" ' +
        (active ? 'aria-current="page" ' : "") +
        'class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors text-left focus-visible:outline-2 focus-visible:outline-[#B79CE0] ' +
        (active
          ? "bg-[#7E61A8]/30 text-white border-l-2 border-[#B79CE0]"
          : "text-[#C4BCD4] hover:bg-white/5 hover:text-white") +
        '">' +
        '<span class="' + (active ? "text-[#CBB5EC]" : "text-[#8E83A6]") + '">' +
        (ICONS[item.id] || ICONS.OVERVIEW) +
        "</span>" +
        "<span>" + esc(item.label) + "</span>" +
        "</button>"
      );
    }).join("");

    navListEl.querySelectorAll("[data-nav-tab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const tabId = btn.getAttribute("data-nav-tab");
        if (state.mfaEnrollmentRequired && tabId !== "SECURITY") {
          notify("error", "Mandatory MFA enrollment must be completed before accessing administrative tabs.");
          return;
        }
        switchTab(tabId);
        closeMobileSidebar();
      });
    });

    const currentItem = NAV_ITEMS.find(function (i) {
      return i.id === state.activeTab;
    });
    if (tabTitleEl && currentItem) {
      tabTitleEl.textContent = currentItem.label;
    }
  }

  function switchTab(tabId) {
    state.activeTab = tabId;
    renderNav();
    renderMain();
    loadTabData(tabId);
  }

  function loadTabData(tabId) {
    if (tabId === "OVERVIEW" || tabId === "ATTENDANCE") fetchOverview();
    else if (tabId === "REGISTRATIONS") fetchRegistrations();
    else if (tabId === "VOLUNTEERS") fetchVolunteers();
    else if (tabId === "SLI") fetchSli();
    else if (tabId === "ACCOMMODATIONS") fetchAccommodations();
    else if (tabId === "COUPONS") fetchCoupons();
    else if (tabId === "CMS") fetchCms();
    else if (tabId === "USERS") fetchUsers();
    else if (tabId === "AUDIT_LOGS") fetchAuditLogs(1);
  }

  // =========================================================================
  // API FETCHERS
  // =========================================================================

  async function fetchOverview() {
    state.overviewLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/overview");
      if (res.ok) {
        state.overview = await res.json();
      }
    } catch (e) {
      notify("error", "Could not load overview metrics.");
    } finally {
      state.overviewLoading = false;
      renderMain();
    }
  }

  async function fetchRegistrations(opts) {
    state.regLoading = true;
    renderMain();
    try {
      const p = (opts && opts.page) || state.regPage || 1;
      const cat = opts && opts.category !== undefined ? opts.category : state.regCategory;
      const st = opts && opts.status !== undefined ? opts.status : state.regStatus;
      const ck = opts && opts.checkinStatus !== undefined ? opts.checkinStatus : state.regCheckin;
      const sr = opts && opts.search !== undefined ? opts.search : state.regSearch;

      const params = new URLSearchParams();
      params.set("page", String(p));
      params.set("pageSize", "25");
      if (sr) params.set("search", sr);
      if (cat) params.set("category", cat);
      if (st) params.set("status", st);
      if (ck) params.set("checkinStatus", ck);

      const res = await apiFetch("/api/admin/registrations?" + params.toString());
      if (res.ok) {
        const data = await res.json();
        state.regItems = data.items || [];
        state.regPagination = data.pagination || { page: 1, pageSize: 25, total: 0, totalPages: 1 };
      }
    } finally {
      state.regLoading = false;
      renderMain();
    }
  }

  async function fetchVolunteers() {
    state.volLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/registrations?category=VOLUNTEER&pageSize=100");
      if (res.ok) {
        const data = await res.json();
        state.volItems = data.items || [];
      }
    } finally {
      state.volLoading = false;
      renderMain();
    }
  }

  async function fetchSli() {
    state.sliLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/registrations?category=SLI&pageSize=100");
      if (res.ok) {
        const data = await res.json();
        state.sliItems = data.items || [];
      }
    } finally {
      state.sliLoading = false;
      renderMain();
    }
  }

  async function fetchAccommodations() {
    state.accommLoading = true;
    renderMain();
    try {
      const qs = state.accommStatusFilter ? "?status=" + encodeURIComponent(state.accommStatusFilter) : "";
      const res = await apiFetch("/api/admin/accommodations" + qs);
      if (res.ok) {
        const data = await res.json();
        state.accommItems = data.items || [];
        state.accommItems.forEach(function (it) {
          state.accommNotesDraft[it.id] = it.coordinatorNotes || "";
        });
      }
    } finally {
      state.accommLoading = false;
      renderMain();
    }
  }

  async function fetchCoupons() {
    state.couponsLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/coupons");
      if (res.ok) {
        const data = await res.json();
        state.coupons = data.items || [];
      }
    } finally {
      state.couponsLoading = false;
      renderMain();
    }
  }

  async function fetchCms() {
    state.cmsLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/cms");
      if (res.ok) {
        const data = await res.json();
        state.cmsData = data;
        const drafts = {};
        (data.sections || []).forEach(function (s) {
          drafts[s.id] = {
            title: s.title || "",
            subtitle: s.subtitle || "",
            body: s.body || "",
            status: s.status || "PUBLISHED",
          };
        });
        state.sectionDrafts = drafts;
      }
    } finally {
      state.cmsLoading = false;
      renderMain();
    }
  }

  async function fetchUsers() {
    if (!isSuperadmin) return;
    state.usersLoading = true;
    renderMain();
    try {
      const res = await apiFetch("/api/admin/users");
      if (res.ok) {
        const data = await res.json();
        state.staffUsers = data.items || [];
        state.availablePermissions = data.availablePermissions || [];
      }
    } finally {
      state.usersLoading = false;
      renderMain();
    }
  }

  async function fetchAuditLogs(pageNum) {
    state.auditLoading = true;
    renderMain();
    try {
      const p = pageNum || 1;
      const params = new URLSearchParams({ page: String(p), pageSize: "30" });
      if (state.auditSearch.trim()) {
        params.set("search", state.auditSearch.trim());
      }
      const res = await apiFetch("/api/admin/audit-logs?" + params.toString());
      if (res.ok) {
        const data = await res.json();
        state.auditLogs = data.items || [];
        state.auditPagination = data.pagination || { page: 1, pageSize: 30, total: 0, totalPages: 1 };
      }
    } finally {
      state.auditLoading = false;
      renderMain();
    }
  }

  function buildExportUrl(format, includeAccommodations) {
    const params = new URLSearchParams();
    params.set("export", format);
    params.set("format", format);
    if (state.regSearch) params.set("search", state.regSearch);
    if (state.regCategory) params.set("category", state.regCategory);
    if (state.regStatus) params.set("status", state.regStatus);
    if (state.regCheckin) params.set("checkinStatus", state.regCheckin);
    if (includeAccommodations) params.set("includeAccommodations", "true");
    return "/api/admin/registrations.php?" + params.toString();
  }

  function jumpToFilteredRegistrations(filters) {
    state.regCategory = (filters && filters.category) || "";
    state.regStatus = (filters && filters.status) || "";
    state.regCheckin = (filters && filters.checkinStatus) || "";
    state.regSearch = "";
    state.regPage = 1;
    state.activeTab = "REGISTRATIONS";
    renderNav();
    fetchRegistrations({
      page: 1,
      category: state.regCategory,
      status: state.regStatus,
      checkinStatus: state.regCheckin,
      search: "",
    });
  }

  // =========================================================================
  // MODAL RENDERERS (Registration Detail & Permissions)
  // =========================================================================

  async function openRegistrationDetail(id) {
    state.selectedRegId = id;
    state.detailLoading = true;
    state.regDetail = null;
    renderModals();
    try {
      const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(id));
      if (res.ok) {
        const data = await res.json();
        state.regDetail = data;
        state.detailStatus = (data.registration && data.registration.status) || "SUBMITTED";
        state.detailNotes = (data.registration && data.registration.reviewNotes) || "";
      }
    } finally {
      state.detailLoading = false;
      renderModals();
    }
  }

  async function saveRegistrationReview(credentialAction) {
    if (!state.selectedRegId) return;
    state.savingDetail = true;
    renderModals();
    try {
      const statusEl = document.getElementById("modal-reg-status");
      const notesEl = document.getElementById("modal-reg-notes");
      if (statusEl) state.detailStatus = statusEl.value;
      if (notesEl) state.detailNotes = notesEl.value;

      const payload = {
        id: state.selectedRegId,
        status: state.detailStatus,
        reviewNotes: state.detailNotes,
      };
      if (credentialAction) {
        payload.credentialAction = credentialAction;
        if (credentialAction === "REVOKE") {
          payload.revocationReason = state.detailNotes || "Revoked by administrator";
        }
      }
      const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(state.selectedRegId), {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(function () {
        return {};
      });
      if (!res.ok) {
        notify("error", data.error || "Update failed.");
        return;
      }
      notify(
        "success",
        data.credentialResult && data.credentialResult.credentialCode
          ? "Saved! QR Credential " + data.credentialResult.credentialCode + " issued."
          : "Registration updated successfully."
      );
      await openRegistrationDetail(state.selectedRegId);
      fetchRegistrations();
      fetchOverview();
    } finally {
      state.savingDetail = false;
      renderModals();
    }
  }

  function renderModals() {
    if (!modalRootEl) return;
    let html = "";

    // 1. Registration Detail Modal
    if (state.selectedRegId) {
      const reg = state.regDetail && state.regDetail.registration;
      const creds = (state.regDetail && state.regDetail.credentials) || [];
      const accomms = (state.regDetail && state.regDetail.accommodations) || [];
      const activeCred = creds.find(function (c) {
        return c.status === "ACTIVE";
      });

      html +=
        '<div role="dialog" aria-modal="true" aria-label="Registration review modal" class="fixed inset-0 z-50 bg-black/55 flex items-center justify-end">' +
        '<div class="bg-white w-full max-w-xl h-full overflow-y-auto shadow-2xl p-6 space-y-5">' +
        '<div class="flex items-center justify-between border-b border-[#ECE7F5] pb-3">' +
        '<h3 class="text-base font-bold text-[#17151C]">Registration Detail &amp; Review</h3>' +
        '<button type="button" id="modal-reg-close" class="p-1.5 rounded-lg hover:bg-[#F3EEF9]" aria-label="Close modal">&times;</button>' +
        "</div>";

      if (state.detailLoading || !reg) {
        html += '<p class="text-sm text-[#6E6580]">Loading full record…</p>';
      } else {
        html +=
          '<div class="space-y-4 text-sm">' +
          '<div class="p-4 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] space-y-1">' +
          '<p class="font-mono text-xs font-bold text-[#563A80]">' + esc(reg.registrationCode) + "</p>" +
          '<p class="text-base font-bold text-[#17151C]">' + esc(reg.fullName) + "</p>" +
          '<p class="text-xs text-[#5C536E]">' + esc(reg.email) + " • " + esc(reg.phone) + " • " + esc(reg.country) + "</p>" +
          '<p class="text-xs text-[#5C536E]">Category: <strong>' + esc(reg.categoryCode) + "</strong> • Payment: <strong>" + esc(reg.paymentStatus) + "</strong></p>" +
          "</div>";

        if (accomms.length > 0) {
          html +=
            '<div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1">' +
            '<p class="font-bold text-amber-900">Accommodation Requests</p>' +
            accomms
              .map(function (a) {
                const types = Array.isArray(a.selectedTypes) ? a.selectedTypes.join(", ") : "";
                return (
                  '<p class="text-amber-900">' +
                  esc(types) +
                  (a.freeTextDetails ? " — " + esc(a.freeTextDetails) : "") +
                  " (" +
                  esc(a.status) +
                  ")</p>"
                );
              })
              .join("") +
            "</div>";
        }

        html +=
          '<div class="space-y-2">' +
          '<label for="modal-reg-status" class="block text-xs font-semibold text-[#4A415C]">Application Status</label>' +
          '<select id="modal-reg-status" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
          ["SUBMITTED", "UNDER_REVIEW", "ADDITIONAL_INFO_REQUIRED", "APPROVED", "CONFIRMED", "WAITLISTED", "REJECTED", "CANCELLED"]
            .map(function (st) {
              return (
                '<option value="' +
                esc(st) +
                '" ' +
                (state.detailStatus === st ? "selected" : "") +
                ">" +
                esc(st) +
                "</option>"
              );
            })
            .join("") +
          "</select>" +
          "</div>" +
          '<div class="space-y-2">' +
          '<label for="modal-reg-notes" class="block text-xs font-semibold text-[#4A415C]">Reviewer Notes</label>' +
          '<textarea id="modal-reg-notes" rows="3" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" placeholder="Add review notes or reason…">' +
          esc(state.detailNotes) +
          "</textarea>" +
          "</div>" +
          '<div class="flex flex-wrap gap-2">' +
          '<button type="button" id="modal-reg-save" ' +
          (state.savingDetail ? "disabled " : "") +
          'class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white disabled:opacity-50">' +
          (state.savingDetail ? "Saving…" : "Save Review") +
          "</button>" +
          (can("credentials.revoke") && activeCred
            ? '<button type="button" id="modal-reg-revoke" ' +
              (state.savingDetail ? "disabled " : "") +
              'class="px-3 py-2 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900">Revoke Legacy Pass</button>'
            : "") +
          "</div>";

        if (activeCred && activeCred.qrPayload) {
          html +=
            '<div class="p-4 rounded-xl bg-[#FAF8FD] border border-[#ECE7F5] flex flex-col items-center gap-2">' +
            '<p class="text-xs font-bold text-[#4A415C]">Active Opaque QR Credential (' + esc(activeCred.credentialCode) + ")</p>" +
            '<div data-qr-payload="' + esc(activeCred.qrPayload) + '" data-qr-size="160" class="bg-white p-2 rounded-lg border border-[#E5DFEF]"></div>' +
            '<a href="/dashboard/pass-print.php?registrationId=' + encodeURIComponent(reg.id) + '" target="_blank" class="text-xs font-semibold text-[#6B4E96] hover:underline">Open Printable Accessible Pass &rarr;</a>' +
            "</div>";
        }

        if (creds.length > 0) {
          html +=
            '<div class="pt-3 border-t border-[#ECE7F5] space-y-2">' +
            '<p class="text-xs font-bold text-[#4A415C]">QR Credentials History</p>' +
            creds
              .map(function (cr) {
                return (
                  '<div class="flex items-center justify-between text-xs p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]">' +
                  '<span class="font-mono font-semibold">' + esc(cr.credentialCode) + "</span>" +
                  '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold border ' +
                  statusBadgeClass(cr.status) +
                  '">' +
                  esc(cr.status) +
                  "</span>" +
                  "</div>"
                );
              })
              .join("") +
            "</div>";
        }

        html += "</div>";
      }

      html += "</div></div>";
    }

    // 2. Superadmin Custom Permissions Modal
    if (state.editingPermsUser) {
      const u = state.editingPermsUser;
      html +=
        '<div role="dialog" aria-modal="true" aria-label="Edit admin custom permissions" class="fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4">' +
        '<div class="bg-white rounded-xl max-w-lg w-full p-6 space-y-4 shadow-2xl">' +
        '<div class="flex items-center justify-between">' +
        '<h3 class="text-base font-bold">Custom Permissions — ' + esc(u.fullName) + "</h3>" +
        '<button type="button" id="modal-perms-close" aria-label="Close permissions dialog">&times;</button>' +
        "</div>" +
        '<p class="text-xs text-[#645C75]">Grant additional permissions (such as <code>cms.manage</code> or <code>audit_logs.read</code>) on top of default ADMIN permissions.</p>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto p-2 border border-[#ECE7F5] rounded-lg">' +
        state.availablePermissions
          .map(function (perm) {
            const checked = state.permsDraft.includes(perm);
            return (
              '<label class="flex items-center gap-2 text-xs font-mono">' +
              '<input type="checkbox" data-perm-check="' +
              esc(perm) +
              '" ' +
              (checked ? "checked" : "") +
              " />" +
              esc(perm) +
              "</label>"
            );
          })
          .join("") +
        "</div>" +
        '<div class="flex justify-end gap-2">' +
        '<button type="button" id="modal-perms-cancel" class="px-4 py-2 rounded-lg text-xs font-semibold border border-[#DDD5EA]">Cancel</button>' +
        '<button type="button" id="modal-perms-save" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white">Save Permissions</button>' +
        "</div>" +
        "</div></div>";
    }

    modalRootEl.innerHTML = html;

    if (window.PFGQrCode && typeof window.PFGQrCode.renderAll === "function") {
      window.PFGQrCode.renderAll(modalRootEl);
    }

    // Bind modal listeners
    const closeRegBtn = document.getElementById("modal-reg-close");
    if (closeRegBtn) {
      closeRegBtn.addEventListener("click", function () {
        state.selectedRegId = null;
        state.regDetail = null;
        renderModals();
      });
    }
    const saveRegBtn = document.getElementById("modal-reg-save");
    if (saveRegBtn) {
      saveRegBtn.addEventListener("click", function () {
        saveRegistrationReview();
      });
    }
    const reissueBtn = document.getElementById("modal-reg-reissue");
    if (reissueBtn) {
      reissueBtn.addEventListener("click", function () {
        saveRegistrationReview("REISSUE");
      });
    }
    const revokeBtn = document.getElementById("modal-reg-revoke");
    if (revokeBtn) {
      revokeBtn.addEventListener("click", function () {
        saveRegistrationReview("REVOKE");
      });
    }

    const closePermsBtn = document.getElementById("modal-perms-close");
    const cancelPermsBtn = document.getElementById("modal-perms-cancel");
    [closePermsBtn, cancelPermsBtn].forEach(function (b) {
      if (b) {
        b.addEventListener("click", function () {
          state.editingPermsUser = null;
          renderModals();
        });
      }
    });

    modalRootEl.querySelectorAll("[data-perm-check]").forEach(function (cb) {
      cb.addEventListener("change", function () {
        const perm = cb.getAttribute("data-perm-check");
        if (cb.checked) {
          if (!state.permsDraft.includes(perm)) state.permsDraft.push(perm);
        } else {
          state.permsDraft = state.permsDraft.filter(function (p) {
            return p !== perm;
          });
        }
      });
    });

    const savePermsBtn = document.getElementById("modal-perms-save");
    if (savePermsBtn) {
      savePermsBtn.addEventListener("click", async function () {
        if (!state.editingPermsUser) return;
        const res = await apiFetch("/api/admin/users", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            userId: state.editingPermsUser.id,
            customPermissions: state.permsDraft,
          }),
        });
        if (!res.ok) {
          notify("error", "Failed to update permissions.");
          return;
        }
        notify("success", "Permissions updated for " + state.editingPermsUser.email + ".");
        state.editingPermsUser = null;
        renderModals();
        fetchUsers();
      });
    }
  }

  // =========================================================================
  // TAB CONTENT RENDERERS
  // =========================================================================

  function renderOverviewTab() {
    const ov = state.overview;
    const m = (ov && ov.metrics) || {};
    const cards = [
      { key: "uniquePeople", label: "Unique People", value: m.uniquePeople || 0, sub: "Distinct registered accounts", action: "ALL_REGS" },
      { key: "totalApplications", label: "Total Applications", value: m.totalApplications || 0, sub: "All active submissions", action: "ALL_REGS" },
      { key: "confirmedRegistrations", label: "Confirmed Registrations", value: m.confirmedRegistrations || 0, sub: "Confirmed & approved passes", action: "CONFIRMED_REGS" },
      { key: "pendingApprovals", label: "Pending Approvals", value: m.pendingApprovals || 0, sub: "Awaiting organizer review", action: "PENDING_REGS" },
      { key: "delegates", label: "Delegates", value: m.delegates || 0, sub: "3-Day conference track", action: "DELEGATE_REGS" },
      { key: "volunteerApplications", label: "Volunteer Applications", value: m.volunteerApplications || 0, sub: "Total volunteer applicants", action: "TAB_VOLUNTEERS" },
      { key: "approvedVolunteers", label: "Approved Volunteers", value: m.approvedVolunteers || 0, sub: "Cleared for duty", action: "APPROVED_VOLS" },
      { key: "sliApplications", label: "SLI Applications", value: m.sliApplications || 0, sub: "Sign language interpreters", action: "TAB_SLI" },
      { key: "approvedSli", label: "Approved SLI Pros", value: m.approvedSli || 0, sub: "Verified ISL interpreters", action: "APPROVED_SLI" },
      { key: "alliesSupporters", label: "Allies & Supporters", value: m.alliesSupporters || 0, sub: "Community ally track", action: "ALLY_REGS" },
      { key: "accommodations", label: "Accommodation Requests", value: m.participantsRequestingAccommodations || 0, sub: "ISL, Braille, mobility & sensory", action: "TAB_ACCOMMODATIONS" },
      { key: "totalCheckins", label: "Total Check-Ins", value: m.totalCheckins || 0, sub: (m.todayCheckins || 0) + " today (IST)", action: "TAB_ATTENDANCE" },
    ];

    const catDist = (ov && ov.categoryDistribution) || [];
    const attByDay = (ov && ov.attendanceByDay) || [];
    const volPipe = (ov && ov.volunteerPipeline) || { total: 0, approved: 0, submitted: 0, underReview: 0, waitlisted: 0, rejected: 0 };
    const sliPipe = (ov && ov.sliPipeline) || { total: 0, approved: 0, submitted: 0, underReview: 0, waitlisted: 0, rejected: 0 };
    const payRec = (ov && ov.paymentReconciliation) || null;

    let html = '<div class="space-y-6">';

    // 12 Clickable Metric Cards
    html +=
      '<section aria-label="Key festival metrics">' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">' +
      cards
        .map(function (c) {
          return (
            '<button type="button" data-overview-card="' +
            esc(c.action) +
            '" class="text-left bg-white border border-[#E5DFEF] hover:border-[#8D6FBC] hover:shadow-sm rounded-xl p-4 transition-all focus-visible:outline-2 focus-visible:outline-[#6B4E96] group">' +
            '<p class="text-xs font-semibold uppercase tracking-wide text-[#665B7A] group-hover:text-[#5B3E87]">' +
            esc(c.label) +
            "</p>" +
            '<p class="text-3xl font-bold text-[#17151C] mt-1.5 tabular-nums">' +
            (state.overviewLoading && !ov ? "…" : Number(c.value).toLocaleString("en-IN")) +
            "</p>" +
            '<p class="text-xs text-[#6E6580] mt-1">' +
            esc(c.sub) +
            "</p>" +
            "</button>"
          );
        })
        .join("") +
      "</div></section>";

    // Accessible Charts with Table Toggle
    html +=
      '<section class="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-5">' +
      '<div class="flex flex-wrap items-center justify-between gap-3">' +
      "<div>" +
      '<h3 class="text-base font-bold text-[#17151C]">Festival Analytics &amp; Pipelines</h3>' +
      '<p class="text-xs text-[#645C75]">Switch between visual bar charts and accessible tabular views at any time.</p>' +
      "</div>" +
      '<div class="inline-flex rounded-lg border border-[#DDD5EA] p-0.5 bg-[#F7F4FC]">' +
      '<button type="button" data-chart-mode="chart" aria-pressed="' +
      (state.chartViewMode === "chart" ? "true" : "false") +
      '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold ' +
      (state.chartViewMode === "chart" ? "bg-[#6B4E96] text-white" : "text-[#4D4263] hover:text-[#17151C]") +
      '">Bar Charts</button>' +
      '<button type="button" data-chart-mode="table" aria-pressed="' +
      (state.chartViewMode === "table" ? "true" : "false") +
      '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold ' +
      (state.chartViewMode === "table" ? "bg-[#6B4E96] text-white" : "text-[#4D4263] hover:text-[#17151C]") +
      '">Data Tables</button>' +
      "</div></div>";

    html += '<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">';

    // Chart 1: Registration by Category
    html += '<div class="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">';
    html += '<h4 class="text-sm font-bold text-[#1E1929] mb-3">Registration by Category</h4>';
    if (state.chartViewMode === "chart") {
      if (catDist.length === 0) {
        html += '<p class="text-xs text-[#6E6580]">No registrations recorded yet.</p>';
      } else {
        const maxCat = Math.max.apply(
          null,
          [1].concat(
            catDist.map(function (x) {
              return x.total || 0;
            })
          )
        );
        html +=
          '<div class="space-y-3">' +
          catDist
            .map(function (c) {
              const pct = Math.round(((c.total || 0) / maxCat) * 100);
              return (
                '<div class="space-y-1">' +
                '<div class="flex justify-between text-xs font-medium">' +
                "<span>" + esc(c.categoryCode) + "</span>" +
                '<span class="tabular-nums text-[#544A68]">' + esc(c.total) + " (" + esc(c.confirmed) + " confirmed)</span>" +
                "</div>" +
                '<div class="h-2.5 w-full bg-[#EAE3F5] rounded-full overflow-hidden">' +
                '<div class="h-full bg-[#6B4E96] rounded-full" style="width:' + pct + '%"></div>' +
                "</div></div>"
              );
            })
            .join("") +
          "</div>";
      }
    } else {
      html +=
        '<div class="overflow-x-auto"><table class="w-full text-xs text-left">' +
        '<caption class="sr-only">Registration counts by category</caption>' +
        '<thead><tr class="border-b border-[#E5DFEF] text-[#5F5573]"><th class="py-2 pr-2">Category</th><th class="py-2 px-2 text-right">Total</th><th class="py-2 px-2 text-right">Confirmed</th><th class="py-2 pl-2 text-right">Checked In</th></tr></thead>' +
        "<tbody>" +
        catDist
          .map(function (c) {
            return (
              '<tr class="border-b border-[#F2EEF9]">' +
              '<td class="py-2 pr-2 font-medium">' + esc(c.categoryCode) + "</td>" +
              '<td class="py-2 px-2 text-right tabular-nums">' + esc(c.total) + "</td>" +
              '<td class="py-2 px-2 text-right tabular-nums">' + esc(c.confirmed) + "</td>" +
              '<td class="py-2 pl-2 text-right tabular-nums">' + esc(c.checkedIn) + "</td>" +
              "</tr>"
            );
          })
          .join("") +
        "</tbody></table></div>";
    }
    html += "</div>";

    // Chart 2: Attendance by Day (IST)
    html += '<div class="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">';
    html += '<h4 class="text-sm font-bold text-[#1E1929] mb-3">Attendance by Day (IST)</h4>';
    if (state.chartViewMode === "chart") {
      if (attByDay.length === 0) {
        html += '<p class="text-xs text-[#6E6580]">No check-ins recorded yet.</p>';
      } else {
        const maxDay = Math.max.apply(
          null,
          [1].concat(
            attByDay.map(function (x) {
              return x.count || 0;
            })
          )
        );
        html +=
          '<div class="space-y-3">' +
          attByDay
            .map(function (d) {
              const pct = Math.round(((d.count || 0) / maxDay) * 100);
              return (
                '<div class="space-y-1">' +
                '<div class="flex justify-between text-xs font-medium"><span>' + esc(d.date) + '</span><span class="tabular-nums text-[#544A68]">' + esc(d.count) + "</span></div>" +
                '<div class="h-2.5 w-full bg-[#EAE3F5] rounded-full overflow-hidden"><div class="h-full bg-[#4E7B6B] rounded-full" style="width:' + pct + '%"></div></div>' +
                "</div>"
              );
            })
            .join("") +
          "</div>";
      }
    } else {
      html +=
        '<table class="w-full text-xs text-left">' +
        '<thead><tr class="border-b border-[#E5DFEF] text-[#5F5573]"><th class="py-2">Date (IST)</th><th class="py-2 text-right">Check-Ins</th></tr></thead>' +
        "<tbody>" +
        attByDay
          .map(function (d) {
            return '<tr class="border-b border-[#F2EEF9]"><td class="py-2 font-medium">' + esc(d.date) + '</td><td class="py-2 text-right tabular-nums">' + esc(d.count) + "</td></tr>";
          })
          .join("") +
        "</tbody></table>";
    }
    html += "</div>";

    // Chart 3: Volunteer & SLI Review Pipeline
    html += '<div class="border border-[#ECE7F5] rounded-xl p-4 bg-[#FCFBFE]">';
    html += '<h4 class="text-sm font-bold text-[#1E1929] mb-3">Volunteer &amp; SLI Review Pipeline</h4>';
    if (state.chartViewMode === "chart") {
      html +=
        '<div class="space-y-4">' +
        [
          { label: "Volunteers", p: volPipe },
          { label: "SLI Professionals", p: sliPipe },
        ]
          .map(function (row) {
            const p = row.p;
            const total = Math.max(1, p.total || 0);
            const appPct = ((p.approved || 0) / total) * 100;
            const pendPct = (((p.submitted || 0) + (p.underReview || 0)) / total) * 100;
            const waitPct = ((p.waitlisted || 0) / total) * 100;
            return (
              '<div class="space-y-1.5">' +
              '<div class="flex justify-between text-xs font-semibold"><span>' + esc(row.label) + '</span><span class="tabular-nums">' + esc(p.total || 0) + " total</span></div>" +
              '<div class="h-3 w-full bg-[#EAE3F5] rounded-full overflow-hidden flex">' +
              '<div class="bg-emerald-600 h-full" style="width:' + appPct + '%"></div>' +
              '<div class="bg-amber-500 h-full" style="width:' + pendPct + '%"></div>' +
              '<div class="bg-purple-500 h-full" style="width:' + waitPct + '%"></div>' +
              "</div>" +
              '<div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-[#5C536E]">' +
              "<span>Approved: " + esc(p.approved || 0) + "</span>" +
              "<span>Pending: " + esc((p.submitted || 0) + (p.underReview || 0)) + "</span>" +
              "<span>Waitlisted: " + esc(p.waitlisted || 0) + "</span>" +
              "<span>Rejected: " + esc(p.rejected || 0) + "</span>" +
              "</div></div>"
            );
          })
          .join("") +
        "</div>";
    } else {
      html +=
        '<table class="w-full text-xs text-left">' +
        '<thead><tr class="border-b border-[#E5DFEF] text-[#5F5573]"><th class="py-2">Track</th><th class="py-2 text-right">Total</th><th class="py-2 text-right">Approved</th><th class="py-2 text-right">Pending</th><th class="py-2 text-right">Waitlist</th></tr></thead>' +
        "<tbody>" +
        '<tr class="border-b border-[#F2EEF9]"><td class="py-2 font-medium">Volunteers</td><td class="py-2 text-right tabular-nums">' + esc(volPipe.total) + '</td><td class="py-2 text-right tabular-nums">' + esc(volPipe.approved) + '</td><td class="py-2 text-right tabular-nums">' + esc((volPipe.submitted || 0) + (volPipe.underReview || 0)) + '</td><td class="py-2 text-right tabular-nums">' + esc(volPipe.waitlisted) + "</td></tr>" +
        '<tr><td class="py-2 font-medium">SLI</td><td class="py-2 text-right tabular-nums">' + esc(sliPipe.total) + '</td><td class="py-2 text-right tabular-nums">' + esc(sliPipe.approved) + '</td><td class="py-2 text-right tabular-nums">' + esc((sliPipe.submitted || 0) + (sliPipe.underReview || 0)) + '</td><td class="py-2 text-right tabular-nums">' + esc(sliPipe.waitlisted) + "</td></tr>" +
        "</tbody></table>";
    }
    html += "</div></div></section>";

    if (can("payments.read") && payRec) {
      html +=
        '<section class="bg-white border border-[#E5DFEF] rounded-xl p-5">' +
        '<h3 class="text-sm font-bold text-[#17151C] mb-3">Payment Reconciliation (INR)</h3>' +
        '<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">' +
        '<div class="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]"><p class="text-xs text-[#645C75]">Captured Revenue</p><p class="text-xl font-bold text-emerald-800 mt-0.5 tabular-nums">&#8377;' + Number(payRec.capturedRevenueInr || 0).toLocaleString("en-IN") + '</p><p class="text-[11px] text-[#6E6580]">' + esc(payRec.capturedOrders || 0) + " orders</p></div>" +
        '<div class="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]"><p class="text-xs text-[#645C75]">Pending / Authorized</p><p class="text-xl font-bold text-amber-800 mt-0.5 tabular-nums">&#8377;' + Number(payRec.pendingAmountInr || 0).toLocaleString("en-IN") + '</p><p class="text-[11px] text-[#6E6580]">' + esc(payRec.pendingOrders || 0) + " orders</p></div>" +
        '<div class="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]"><p class="text-xs text-[#645C75]">Coupon Discounts</p><p class="text-xl font-bold text-[#5B3E87] mt-0.5 tabular-nums">&#8377;' + Number(payRec.totalDiscountInr || 0).toLocaleString("en-IN") + "</p></div>" +
        '<div class="p-3 rounded-lg bg-[#F8F6FC] border border-[#EAE3F5]"><p class="text-xs text-[#645C75]">Refunded</p><p class="text-xl font-bold text-rose-800 mt-0.5 tabular-nums">&#8377;' + Number(payRec.refundedAmountInr || 0).toLocaleString("en-IN") + '</p><p class="text-[11px] text-[#6E6580]">' + esc(payRec.refundedOrders || 0) + " orders</p></div>" +
        "</div></section>";
    }

    html += "</div>";
    return html;
  }

  function renderRegistrationsTab() {
    let html = '<div class="space-y-4">';
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
      '<div class="grid grid-cols-1 md:grid-cols-5 gap-3">' +
      '<div class="md:col-span-2 relative">' +
      '<input id="reg-search-input" type="search" value="' + esc(state.regSearch) + '" placeholder="Search name, email, phone, or PFG code…" aria-label="Search registrations" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6B4E96]" />' +
      "</div>" +
      '<select id="reg-category-select" aria-label="Filter by category" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="">All Categories</option>' +
      ["ATTENDEE", "DELEGATE", "VOLUNTEER", "SLI", "STUDENT", "ALLY", "INSTITUTIONAL"]
        .map(function (cat) {
          return '<option value="' + cat + '" ' + (state.regCategory === cat ? "selected" : "") + ">" + cat + "</option>";
        })
        .join("") +
      "</select>" +
      '<select id="reg-status-select" aria-label="Filter by status" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="">All Statuses</option>' +
      ["CONFIRMED", "APPROVED", "SUBMITTED", "UNDER_REVIEW", "ADDITIONAL_INFO_REQUIRED", "WAITLISTED", "REJECTED"]
        .map(function (st) {
          return '<option value="' + st + '" ' + (state.regStatus === st ? "selected" : "") + ">" + st + "</option>";
        })
        .join("") +
      "</select>" +
      '<select id="reg-checkin-select" aria-label="Filter by check-in status" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="">All Check-in States</option>' +
      '<option value="CHECKED_IN" ' + (state.regCheckin === "CHECKED_IN" ? "selected" : "") + ">Checked In</option>" +
      '<option value="NOT_CHECKED_IN" ' + (state.regCheckin === "NOT_CHECKED_IN" ? "selected" : "") + ">Not Checked In</option>" +
      "</select>" +
      "</div>" +
      '<div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#F0EBF8]">' +
      '<div class="flex items-center gap-2">' +
      '<button type="button" id="reg-apply-btn" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#583D80] text-white">Apply Filters</button>' +
      '<button type="button" id="reg-reset-btn" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#DDD5EA] text-[#4E4466] hover:bg-[#F5F1FA]">Reset</button>' +
      '<span class="text-xs text-[#645C75] ml-1">' + esc(state.regPagination.total) + " records</span>" +
      "</div>";

    if (can("registrations.export")) {
      html +=
        '<div class="flex flex-wrap items-center gap-2">' +
        '<a href="' + esc(buildExportUrl("csv", false)) + '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] bg-[#F7F3FC] hover:bg-[#ECE4F8] text-[#4C3273]">Export CSV</a>' +
        (can("accommodations.read")
          ? '<a href="' + esc(buildExportUrl("csv", true)) + '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900">Accommodation CSV</a>'
          : "") +
        "</div>";
    }

    html += "</div></div>";

    // Table
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden">' +
      '<div class="overflow-x-auto"><table class="w-full text-sm text-left">' +
      '<caption class="sr-only">Festival registrations table</caption>' +
      '<thead class="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]">' +
      '<tr><th class="px-4 py-3">Reg Code</th><th class="px-4 py-3">Participant</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">QR / Check-in</th><th class="px-4 py-3 text-right">Actions</th></tr>' +
      '</thead><tbody class="divide-y divide-[#F0EBF8]">';

    if (state.regLoading) {
      html += '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-[#6E6580]">Loading registrations…</td></tr>';
    } else if (state.regItems.length === 0) {
      html += '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-[#6E6580]">No registrations match the current filters.</td></tr>';
    } else {
      html += state.regItems
        .map(function (r) {
          const qrState = r.checkedIn
            ? '<span class="text-emerald-800 font-semibold">Checked In (' + esc(r.checkinCount) + ")</span>"
            : r.credentialStatus === "ACTIVE"
              ? '<span class="text-[#5B3E87]">Pass Ready</span>'
              : '<span class="text-[#7C738E]">No Pass</span>';

          return (
            '<tr class="hover:bg-[#FAF8FD]">' +
            '<td class="px-4 py-3 font-mono text-xs font-semibold text-[#4B3470]">' + esc(r.registrationCode) + "</td>" +
            '<td class="px-4 py-3"><p class="font-semibold text-[#17151C]">' + esc(r.fullName) + '</p><p class="text-xs text-[#645C75]">' + esc(r.email) + " • " + esc(r.phone) + "</p></td>" +
            '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-md text-xs font-semibold bg-[#F2ECF9] text-[#51377A]">' + esc(r.categoryCode) + "</span></td>" +
            '<td class="px-4 py-3"><span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(r.status) + '">' + esc(r.status) + "</span></td>" +
            '<td class="px-4 py-3 text-xs">' + qrState + "</td>" +
            '<td class="px-4 py-3 text-right"><button type="button" data-open-reg="' + esc(r.id) + '" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D8CCE9] text-[#4C3273] hover:bg-[#F1EBF9]">Review</button></td>' +
            "</tr>"
          );
        })
        .join("");
    }

    html +=
      "</tbody></table></div>" +
      '<div class="px-4 py-3 border-t border-[#ECE7F5] flex items-center justify-between text-xs text-[#5E5470]">' +
      "<span>Page " + esc(state.regPagination.page) + " of " + esc(state.regPagination.totalPages) + " (" + esc(state.regPagination.total) + " total)</span>" +
      '<div class="flex items-center gap-2">' +
      '<button type="button" id="reg-prev-btn" ' + (state.regPagination.page <= 1 ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">&larr; Prev</button>' +
      '<button type="button" id="reg-next-btn" ' + (state.regPagination.page >= state.regPagination.totalPages ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">Next &rarr;</button>' +
      "</div></div></div></div>";

    return html;
  }

  function renderVolunteersTab() {
    let html = '<div class="space-y-4">';
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 flex items-center justify-between">' +
      "<div>" +
      '<h3 class="text-sm font-bold text-[#17151C]">Volunteer Applications Queue</h3>' +
      '<p class="text-xs text-[#645C75]">Approve, waitlist, or reject applicants and assign festival departments, shifts, and venues.</p>' +
      "</div>" +
      '<span class="text-xs font-semibold px-3 py-1 rounded-full bg-[#F1EBF9] text-[#51377A]">' + esc(state.volItems.length) + " applicants</span>" +
      "</div>";

    if (state.volLoading) {
      html += '<div class="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">Loading volunteer queue…</div>';
    } else if (state.volItems.length === 0) {
      html += '<div class="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">No volunteer applications submitted yet.</div>';
    } else {
      html +=
        '<div class="space-y-3">' +
        state.volItems
          .map(function (v) {
            const vd = v.volunteerDetails || {};
            const isEditing = state.activeVolAssign === v.id;
            const prefRoles = Array.isArray(vd.preferredRoles) && vd.preferredRoles.length > 0
              ? '<p class="text-xs text-[#4C4263] mt-1"><strong>Preferred Roles:</strong> ' + esc(vd.preferredRoles.join(", ")) + "</p>"
              : "";
            const assignedBanner = vd.assignedDepartment
              ? '<p class="text-xs text-emerald-800 mt-1 font-medium">Assigned: ' +
                esc(vd.assignedDepartment) +
                " • " +
                esc(vd.assignedVenue || "Venue TBD") +
                " • Shift: " +
                esc(vd.assignedShift || "TBD") +
                " (Report: " +
                esc(vd.reportingTime || "TBD") +
                ")</p>"
              : "";

            let card =
              '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
              '<div class="flex flex-wrap items-start justify-between gap-3">' +
              "<div>" +
              '<div class="flex items-center gap-2 flex-wrap">' +
              '<span class="font-mono text-xs font-bold text-[#563A80]">' + esc(v.registrationCode) + "</span>" +
              '<h4 class="font-bold text-[#17151C]">' + esc(v.fullName) + "</h4>" +
              '<span class="px-2 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(v.status) + '">' + esc(v.status) + "</span>" +
              (vd.isMsuStudentOrAlumni
                ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200">MSU Baroda</span>'
                : "") +
              "</div>" +
              '<p class="text-xs text-[#645C75] mt-1">' + esc(v.email) + " • " + esc(v.phone) + " • " + esc(vd.institutionName || v.organizationName || "Independent") + "</p>" +
              prefRoles +
              assignedBanner +
              "</div>" +
              '<div class="flex flex-wrap items-center gap-2">' +
              '<button type="button" data-vol-status="APPROVED" data-vol-id="' + esc(v.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white">Approve &amp; Issue Pass</button>' +
              '<button type="button" data-vol-status="WAITLISTED" data-vol-id="' + esc(v.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-100 hover:bg-amber-200 text-amber-900">Waitlist</button>' +
              '<button type="button" data-vol-status="REJECTED" data-vol-id="' + esc(v.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900">Reject</button>' +
              '<button type="button" data-vol-toggle-assign="' + esc(v.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]">' + (isEditing ? "Close Duty Form" : "Assign Duty") + "</button>" +
              "</div></div>";

            if (isEditing) {
              card +=
                '<div class="pt-3 border-t border-[#ECE7F5] grid grid-cols-1 sm:grid-cols-3 gap-3">' +
                '<input id="vol-dept" type="text" placeholder="Department (e.g. Accessibility Desk)" value="' + esc(state.volForm.assignedDepartment) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="vol-sup" type="text" placeholder="Supervisor Name" value="' + esc(state.volForm.assignedSupervisor) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="vol-venue" type="text" placeholder="Venue (e.g. Sama Indoor Stadium)" value="' + esc(state.volForm.assignedVenue) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="vol-shift" type="text" placeholder="Shift (e.g. Morning 08:30–13:30)" value="' + esc(state.volForm.assignedShift) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="vol-rtime" type="text" placeholder="Reporting Time (e.g. 08:00 IST)" value="' + esc(state.volForm.reportingTime) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="vol-resp" type="text" placeholder="Responsibility" value="' + esc(state.volForm.assignedResponsibility) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<div class="sm:col-span-3 flex items-center gap-2">' +
                '<input id="vol-notes" type="text" placeholder="Additional assignment notes…" value="' + esc(state.volForm.assignmentNotes) + '" class="flex-1 px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<button type="button" data-vol-save-assign="' + esc(v.id) + '" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">Save Assignment</button>' +
                "</div></div>";
            }

            card += "</div>";
            return card;
          })
          .join("") +
        "</div>";
    }

    html += "</div>";
    return html;
  }

  function renderSliTab() {
    let html = '<div class="space-y-4">';
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 flex items-center justify-between">' +
      "<div>" +
      '<h3 class="text-sm font-bold text-[#17151C]">Sign Language Interpreter (SLI) Verification &amp; Assignment</h3>' +
      '<p class="text-xs text-[#645C75]">Review RCI CRR numbers, Gujarat availability, qualifications, and video demos before assigning sessions.</p>' +
      "</div>" +
      '<span class="text-xs font-semibold px-3 py-1 rounded-full bg-[#F1EBF9] text-[#51377A]">' + esc(state.sliItems.length) + " applicants</span>" +
      "</div>";

    if (state.sliLoading) {
      html += '<div class="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">Loading SLI applications…</div>';
    } else if (state.sliItems.length === 0) {
      html += '<div class="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">No SLI applications submitted yet.</div>';
    } else {
      html +=
        '<div class="space-y-3">' +
        state.sliItems
          .map(function (s) {
            const sd = s.sliDetails || {};
            const isEditing = state.activeSliAssign === s.id;

            let card =
              '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
              '<div class="flex flex-wrap items-start justify-between gap-3">' +
              '<div class="space-y-1">' +
              '<div class="flex items-center gap-2 flex-wrap">' +
              '<span class="font-mono text-xs font-bold text-[#563A80]">' + esc(s.registrationCode) + "</span>" +
              '<h4 class="font-bold text-[#17151C]">' + esc(s.fullName) + "</h4>" +
              '<span class="px-2 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(s.status) + '">' + esc(s.status) + "</span>" +
              '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F3EEF9] text-[#4C3273]">' + (sd.basedInGujarat ? "Based in Gujarat" : "Outstation") + "</span>" +
              (sd.crrNumber
                ? '<span class="px-2 py-0.5 rounded-full text-xs font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">CRR: ' + esc(sd.crrNumber) + "</span>"
                : "") +
              "</div>" +
              '<p class="text-xs text-[#645C75]">' + esc(s.email) + " • " + esc(s.phone) + " • Experience: " + esc(sd.yearsOfExperience || 0) + " yrs • " + esc(sd.qualifications || "Qualifications not listed") + "</p>" +
              (safeHref(sd.videoDemoUrl)
                ? '<a href="' + esc(safeHref(sd.videoDemoUrl)) + '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-semibold text-[#6B4E96] hover:underline">View ISL Video Demo &rarr;</a>'
                : "") +
              (sd.assignedVenue
                ? '<p class="text-xs text-emerald-800 font-medium">Assigned Venue: ' + esc(sd.assignedVenue) + " • Duty: " + esc(sd.dutyTimes || "TBD") + " (" + ((sd.assignedSessions || []).length) + " sessions)</p>"
                : "") +
              "</div>" +
              '<div class="flex flex-wrap items-center gap-2">' +
              '<button type="button" data-sli-status="APPROVED" data-sli-id="' + esc(s.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white">Approve &amp; Issue Pass</button>' +
              '<button type="button" data-sli-status="REJECTED" data-sli-id="' + esc(s.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900">Reject</button>' +
              '<button type="button" data-sli-toggle-assign="' + esc(s.id) + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]">' + (isEditing ? "Close Assignment" : "Assign Session / Venue") + "</button>" +
              "</div></div>";

            if (isEditing) {
              card +=
                '<div class="pt-3 border-t border-[#ECE7F5] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">' +
                '<input id="sli-venue" type="text" placeholder="Assigned Venue" value="' + esc(state.sliForm.assignedVenue) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="sli-duty" type="text" placeholder="Duty Times (e.g. 09:30–13:00 IST)" value="' + esc(state.sliForm.dutyTimes) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<input id="sli-session" type="text" placeholder="Session Title to add (optional)" value="' + esc(state.sliForm.sessionTitle) + '" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<select id="sli-day" class="px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg bg-white">' +
                '<option value="Day 1 (21 Jan 2027)">Day 1 (21 Jan 2027)</option>' +
                '<option value="Day 2 (22 Jan 2027)">Day 2 (22 Jan 2027)</option>' +
                '<option value="Day 3 (23 Jan 2027)">Day 3 (23 Jan 2027)</option>' +
                "</select>" +
                '<div class="sm:col-span-2 lg:col-span-4 flex items-center gap-2">' +
                '<input id="sli-notes" type="text" placeholder="Additional notes for interpreter…" value="' + esc(state.sliForm.assignmentNotes) + '" class="flex-1 px-3 py-2 text-xs border border-[#DDD5EA] rounded-lg" />' +
                '<button type="button" data-sli-save-assign="' + esc(s.id) + '" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">Save Assignment</button>' +
                "</div></div>";
            }

            card += "</div>";
            return card;
          })
          .join("") +
        "</div>";
    }

    html += "</div>";
    return html;
  }

  function renderAccommodationsTab() {
    let html = '<div class="space-y-4">';
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">' +
      "<div>" +
      '<h3 class="text-sm font-bold text-[#17151C]">Accessibility &amp; Accommodation Coordination (Permission Protected)</h3>' +
      '<p class="text-xs text-[#645C75]">Coordinate ISL interpreters, Braille schedules, wheelchair seating, and sensory sanctuary access.</p>' +
      "</div>" +
      '<select id="accomm-status-filter" aria-label="Filter accommodation requests by status" class="px-3 py-1.5 text-xs border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="">All Statuses</option>' +
      ["REQUESTED", "IN_PROGRESS", "ARRANGED", "DECLINED"]
        .map(function (st) {
          return '<option value="' + st + '" ' + (state.accommStatusFilter === st ? "selected" : "") + ">" + st + "</option>";
        })
        .join("") +
      "</select></div>";

    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden"><div class="overflow-x-auto">' +
      '<table class="w-full text-sm text-left"><caption class="sr-only">Participant accommodation requests</caption>' +
      '<thead class="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]"><tr><th class="px-4 py-3">Participant</th><th class="px-4 py-3">Requested Accommodations</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Coordinator Notes &amp; Action</th></tr></thead>' +
      '<tbody class="divide-y divide-[#F0EBF8]">';

    if (state.accommLoading) {
      html += '<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-[#6E6580]">Loading accommodation requests…</td></tr>';
    } else if (state.accommItems.length === 0) {
      html += '<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-[#6E6580]">No accommodation requests found.</td></tr>';
    } else {
      html += state.accommItems
        .map(function (a) {
          const labels = (a.selectedTypeLabels || a.selectedTypes || []).map(function (lbl) {
            return '<span class="px-2 py-0.5 rounded-md text-xs font-medium bg-[#F2ECF9] text-[#4C3273]">' + esc(lbl) + "</span>";
          });
          return (
            '<tr class="align-top">' +
            '<td class="px-4 py-3"><p class="font-mono text-xs font-bold text-[#563A80]">' + esc(a.registrationCode) + '</p><p class="font-semibold text-[#17151C]">' + esc(a.fullName) + '</p><p class="text-xs text-[#645C75]">' + esc(a.email) + " • " + esc(a.phone) + "</p></td>" +
            '<td class="px-4 py-3"><div class="flex flex-wrap gap-1.5">' + labels.join("") + "</div>" +
            (a.freeTextDetails
              ? '<p class="text-xs text-[#4A435C] mt-2 bg-[#FAF8FD] p-2 rounded border border-[#ECE7F5]">' + esc(a.freeTextDetails) + "</p>"
              : "") +
            "</td>" +
            '<td class="px-4 py-3"><span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(a.status) + '">' + esc(a.status) + "</span></td>" +
            '<td class="px-4 py-3 space-y-2 min-w-[260px]">' +
            '<input type="text" data-accomm-notes="' + esc(a.id) + '" value="' + esc(state.accommNotesDraft[a.id] || "") + '" placeholder="Coordinator notes…" class="w-full px-2.5 py-1.5 text-xs border border-[#DDD5EA] rounded-lg" />' +
            '<div class="flex flex-wrap gap-1.5">' +
            ["IN_PROGRESS", "ARRANGED", "DECLINED"]
              .map(function (st) {
                return (
                  '<button type="button" data-accomm-update="' +
                  esc(a.id) +
                  '" data-accomm-status="' +
                  st +
                  '" class="px-2.5 py-1 rounded text-[11px] font-semibold border border-[#DDD5EA] hover:bg-[#F3EEF9] text-[#3E2B5E]">Mark ' +
                  st.replace("_", " ") +
                  "</button>"
                );
              })
              .join("") +
            "</div></td></tr>"
          );
        })
        .join("");
    }

    html += "</tbody></table></div></div></div>";
    return html;
  }

  function renderAttendanceTab() {
    const ov = state.overview;
    const m = (ov && ov.metrics) || {};
    const attVenue = (ov && ov.attendanceByVenue) || [];
    const rec = (ov && ov.recentCheckins) || [];

    return (
      '<div class="space-y-6">' +
      '<div class="bg-[#181421] text-white rounded-xl p-5 flex flex-wrap items-center justify-between gap-4">' +
      "<div>" +
      '<p class="text-xs uppercase tracking-wider text-[#C4ACEB] font-semibold">Live Reception Operations</p>' +
      '<h3 class="text-lg font-bold mt-0.5">Real-Time Attendance &amp; Gate Check-In Monitor</h3>' +
      '<p class="text-xs text-[#C8C0D8] mt-1">Today (IST): ' + esc((ov && ov.todayIst) || "—") + " • Duplicate entry prevented at database level</p>" +
      "</div>" +
      '<a href="/rgst" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-[#7E61A8] hover:bg-[#6D5096] text-white">Open RGST QR Scanner</a>' +
      "</div>" +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">' +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><p class="text-xs font-semibold text-[#645C75] uppercase">Confirmed Eligible</p><p class="text-3xl font-bold mt-1 tabular-nums">' + esc(m.confirmedRegistrations || 0) + "</p></div>" +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><p class="text-xs font-semibold text-emerald-700 uppercase">Unique Checked-In</p><p class="text-3xl font-bold text-emerald-800 mt-1 tabular-nums">' + esc(m.uniqueCheckedInParticipants || 0) + "</p></div>" +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><p class="text-xs font-semibold text-amber-700 uppercase">Not Yet Arrived</p><p class="text-3xl font-bold text-amber-800 mt-1 tabular-nums">' + esc(m.notYetArrived || 0) + "</p></div>" +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><p class="text-xs font-semibold text-[#5B3E87] uppercase">Today\'s Arrivals (IST)</p><p class="text-3xl font-bold text-[#5B3E87] mt-1 tabular-nums">' + esc(m.todayCheckins || 0) + "</p></div>" +
      "</div>" +
      '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">' +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><h4 class="text-sm font-bold mb-3">Attendance by Venue / Gate</h4>' +
      '<table class="w-full text-xs text-left"><thead><tr class="border-b border-[#E5DFEF] text-[#5C536E]"><th class="py-2">Venue / Gate</th><th class="py-2 text-right">Check-Ins</th></tr></thead><tbody>' +
      (attVenue.length === 0
        ? '<tr><td colspan="2" class="py-4 text-center text-[#6E6580]">No venue check-ins recorded yet.</td></tr>'
        : attVenue
            .map(function (v) {
              return '<tr class="border-b border-[#F2EEF9]"><td class="py-2 font-medium">' + esc(v.venueLabel) + '</td><td class="py-2 text-right tabular-nums font-semibold">' + esc(v.count) + "</td></tr>";
            })
            .join("")) +
      "</tbody></table></div>" +
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4"><h4 class="text-sm font-bold mb-3">Recent Check-Ins</h4>' +
      '<div class="space-y-2 max-h-72 overflow-y-auto">' +
      (rec.length === 0
        ? '<p class="text-xs text-[#6E6580]">No recent check-ins.</p>'
        : rec
            .map(function (c) {
              return (
                '<div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5] text-xs">' +
                '<div><p class="font-semibold text-[#17151C]">' + esc(c.participantName) + ' <span class="font-mono text-[#5B3E87]">(' + esc(c.registrationCode) + ')</span></p><p class="text-[#645C75]">' + esc(c.categoryCode) + " • " + esc(c.venueLabel) + " • " + esc(c.method) + "</p></div>" +
                '<span class="text-[11px] text-[#5C536E] shrink-0">' + esc(formatDateShort(c.checkedInAt)) + "</span>" +
                "</div>"
              );
            })
            .join("")) +
      "</div></div></div></div>"
    );
  }

  function renderCouponsTab() {
    let html = '<div class="space-y-6">';
    html +=
      '<form id="coupon-create-form" class="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-4">' +
      '<h3 class="text-sm font-bold text-[#17151C]">Create Institutional / Student Coupon Code</h3>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">' +
      '<input id="cp-code" required type="text" placeholder="CODE (e.g. MSU2027)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg font-mono uppercase" />' +
      '<select id="cp-type" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="PERCENTAGE">Percentage (%)</option>' +
      '<option value="FIXED_AMOUNT">Fixed Amount (&#8377;)</option>' +
      '<option value="FULL_WAIVER">100% Full Waiver</option>' +
      "</select>" +
      '<input id="cp-val" type="number" min="0" max="100000" value="100" placeholder="Value" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<input id="cp-max" type="number" min="1" placeholder="Max Uses (optional)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">+ Create Coupon</button>' +
      "</div>" +
      '<input id="cp-desc" type="text" placeholder="Description (e.g. MSU Baroda faculty & student waiver)" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      "</form>";

    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden"><table class="w-full text-sm text-left">' +
      '<thead class="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]"><tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Redemptions</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>' +
      '<tbody class="divide-y divide-[#F0EBF8]">';

    if (state.couponsLoading) {
      html += '<tr><td colspan="5" class="px-4 py-6 text-center text-[#6E6580]">Loading coupons…</td></tr>';
    } else if (state.coupons.length === 0) {
      html += '<tr><td colspan="5" class="px-4 py-6 text-center text-[#6E6580]">No coupon codes created yet.</td></tr>';
    } else {
      html += state.coupons
        .map(function (c) {
          const discLabel =
            c.discountType === "FULL_WAIVER"
              ? "100% Waiver"
              : c.discountType === "PERCENTAGE"
                ? c.discountValue + "% Off"
                : "₹" + c.discountValue + " Off";
          return (
            "<tr>" +
            '<td class="px-4 py-3"><p class="font-mono font-bold text-[#4B3470]">' + esc(c.code) + "</p>" + (c.description ? '<p class="text-xs text-[#645C75]">' + esc(c.description) + "</p>" : "") + "</td>" +
            '<td class="px-4 py-3 text-xs font-semibold">' + esc(discLabel) + "</td>" +
            '<td class="px-4 py-3 text-xs tabular-nums">' + esc(c.redemptionCount || 0) + " / " + esc(c.maxUses !== null && c.maxUses !== undefined ? c.maxUses : "∞") + "</td>" +
            '<td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(c.isActive ? "ACTIVE" : "REVOKED") + '">' + (c.isActive ? "Active" : "Inactive") + "</span></td>" +
            '<td class="px-4 py-3 text-right"><button type="button" data-coupon-toggle="' + esc(c.id) + '" data-coupon-active="' + (c.isActive ? "1" : "0") + '" class="px-3 py-1 rounded-lg text-xs font-semibold border border-[#DDD5EA] hover:bg-[#F3EEF9]">' + (c.isActive ? "Disable" : "Enable") + "</button></td>" +
            "</tr>"
          );
        })
        .join("");
    }

    html += "</tbody></table></div></div>";
    return html;
  }

  function renderCmsTab() {
    const subTabs = [
      ["sections", "Page Sections"],
      ["programme", "Programme"],
      ["partners", "Partners"],
      ["faqs", "FAQs"],
      ["media", "Gallery & Media"],
      ["announcements", "Announcements"],
      ["categories", "Registration Categories"],
    ];

    let html = '<div class="space-y-4">';
    html +=
      '<div class="flex flex-wrap gap-2 bg-white border border-[#E5DFEF] rounded-xl p-2">' +
      subTabs
        .map(function (pair) {
          const key = pair[0];
          const label = pair[1];
          const active = state.cmsSubTab === key;
          return (
            '<button type="button" data-cms-subtab="' +
            key +
            '" class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors ' +
            (active ? "bg-[#6B4E96] text-white" : "text-[#4D4263] hover:bg-[#F3EEF9]") +
            '">' +
            esc(label) +
            "</button>"
          );
        })
        .join("") +
      "</div>";

    if (state.cmsLoading || !state.cmsData) {
      html += '<div class="bg-white border border-[#E5DFEF] rounded-xl p-8 text-center text-sm text-[#6E6580]">Loading CMS content…</div></div>';
      return html;
    }

    const cms = state.cmsData;

    if (state.cmsSubTab === "sections") {
      html +=
        '<div class="space-y-4">' +
        (cms.sections || [])
          .map(function (s) {
            const d = state.sectionDrafts[s.id] || {
              title: s.title || "",
              subtitle: s.subtitle || "",
              body: s.body || "",
            };
            return (
              '<div class="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-3">' +
              '<div class="flex items-center justify-between">' +
              '<span class="font-mono text-xs uppercase font-bold px-2.5 py-1 rounded bg-[#F1EBF9] text-[#51377A]">' + esc(s.sectionKey) + "</span>" +
              '<button type="button" data-cms-save-section="' + esc(s.id) + '" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">Save &amp; Record Revision</button>' +
              "</div>" +
              '<input type="text" data-sec-title="' + esc(s.id) + '" value="' + esc(d.title) + '" class="w-full px-3 py-2 text-sm font-semibold border border-[#DDD5EA] rounded-lg" placeholder="Section Title" />' +
              '<input type="text" data-sec-subtitle="' + esc(s.id) + '" value="' + esc(d.subtitle) + '" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" placeholder="Subtitle" />' +
              '<textarea rows="3" data-sec-body="' + esc(s.id) + '" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" placeholder="Section Body Copy">' + esc(d.body) + "</textarea>" +
              "</div>"
            );
          })
          .join("") +
        "</div>";
    } else if (state.cmsSubTab === "programme") {
      html +=
        '<div class="space-y-4">' +
        '<form id="cms-prog-form" class="bg-white border border-[#E5DFEF] rounded-xl p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        '<input id="prog-title" required type="text" placeholder="Session Title" class="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<select id="prog-day" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"><option value="1">Day 1 — 21 Jan 2027</option><option value="2">Day 2 — 22 Jan 2027</option><option value="3">Day 3 — 23 Jan 2027</option></select>' +
        '<input id="prog-slot" required type="text" value="10:00 – 11:30 IST" placeholder="Time Slot" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="prog-cat" required type="text" value="Plenary" placeholder="Category (e.g. Para Sports, Plenary)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="prog-venue" type="text" value="Sama Indoor Stadium" placeholder="Venue Name" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<button type="submit" class="sm:col-span-3 px-4 py-2 rounded-lg text-sm font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">+ Add Programme Session</button>' +
        "</form>" +
        '<div class="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">' +
        (cms.programmeItems || [])
          .map(function (p) {
            return (
              '<div class="p-4 flex items-center justify-between gap-3">' +
              "<div>" +
              '<p class="text-xs font-semibold text-[#5B3E87]">Day ' + esc(p.dayNumber) + " • " + esc(p.timeSlot) + " • " + esc(p.category) + "</p>" +
              '<p class="font-bold text-[#17151C]">' + esc(p.title) + "</p>" +
              (p.venueName ? '<p class="text-xs text-[#645C75]">' + esc(p.venueName) + "</p>" : "") +
              "</div>" +
              '<button type="button" data-cms-delete="programme" data-cms-id="' + esc(p.id) + '" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>' +
              "</div>"
            );
          })
          .join("") +
        "</div></div>";
    } else if (state.cmsSubTab === "partners") {
      html +=
        '<div class="space-y-4">' +
        '<form id="cms-partner-form" class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        '<input id="pt-name" required type="text" placeholder="Organization Name" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="pt-cat" required type="text" value="Knowledge Partners" placeholder="Category" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="pt-web" type="url" placeholder="Website URL (optional)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        "</div>" +
        '<div class="flex flex-wrap items-center justify-between gap-3">' +
        '<label class="inline-flex items-center gap-2 text-xs font-medium text-[#3C334E]"><input id="pt-consent" type="checkbox" /> Written partner logo/name consent verified</label>' +
        '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">+ Add Partner (Draft)</button>' +
        "</div></form>" +
        '<div class="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">' +
        (cms.partners || [])
          .map(function (pt) {
            return (
              '<div class="p-4 flex flex-wrap items-center justify-between gap-3">' +
              "<div>" +
              '<div class="flex items-center gap-2">' +
              '<p class="font-bold text-[#17151C]">' + esc(pt.organizationName) + "</p>" +
              '<span class="px-2 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(pt.status) + '">' + esc(pt.status) + "</span>" +
              "</div>" +
              '<p class="text-xs text-[#645C75]">' + esc(pt.category) + " • Consent: " + (pt.consentVerified ? "Verified" : "Unverified") + "</p>" +
              "</div>" +
              '<div class="flex items-center gap-2">' +
              '<label class="inline-flex items-center gap-1.5 text-xs"><input type="checkbox" data-pt-consent="' + esc(pt.id) + '" data-pt-status="' + esc(pt.status) + '" ' + (pt.consentVerified ? "checked" : "") + " /> Consent</label>" +
              '<button type="button" data-pt-publish="' + esc(pt.id) + '" data-pt-consent-val="' + (pt.consentVerified ? "1" : "0") + '" data-pt-next-status="' + (pt.status === "PUBLISHED" ? "DRAFT" : "PUBLISHED") + '" ' + (!pt.consentVerified && pt.status !== "PUBLISHED" ? "disabled " : "") + 'class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white disabled:opacity-40">' + (pt.status === "PUBLISHED" ? "Unpublish" : "Publish") + "</button>" +
              '<button type="button" data-cms-delete="partner" data-cms-id="' + esc(pt.id) + '" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>' +
              "</div></div>"
            );
          })
          .join("") +
        "</div></div>";
    } else if (state.cmsSubTab === "faqs") {
      html +=
        '<div class="space-y-4">' +
        '<form id="cms-faq-form" class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        '<input id="faq-cat" required type="text" value="General" placeholder="Category" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="faq-q" required type="text" placeholder="Question" class="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        "</div>" +
        '<textarea id="faq-a" required rows="2" placeholder="Answer" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"></textarea>' +
        '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white">+ Add FAQ</button>' +
        "</form>" +
        '<div class="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">' +
        (cms.faqItems || [])
          .map(function (f) {
            return (
              '<div class="p-4 flex items-start justify-between gap-3">' +
              '<div><span class="text-[11px] font-semibold uppercase text-[#5B3E87]">' + esc(f.category) + '</span><p class="font-bold text-sm text-[#17151C]">' + esc(f.question) + '</p><p class="text-xs text-[#544A68] mt-1">' + esc(f.answer) + "</p></div>" +
              '<button type="button" data-cms-delete="faq" data-cms-id="' + esc(f.id) + '" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>' +
              "</div>"
            );
          })
          .join("") +
        "</div></div>";
    } else if (state.cmsSubTab === "media") {
      html +=
        '<div class="space-y-4">' +
        '<form id="cms-media-form" class="bg-white border border-[#E5DFEF] rounded-xl p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        '<input id="media-url" required type="text" placeholder="Asset URL (e.g. /assets/gallery/venue-01.jpg)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="media-alt" required type="text" placeholder="Accessible Alt Text (Mandatory WCAG 2.2 AA)" class="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<button type="submit" class="sm:col-span-3 px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white">+ Add Gallery Media Asset</button>' +
        "</form>" +
        '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">' +
        ((cms.mediaAssets || []).length === 0
          ? '<p class="text-xs text-[#6E6580] col-span-full bg-white p-6 rounded-xl border border-[#E5DFEF] text-center">No gallery assets in database yet.</p>'
          : (cms.mediaAssets || [])
              .map(function (m) {
                return (
                  '<div class="bg-white border border-[#E5DFEF] rounded-xl p-3 space-y-2 flex flex-col justify-between">' +
                  '<div><p class="font-mono text-[11px] font-semibold text-[#563A80] truncate">' + esc(m.fileName) + '</p><p class="text-xs text-[#4C4263] mt-1">' + esc(m.altText) + "</p></div>" +
                  '<div class="flex items-center justify-between pt-2 border-t border-[#F0EBF8]">' +
                  '<span class="text-[10px] font-semibold uppercase text-[#6E6580]">' + esc(m.category) + "</span>" +
                  '<button type="button" data-cms-delete="media" data-cms-id="' + esc(m.id) + '" class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>' +
                  "</div></div>"
                );
              })
              .join("")) +
        "</div></div>";
    } else if (state.cmsSubTab === "announcements") {
      html +=
        '<div class="space-y-4">' +
        '<form id="cms-ann-form" class="bg-white border border-[#E5DFEF] rounded-xl p-4 space-y-3">' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        '<input id="ann-title" required type="text" placeholder="Announcement Title" class="sm:col-span-2 px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<select id="ann-sev" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white"><option value="INFO">Info</option><option value="IMPORTANT">Important</option><option value="ALERT">Alert</option></select>' +
        "</div>" +
        '<textarea id="ann-msg" required rows="2" placeholder="Announcement message…" class="w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg"></textarea>' +
        '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white">+ Publish Announcement</button>' +
        "</form>" +
        '<div class="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">' +
        ((cms.announcements || []).length === 0
          ? '<p class="p-6 text-center text-xs text-[#6E6580]">No announcements posted yet.</p>'
          : (cms.announcements || [])
              .map(function (a) {
                return (
                  '<div class="p-4 flex items-start justify-between gap-3">' +
                  '<div><p class="font-bold text-sm">' + esc(a.title) + '</p><p class="text-xs text-[#544A68] mt-1">' + esc(a.message) + "</p></div>" +
                  '<button type="button" data-cms-delete="announcement" data-cms-id="' + esc(a.id) + '" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>' +
                  "</div>"
                );
              })
              .join("")) +
        "</div></div>";
    } else if (state.cmsSubTab === "categories") {
      html +=
        '<div class="bg-white border border-[#E5DFEF] rounded-xl divide-y divide-[#F0EBF8]">' +
        (cms.registrationCategories || [])
          .map(function (cat) {
            return (
              '<div class="p-4 flex flex-wrap items-center justify-between gap-4">' +
              "<div>" +
              '<div class="flex items-center gap-2"><span class="font-mono text-xs font-bold text-[#563A80]">' + esc(cat.code) + '</span><p class="font-bold text-sm text-[#17151C]">' + esc(cat.name) + "</p></div>" +
              '<p class="text-xs text-[#645C75] mt-0.5">' + (cat.requiresApproval ? "Requires organizer approval" : "Instant confirmation") + "</p>" +
              "</div>" +
              '<div class="flex flex-wrap items-center gap-3">' +
              '<label class="flex items-center gap-1.5 text-xs"><span>Fee (&#8377;):</span>' +
              '<input type="number" min="0" data-cat-fee="' + esc(cat.id) + '" value="' + esc(cat.feeAmountInr) + '" class="w-24 px-2 py-1 border border-[#DDD5EA] rounded text-xs" />' +
              "</label>" +
              '<button type="button" data-cat-toggle="' + esc(cat.id) + '" data-cat-active="' + (cat.isActive ? "1" : "0") + '" class="px-3 py-1.5 rounded-lg text-xs font-semibold ' + (cat.isActive ? "bg-emerald-700 text-white" : "bg-slate-200 text-slate-700") + '">' + (cat.isActive ? "Open" : "Closed") + "</button>" +
              "</div></div>"
            );
          })
          .join("") +
        "</div>";
    }

    html += "</div>";
    return html;
  }

  function renderUsersTab() {
    let html = '<div class="space-y-6">';
    html +=
      '<form id="staff-create-form" class="bg-white border border-[#E5DFEF] rounded-xl p-5 space-y-4">' +
      '<h3 class="text-sm font-bold text-[#17151C]">Create Staff Account (Admin, RGST Check-In, or Superadmin)</h3>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">' +
      '<input id="st-name" required type="text" placeholder="Full Name" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<input id="st-username" required type="text" placeholder="Username (e.g. admin1, rgst1)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<input id="st-email" type="email" placeholder="Email (optional)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<input id="st-pw" required type="password" minlength="8" placeholder="Initial Password (min 8)" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<select id="st-role" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white">' +
      '<option value="ADMIN">ADMIN (Operations)</option>' +
      '<option value="RGST">RGST (QR Desk Only)</option>' +
      '<option value="SUPERADMIN">SUPERADMIN</option>' +
      "</select>" +
      "</div>" +
      '<div class="flex flex-wrap items-center justify-between gap-3">' +
      '<label class="inline-flex items-center gap-2 text-xs font-medium text-[#3C334E]"><input id="st-grant-cms" type="checkbox" checked /> Grant custom CMS permission (cms.manage) to this Admin</label>' +
      '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">+ Create Staff Account</button>' +
      "</div></form>";

    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden"><div class="overflow-x-auto">' +
      '<table class="w-full text-sm text-left"><caption class="sr-only">Staff and user accounts</caption>' +
      '<thead class="bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Roles</th><th class="px-4 py-3">Custom Permissions</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>' +
      '<tbody class="divide-y divide-[#F0EBF8]">';

    if (state.usersLoading) {
      html += '<tr><td colspan="5" class="px-4 py-6 text-center text-[#6E6580]">Loading users…</td></tr>';
    } else {
      html += state.staffUsers
        .map(function (u) {
          const roleBadges = (u.roles || [])
            .map(function (r) {
              return '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#F1EBF9] text-[#4C3273]">' + esc(r) + "</span>";
            })
            .join(" ");
          const cPerms = Array.isArray(u.customPermissions) && u.customPermissions.length > 0
            ? u.customPermissions.join(", ")
            : "Default role set";

          return (
            "<tr>" +
            '<td class="px-4 py-3"><p class="font-semibold text-[#17151C]">' + esc(u.fullName) + '</p><p class="text-xs text-[#645C75]">@' + esc(u.username || u.email) + (u.username && u.email && !u.email.endsWith("@staff.purplefestivalgujarat.com") ? " · " + esc(u.email) : "") + "</p></td>" +
            '<td class="px-4 py-3"><div class="flex flex-wrap gap-1">' + roleBadges + "</div></td>" +
            '<td class="px-4 py-3 text-xs text-[#5C536E]">' + esc(cPerms) + "</td>" +
            '<td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(u.isActive ? "ACTIVE" : "REVOKED") + '">' + (u.isActive ? "Active" : "Disabled") + "</span></td>" +
            '<td class="px-4 py-3 text-right space-x-2">' +
            ((u.roles || []).includes("ADMIN")
              ? '<button type="button" data-user-perms="' + esc(u.id) + '" class="px-2.5 py-1 rounded text-xs font-semibold border border-[#D4C7EA] text-[#4C3273] hover:bg-[#F3EEF9]">Permissions</button>'
              : "") +
            '<button type="button" data-user-reset-pw="' + esc(u.id) + '" data-user-email="' + esc(u.email) + '" class="px-2.5 py-1 rounded text-xs font-semibold border border-[#DDD5EA] hover:bg-slate-50">Reset Password</button>' +
            (u.id !== currentUser.id
              ? '<button type="button" data-user-toggle="' + esc(u.id) + '" data-user-active="' + (u.isActive ? "1" : "0") + '" class="px-2.5 py-1 rounded text-xs font-semibold border border-[#DDD5EA] hover:bg-slate-50">' + (u.isActive ? "Deactivate" : "Activate") + "</button>"
              : "") +
            "</td></tr>"
          );
        })
        .join("");
    }

    html += "</tbody></table></div></div></div>";
    return html;
  }

  function renderSecurityTab() {
    let html = '<div class="max-w-2xl space-y-6">';
    if (state.mfaEnrollmentRequired) {
      html +=
        '<div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-950 space-y-1">' +
        '<p class="font-bold">Mandatory Two-Factor Authentication Required (Policy D-20)</p>' +
        '<p>All SUPERADMIN and ADMIN accounts must enroll a TOTP authenticator app before accessing administrative data or operations.</p>' +
        "</div>";
    }
    if (Array.isArray(state.mfaRecoveryCodes) && state.mfaRecoveryCodes.length > 0) {
      html +=
        '<div class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 space-y-3">' +
        '<h3 class="text-sm font-bold text-emerald-950">Save Your Single-Use Recovery Codes (Shown Once)</h3>' +
        '<p class="text-xs text-emerald-900">Store these codes securely offline. Each code can be used once if you lose access to your authenticator device.</p>' +
        '<div class="grid grid-cols-2 sm:grid-cols-4 gap-2">' +
        state.mfaRecoveryCodes
          .map(function (c) {
            return '<code class="block text-center font-mono text-xs font-bold bg-white border border-emerald-200 rounded px-2 py-1.5 text-emerald-950 select-all">' + esc(c) + "</code>";
          })
          .join("") +
        "</div></div>";
    }

    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-6 space-y-4">' +
      '<div class="flex items-start justify-between gap-4">' +
      "<div>" +
      '<h3 class="text-base font-bold text-[#17151C]">Two-Factor Authentication (RFC 6238 TOTP MFA)</h3>' +
      '<p class="text-xs text-[#645C75] mt-1">160-bit TOTP secrets encrypted with AES-256-GCM. Compatible with Google Authenticator, Authy, 1Password, and Microsoft Authenticator.</p>' +
      "</div>" +
      '<span class="px-3 py-1 rounded-full text-xs font-semibold border ' +
      statusBadgeClass(state.mfaEnabled ? "ACTIVE" : "PENDING") +
      '">' +
      (state.mfaEnabled ? "MFA Enabled" : "MFA Not Enabled") +
      "</span>" +
      "</div>";

    if (state.mfaSetupData) {
      html +=
        '<div class="pt-4 border-t border-[#ECE7F5] space-y-4">' +
        '<div class="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-xl bg-[#FAF8FD] border border-[#ECE7F5]">' +
        '<div data-qr-payload="' + esc(state.mfaSetupData.otpauthUrl || state.mfaSetupData.secret) + '" data-qr-size="180" class="bg-white p-2 rounded-lg border border-[#E5DFEF] shrink-0"></div>' +
        '<div class="space-y-2 text-xs">' +
        '<p class="font-bold text-[#17151C]">1. Scan QR Code in your Authenticator App</p>' +
        '<p class="text-[#5C536E]">Or manually enter this 160-bit Base32 Setup Key:</p>' +
        '<p class="font-mono text-xs font-bold break-all px-3 py-1.5 rounded bg-white border border-[#DDD5EA] text-[#4C3273] select-all">' + esc(state.mfaSetupData.secret) + "</p>" +
        "</div></div>" +
        '<form id="mfa-verify-form" class="space-y-3">' +
        '<label for="mfa-verify-code" class="block text-xs font-semibold text-[#17151C]">2. Enter the 6-digit verification code from your app</label>' +
        '<div class="flex flex-wrap items-center gap-3">' +
        '<input id="mfa-verify-code" required type="text" inputmode="numeric" maxlength="8" placeholder="000 000" class="px-3 py-2 text-sm font-mono tracking-widest border border-[#DDD5EA] rounded-lg w-44 text-center" />' +
        '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white">Verify &amp; Activate MFA</button>' +
        "</div></form></div>";
    } else if (state.mfaEnabled) {
      html +=
        '<form id="mfa-replace-form" class="pt-4 border-t border-[#ECE7F5] space-y-3">' +
        '<p class="text-xs text-[#4C4263]">MFA is mandatory for your role and cannot be disabled. To replace your authenticator device, confirm your current password and a current 6-digit code:</p>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
        '<input id="mfa-reauth-pw" required type="password" placeholder="Current password" class="px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
        '<input id="mfa-reauth-code" required type="text" inputmode="numeric" maxlength="10" placeholder="Current 6-digit code" class="px-3 py-2 text-sm font-mono tracking-widest border border-[#DDD5EA] rounded-lg text-center" />' +
        "</div>" +
        '<button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">Start Authenticator Replacement</button>' +
        "</form>";
    } else {
      html +=
        '<div class="pt-3 border-t border-[#ECE7F5]">' +
        '<button type="button" id="mfa-init-btn" ' +
        (state.mfaLoading ? "disabled " : "") +
        'class="px-4 py-2.5 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white">' +
        (state.mfaLoading ? "Generating Secret…" : "Set Up Authenticator App (TOTP)") +
        "</button></div>";
    }

    html += "</div></div>";
    return html;
  }

  function renderAuditLogsTab() {
    let html = '<div class="space-y-4">';
    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl p-4 flex flex-wrap items-center gap-3">' +
      '<input id="audit-search-input" type="search" value="' + esc(state.auditSearch) + '" placeholder="Filter by action, resource type, or actor email…" aria-label="Filter audit logs" class="flex-1 min-w-[220px] px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg" />' +
      '<button type="button" id="audit-filter-btn" class="px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] text-white">Filter Logs</button>' +
      "</div>";

    html +=
      '<div class="bg-white border border-[#E5DFEF] rounded-xl overflow-hidden"><div class="overflow-x-auto">' +
      '<table class="w-full text-xs text-left"><caption class="sr-only">Immutable security and admin audit trail</caption>' +
      '<thead class="bg-[#F8F6FC] border-b border-[#E5DFEF] uppercase text-[#5C536E]"><tr><th class="px-4 py-3">Timestamp (IST)</th><th class="px-4 py-3">Actor</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Resource</th><th class="px-4 py-3">Metadata</th></tr></thead>' +
      '<tbody class="divide-y divide-[#F0EBF8]">';

    if (state.auditLoading) {
      html += '<tr><td colspan="5" class="px-4 py-8 text-center text-[#6E6580]">Loading audit trail…</td></tr>';
    } else if (state.auditLogs.length === 0) {
      html += '<tr><td colspan="5" class="px-4 py-8 text-center text-[#6E6580]">No audit entries found.</td></tr>';
    } else {
      html += state.auditLogs
        .map(function (log) {
          return (
            "<tr>" +
            '<td class="px-4 py-3 whitespace-nowrap text-[#544A68]">' + esc(formatDateShort(log.createdAt)) + "</td>" +
            '<td class="px-4 py-3"><p class="font-semibold text-[#17151C]">' + esc(log.actorName || "System") + '</p><p class="text-[11px] text-[#645C75]">' + esc(log.actorEmail || "—") + "</p></td>" +
            '<td class="px-4 py-3 font-mono font-semibold text-[#4C3273]">' + esc(log.action) + "</td>" +
            '<td class="px-4 py-3"><span class="font-medium">' + esc(log.resourceType) + "</span>" + (log.resourceId ? '<p class="font-mono text-[10px] text-[#6E6580]">' + esc(String(log.resourceId).slice(0, 14)) + "…</p>" : "") + "</td>" +
            '<td class="px-4 py-3 max-w-xs truncate font-mono text-[11px] text-[#5C536E]">' + (log.metadata ? esc(JSON.stringify(log.metadata)) : "—") + "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    html +=
      "</tbody></table></div>" +
      '<div class="px-4 py-3 border-t border-[#ECE7F5] flex items-center justify-between text-xs text-[#5E5470]">' +
      "<span>Page " + esc(state.auditPagination.page) + " of " + esc(state.auditPagination.totalPages) + " (" + esc(state.auditPagination.total) + " entries)</span>" +
      '<div class="flex gap-2">' +
      '<button type="button" id="audit-prev-btn" ' + (state.auditPagination.page <= 1 ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">Prev</button>' +
      '<button type="button" id="audit-next-btn" ' + (state.auditPagination.page >= state.auditPagination.totalPages ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">Next</button>' +
      "</div></div></div></div>";

    return html;
  }

  // =========================================================================
  // MAIN RENDER & EVENT DELEGATION
  // =========================================================================

  function renderMain() {
    if (!mainEl) return;

    if (state.activeTab === "OVERVIEW") mainEl.innerHTML = renderOverviewTab();
    else if (state.activeTab === "REGISTRATIONS") mainEl.innerHTML = renderRegistrationsTab();
    else if (state.activeTab === "VOLUNTEERS") mainEl.innerHTML = renderVolunteersTab();
    else if (state.activeTab === "SLI") mainEl.innerHTML = renderSliTab();
    else if (state.activeTab === "ACCOMMODATIONS") mainEl.innerHTML = renderAccommodationsTab();
    else if (state.activeTab === "ATTENDANCE") mainEl.innerHTML = renderAttendanceTab();
    else if (state.activeTab === "COUPONS") mainEl.innerHTML = renderCouponsTab();
    else if (state.activeTab === "CMS") mainEl.innerHTML = renderCmsTab();
    else if (state.activeTab === "USERS") mainEl.innerHTML = renderUsersTab();
    else if (state.activeTab === "SECURITY") mainEl.innerHTML = renderSecurityTab();
    else if (state.activeTab === "AUDIT_LOGS") mainEl.innerHTML = renderAuditLogsTab();

    if (window.PFGQrCode && typeof window.PFGQrCode.renderAll === "function") {
      window.PFGQrCode.renderAll(mainEl);
    }

    bindTabEvents();
  }

  function bindTabEvents() {
    // Overview card jumps
    mainEl.querySelectorAll("[data-overview-card]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const act = btn.getAttribute("data-overview-card");
        if (act === "ALL_REGS") jumpToFilteredRegistrations({});
        else if (act === "CONFIRMED_REGS") jumpToFilteredRegistrations({ status: "CONFIRMED,APPROVED" });
        else if (act === "PENDING_REGS") jumpToFilteredRegistrations({ status: "SUBMITTED,UNDER_REVIEW,ADDITIONAL_INFO_REQUIRED" });
        else if (act === "DELEGATE_REGS") jumpToFilteredRegistrations({ category: "DELEGATE" });
        else if (act === "APPROVED_VOLS") jumpToFilteredRegistrations({ category: "VOLUNTEER", status: "APPROVED,CONFIRMED" });
        else if (act === "APPROVED_SLI") jumpToFilteredRegistrations({ category: "SLI", status: "APPROVED,CONFIRMED" });
        else if (act === "ALLY_REGS") jumpToFilteredRegistrations({ category: "ALLY" });
        else if (act === "TAB_VOLUNTEERS") switchTab("VOLUNTEERS");
        else if (act === "TAB_SLI") switchTab("SLI");
        else if (act === "TAB_ACCOMMODATIONS") switchTab("ACCOMMODATIONS");
        else if (act === "TAB_ATTENDANCE") switchTab("ATTENDANCE");
      });
    });

    mainEl.querySelectorAll("[data-chart-mode]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        state.chartViewMode = btn.getAttribute("data-chart-mode");
        renderMain();
      });
    });

    // Registrations filters
    const regSearchInput = document.getElementById("reg-search-input");
    const regCatSelect = document.getElementById("reg-category-select");
    const regStSelect = document.getElementById("reg-status-select");
    const regCkSelect = document.getElementById("reg-checkin-select");
    const regApplyBtn = document.getElementById("reg-apply-btn");
    const regResetBtn = document.getElementById("reg-reset-btn");

    if (regSearchInput) {
      regSearchInput.addEventListener("input", function (e) {
        state.regSearch = e.target.value;
      });
      regSearchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          state.regPage = 1;
          fetchRegistrations({ page: 1 });
        }
      });
    }
    if (regCatSelect) {
      regCatSelect.addEventListener("change", function (e) {
        state.regCategory = e.target.value;
        state.regPage = 1;
        fetchRegistrations({ page: 1, category: state.regCategory });
      });
    }
    if (regStSelect) {
      regStSelect.addEventListener("change", function (e) {
        state.regStatus = e.target.value;
        state.regPage = 1;
        fetchRegistrations({ page: 1, status: state.regStatus });
      });
    }
    if (regCkSelect) {
      regCkSelect.addEventListener("change", function (e) {
        state.regCheckin = e.target.value;
        state.regPage = 1;
        fetchRegistrations({ page: 1, checkinStatus: state.regCheckin });
      });
    }
    if (regApplyBtn) {
      regApplyBtn.addEventListener("click", function () {
        state.regPage = 1;
        fetchRegistrations({ page: 1 });
      });
    }
    if (regResetBtn) {
      regResetBtn.addEventListener("click", function () {
        state.regSearch = "";
        state.regCategory = "";
        state.regStatus = "";
        state.regCheckin = "";
        state.regPage = 1;
        fetchRegistrations({ page: 1, search: "", category: "", status: "", checkinStatus: "" });
      });
    }

    mainEl.querySelectorAll("[data-open-reg]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openRegistrationDetail(btn.getAttribute("data-open-reg"));
      });
    });

    const regPrevBtn = document.getElementById("reg-prev-btn");
    const regNextBtn = document.getElementById("reg-next-btn");
    if (regPrevBtn) {
      regPrevBtn.addEventListener("click", function () {
        if (state.regPagination.page > 1) {
          state.regPage = state.regPagination.page - 1;
          fetchRegistrations({ page: state.regPage });
        }
      });
    }
    if (regNextBtn) {
      regNextBtn.addEventListener("click", function () {
        if (state.regPagination.page < state.regPagination.totalPages) {
          state.regPage = state.regPagination.page + 1;
          fetchRegistrations({ page: state.regPage });
        }
      });
    }

    // Volunteers queue actions
    mainEl.querySelectorAll("[data-vol-status]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-vol-id");
        const status = btn.getAttribute("data-vol-status");
        const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(id), {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: id, status: status }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Failed to update status.");
          return;
        }
        notify("success", status === "APPROVED" ? "Approved & QR pass issued!" : "Status updated to " + status + ".");
        fetchVolunteers();
        fetchOverview();
      });
    });

    mainEl.querySelectorAll("[data-vol-toggle-assign]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const id = btn.getAttribute("data-vol-toggle-assign");
        if (state.activeVolAssign === id) {
          state.activeVolAssign = null;
        } else {
          state.activeVolAssign = id;
          const item = state.volItems.find(function (x) { return x.id === id; });
          const vd = (item && item.volunteerDetails) || {};
          state.volForm = {
            assignedDepartment: vd.assignedDepartment || "",
            assignedResponsibility: vd.assignedResponsibility || "",
            assignedSupervisor: vd.assignedSupervisor || "",
            assignedVenue: vd.assignedVenue || "",
            assignedShift: vd.assignedShift || "",
            reportingTime: vd.reportingTime || "",
            assignmentNotes: vd.assignmentNotes || "",
          };
        }
        renderMain();
      });
    });

    mainEl.querySelectorAll("[data-vol-save-assign]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-vol-save-assign");
        const volForm = {
          assignedDepartment: (document.getElementById("vol-dept") || {}).value || "",
          assignedSupervisor: (document.getElementById("vol-sup") || {}).value || "",
          assignedVenue: (document.getElementById("vol-venue") || {}).value || "",
          assignedShift: (document.getElementById("vol-shift") || {}).value || "",
          reportingTime: (document.getElementById("vol-rtime") || {}).value || "",
          assignedResponsibility: (document.getElementById("vol-resp") || {}).value || "",
          assignmentNotes: (document.getElementById("vol-notes") || {}).value || "",
        };
        const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(id), {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: id, volunteerAssignment: volForm }),
        });
        if (!res.ok) {
          notify("error", "Failed to save volunteer duty assignment.");
          return;
        }
        notify("success", "Volunteer duty assignment saved.");
        state.activeVolAssign = null;
        fetchVolunteers();
      });
    });

    // SLI queue actions
    mainEl.querySelectorAll("[data-sli-status]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-sli-id");
        const status = btn.getAttribute("data-sli-status");
        const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(id), {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: id, status: status }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Failed to update SLI status.");
          return;
        }
        notify("success", status === "APPROVED" ? "SLI Approved & QR pass issued!" : "Status updated to " + status + ".");
        fetchSli();
        fetchOverview();
      });
    });

    mainEl.querySelectorAll("[data-sli-toggle-assign]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const id = btn.getAttribute("data-sli-toggle-assign");
        if (state.activeSliAssign === id) {
          state.activeSliAssign = null;
        } else {
          state.activeSliAssign = id;
          const item = state.sliItems.find(function (x) { return x.id === id; });
          const sd = (item && item.sliDetails) || {};
          state.sliForm = {
            assignedVenue: sd.assignedVenue || "",
            dutyTimes: sd.dutyTimes || "",
            sessionTitle: "",
            sessionDay: "Day 1 (21 Jan 2027)",
            assignmentNotes: sd.assignmentNotes || "",
          };
        }
        renderMain();
      });
    });

    mainEl.querySelectorAll("[data-sli-save-assign]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-sli-save-assign");
        const existingItem = state.sliItems.find(function (x) { return x.id === id; });
        const currentSessions = (existingItem && existingItem.sliDetails && existingItem.sliDetails.assignedSessions) || [];
        const assignedVenue = (document.getElementById("sli-venue") || {}).value || "";
        const dutyTimes = (document.getElementById("sli-duty") || {}).value || "";
        const sessionTitle = ((document.getElementById("sli-session") || {}).value || "").trim();
        const sessionDay = (document.getElementById("sli-day") || {}).value || "Day 1 (21 Jan 2027)";
        const assignmentNotes = (document.getElementById("sli-notes") || {}).value || "";

        const nextSessions = sessionTitle
          ? currentSessions.concat([{ sessionTitle: sessionTitle, venue: assignedVenue || "Main Auditorium", dutyTime: dutyTimes || "10:00 – 13:00 IST", day: sessionDay }])
          : currentSessions;

        const res = await apiFetch("/api/admin/registrations?id=" + encodeURIComponent(id), {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            id: id,
            sliAssignment: {
              assignedVenue: assignedVenue,
              dutyTimes: dutyTimes,
              assignmentNotes: assignmentNotes,
              assignedSessions: nextSessions,
            },
          }),
        });
        if (!res.ok) {
          notify("error", "Failed to save SLI assignment.");
          return;
        }
        notify("success", "SLI session & venue assignment saved.");
        state.activeSliAssign = null;
        fetchSli();
      });
    });

    // Accommodations events
    const accommFilterEl = document.getElementById("accomm-status-filter");
    if (accommFilterEl) {
      accommFilterEl.addEventListener("change", function (e) {
        state.accommStatusFilter = e.target.value;
        fetchAccommodations();
      });
    }

    mainEl.querySelectorAll("[data-accomm-notes]").forEach(function (inp) {
      inp.addEventListener("input", function () {
        state.accommNotesDraft[inp.getAttribute("data-accomm-notes")] = inp.value;
      });
    });

    mainEl.querySelectorAll("[data-accomm-update]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const reqId = btn.getAttribute("data-accomm-update");
        const status = btn.getAttribute("data-accomm-status");
        const res = await apiFetch("/api/admin/accommodations", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            requestId: reqId,
            status: status,
            coordinatorNotes: state.accommNotesDraft[reqId] || "",
          }),
        });
        if (!res.ok) {
          notify("error", "Failed to update accommodation request.");
          return;
        }
        notify("success", "Accommodation status updated to " + status + ".");
        fetchAccommodations();
        fetchOverview();
      });
    });

    // Coupons events
    const couponForm = document.getElementById("coupon-create-form");
    if (couponForm) {
      couponForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const code = ((document.getElementById("cp-code") || {}).value || "").trim().toUpperCase();
        const discountType = (document.getElementById("cp-type") || {}).value || "PERCENTAGE";
        const discountValue = Number((document.getElementById("cp-val") || {}).value || 100);
        const maxUsesRaw = ((document.getElementById("cp-max") || {}).value || "").trim();
        const description = ((document.getElementById("cp-desc") || {}).value || "").trim();

        const res = await apiFetch("/api/admin/coupons", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            code: code,
            discountType: discountType,
            discountValue: discountType === "FULL_WAIVER" ? 100 : discountValue,
            maxUses: maxUsesRaw ? Number(maxUsesRaw) : null,
            description: description,
            eligibleCategories: ["DELEGATE"],
          }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Failed to create coupon.");
          return;
        }
        notify("success", "Coupon " + code + " created.");
        fetchCoupons();
      });
    }

    mainEl.querySelectorAll("[data-coupon-toggle]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-coupon-toggle");
        const isCurrentlyActive = btn.getAttribute("data-coupon-active") === "1";
        const res = await apiFetch("/api/admin/coupons", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: id, isActive: !isCurrentlyActive }),
        });
        if (res.ok) {
          notify("success", "Coupon status updated.");
          fetchCoupons();
        }
      });
    });

    // CMS events
    mainEl.querySelectorAll("[data-cms-subtab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        state.cmsSubTab = btn.getAttribute("data-cms-subtab");
        renderMain();
      });
    });

    mainEl.querySelectorAll("[data-sec-title]").forEach(function (inp) {
      inp.addEventListener("input", function () {
        const id = inp.getAttribute("data-sec-title");
        if (!state.sectionDrafts[id]) state.sectionDrafts[id] = {};
        state.sectionDrafts[id].title = inp.value;
      });
    });
    mainEl.querySelectorAll("[data-sec-subtitle]").forEach(function (inp) {
      inp.addEventListener("input", function () {
        const id = inp.getAttribute("data-sec-subtitle");
        if (!state.sectionDrafts[id]) state.sectionDrafts[id] = {};
        state.sectionDrafts[id].subtitle = inp.value;
      });
    });
    mainEl.querySelectorAll("[data-sec-body]").forEach(function (inp) {
      inp.addEventListener("input", function () {
        const id = inp.getAttribute("data-sec-body");
        if (!state.sectionDrafts[id]) state.sectionDrafts[id] = {};
        state.sectionDrafts[id].body = inp.value;
      });
    });

    mainEl.querySelectorAll("[data-cms-save-section]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-cms-save-section");
        const draft = state.sectionDrafts[id];
        if (!draft) return;
        const res = await apiFetch("/api/admin/cms", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "section", id: id, payload: draft }),
        });
        if (!res.ok) {
          notify("error", "Failed to save section.");
          return;
        }
        notify("success", "CMS section saved and revision recorded.");
        fetchCms();
      });
    });

    const progForm = document.getElementById("cms-prog-form");
    if (progForm) {
      progForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const payload = {
          title: (document.getElementById("prog-title") || {}).value || "",
          dayNumber: Number((document.getElementById("prog-day") || {}).value || 1),
          timeSlot: (document.getElementById("prog-slot") || {}).value || "",
          category: (document.getElementById("prog-cat") || {}).value || "",
          venueName: (document.getElementById("prog-venue") || {}).value || "",
        };
        const res = await apiFetch("/api/admin/cms", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "programme", payload: payload }),
        });
        if (res.ok) {
          notify("success", "Programme item added.");
          fetchCms();
        }
      });
    }

    const partnerForm = document.getElementById("cms-partner-form");
    if (partnerForm) {
      partnerForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const payload = {
          organizationName: (document.getElementById("pt-name") || {}).value || "",
          category: (document.getElementById("pt-cat") || {}).value || "",
          websiteUrl: (document.getElementById("pt-web") || {}).value || "",
          consentVerified: Boolean((document.getElementById("pt-consent") || {}).checked),
          status: "DRAFT",
        };
        const res = await apiFetch("/api/admin/cms", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "partner", payload: payload }),
        });
        if (res.ok) {
          notify("success", "Partner saved.");
          fetchCms();
        }
      });
    }

    mainEl.querySelectorAll("[data-pt-consent]").forEach(function (cb) {
      cb.addEventListener("change", async function () {
        const id = cb.getAttribute("data-pt-consent");
        const currStatus = cb.getAttribute("data-pt-status");
        const nextStatus = cb.checked ? currStatus : "DRAFT";
        const res = await apiFetch("/api/admin/cms", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "partner", id: id, payload: { consentVerified: cb.checked, status: nextStatus } }),
        });
        if (res.ok) {
          notify("success", "Partner consent updated.");
          fetchCms();
        }
      });
    });

    mainEl.querySelectorAll("[data-pt-publish]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-pt-publish");
        const consentVerified = btn.getAttribute("data-pt-consent-val") === "1";
        const nextStatus = btn.getAttribute("data-pt-next-status");
        const res = await apiFetch("/api/admin/cms", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "partner", id: id, payload: { consentVerified: consentVerified, status: nextStatus } }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Could not update partner.");
          return;
        }
        notify("success", "Partner status updated (" + nextStatus + ").");
        fetchCms();
      });
    });

    const faqForm = document.getElementById("cms-faq-form");
    if (faqForm) {
      faqForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const payload = {
          category: (document.getElementById("faq-cat") || {}).value || "General",
          question: (document.getElementById("faq-q") || {}).value || "",
          answer: (document.getElementById("faq-a") || {}).value || "",
        };
        const res = await apiFetch("/api/admin/cms", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "faq", payload: payload }),
        });
        if (res.ok) {
          notify("success", "FAQ added.");
          fetchCms();
        }
      });
    }

    const mediaForm = document.getElementById("cms-media-form");
    if (mediaForm) {
      mediaForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const url = ((document.getElementById("media-url") || {}).value || "").trim();
        const altText = ((document.getElementById("media-alt") || {}).value || "").trim();
        const res = await apiFetch("/api/admin/cms", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "media", payload: { url: url, altText: altText, category: "GALLERY" } }),
        });
        if (res.ok) {
          notify("success", "Gallery media asset added.");
          fetchCms();
        }
      });
    }

    const annForm = document.getElementById("cms-ann-form");
    if (annForm) {
      annForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const payload = {
          title: (document.getElementById("ann-title") || {}).value || "",
          severity: (document.getElementById("ann-sev") || {}).value || "INFO",
          message: (document.getElementById("ann-msg") || {}).value || "",
        };
        const res = await apiFetch("/api/admin/cms", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "announcement", payload: payload }),
        });
        if (res.ok) {
          notify("success", "Announcement published.");
          fetchCms();
        }
      });
    }

    mainEl.querySelectorAll("[data-cms-delete]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const entityType = btn.getAttribute("data-cms-delete");
        const id = btn.getAttribute("data-cms-id");
        const res = await apiFetch("/api/admin/cms?entityType=" + encodeURIComponent(entityType) + "&id=" + encodeURIComponent(id), {
          method: "DELETE",
        });
        if (res.ok) {
          notify("success", "Item deleted and revision logged.");
          fetchCms();
        }
      });
    });

    mainEl.querySelectorAll("[data-cat-fee]").forEach(function (inp) {
      inp.addEventListener("change", async function () {
        const id = inp.getAttribute("data-cat-fee");
        const val = Math.max(0, Number(inp.value) || 0);
        const res = await apiFetch("/api/admin/cms", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "category", id: id, payload: { feeAmountInr: val, requiresPayment: val > 0 } }),
        });
        if (res.ok) {
          notify("success", "Registration category fee updated.");
          fetchCms();
        }
      });
    });

    mainEl.querySelectorAll("[data-cat-toggle]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-cat-toggle");
        const isActive = btn.getAttribute("data-cat-active") === "1";
        const res = await apiFetch("/api/admin/cms", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entityType: "category", id: id, payload: { isActive: !isActive } }),
        });
        if (res.ok) {
          notify("success", "Registration category updated.");
          fetchCms();
        }
      });
    });

    // Superadmin Staff & Roles events
    const staffForm = document.getElementById("staff-create-form");
    if (staffForm) {
      staffForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const fullName = ((document.getElementById("st-name") || {}).value || "").trim();
        const username = ((document.getElementById("st-username") || {}).value || "").trim();
        const email = ((document.getElementById("st-email") || {}).value || "").trim();
        const password = (document.getElementById("st-pw") || {}).value || "";
        const roleCode = (document.getElementById("st-role") || {}).value || "ADMIN";
        const grantCms = Boolean((document.getElementById("st-grant-cms") || {}).checked);

        const res = await apiFetch("/api/admin/users", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            fullName: fullName,
            username: username,
            email: email,
            password: password,
            roleCode: roleCode,
            customPermissions: roleCode === "ADMIN" && grantCms ? ["cms.manage"] : [],
          }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Failed to create staff account.");
          return;
        }
        notify("success", roleCode + " account created for @" + (data.user && data.user.username ? data.user.username : username) + ".");
        fetchUsers();
      });
    }

    mainEl.querySelectorAll("[data-user-perms]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const uid = btn.getAttribute("data-user-perms");
        const u = state.staffUsers.find(function (x) { return x.id === uid; });
        if (!u) return;
        state.editingPermsUser = u;
        state.permsDraft = (u.customPermissions || []).slice();
        renderModals();
      });
    });

    mainEl.querySelectorAll("[data-user-reset-pw]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const uid = btn.getAttribute("data-user-reset-pw");
        const email = btn.getAttribute("data-user-email");
        const newPassword = window.prompt("Enter new password (min 8 characters) for " + email + ":");
        if (!newPassword) return;
        if (newPassword.length < 8) {
          notify("error", "Password must be at least 8 characters.");
          return;
        }
        const res = await apiFetch("/api/admin/users", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ userId: uid, newPassword: newPassword }),
        });
        if (res.ok) {
          notify("success", "Password reset for " + email + ".");
        } else {
          notify("error", "Failed to reset password.");
        }
      });
    });

    mainEl.querySelectorAll("[data-user-toggle]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const uid = btn.getAttribute("data-user-toggle");
        const isActive = btn.getAttribute("data-user-active") === "1";
        const res = await apiFetch("/api/admin/users", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ userId: uid, isActive: !isActive }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Failed to update account.");
          return;
        }
        notify("success", "User account status updated.");
        fetchUsers();
      });
    });

    // Security & MFA events (POST + CSRF; GET /api/auth/mfa is strictly read-only)
    const mfaInitBtn = document.getElementById("mfa-init-btn");
    if (mfaInitBtn) {
      mfaInitBtn.addEventListener("click", async function () {
        state.mfaLoading = true;
        renderMain();
        try {
          const res = await apiFetch("/api/auth/mfa", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "setup" }),
          });
          const data = await res.json().catch(function () { return {}; });
          if (res.ok && data.secret) {
            state.mfaSetupData = data;
          } else {
            notify("error", data.error || "Unable to initialize MFA setup.");
          }
        } finally {
          state.mfaLoading = false;
          renderMain();
        }
      });
    }

    const mfaReplaceForm = document.getElementById("mfa-replace-form");
    if (mfaReplaceForm) {
      mfaReplaceForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const currentPassword = (document.getElementById("mfa-reauth-pw") || {}).value || "";
        const currentCode = ((document.getElementById("mfa-reauth-code") || {}).value || "").trim();
        state.mfaLoading = true;
        renderMain();
        try {
          const res = await apiFetch("/api/auth/mfa", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "setup", currentPassword: currentPassword, currentCode: currentCode }),
          });
          const data = await res.json().catch(function () { return {}; });
          if (res.ok && data.secret) {
            state.mfaSetupData = data;
          } else {
            notify("error", data.error || "Re-authentication failed.");
          }
        } finally {
          state.mfaLoading = false;
          renderMain();
        }
      });
    }

    const mfaVerifyForm = document.getElementById("mfa-verify-form");
    if (mfaVerifyForm) {
      mfaVerifyForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const code = ((document.getElementById("mfa-verify-code") || {}).value || "").trim();
        const res = await apiFetch("/api/auth/mfa", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ code: code, action: "enable" }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
          notify("error", data.error || "Invalid verification code.");
          return;
        }
        state.mfaEnabled = true;
        state.mfaEnrollmentRequired = false;
        state.mfaSetupData = null;
        state.mfaRecoveryCodes = Array.isArray(data.recoveryCodes) ? data.recoveryCodes : null;
        notify("success", data.message || "Multi-factor authentication is now active on your account.");
        renderNav();
        renderMain();
      });
    }

    // Audit Logs events
    const auditSearchInput = document.getElementById("audit-search-input");
    const auditFilterBtn = document.getElementById("audit-filter-btn");
    const auditPrevBtn = document.getElementById("audit-prev-btn");
    const auditNextBtn = document.getElementById("audit-next-btn");

    if (auditSearchInput) {
      auditSearchInput.addEventListener("input", function (e) {
        state.auditSearch = e.target.value;
      });
      auditSearchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") fetchAuditLogs(1);
      });
    }
    if (auditFilterBtn) {
      auditFilterBtn.addEventListener("click", function () {
        fetchAuditLogs(1);
      });
    }
    if (auditPrevBtn) {
      auditPrevBtn.addEventListener("click", function () {
        if (state.auditPagination.page > 1) {
          fetchAuditLogs(state.auditPagination.page - 1);
        }
      });
    }
    if (auditNextBtn) {
      auditNextBtn.addEventListener("click", function () {
        if (state.auditPagination.page < state.auditPagination.totalPages) {
          fetchAuditLogs(state.auditPagination.page + 1);
        }
      });
    }
  }

  // Global Shell Events
  const openBtn = document.getElementById("admin-sidebar-open");
  const closeBtn = document.getElementById("admin-sidebar-close");
  if (openBtn) openBtn.addEventListener("click", openMobileSidebar);
  if (closeBtn) closeBtn.addEventListener("click", closeMobileSidebar);
  if (backdropEl) backdropEl.addEventListener("click", closeMobileSidebar);

  const refreshBtn = document.getElementById("admin-refresh-btn");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      loadTabData(state.activeTab);
    });
  }

  const logoutBtn = document.getElementById("admin-logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", async function () {
      await apiFetch("/api/auth/logout", { method: "POST" }).catch(function () { return null; });
      window.location.href = "/login";
    });
  }

  // Initialize Portal
  renderNav();
  renderMain();
  if (!state.mfaEnrollmentRequired) {
    fetchOverview();
  }
})();
