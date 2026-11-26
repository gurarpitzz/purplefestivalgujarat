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
      case "ASSIGNED":
      case "DELIVERED":
      case "PROVIDER_ACCEPTED":
      case "CAPTURED_LOCAL":
      case "SENT":
        return "bg-emerald-50 text-emerald-800 border-emerald-200";
      case "SUBMITTED":
      case "UNDER_REVIEW":
      case "REQUESTED":
      case "IN_PROGRESS":
      case "PROPOSED":
      case "QUEUED":
      case "PROCESSING":
      case "WARNING":
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
      case "CRITICAL":
        return "bg-rose-50 text-rose-800 border-rose-200";
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  }

  function formatDateShort(iso) {
    if (!iso) return "—";
    try {
      const normalized = normIso(iso);
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

  Object.assign(ICONS, {
    ATTENDEES: ICONS.REGISTRATIONS,
    PROGRAMME: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>',
    ASSIGNMENTS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>',
    COMMS: '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
  });

  function canProgramme() {
    return can("cms.manage") || can("programme.manage");
  }
  function canAssignDuty() {
    return can("volunteers.review") || can("sli.review");
  }
  function canAnnounce() {
    return can("cms.manage") || can("communications.manage");
  }

  const NAV_ITEMS = [
    { id: "OVERVIEW", label: "Overview & Analytics", visible: true },
    { id: "ATTENDEES", label: "Attendees", visible: can("registrations.read.all") },
    { id: "VOLUNTEERS", label: "Volunteers", visible: can("registrations.read.all") },
    { id: "SLI", label: "SLI Professionals", visible: can("registrations.read.all") },
    { id: "ACCOMMODATIONS", label: "Accessibility", visible: can("accommodations.read") },
    { id: "ATTENDANCE", label: "Attendance / Check-In", visible: can("attendance.read.full") },
    { id: "PROGRAMME", label: "Programme & Venues", visible: canProgramme() },
    { id: "ASSIGNMENTS", label: "Duty Assignments", visible: canAssignDuty() },
    { id: "COMMS", label: "Communications Center", visible: can("registrations.read.all") },
    { id: "COUPONS", label: "Coupons", visible: can("coupons.manage") },
    { id: "CMS", label: "Website CMS", visible: can("cms.manage") },
    { id: "USERS", label: "Staff & Permission Profiles", visible: isSuperadmin },
    { id: "SECURITY", label: "Security & MFA", visible: true },
    { id: "AUDIT_LOGS", label: "Audit Logs", visible: can("audit_logs.read") },
  ].filter(function (i) {
    return i.visible;
  });

  const PEOPLE_TABS = { ATTENDEES: "ATTENDEE", VOLUNTEERS: "VOLUNTEER", SLI: "SLI" };
  const PEOPLE_META = {
    ATTENDEE: { title: "Attendees", blurb: "All festival attendees, delegates, students, allies and institutional participants. Server-side pagination: every record is reachable." },
    VOLUNTEER: { title: "Volunteers", blurb: "Review volunteer applications and see who still needs a duty assignment." },
    SLI: { title: "SLI Professionals", blurb: "Verify sign language interpreters (RCI CRR, experience, video demo) and track their duty assignments." },
  };
  const REG_STATUSES = ["SUBMITTED", "UNDER_REVIEW", "ADDITIONAL_INFO_REQUIRED", "APPROVED", "CONFIRMED", "WAITLISTED", "REJECTED", "CANCELLED"];
  const PROGRAMME_STATUSES = ["DRAFT", "PROPOSED", "CONFIRMED", "PUBLISHED", "CANCELLED"];
  const VENUE_STATUSES = ["PENDING", "PROPOSED", "CONFIRMED", "ARCHIVED"];
  const NOTIF_STATUSES = ["FAILED", "QUEUED", "PROCESSING", "PROVIDER_ACCEPTED", "DELIVERED", "CAPTURED_LOCAL"];
  const PAGE_SIZES = [25, 50, 100, 200];

  function newPagination(size) {
    return { page: 1, pageSize: size || 25, total: 0, totalPages: 1 };
  }
  function newListState(extra) {
    return Object.assign(
      {
        search: "",
        status: "",
        assignmentStatus: "",
        checkin: "",
        subCategory: "",
        page: 1,
        pageSize: 25,
        items: [],
        pagination: newPagination(25),
        loading: false,
        loaded: false,
        error: "",
        seq: 0,
      },
      extra || {}
    );
  }

  // Portal State
  const mustEnrollMfa = Boolean(currentUser.mfaEnrollmentRequired);
  const state = {
    activeTab: mustEnrollMfa ? "SECURITY" : "OVERVIEW",
    mfaEnrollmentRequired: mustEnrollMfa,
    mfaRecoveryCodes: null,
    chartViewMode: "chart",
    overview: null,
    overviewLoading: false,
    overviewError: "",

    // Attendees / Volunteers / SLI (server-side paginated)
    people: {
      ATTENDEE: newListState(),
      VOLUNTEER: newListState(),
      SLI: newListState(),
    },

    // Accessibility
    accommItems: [],
    accommTotal: 0,
    accommLoading: false,
    accommError: "",
    accommStatusFilter: "",

    // Programme & Venues
    prog: {
      sub: "sessions",
      day: "",
      status: "",
      sessions: [],
      items: [],
      loading: false,
      error: "",
      venues: [],
      venueSearch: "",
      venueStatus: "",
      venuesLoading: false,
      venuesError: "",
    },

    // Duty assignments
    asg: newListState({ category: "", date: "", sessionId: "", status: "", pageSize: 25 }),

    // Communications
    comms: { sub: "queue", status: "", eventType: "", page: 1, pageSize: 25, items: [], pagination: newPagination(25), loading: false, error: "" },
    ann: { items: [], loading: false, error: "" },

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

    // Staff & permission profiles (SUPERADMIN)
    staff: {
      sub: "staff",
      users: newListState({ role: "" }),
      profiles: [],
      profileOptions: [],
      availablePermissions: [],
      forbiddenForAdmin: [],
      forbiddenPermissions: [],
      profilesLoading: false,
      profilesError: "",
    },

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
    const isWarning = type === "warning";
    toastRegionEl.innerHTML =
      '<div role="' + (isSuccess || isWarning ? "status" : "alert") + '" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-xs ' +
      (isSuccess
        ? "bg-emerald-50 border-emerald-200 text-emerald-900"
        : isWarning
          ? "bg-amber-50 border-amber-200 text-amber-900"
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
        switchTab(tabId, true);
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


  function switchTab(tabId, focusHeading) {
    if (!NAV_ITEMS.some(function (i) { return i.id === tabId; })) return;
    state.activeTab = tabId;
    renderNav();
    renderMain();
    loadTabData(tabId);
    if (focusHeading && tabTitleEl) {
      tabTitleEl.setAttribute("tabindex", "-1");
      tabTitleEl.focus();
    }
  }

  function loadTabData(tabId) {
    if (tabId === "OVERVIEW" || tabId === "ATTENDANCE") fetchOverview();
    else if (PEOPLE_TABS[tabId]) fetchPeople(PEOPLE_TABS[tabId]);
    else if (tabId === "ACCOMMODATIONS") fetchAccommodations();
    else if (tabId === "PROGRAMME") { fetchProgramme(); fetchVenues(); }
    else if (tabId === "ASSIGNMENTS") { fetchAssignments(); ensureProgrammeRefs(); }
    else if (tabId === "COMMS") { if (state.comms.sub === "announcements" && canAnnounce()) fetchAnnouncements(); else fetchNotifications(); }
    else if (tabId === "COUPONS") fetchCoupons();
    else if (tabId === "CMS") fetchCms();
    else if (tabId === "USERS") fetchStaffTab();
    else if (tabId === "AUDIT_LOGS") fetchAuditLogs(1);
  }

  // =========================================================================
  // SHARED HELPERS (api wrapper, form/markup builders, pagination)
  // =========================================================================

  async function api(path, method, body) {
    const opts = { method: method || "GET" };
    if (body !== undefined) {
      opts.headers = { "Content-Type": "application/json" };
      opts.body = JSON.stringify(body);
    }
    try {
      const res = await apiFetch(path, opts);
      const data = await res.json().catch(function () { return {}; });
      return { ok: res.ok, status: res.status, data: data || {} };
    } catch (e) {
      return { ok: false, status: 0, data: { error: "Network error. Please check your connection and try again." } };
    }
  }

  const C = {
    input: "w-full px-3 py-2 text-sm border border-[#DDD5EA] rounded-lg bg-white",
    btn: "px-4 py-2 rounded-lg text-xs font-semibold bg-[#6B4E96] hover:bg-[#5A3F82] text-white disabled:opacity-50",
    btn2: "px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D8CCE9] text-[#4C3273] hover:bg-[#F1EBF9] disabled:opacity-50",
    danger: "px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900 disabled:opacity-50",
    ok: "px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-700 hover:bg-emerald-800 text-white disabled:opacity-50",
    card: "bg-white border border-[#E5DFEF] rounded-xl",
    label: "block text-xs font-semibold text-[#4A415C]",
    th: "px-4 py-3",
    td: "px-4 py-3",
    thead: "bg-[#F8F6FC] border-b border-[#E5DFEF] text-xs uppercase text-[#5C536E]",
    tbody: "divide-y divide-[#F0EBF8]",
    muted: "text-xs text-[#645C75]",
  };

  function val(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || "").trim() : "";
  }
  function checked(id) {
    const el = document.getElementById(id);
    return Boolean(el && el.checked);
  }

  function opts(list, value) {
    let found = false;
    let html = list
      .map(function (it) {
        const v = Array.isArray(it) ? it[0] : it;
        const l = Array.isArray(it) ? it[1] : it;
        const sel = String(v) === String(value === null || value === undefined ? "" : value);
        if (sel) found = true;
        return '<option value="' + esc(v) + '"' + (sel ? " selected" : "") + ">" + esc(l) + "</option>";
      })
      .join("");
    if (value && !found) html += '<option value="' + esc(value) + '" selected>' + esc(value) + "</option>";
    return html;
  }
  function selectEl(id, label, list, value, extra) {
    return '<select id="' + id + '" aria-label="' + esc(label) + '" class="' + C.input + '" ' + (extra || "") + ">" + opts(list, value) + "</select>";
  }
  function inputEl(id, value, o) {
    o = o || {};
    return (
      '<input id="' + id + '" type="' + (o.type || "text") + '" value="' + esc(value) + '"' +
      (o.required ? " required" : "") +
      (o.placeholder ? ' placeholder="' + esc(o.placeholder) + '"' : "") +
      (o.autocomplete ? ' autocomplete="' + o.autocomplete + '"' : "") +
      (o.attrs ? " " + o.attrs : "") +
      ' class="' + C.input + '" />'
    );
  }
  function textareaEl(id, value, rows, o) {
    o = o || {};
    return '<textarea id="' + id + '" rows="' + (rows || 3) + '"' + (o.required ? " required" : "") + (o.placeholder ? ' placeholder="' + esc(o.placeholder) + '"' : "") + ' class="' + C.input + '">' + esc(value) + "</textarea>";
  }
  function field(label, id, control, hint, required) {
    return (
      '<div class="space-y-1"><label for="' + id + '" class="' + C.label + '">' + esc(label) + (required ? ' <span aria-hidden="true">*</span>' : "") + "</label>" +
      control +
      (hint ? '<p class="text-[11px] text-[#645C75]">' + esc(hint) + "</p>" : "") +
      "</div>"
    );
  }
  function checkboxEl(id, label, isChecked) {
    return '<label class="flex items-center gap-2 text-xs font-medium text-[#4A415C]"><input id="' + id + '" type="checkbox"' + (isChecked ? " checked" : "") + " /> " + esc(label) + "</label>";
  }
  function badge(status, text) {
    return '<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ' + statusBadgeClass(status) + '">' + esc(text || status || "—") + "</span>";
  }
  function confirmationBadge(status) {
    const s = String(status || "").toUpperCase();
    if (s === "DRAFT" || s === "PROPOSED" || s === "PENDING") return badge(s, s + " — not confirmed");
    return badge(s);
  }
  function errorBox(msg) {
    return '<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900">' + esc(msg) + "</div>";
  }
  function emptyRow(cols, text) {
    return '<tr><td colspan="' + cols + '" class="px-4 py-8 text-center text-sm text-[#6E6580]">' + esc(text) + "</td></tr>";
  }
  function section(title, inner) {
    return '<section class="space-y-2 pt-3 border-t border-[#ECE7F5]"><h4 class="text-xs font-bold text-[#4A415C] uppercase tracking-wide">' + esc(title) + "</h4>" + inner + "</section>";
  }
  function pageHeader(title, blurb, rightHtml) {
    return (
      '<div class="' + C.card + ' p-4 flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-sm font-bold text-[#17151C]">' +
      esc(title) + '</h3><p class="' + C.muted + '">' + esc(blurb) + "</p></div>" + (rightHtml || "") + "</div>"
    );
  }
  function subTabs(group, current, list) {
    return (
      '<div class="flex flex-wrap gap-2 ' + C.card + ' p-2" role="group" aria-label="Sections">' +
      list
        .map(function (pair) {
          const on = current === pair[0];
          return '<button type="button" data-act="subtab" data-group="' + group + '" data-key="' + esc(pair[0]) + '" aria-pressed="' + (on ? "true" : "false") + '" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold ' + (on ? "bg-[#6B4E96] text-white" : "text-[#4D4263] hover:bg-[#F3EEF9]") + '">' + esc(pair[1]) + "</button>";
        })
        .join("") +
      "</div>"
    );
  }

  const SENSITIVE_KEY = /password|secret|token|hash|mfa|recovery|qrpayload|salt/i;
  function humanize(k) {
    return String(k).replace(/_/g, " ").replace(/([a-z0-9])([A-Z])/g, "$1 $2").replace(/^./, function (c) { return c.toUpperCase(); });
  }
  function fmtValueHtml(k, v) {
    if (v === null || v === undefined || v === "") return "—";
    if (typeof v === "boolean") return v ? "Yes" : "No";
    if (Array.isArray(v)) {
      return v.length ? esc(v.map(function (x) { return x && typeof x === "object" ? JSON.stringify(x) : String(x); }).join(", ")) : "—";
    }
    if (typeof v === "object") return esc(JSON.stringify(v));
    if (/url$|link$/i.test(k)) {
      const h = safeHref(String(v));
      if (h) return '<a href="' + esc(h) + '" target="_blank" rel="noopener noreferrer" class="text-[#6B4E96] font-semibold hover:underline">' + esc(h) + "</a>";
    }
    if (/(At|Date)$/.test(k) && /^\d{4}-\d{2}-\d{2}/.test(String(v))) return esc(formatDateShort(v));
    return esc(v);
  }
  function kvList(obj, skip) {
    if (!obj || typeof obj !== "object") return '<p class="' + C.muted + '">No details recorded.</p>';
    const rows = Object.keys(obj).filter(function (k) {
      if (SENSITIVE_KEY.test(k)) return false;
      if (k === "id" || /Id$/.test(k)) return false;
      if (skip && skip.indexOf(k) >= 0) return false;
      return true;
    });
    if (!rows.length) return '<p class="' + C.muted + '">No details recorded.</p>';
    return (
      '<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-xs">' +
      rows.map(function (k) {
        return '<div><dt class="font-semibold text-[#4A415C]">' + esc(humanize(k)) + '</dt><dd class="text-[#17151C] break-words">' + fmtValueHtml(k, obj[k]) + "</dd></div>";
      }).join("") +
      "</dl>"
    );
  }

  function normIso(s) {
    s = String(s);
    if (!s.includes("T")) s = s.replace(" ", "T");
    if (!/(Z|[+-]\d{2}:?\d{2})$/.test(s)) s += "+05:30";
    return s;
  }
  function toIstInput(v) {
    if (!v) return "";
    const d = new Date(normIso(v));
    if (isNaN(d.getTime())) return "";
    const p = {};
    new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Kolkata", year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
      .formatToParts(d)
      .forEach(function (x) { p[x.type] = x.value; });
    return p.year + "-" + p.month + "-" + p.day + "T" + p.hour + ":" + p.minute;
  }
  function fromIstInput(v) {
    return v ? v + ":00+05:30" : null;
  }
  function fmtTimeIst(v) {
    if (!v) return "—";
    const d = new Date(normIso(v));
    if (isNaN(d.getTime())) return String(v);
    return new Intl.DateTimeFormat("en-IN", { timeZone: "Asia/Kolkata", hour: "2-digit", minute: "2-digit", hour12: false }).format(d);
  }
  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") return window.crypto.randomUUID();
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      const r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
    });
  }
  function splitList(s) {
    return String(s || "").split(/[\n,]/).map(function (x) { return x.trim(); }).filter(Boolean);
  }

  function renderPager(key, p, label) {
    const total = Math.max(1, Number(p.totalPages) || 1);
    const cur = Math.min(total, Math.max(1, Number(p.page) || 1));
    const set = {};
    [1, total, cur - 2, cur - 1, cur, cur + 1, cur + 2].forEach(function (n) { if (n >= 1 && n <= total) set[n] = true; });
    const nums = Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
    let html =
      '<nav aria-label="' + esc(label || "Pagination") + '" class="px-4 py-3 border-t border-[#ECE7F5] flex flex-wrap items-center justify-between gap-2 text-xs text-[#5E5470]">' +
      "<span>Page " + cur + " of " + total + " (" + esc(p.total || 0) + " total)</span>" +
      '<div class="flex flex-wrap items-center gap-1">' +
      '<button type="button" data-act="page" data-pager="' + esc(key) + '" data-page="' + (cur - 1) + '" ' + (cur <= 1 ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">&larr; Prev</button>';
    let prev = 0;
    nums.forEach(function (n) {
      if (prev && n - prev > 1) html += '<span class="px-1" aria-hidden="true">…</span>';
      html +=
        '<button type="button" data-act="page" data-pager="' + esc(key) + '" data-page="' + n + '" aria-label="Page ' + n + '"' + (n === cur ? ' aria-current="page"' : "") +
        ' class="px-2.5 py-1 rounded border ' + (n === cur ? "border-[#DDD5EA] bg-[#6B4E96] text-white" : "border-[#DDD5EA] hover:bg-[#F3EEF9]") + '">' + n + "</button>";
      prev = n;
    });
    html +=
      '<button type="button" data-act="page" data-pager="' + esc(key) + '" data-page="' + (cur + 1) + '" ' + (cur >= total ? "disabled " : "") + 'class="px-2.5 py-1 rounded border border-[#DDD5EA] disabled:opacity-40">Next &rarr;</button>' +
      "</div></nav>";
    return html;
  }

  // =========================================================================
  // MODAL INFRASTRUCTURE (focus trap, Escape close, stacked dialogs)
  // =========================================================================

  const modalStack = [];
  let modalSeq = 0;
  const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function modalOf(el) {
    const c = el && el.closest ? el.closest("[data-modal-uid]") : null;
    if (!c) return null;
    const uid = c.getAttribute("data-modal-uid");
    return modalStack.find(function (m) { return String(m.uid) === uid; }) || null;
  }
  function modalContainer(m) {
    return modalRootEl.querySelector('[data-modal-uid="' + m.uid + '"]');
  }
  function visibleFocusables(c) {
    return Array.prototype.filter.call(c.querySelectorAll(FOCUSABLE), function (el) {
      return el.getClientRects().length > 0;
    });
  }
  function dialogHtml(m) {
    const side = m.size === "side";
    const w = m.size === "sm" ? "max-w-md" : m.size === "lg" ? "max-w-3xl" : m.size === "xl" ? "max-w-5xl" : side ? "max-w-2xl" : "max-w-xl";
    return (
      '<div data-modal-overlay class="fixed inset-0 z-50 bg-black/55 flex ' + (side ? "items-center justify-end" : "items-center justify-center p-4") + '">' +
      '<div role="dialog" aria-modal="true" aria-labelledby="modal-title-' + m.uid + '" tabindex="-1"' + (side ? "" : ' style="max-height:90vh"') + ' class="bg-white w-full ' + w + " " + (side ? "h-full" : "rounded-xl") + ' overflow-y-auto shadow-2xl p-6 space-y-4">' +
      '<div class="flex items-start justify-between gap-3 border-b border-[#ECE7F5] pb-3">' +
      '<h3 id="modal-title-' + m.uid + '" class="text-base font-bold text-[#17151C]">' + esc(m.title) + "</h3>" +
      '<button type="button" data-act="modal-close" class="p-1.5 rounded-lg hover:bg-[#F3EEF9] text-lg leading-none" aria-label="Close dialog">&times;</button>' +
      "</div>" +
      '<div data-modal-body class="space-y-4">' + m.body(m) + "</div>" +
      "</div></div>"
    );
  }
  function paintModal(m, c) {
    const ae = document.activeElement;
    const keepId = ae && c.contains(ae) && ae.id ? ae.id : "";
    const oldDlg = c.querySelector('[role="dialog"]');
    const scrollTop = oldDlg ? oldDlg.scrollTop : 0;
    c.innerHTML = dialogHtml(m);
    if (window.PFGQrCode && typeof window.PFGQrCode.renderAll === "function") window.PFGQrCode.renderAll(c);
    if (typeof m.bind === "function") m.bind(c, m);
    const dlg = c.querySelector('[role="dialog"]');
    if (dlg) dlg.scrollTop = scrollTop;
    if (keepId) {
      const el = document.getElementById(keepId);
      if (el && c.contains(el)) el.focus();
    }
  }
  function refreshModal(m) {
    const c = modalContainer(m);
    if (c) paintModal(m, c);
  }
  function syncModals() {
    Array.prototype.slice.call(modalRootEl.children).forEach(function (c) {
      const uid = c.getAttribute("data-modal-uid");
      if (!modalStack.some(function (m) { return String(m.uid) === uid; })) c.remove();
    });
    modalStack.forEach(function (m) {
      if (!modalContainer(m)) {
        const c = document.createElement("div");
        c.setAttribute("data-modal-uid", String(m.uid));
        modalRootEl.appendChild(c);
        paintModal(m, c);
      }
    });
    const top = modalStack[modalStack.length - 1];
    Array.prototype.slice.call(modalRootEl.children).forEach(function (c) {
      const isTop = top && String(top.uid) === c.getAttribute("data-modal-uid");
      if (isTop) {
        c.removeAttribute("inert");
        c.removeAttribute("aria-hidden");
      } else {
        c.setAttribute("inert", "");
        c.setAttribute("aria-hidden", "true");
      }
    });
    Array.prototype.forEach.call(document.querySelectorAll("body > div"), function (d) {
      if (d === modalRootEl) return;
      if (modalStack.length) d.setAttribute("inert", "");
      else d.removeAttribute("inert");
    });
    document.body.style.overflow = modalStack.length ? "hidden" : "";
    if (top) {
      const c = modalContainer(top);
      if (c && !c.contains(document.activeElement)) {
        const first = c.querySelector("[data-modal-body] " + FOCUSABLE.split(",").join(",[data-modal-body] "));
        const dlg = c.querySelector('[role="dialog"]');
        if (first) first.focus();
        else if (dlg) dlg.focus();
      }
    }
  }
  function openModal(cfg) {
    cfg.uid = ++modalSeq;
    cfg.opener = document.activeElement;
    modalStack.push(cfg);
    syncModals();
    return cfg;
  }
  function closeModal(m) {
    m = m || modalStack[modalStack.length - 1];
    if (!m) return;
    const i = modalStack.indexOf(m);
    if (i < 0) return;
    modalStack.splice(i, 1);
    syncModals();
    if (!modalStack.length && m.opener && document.contains(m.opener) && typeof m.opener.focus === "function") {
      m.opener.focus();
    }
    if (typeof m.onClose === "function") m.onClose(m);
  }
  document.addEventListener("keydown", function (e) {
    if (!modalStack.length) return;
    const top = modalStack[modalStack.length - 1];
    if (e.key === "Escape") {
      if (!top.busy) {
        e.preventDefault();
        closeModal(top);
      }
      return;
    }
    if (e.key === "Tab") {
      const c = modalContainer(top);
      if (!c) return;
      const f = visibleFocusables(c);
      const dlg = c.querySelector('[role="dialog"]');
      if (!f.length) {
        e.preventDefault();
        if (dlg) dlg.focus();
        return;
      }
      const first = f[0];
      const last = f[f.length - 1];
      const ae = document.activeElement;
      if (e.shiftKey && (ae === first || ae === dlg || !c.contains(ae))) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && (ae === last || !c.contains(ae))) {
        e.preventDefault();
        first.focus();
      }
    }
  });
  if (modalRootEl) {
    modalRootEl.addEventListener("mousedown", function (e) {
      if (e.target && e.target.hasAttribute && e.target.hasAttribute("data-modal-overlay")) {
        const m = modalOf(e.target);
        if (m && !m.busy && m === modalStack[modalStack.length - 1]) closeModal(m);
      }
    });
  }

  // Reason / confirmation prompt. Resolves {value} on confirm, or null when cancelled.
  function promptDialog(o) {
    return new Promise(function (resolve) {
      openModal({
        title: o.title,
        size: "sm",
        isPrompt: true,
        required: Boolean(o.required),
        hasInput: o.input !== false,
        resolve: resolve,
        done: false,
        error: "",
        body: function (m) {
          return (
            '<form data-form="prompt" class="space-y-3">' +
            (o.message ? '<p class="text-sm text-[#4A415C]">' + esc(o.message) + "</p>" : "") +
            (m.hasInput ? field(o.label || "Reason", "prompt-input", textareaEl("prompt-input", o.defaultValue || "", 3, { required: o.required }), o.hint, o.required) : "") +
            (m.error ? errorBox(m.error) : "") +
            '<div class="flex justify-end gap-2"><button type="button" data-act="modal-close" class="' + C.btn2 + '">Cancel</button>' +
            '<button type="submit" class="' + (o.danger ? "px-4 py-2 rounded-lg text-xs font-semibold bg-rose-100 hover:bg-rose-200 text-rose-900" : C.btn) + '">' + esc(o.confirmLabel || "Confirm") + "</button></div></form>"
          );
        },
        onClose: function (m) {
          if (!m.done) resolve(null);
        },
      });
    });
  }

  // Delegated event registries
  const ACTIONS = {};
  const FORMS = {};
  const CHANGES = {};
  function delegate(root) {
    if (!root) return;
    root.addEventListener("click", function (e) {
      const el = e.target.closest ? e.target.closest("[data-act]") : null;
      if (!el || !root.contains(el) || el.disabled) return;
      const fn = ACTIONS[el.getAttribute("data-act")];
      if (fn) fn(el, e);
    });
    root.addEventListener("submit", function (e) {
      const f = e.target.closest ? e.target.closest("[data-form]") : null;
      if (!f) return;
      const fn = FORMS[f.getAttribute("data-form")];
      if (fn) {
        e.preventDefault();
        fn(f, e);
      }
    });
    root.addEventListener("change", function (e) {
      const el = e.target.closest ? e.target.closest("[data-change]") : null;
      if (!el) return;
      const fn = CHANGES[el.getAttribute("data-change")];
      if (fn) fn(el, e);
    });
  }
  delegate(mainEl);
  delegate(modalRootEl);

  ACTIONS["modal-close"] = function (el) {
    const m = modalOf(el);
    if (m && !m.busy) closeModal(m);
  };
  FORMS.prompt = function (form) {
    const m = modalOf(form);
    if (!m) return;
    const input = form.querySelector("#prompt-input");
    const v = input ? input.value.trim() : "";
    if (m.required && !v) {
      m.error = "This field is required.";
      refreshModal(m);
      return;
    }
    m.done = true;
    closeModal(m);
    m.resolve({ value: v });
  };
  ACTIONS.page = function (el) {
    const fn = PAGERS[el.getAttribute("data-pager")];
    const n = parseInt(el.getAttribute("data-page"), 10);
    if (fn && n >= 1) fn(n);
  };
  ACTIONS.subtab = function (el) {
    const group = el.getAttribute("data-group");
    const key = el.getAttribute("data-key");
    if (group === "prog") state.prog.sub = key;
    else if (group === "comms") state.comms.sub = key;
    else if (group === "staff") state.staff.sub = key;
    renderMain();
    loadTabData(state.activeTab);
  };
  const PAGERS = {
    "people:ATTENDEE": function (n) { fetchPeople("ATTENDEE", { page: n }); },
    "people:VOLUNTEER": function (n) { fetchPeople("VOLUNTEER", { page: n }); },
    "people:SLI": function (n) { fetchPeople("SLI", { page: n }); },
    asg: function (n) { fetchAssignments({ page: n }); },
    notif: function (n) { fetchNotifications({ page: n }); },
    audit: function (n) { fetchAuditLogs(n); },
    staff: function (n) { fetchStaffUsers({ page: n }); },
  };

  // =========================================================================
  // API FETCHERS
  // =========================================================================

  async function fetchPeople(cat, patch) {
    const st = state.people[cat];
    if (!st) return;
    if (patch) Object.assign(st, patch);
    st.loading = true;
    st.error = "";
    const seq = ++st.seq;
    renderMain();
    const params = new URLSearchParams();
    params.set("page", String(st.page || 1));
    params.set("pageSize", String(st.pageSize || 25));
    params.set("category", cat === "ATTENDEE" && st.subCategory ? st.subCategory : cat);
    if (st.search) params.set("search", st.search);
    if (st.status) params.set("status", st.status);
    if (st.assignmentStatus && cat !== "ATTENDEE") params.set("assignmentStatus", st.assignmentStatus);
    if (st.checkin && cat === "ATTENDEE") params.set("checkinStatus", st.checkin);
    const r = await api("/api/admin/registrations?" + params.toString());
    if (seq !== st.seq) return;
    st.loading = false;
    if (r.ok) {
      st.items = r.data.items || [];
      st.pagination = r.data.pagination || newPagination(st.pageSize);
      st.loaded = true;
      st.page = st.pagination.page || st.page;
      if (st.items.length === 0 && st.pagination.totalPages >= 1 && st.page > st.pagination.totalPages) {
        fetchPeople(cat, { page: st.pagination.totalPages });
        return;
      }
    } else {
      st.items = [];
      st.error = r.data.error || "Could not load records (HTTP " + r.status + ").";
    }
    renderMain();
  }

  async function fetchAccommodations() {
    state.accommLoading = true;
    state.accommError = "";
    renderMain();
    const qs = state.accommStatusFilter ? "?status=" + encodeURIComponent(state.accommStatusFilter) : "";
    const r = await api("/api/admin/accommodations" + qs);
    state.accommLoading = false;
    if (r.ok) {
      state.accommItems = r.data.items || [];
      state.accommTotal = r.data.total !== undefined ? r.data.total : state.accommItems.length;
    } else {
      state.accommItems = [];
      state.accommError = r.data.error || "Could not load accessibility requests.";
    }
    renderMain();
  }

  async function fetchProgramme() {
    if (!canProgramme()) return;
    const pr = state.prog;
    pr.loading = true;
    pr.error = "";
    renderMain();
    const params = new URLSearchParams();
    if (pr.day) params.set("day", pr.day);
    if (pr.status) params.set("status", pr.status);
    const r = await api("/api/admin/programme" + (params.toString() ? "?" + params.toString() : ""));
    pr.loading = false;
    if (r.ok) {
      pr.sessions = r.data.sessions || [];
      pr.items = r.data.items || [];
    } else {
      pr.error = r.data.error || "Could not load programme.";
    }
    renderMain();
  }

  async function ensureProgrammeRefs() {
    if (!state.prog.sessions.length) {
      const r = await api("/api/admin/programme");
      if (r.ok) state.prog.sessions = r.data.sessions || [];
    }
    if (!state.prog.venues.length) await fetchVenues();
    if (state.activeTab === "ASSIGNMENTS") renderMain();
  }

  async function fetchVenues() {
    const pr = state.prog;
    pr.venuesLoading = true;
    pr.venuesError = "";
    const params = new URLSearchParams();
    if (pr.venueStatus) params.set("status", pr.venueStatus);
    if (pr.venueStatus === "ARCHIVED") params.set("includeArchived", "1");
    if (pr.venueSearch) params.set("search", pr.venueSearch);
    const r = await api("/api/admin/venues" + (params.toString() ? "?" + params.toString() : ""));
    pr.venuesLoading = false;
    if (r.ok) pr.venues = r.data.items || [];
    else pr.venuesError = r.data.error || "Could not load venues.";
    if (state.activeTab === "PROGRAMME") renderMain();
  }

  async function fetchAssignments(patch) {
    const st = state.asg;
    if (patch) Object.assign(st, patch);
    st.loading = true;
    st.error = "";
    const seq = ++st.seq;
    renderMain();
    const params = new URLSearchParams();
    params.set("page", String(st.page || 1));
    params.set("pageSize", String(st.pageSize || 25));
    if (st.category) params.set("category", st.category);
    if (st.status) params.set("status", st.status);
    if (st.date) params.set("date", st.date);
    if (st.sessionId) params.set("sessionId", st.sessionId);
    if (st.registrationId) params.set("registrationId", st.registrationId);
    const r = await api("/api/admin/assignments?" + params.toString());
    if (seq !== st.seq) return;
    st.loading = false;
    if (r.ok) {
      st.items = r.data.items || [];
      st.pagination = r.data.pagination || newPagination(st.pageSize);
      st.loaded = true;
    } else {
      st.items = [];
      st.error = r.data.error || "Could not load assignments.";
    }
    renderMain();
  }

  async function fetchNotifications(patch) {
    const cm = state.comms;
    if (patch) Object.assign(cm, patch);
    cm.loading = true;
    cm.error = "";
    renderMain();
    const params = new URLSearchParams();
    params.set("page", String(cm.page || 1));
    params.set("pageSize", String(cm.pageSize || 25));
    if (cm.status) params.set("status", cm.status);
    if (cm.eventType) params.set("eventType", cm.eventType);
    const r = await api("/api/admin/notifications?" + params.toString());
    cm.loading = false;
    if (r.ok) {
      cm.items = r.data.items || [];
      cm.pagination = r.data.pagination || newPagination(cm.pageSize);
    } else {
      cm.items = [];
      cm.error = r.data.error || "Could not load the notification queue.";
    }
    renderMain();
  }

  async function fetchAnnouncements() {
    state.ann.loading = true;
    state.ann.error = "";
    renderMain();
    const r = await api("/api/admin/announcements");
    state.ann.loading = false;
    if (r.ok) state.ann.items = r.data.items || [];
    else state.ann.error = r.data.error || "Could not load announcements.";
    renderMain();
  }

  async function fetchStaffUsers(patch) {
    if (!isSuperadmin) return;
    const st = state.staff.users;
    if (patch) Object.assign(st, patch);
    st.loading = true;
    st.error = "";
    const seq = ++st.seq;
    renderMain();
    const params = new URLSearchParams();
    params.set("page", String(st.page || 1));
    params.set("pageSize", String(st.pageSize || 25));
    if (st.role) params.set("role", st.role);
    if (st.search) params.set("search", st.search);
    const r = await api("/api/admin/users?" + params.toString());
    if (seq !== st.seq) return;
    st.loading = false;
    if (r.ok) {
      st.items = r.data.items || [];
      st.pagination = r.data.pagination || newPagination(st.pageSize);
      state.staff.availablePermissions = r.data.availablePermissions || state.staff.availablePermissions;
      state.staff.profileOptions = r.data.profiles || state.staff.profileOptions;
      state.staff.forbiddenForAdmin = r.data.forbiddenForAdmin || state.staff.forbiddenForAdmin;
      st.loaded = true;
    } else {
      st.items = [];
      st.error = r.data.error || "Could not load staff accounts.";
    }
    renderMain();
  }

  async function fetchProfiles() {
    if (!isSuperadmin) return;
    const sf = state.staff;
    sf.profilesLoading = true;
    sf.profilesError = "";
    renderMain();
    const r = await api("/api/admin/permission-profiles");
    sf.profilesLoading = false;
    if (r.ok) {
      sf.profiles = r.data.items || [];
      sf.availablePermissions = r.data.availablePermissions || sf.availablePermissions;
      sf.forbiddenPermissions = r.data.forbiddenPermissions || sf.forbiddenPermissions;
    } else {
      sf.profilesError = r.data.error || "Could not load permission profiles.";
    }
    renderMain();
  }

  function fetchStaffTab() {
    if (state.staff.sub === "profiles") fetchProfiles();
    else fetchStaffUsers();
  }

  function buildExportUrl(format, includeAccommodations) {
    const cat = PEOPLE_TABS[state.activeTab] || "ATTENDEE";
    const st = state.people[cat];
    const params = new URLSearchParams();
    params.set("export", format);
    params.set("format", format);
    params.set("category", cat === "ATTENDEE" && st.subCategory ? st.subCategory : cat);
    if (st.search) params.set("search", st.search);
    if (st.status) params.set("status", st.status);
    if (st.assignmentStatus && cat !== "ATTENDEE") params.set("assignmentStatus", st.assignmentStatus);
    if (st.checkin && cat === "ATTENDEE") params.set("checkinStatus", st.checkin);
    if (includeAccommodations) params.set("includeAccommodations", "true");
    return "/api/admin/registrations.php?" + params.toString();
  }

  function jumpToFilteredRegistrations(filters) {
    filters = filters || {};
    const category = filters.category || "ATTENDEE";
    const cat = category === "VOLUNTEER" || category === "SLI" ? category : "ATTENDEE";
    const tab = cat === "VOLUNTEER" ? "VOLUNTEERS" : cat === "SLI" ? "SLI" : "ATTENDEES";
    if (!NAV_ITEMS.some(function (i) { return i.id === tab; })) return;
    state.activeTab = tab;
    renderNav();
    fetchPeople(cat, {
      page: 1,
      search: "",
      status: filters.status || "",
      assignmentStatus: filters.assignmentStatus || "",
      checkin: filters.checkinStatus || "",
      subCategory: cat === "ATTENDEE" && category !== "ATTENDEE" ? category : "",
    });
  }

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
      { key: "uniqueCheckedIn", label: "Unique Checked-In", value: m.uniqueCheckedInParticipants || 0, sub: (m.totalCheckins || 0) + " total check-ins", action: "TAB_ATTENDANCE" },
      { key: "todayCheckins", label: "Today's Check-Ins", value: m.todayCheckins || 0, sub: "Arrivals today (IST)", action: "TAB_ATTENDANCE" },
      { key: "notYetArrived", label: "Not Yet Arrived", value: m.notYetArrived || 0, sub: "Confirmed, not checked in", action: "NOT_ARRIVED" },
      { key: "unassignedVol", label: "Unassigned Volunteers", value: m.unassignedApprovedVolunteers || 0, sub: "Approved, no duty yet", action: "UNASSIGNED_VOLS" },
      { key: "unassignedSli", label: "Unassigned SLI", value: m.unassignedApprovedSli || 0, sub: "Approved, no duty yet", action: "UNASSIGNED_SLI" },
      { key: "failedNotifications", label: "Failed Notifications", value: m.failedNotifications || 0, sub: "Needs retry in Communications", action: "COMMS_FAILED" },
    ];

    const catDist = (ov && ov.categoryDistribution) || [];
    const attByDay = ((ov && ov.attendance && ov.attendance.byDay) || (ov && ov.attendanceByDay) || []).map(function (d) {
      return { date: d.date, count: d.uniquePeople !== undefined ? d.uniquePeople : d.count, marks: d.marks };
    });
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
    html += '<h4 class="text-sm font-bold text-[#1E1929] mb-3">Unique Attendees by Day (IST)</h4>';
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


  // =========================================================================
  // ATTENDEES / VOLUNTEERS / SLI  (server-side paginated people lists)
  // =========================================================================

  function renderPeopleTab(tabId) {
    const cat = PEOPLE_TABS[tabId];
    const st = state.people[cat];
    const meta = PEOPLE_META[cat];
    const isAtt = cat === "ATTENDEE";
    const cols = 6;

    let html = '<div class="space-y-4">';
    html += pageHeader(meta.title, meta.blurb, '<span class="text-xs font-semibold px-3 py-1 rounded-full bg-[#F1EBF9] text-[#51377A]" role="status">' + esc(st.pagination.total) + " records</span>");

    html +=
      '<form data-form="people-filter" class="' + C.card + ' p-4 space-y-3" role="search" aria-label="' + esc(meta.title) + ' filters">' +
      '<div class="grid grid-cols-1 md:grid-cols-5 gap-3">' +
      '<div class="md:col-span-2">' + field("Search", "people-search", inputEl("people-search", st.search, { type: "search", placeholder: "Name, email, phone or PFG code…" })) + "</div>" +
      "<div>" + field("Status", "people-status", selectEl("people-status", "Filter by status", [["", "All statuses"]].concat(REG_STATUSES.map(function (x) { return [x, x]; })), st.status, 'data-change="people-filter-change"')) + "</div>";
    if (isAtt) {
      html +=
        "<div>" + field("Attendee type", "people-sub", selectEl("people-sub", "Filter by attendee type", [["", "All attendee types"], "DELEGATE", "STUDENT", "ALLY", "INSTITUTIONAL"], st.subCategory, 'data-change="people-filter-change"')) + "</div>" +
        "<div>" + field("Check-in", "people-checkin", selectEl("people-checkin", "Filter by check-in state", [["", "All check-in states"], ["CHECKED_IN", "Checked in"], ["NOT_CHECKED_IN", "Not checked in"]], st.checkin, 'data-change="people-filter-change"')) + "</div>";
    } else {
      html += "<div>" + field("Assignment", "people-assign", selectEl("people-assign", "Filter by duty assignment status", [["", "All"], ["ASSIGNED", "Assigned"], ["UNASSIGNED", "Unassigned"]], st.assignmentStatus, 'data-change="people-filter-change"')) + "</div>";
    }
    html +=
      "<div>" + field("Rows per page", "people-pagesize", selectEl("people-pagesize", "Rows per page", PAGE_SIZES.map(function (n) { return [String(n), String(n)]; }), String(st.pageSize), 'data-change="people-filter-change"')) + "</div>" +
      "</div>" +
      '<div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#F0EBF8]">' +
      '<div class="flex items-center gap-2"><button type="submit" class="' + C.btn + '">Apply Filters</button>' +
      '<button type="button" data-act="people-reset" class="' + C.btn2 + '">Reset</button></div>';
    if (can("registrations.export")) {
      html +=
        '<div class="flex flex-wrap items-center gap-2"><a href="' + esc(buildExportUrl("csv", false)) + '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#D4C7EA] bg-[#F7F3FC] hover:bg-[#ECE4F8] text-[#4C3273]">Export CSV</a>' +
        (can("accommodations.read") ? '<a href="' + esc(buildExportUrl("csv", true)) + '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900">Accommodation CSV</a>' : "") +
        "</div>";
    }
    html += "</div></form>";

    if (st.error) html += errorBox(st.error);

    const midHead = isAtt ? "Category" : cat === "VOLUNTEER" ? "Roles & institution" : "Credentials";
    const lastHead = isAtt ? "Attendance" : "Duty assignment";
    html +=
      '<div class="' + C.card + ' overflow-hidden" aria-busy="' + (st.loading ? "true" : "false") + '"><div class="overflow-x-auto"><table class="w-full text-sm text-left">' +
      '<caption class="sr-only">' + esc(meta.title) + " list, page " + esc(st.pagination.page) + " of " + esc(st.pagination.totalPages) + "</caption>" +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Reg Code</th><th scope="col" class="' + C.th + '">Participant</th><th scope="col" class="' + C.th + '">' + midHead + '</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">' + lastHead + '</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead>' +
      '<tbody class="' + C.tbody + '">';
    if (st.loading && !st.items.length) html += emptyRow(cols, "Loading…");
    else if (!st.items.length) html += emptyRow(cols, st.error ? "Nothing to show." : "No records match the current filters.");
    else html += st.items.map(function (r) { return personRow(cat, r); }).join("");
    html += "</tbody></table></div>" + renderPager("people:" + cat, st.pagination, meta.title + " pagination") + "</div></div>";
    return html;
  }

  function personRow(cat, r) {
    const vd = r.volunteerDetails || {};
    const sd = r.sliDetails || {};
    let mid;
    if (cat === "ATTENDEE") {
      mid = '<span class="inline-block px-2 py-0.5 rounded-md text-xs font-semibold bg-[#F2ECF9] text-[#51377A]">' + esc(r.categoryCode) + "</span>";
    } else if (cat === "VOLUNTEER") {
      const roles = Array.isArray(vd.preferredRoles) ? vd.preferredRoles.join(", ") : "";
      mid = '<p class="text-xs text-[#4A415C]">' + esc(roles || "No role preference") + '</p><p class="text-[11px] text-[#645C75]">' + esc(vd.institutionName || r.organizationName || "Independent") + "</p>";
    } else {
      mid =
        '<p class="text-xs text-[#4A415C]">' + (sd.crrNumber ? "CRR: " + esc(sd.crrNumber) : "CRR not provided") + " • " + esc(sd.yearsOfExperience || 0) + ' yrs</p><p class="text-[11px] text-[#645C75]">' + (sd.basedInGujarat ? "Based in Gujarat" : "Outstation") + "</p>";
    }
    let last;
    if (cat === "ATTENDEE") {
      last = r.checkedIn
        ? '<span class="text-emerald-800 font-semibold">Checked In (' + esc(r.checkinCount) + ")</span>"
        : '<span class="text-[#7C738E]">Not Checked In</span>';
    } else {
      last = r.assignmentStatus ? badge(r.assignmentStatus === "UNASSIGNED" ? "PENDING" : r.assignmentStatus, r.assignmentStatus) : '<span class="text-[#7C738E]">—</span>';
    }
    const canAssignRow = cat !== "ATTENDEE" && canAssignDuty() && String(r.status).toUpperCase() === "APPROVED";
    return (
      '<tr class="hover:bg-[#FAF8FD]">' +
      '<td class="' + C.td + ' font-mono text-xs font-semibold text-[#4B3470]">' + esc(r.registrationCode) + "</td>" +
      '<td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(r.fullName) + '</p><p class="' + C.muted + '">' + esc(r.email) + " • " + esc(r.phone) + "</p></td>" +
      '<td class="' + C.td + '">' + mid + "</td>" +
      '<td class="' + C.td + '">' + badge(r.status) + "</td>" +
      '<td class="' + C.td + ' text-xs">' + last + "</td>" +
      '<td class="' + C.td + ' text-right whitespace-nowrap"><button type="button" data-act="open-reg" data-id="' + esc(r.id) + '" aria-label="Open full record for ' + esc(r.fullName) + '" class="' + C.btn2 + '">Open</button>' +
      (canAssignRow ? ' <button type="button" data-act="people-assign" data-id="' + esc(r.id) + '" data-code="' + esc(r.registrationCode) + '" data-name="' + esc(r.fullName) + '" data-cat="' + esc(cat) + '" class="' + C.btn2 + '">Assign duty</button>' : "") +
      "</td></tr>"
    );
  }

  function applyPeopleFilters() {
    const cat = PEOPLE_TABS[state.activeTab];
    if (!cat) return;
    const patch = { page: 1, search: val("people-search"), status: val("people-status"), pageSize: parseInt(val("people-pagesize"), 10) || 25 };
    if (cat === "ATTENDEE") {
      patch.subCategory = val("people-sub");
      patch.checkin = val("people-checkin");
    } else {
      patch.assignmentStatus = val("people-assign");
    }
    fetchPeople(cat, patch);
  }
  FORMS["people-filter"] = applyPeopleFilters;
  CHANGES["people-filter-change"] = applyPeopleFilters;
  ACTIONS["people-reset"] = function () {
    const cat = PEOPLE_TABS[state.activeTab];
    if (cat) fetchPeople(cat, { page: 1, search: "", status: "", assignmentStatus: "", checkin: "", subCategory: "" });
  };
  ACTIONS["open-reg"] = function (el) {
    openRegistrationDetail(el.getAttribute("data-id"));
  };
  ACTIONS["people-assign"] = function (el) {
    openAssignmentModal({
      prefill: {
        registrationId: el.getAttribute("data-id"),
        registrationCode: el.getAttribute("data-code"),
        fullName: el.getAttribute("data-name"),
        participantCategory: el.getAttribute("data-cat"),
      },
    });
  };

  // ---- Registration detail modal -------------------------------------------

  function openRegistrationDetail(id) {
    const m = openModal({
      title: "Registration Detail & Review",
      size: "side",
      s: { id: id, loading: true, detail: null, saving: false, error: "", notes: "" },
      body: regDetailBody,
    });
    loadRegistrationDetail(m);
  }

  async function loadRegistrationDetail(m) {
    m.s.loading = true;
    m.s.error = "";
    if (modalStack.indexOf(m) >= 0) refreshModal(m);
    const r = await api("/api/admin/registrations?id=" + encodeURIComponent(m.s.id));
    m.s.loading = false;
    if (r.ok) {
      m.s.detail = r.data;
      m.s.notes = (r.data.registration && r.data.registration.reviewNotes) || "";
    } else {
      m.s.error = r.data.error || "Could not load this record.";
    }
    if (modalStack.indexOf(m) >= 0) refreshModal(m);
  }

  function regDetailBody(m) {
    const s = m.s;
    if (s.loading && !s.detail) return '<p class="text-sm text-[#6E6580]" role="status">Loading full record…</p>';
    if (!s.detail) return errorBox(s.error || "Record unavailable.");
    const d = s.detail;
    const reg = d.registration || {};
    const creds = d.credentials || [];
    const activeCred = creds.find(function (c) { return c.status === "ACTIVE"; });
    const allowed = Array.isArray(d.allowedTransitions) ? d.allowedTransitions : [];
    const canReview = isSuperadmin || can("registrations.write") || can("volunteers.review") || can("sli.review");
    let html = "";

    html +=
      '<div class="p-4 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] space-y-1 text-sm">' +
      '<div class="flex flex-wrap items-center gap-2"><p class="font-mono text-xs font-bold text-[#563A80]">' + esc(reg.registrationCode) + "</p>" + badge(reg.status) + "</div>" +
      '<p class="text-base font-bold text-[#17151C]">' + esc(reg.fullName) + "</p>" +
      '<p class="text-xs text-[#5C536E]">' + esc(reg.email) + " • " + esc(reg.phone) + (reg.country ? " • " + esc(reg.country) : "") + "</p>" +
      '<p class="text-xs text-[#5C536E]">Category: <strong>' + esc(reg.categoryCode) + "</strong> • Payment: <strong>" + esc(reg.paymentStatus || "—") + "</strong></p></div>";
    if (s.error) html += errorBox(s.error);

    if (canReview) {
      html +=
        '<div class="space-y-3">' +
        field("Application status", "modal-reg-status",
          '<select id="modal-reg-status" class="' + C.input + '"' + (allowed.length === 0 ? " disabled" : "") + ">" + opts([reg.status].concat(allowed.filter(function (x) { return x !== reg.status; })), reg.status) + "</select>",
          allowed.length ? "Only transitions allowed from " + reg.status + " are listed." : "No further status changes are allowed from " + reg.status + ".") +
        field("Reviewer notes (internal)", "modal-reg-notes", textareaEl("modal-reg-notes", s.notes, 3, { placeholder: "Add review notes or reason…" })) +
        field("Message to participant (optional)", "modal-reg-public", textareaEl("modal-reg-public", "", 2, { placeholder: "Shown on the participant's dashboard…" }), "Visible to the participant. Leave blank to send nothing.") +
        '<div class="flex flex-wrap gap-2"><button type="button" data-act="reg-save" ' + (s.saving ? "disabled " : "") + 'class="flex-1 ' + C.btn + '">' + (s.saving ? "Saving…" : "Save Review") + "</button>" +
        (can("credentials.revoke") && activeCred ? '<button type="button" data-act="reg-revoke" ' + (s.saving ? "disabled " : "") + 'class="' + C.danger + '">Revoke Legacy Credential</button>' : "") +
        "</div></div>";
    } else {
      html += '<p class="' + C.muted + '">You have read-only access to this record.</p>';
    }

    [["Delegate details", d.delegateDetails], ["Attendee details", d.attendeeDetails], ["Volunteer details", d.volunteerDetails], ["SLI details", d.sliDetails]].forEach(function (pair) {
      if (pair[1]) html += section(pair[0], kvList(pair[1]));
    });

    // Accommodation / disability (restricted)
    if (d.accommodationsRestricted) {
      html += section("Accessibility & disability information", '<p class="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">Restricted. Accommodation and disability details are only visible with the <code>accommodations.read</code> permission.</p>');
    } else if ((d.accommodations && d.accommodations.length) || (d.disabilityDisclosures && (Array.isArray(d.disabilityDisclosures) ? d.disabilityDisclosures.length : true))) {
      let inner = "";
      (d.accommodations || []).forEach(function (a) {
        inner += '<div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1"><p class="font-bold text-amber-900">' + esc((a.selectedTypeLabels || a.selectedTypes || []).join(", ")) + " " + badge(a.status) + "</p>" +
          (a.freeTextDetails ? '<p class="text-amber-900">' + esc(a.freeTextDetails) + "</p>" : "") +
          (a.coordinatorNotes ? '<p class="text-amber-900"><strong>Coordinator notes:</strong> ' + esc(a.coordinatorNotes) + "</p>" : "") + "</div>";
      });
      const dd = d.disabilityDisclosures;
      if (dd) (Array.isArray(dd) ? dd : [dd]).forEach(function (x) { inner += '<div class="p-3 rounded-xl bg-[#FAF8FD] border border-[#ECE7F5]">' + kvList(x) + "</div>"; });
      html += section("Accessibility & disability information", inner);
    }

    // Duty assignments
    const duties = d.dutyAssignments || [];
    if (duties.length) {
      html += section("Duty assignments",
        '<div class="overflow-x-auto"><table class="w-full text-xs text-left"><caption class="sr-only">Duty assignments</caption><thead><tr class="border-b border-[#E5DFEF] text-[#5F5573]"><th scope="col" class="py-1.5 pr-2">Date</th><th scope="col" class="py-1.5 pr-2">Shift</th><th scope="col" class="py-1.5 pr-2">Role</th><th scope="col" class="py-1.5 pr-2">Venue / session</th><th scope="col" class="py-1.5">Status</th></tr></thead><tbody>' +
        duties.map(function (a) {
          return '<tr class="border-b border-[#F2EEF9] align-top"><td class="py-1.5 pr-2 whitespace-nowrap">' + esc(a.dutyDateIst) + '</td><td class="py-1.5 pr-2 whitespace-nowrap">' + esc(a.shiftCode) + " " + esc(a.startTime) + "–" + esc(a.endTime) + '</td><td class="py-1.5 pr-2">' + esc(a.roleTitle) + (a.department ? " (" + esc(a.department) + ")" : "") + '</td><td class="py-1.5 pr-2">' + esc(a.venueName || "—") + (a.sessionTitle ? "<br>" + esc(a.sessionTitle) : "") + '</td><td class="py-1.5">' + badge(a.status) + "</td></tr>";
        }).join("") + "</tbody></table></div>");
    }

    // Attendance + check-ins
    const att = d.attendanceRecords || [];
    if (att.length) {
      html += section("Attendance records",
        '<ul class="space-y-1 text-xs">' + att.map(function (a) {
          return '<li class="flex flex-wrap items-center justify-between gap-2 p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]"><span><strong>' + esc(a.date) + "</strong> • " + esc(a.scope) + " • " + esc(a.venueLabel || "—") + "</span><span>" + badge(a.status) + " " + esc(formatDateShort(a.markedAt)) + "</span></li>";
        }).join("") + "</ul>");
    }
    const chk = d.checkins || [];
    if (chk.length) {
      html += section("Check-in log",
        '<ul class="space-y-1 text-xs">' + chk.map(function (c) {
          return '<li class="p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]">' + esc(formatDateShort(c.checkedInAt || c.createdAt)) + " • " + esc(c.venueLabel || c.gate || "—") + (c.method ? " • " + esc(c.method) : "") + "</li>";
        }).join("") + "</ul>");
    }

    // Status history
    const hist = d.statusHistory || [];
    if (hist.length) {
      html += section("Status history",
        '<ol class="space-y-1.5 text-xs">' + hist.map(function (h) {
          return '<li class="p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]"><strong>' + esc(h.previousStatus || "—") + " &rarr; " + esc(h.newStatus) + "</strong> by " + esc(h.actorRole || "system") + " <span class=\"text-[#645C75]\">(" + esc(formatDateShort(h.createdAt)) + ")</span>" + (h.reason ? "<br>" + esc(h.reason) : "") + "</li>";
        }).join("") + "</ol>");
    }

    // Payments
    const pays = d.paymentOrders || [];
    if (pays.length) {
      html += section("Payment orders", pays.map(function (p) { return '<div class="p-3 rounded-xl bg-[#FAF8FD] border border-[#ECE7F5]">' + kvList(p) + "</div>"; }).join(""));
    }

    // Legacy credential history (D-01: participant QR entry passes are discontinued)
    if (creds.length) {
      html += section("Legacy credential history", creds.map(function (cr) {
        return '<div class="flex items-center justify-between text-xs p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]"><span class="font-mono font-semibold">' + esc(cr.credentialCode) + "</span>" + badge(cr.status) + "</div>";
      }).join(""));
    }
    return html;
  }

  async function saveRegistrationReview(m, credentialAction, revocationReason) {
    const s = m.s;
    const reg = (s.detail && s.detail.registration) || {};
    const statusEl = document.getElementById("modal-reg-status");
    const notesEl = document.getElementById("modal-reg-notes");
    if (notesEl) s.notes = notesEl.value;
    const payload = { id: s.id, reviewNotes: s.notes };
    const pubEl = document.getElementById("modal-reg-public");
    if (pubEl && pubEl.value.trim()) payload.publicMessage = pubEl.value.trim();
    if (statusEl && !statusEl.disabled && statusEl.value && statusEl.value !== reg.status) payload.status = statusEl.value;
    if (credentialAction) {
      payload.credentialAction = credentialAction;
      if (credentialAction === "REVOKE") payload.revocationReason = revocationReason || "Revoked by administrator";
    }
    s.saving = true;
    m.busy = true;
    refreshModal(m);
    const r = await api("/api/admin/registrations?id=" + encodeURIComponent(s.id), "PATCH", payload);
    s.saving = false;
    m.busy = false;
    if (!r.ok) {
      if (r.status === 409 && Array.isArray(r.data.allowedTransitions) && s.detail) s.detail.allowedTransitions = r.data.allowedTransitions;
      notify("error", r.data.error || "Update failed.");
      refreshModal(m);
      return;
    }
    notify("success", "Registration updated successfully.");
    await loadRegistrationDetail(m);
    loadTabData(state.activeTab);
  }
  ACTIONS["reg-save"] = function (el) {
    const m = modalOf(el);
    if (m) saveRegistrationReview(m, null);
  };
  ACTIONS["reg-revoke"] = async function (el) {
    const m = modalOf(el);
    if (!m) return;
    const ans = await promptDialog({ title: "Revoke legacy credential", message: "The historical credential record will be marked REVOKED.", label: "Reason for revocation", required: true, confirmLabel: "Revoke credential", danger: true });
    if (ans) saveRegistrationReview(m, "REVOKE", ans.value);
  };

  // =========================================================================
  // ACCESSIBILITY (accommodation coordination)
  // =========================================================================

  function renderAccommodationsTab() {
    let html = '<div class="space-y-4">';
    html += pageHeader(
      "Accessibility & Accommodation Coordination",
      "Coordinate ISL interpreters, Braille schedules, wheelchair seating and sensory sanctuary access. Leave the notes box blank to keep existing coordinator notes.",
      '<div class="w-48">' + selectEl("accomm-status-filter", "Filter accommodation requests by status", [["", "All statuses"]].concat(["REQUESTED", "IN_PROGRESS", "ARRANGED", "DECLINED"].map(function (x) { return [x, x]; })), state.accommStatusFilter, 'data-change="accomm-filter"') + "</div>"
    );
    if (state.accommError) html += errorBox(state.accommError);
    html +=
      '<div class="' + C.card + ' overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm text-left"><caption class="sr-only">Participant accommodation requests</caption>' +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Participant</th><th scope="col" class="' + C.th + '">Requested accommodations</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">Coordinator notes &amp; action</th></tr></thead><tbody class="' + C.tbody + '">';
    if (state.accommLoading && !state.accommItems.length) html += emptyRow(4, "Loading accessibility requests…");
    else if (!state.accommItems.length) html += emptyRow(4, "No accessibility requests found.");
    else {
      html += state.accommItems.map(function (a) {
        const labels = (a.selectedTypeLabels || a.selectedTypes || []).map(function (l) {
          return '<span class="px-2 py-0.5 rounded-md text-xs font-medium bg-[#F2ECF9] text-[#4C3273]">' + esc(l) + "</span>";
        });
        const reqs = Array.isArray(a.requests) && a.requests.length ? a.requests : [{ id: a.id, status: a.status, coordinatorNotes: a.coordinatorNotes, selectedTypes: a.selectedTypes }];
        const controls = reqs.map(function (rq) {
          const rid = String(rq.id);
          return (
            '<div class="space-y-1.5 ' + (reqs.length > 1 ? "p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5]" : "") + '">' +
            (reqs.length > 1 ? '<p class="text-[11px] font-semibold text-[#4A415C]">' + esc((rq.selectedTypes || []).join(", ") || "Request") + " " + badge(rq.status) + "</p>" : "") +
            (rq.coordinatorNotes ? '<p class="text-xs text-[#4A415C]"><strong>Current notes:</strong> ' + esc(rq.coordinatorNotes) + "</p>" : "") +
            '<label for="accomm-notes-' + esc(rid) + '" class="sr-only">Coordinator notes for ' + esc(a.fullName) + " (leave blank to keep existing notes)</label>" +
            '<input id="accomm-notes-' + esc(rid) + '" type="text" placeholder="Add coordinator notes (blank keeps existing)…" class="w-full px-2.5 py-1.5 text-xs border border-[#DDD5EA] rounded-lg" />' +
            '<div class="flex flex-wrap gap-1.5">' +
            ["REQUESTED", "IN_PROGRESS", "ARRANGED", "DECLINED"].filter(function (st) { return st !== rq.status; }).map(function (st) {
              return '<button type="button" data-act="accomm-update" data-rid="' + esc(rid) + '" data-status="' + st + '" class="px-2.5 py-1 rounded text-[11px] font-semibold border border-[#DDD5EA] hover:bg-[#F3EEF9] text-[#3E2B5E]">Mark ' + esc(st.replace("_", " ")) + "</button>";
            }).join("") + (rq.coordinatorNotes ? '<button type="button" data-act="accomm-clear" data-rid="' + esc(rid) + '" data-status="' + esc(rq.status) + '" class="px-2.5 py-1 rounded text-[11px] font-semibold border border-rose-200 hover:bg-rose-100 text-rose-900">Clear notes</button>' : "") + "</div></div>"
          );
        }).join("");
        return (
          '<tr class="align-top"><td class="' + C.td + '"><p class="font-mono text-xs font-bold text-[#563A80]">' + esc(a.registrationCode) + '</p><p class="font-semibold text-[#17151C]">' + esc(a.fullName) + '</p><p class="' + C.muted + '">' + esc(a.email) + " • " + esc(a.phone) + (a.categoryCode ? " • " + esc(a.categoryCode) : "") + "</p></td>" +
          '<td class="' + C.td + '"><div class="flex flex-wrap gap-1.5">' + labels.join("") + "</div>" +
          (a.freeTextDetails ? '<p class="text-xs text-[#4A435C] mt-2 bg-[#FAF8FD] p-2 rounded border border-[#ECE7F5]">' + esc(a.freeTextDetails) + "</p>" : "") + "</td>" +
          '<td class="' + C.td + '">' + badge(a.status) + "</td>" +
          '<td class="' + C.td + ' space-y-2 min-w-[260px]">' + controls + "</td></tr>"
        );
      }).join("");
    }
    html += "</tbody></table></div></div>";
    if (state.accommItems.length) html += '<p class="' + C.muted + '" role="status">' + esc(state.accommTotal) + " request(s) shown.</p>";
    html += "</div>";
    return html;
  }
  CHANGES["accomm-filter"] = function (el) {
    state.accommStatusFilter = el.value;
    fetchAccommodations();
  };
  ACTIONS["accomm-clear"] = async function (el) {
    const ans = await promptDialog({ title: "Clear coordinator notes", message: "Existing coordinator notes for this request will be permanently erased.", input: false, confirmLabel: "Clear notes", danger: true });
    if (!ans) return;
    const r = await api("/api/admin/accommodations", "PATCH", { requestId: el.getAttribute("data-rid"), status: el.getAttribute("data-status"), clearCoordinatorNotes: true });
    if (!r.ok) return notify("error", r.data.error || "Could not clear the notes.");
    notify("success", "Coordinator notes cleared.");
    fetchAccommodations();
  };
  ACTIONS["accomm-update"] = async function (el) {
    const rid = el.getAttribute("data-rid");
    const status = el.getAttribute("data-status");
    const notes = val("accomm-notes-" + rid);
    const body = { requestId: rid, status: status };
    if (notes) body.coordinatorNotes = notes;
    el.disabled = true;
    const r = await api("/api/admin/accommodations", "PATCH", body);
    if (!r.ok) {
      notify("error", r.data.error || "Could not update the accessibility request.");
      el.disabled = false;
      return;
    }
    notify("success", "Request marked " + status.replace("_", " ") + ".");
    fetchAccommodations();
  };


  // =========================================================================
  // PROGRAMME & VENUES
  // =========================================================================

  const DAY_OPTIONS = [["1", "Day 1 (21 Jan 2027)"], ["2", "Day 2 (22 Jan 2027)"], ["3", "Day 3 (23 Jan 2027)"]];

  function formBusy(form, on) {
    const m = modalOf(form);
    if (m) m.busy = on;
    Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"]'), function (b) {
      b.disabled = on;
    });
  }
  function setFormError(form, msg) {
    const el = form.querySelector("[data-form-error]");
    if (el) el.innerHTML = msg ? errorBox(msg) : "";
  }
  function formFooter(label) {
    return (
      '<div data-form-error aria-live="assertive"></div><div class="flex justify-end gap-2 pt-2"><button type="button" data-act="modal-close" class="' + C.btn2 + '">Cancel</button>' +
      '<button type="submit" class="' + C.btn + '">' + esc(label) + "</button></div>"
    );
  }
  function progToast(r, msg) {
    const w = r.data.warnings || [];
    const notified = typeof r.data.notified === "number" ? r.data.notified : null;
    const affected = typeof r.data.affectedAssignments === "number" ? r.data.affectedAssignments : 0;
    notify(
      w.length ? "warning" : "success",
      msg + (notified ? " " + notified + " participant(s) notified." : "") + (affected ? " " + affected + " duty assignment(s) affected." : "") + (w.length ? " Warnings: " + w.join(" ") : "")
    );
  }
  function venueById(id) {
    return state.prog.venues.find(function (v) { return String(v.id) === String(id); }) || null;
  }

  function renderProgrammeTab() {
    const pr = state.prog;
    let html = '<div class="space-y-4">';
    html += pageHeader("Programme & Venues", "DRAFT, PROPOSED and PENDING entries are not confirmed. Only CONFIRMED / PUBLISHED programme items and CONFIRMED venues are final.");
    html += subTabs("prog", pr.sub, [["sessions", "Sessions"], ["items", "Programme items"], ["venues", "Venues"]]);

    if (pr.sub === "venues") {
      html +=
        '<form data-form="venue-filter" class="' + C.card + ' p-4 flex flex-wrap items-end gap-3" role="search" aria-label="Venue filters">' +
        '<div class="flex-1 min-w-[200px]">' + field("Search venues", "venue-search", inputEl("venue-search", pr.venueSearch, { type: "search", placeholder: "Venue name or address…" })) + "</div>" +
        '<div class="w-48">' + field("Status", "venue-status", selectEl("venue-status", "Filter venues by status", [["", "All statuses"]].concat(VENUE_STATUSES.map(function (x) { return [x, x]; })), pr.venueStatus, 'data-change="venue-filter-change"')) + "</div>" +
        '<button type="submit" class="' + C.btn + '">Apply</button>' +
        '<button type="button" data-act="venue-new" class="' + C.btn + '">+ Add venue</button></form>';
      if (pr.venuesError) html += errorBox(pr.venuesError);
      html +=
        '<div class="' + C.card + ' overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm text-left"><caption class="sr-only">Festival venues</caption>' +
        '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Venue</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">Capacity</th><th scope="col" class="' + C.th + '">Accessibility features</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
      if (pr.venuesLoading && !pr.venues.length) html += emptyRow(5, "Loading venues…");
      else if (!pr.venues.length) html += emptyRow(5, "No venues found.");
      else {
        html += pr.venues.map(function (v) {
          const map = safeHref(v.mapUrl);
          return (
            '<tr class="align-top"><td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(v.name) + '</p><p class="' + C.muted + '">' + esc(v.address || "") + "</p>" +
            (map ? '<a href="' + esc(map) + '" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-[#6B4E96] hover:underline">Map &rarr;</a>' : "") + "</td>" +
            '<td class="' + C.td + '">' + confirmationBadge(v.confirmationStatus) + "</td>" +
            '<td class="' + C.td + ' text-xs tabular-nums">' + esc(v.capacity !== null && v.capacity !== undefined ? v.capacity : "—") + "</td>" +
            '<td class="' + C.td + ' text-xs">' + esc((v.accessibilityFeatures || []).join(", ") || "—") + (v.operationalNotes ? '<p class="text-[11px] text-[#645C75] mt-1">' + esc(v.operationalNotes) + "</p>" : "") + "</td>" +
            '<td class="' + C.td + ' text-right"><button type="button" data-act="venue-edit" data-id="' + esc(v.id) + '" aria-label="Edit venue ' + esc(v.name) + '" class="' + C.btn2 + '">Edit</button></td></tr>'
          );
        }).join("");
      }
      html += "</tbody></table></div></div></div>";
      return html;
    }

    html +=
      '<div class="' + C.card + ' p-4 flex flex-wrap items-end gap-3">' +
      '<div class="w-52">' + field("Day", "prog-day", selectEl("prog-day", "Filter by day", [["", "All days"]].concat(DAY_OPTIONS), pr.day, 'data-change="prog-filter"')) + "</div>" +
      '<div class="w-48">' + field("Status", "prog-status", selectEl("prog-status", "Filter by status", [["", "All statuses"]].concat(PROGRAMME_STATUSES.map(function (x) { return [x, x]; })), pr.status, 'data-change="prog-filter"')) + "</div>" +
      '<button type="button" data-act="' + (pr.sub === "sessions" ? "session-new" : "item-new") + '" class="' + C.btn + '">+ Add ' + (pr.sub === "sessions" ? "session" : "programme item") + "</button></div>";
    if (pr.error) html += errorBox(pr.error);

    if (pr.sub === "sessions") {
      html +=
        '<div class="' + C.card + ' overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm text-left"><caption class="sr-only">Festival sessions</caption>' +
        '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">When (IST)</th><th scope="col" class="' + C.th + '">Session</th><th scope="col" class="' + C.th + '">Venue</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">Access</th><th scope="col" class="' + C.th + '">Staffing</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
      if (pr.loading && !pr.sessions.length) html += emptyRow(7, "Loading sessions…");
      else if (!pr.sessions.length) html += emptyRow(7, "No sessions found.");
      else {
        html += pr.sessions.map(function (s) {
          const v = venueById(s.venueId);
          const vShort = (s.volunteersNeeded || 0) > (s.assignedVolunteers || 0);
          const sShort = (s.slisNeeded || 0) > (s.assignedSlis || 0) || (s.islRequired && !(s.assignedSlis > 0));
          const flags = [s.islRequired ? "ISL" : "", s.captioningAvailable ? "Captioning" : "", s.wheelchairAccessible ? "Wheelchair" : ""].filter(Boolean);
          return (
            '<tr class="align-top"><td class="' + C.td + ' text-xs whitespace-nowrap">Day ' + esc(s.dayNumber) + "<br>" + esc(fmtTimeIst(s.startsAt)) + "–" + esc(fmtTimeIst(s.endsAt)) + "</td>" +
            '<td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(s.title) + '</p><p class="' + C.muted + '">' + esc(s.track || "") + "</p></td>" +
            '<td class="' + C.td + ' text-xs">' + esc(s.venueName || "Venue TBD") + (v && v.confirmationStatus !== "CONFIRMED" ? "<br>" + confirmationBadge(v.confirmationStatus) : "") + "</td>" +
            '<td class="' + C.td + '">' + confirmationBadge(s.status) + "</td>" +
            '<td class="' + C.td + ' text-xs">' + esc(flags.join(", ") || "—") + "</td>" +
            '<td class="' + C.td + ' text-xs"><p class="' + (vShort ? "text-amber-800 font-semibold" : "") + '">Volunteers ' + esc(s.assignedVolunteers || 0) + "/" + esc(s.volunteersNeeded || 0) + '</p><p class="' + (sShort ? "text-rose-800 font-semibold" : "") + '">SLIs ' + esc(s.assignedSlis || 0) + "/" + esc(s.slisNeeded || 0) + (s.islRequired && !(s.assignedSlis > 0) ? " (ISL required!)" : "") + "</p></td>" +
            '<td class="' + C.td + ' text-right"><button type="button" data-act="session-edit" data-id="' + esc(s.id) + '" aria-label="Edit session ' + esc(s.title) + '" class="' + C.btn2 + '">Edit</button></td></tr>'
          );
        }).join("");
      }
      html += "</tbody></table></div></div>";
    } else {
      html +=
        '<div class="' + C.card + ' overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm text-left"><caption class="sr-only">Programme items</caption>' +
        '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Day / slot</th><th scope="col" class="' + C.th + '">Item</th><th scope="col" class="' + C.th + '">Speakers &amp; access</th><th scope="col" class="' + C.th + '">Venue</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
      if (pr.loading && !pr.items.length) html += emptyRow(6, "Loading programme items…");
      else if (!pr.items.length) html += emptyRow(6, "No programme items found.");
      else {
        html += pr.items.map(function (it) {
          return (
            '<tr class="align-top"><td class="' + C.td + ' text-xs whitespace-nowrap">Day ' + esc(it.dayNumber) + "<br>" + esc(it.timeSlot) + "</td>" +
            '<td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(it.title) + '</p><p class="' + C.muted + '">' + esc(it.category || "") + " • order " + esc(it.displayOrder) + "</p></td>" +
            '<td class="' + C.td + ' text-xs">' + esc((it.speakers || []).join(", ") || "—") + '<p class="text-[11px] text-[#645C75]">' + esc((it.accessibilityServices || []).join(", ")) + "</p></td>" +
            '<td class="' + C.td + ' text-xs">' + esc(it.venueName || "—") + "</td>" +
            '<td class="' + C.td + '">' + confirmationBadge(it.status) + "</td>" +
            '<td class="' + C.td + ' text-right"><button type="button" data-act="item-edit" data-id="' + esc(it.id) + '" aria-label="Edit item ' + esc(it.title) + '" class="' + C.btn2 + '">Edit</button></td></tr>'
          );
        }).join("");
      }
      html += "</tbody></table></div></div>";
    }
    html += "</div>";
    return html;
  }

  CHANGES["prog-filter"] = function () {
    state.prog.day = val("prog-day");
    state.prog.status = val("prog-status");
    fetchProgramme();
  };
  CHANGES["venue-filter-change"] = function () {
    state.prog.venueStatus = val("venue-status");
    state.prog.venueSearch = val("venue-search");
    fetchVenues();
  };
  FORMS["venue-filter"] = CHANGES["venue-filter-change"];

  function notifyBlock(editing) {
    if (!editing) return "";
    return (
      '<div class="p-3 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] space-y-2">' +
      checkboxEl("pm-notify", "Notify registered participants about this change (queues a Programme Change Notice)", false) +
      field("Change summary (required when notifying)", "pm-summary", textareaEl("pm-summary", "", 2, { placeholder: "e.g. Session moved to Hall B, 10:30 start." })) +
      field("Who should be notified?", "pm-audience", selectEl("pm-audience", "Notification audience", [["ASSIGNED", "Only participants assigned to this session"]].concat(canAnnounce() ? [["ALL", "All registered participants"]] : []), "ASSIGNED")) +
      "</div>"
    );
  }
  function notifyPayload(form, payload) {
    if (checked("pm-notify")) {
      const summary = val("pm-summary");
      if (!summary) {
        setFormError(form, "Enter a change summary to notify participants.");
        return false;
      }
      payload.notifyParticipants = true;
      payload.changeSummary = summary;
      payload.notifyAudience = val("pm-audience") || "ASSIGNED";
    }
    return true;
  }

  // ---- Session modal ----
  function openSessionModal(session) {
    const editing = Boolean(session);
    const s = session || { dayNumber: 1, status: "DRAFT", volunteersNeeded: 0, slisNeeded: 0 };
    openModal({
      title: editing ? "Edit session" : "Add session",
      size: "lg",
      s: { session: session },
      body: function () {
        const venues = state.prog.venues.filter(function (v) { return v.confirmationStatus !== "ARCHIVED" || String(v.id) === String(s.venueId); });
        return (
          '<form data-form="session-form" class="space-y-4">' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
          '<div class="sm:col-span-2">' + field("Title", "ps-title", inputEl("ps-title", s.title || "", { required: true }), "", true) + "</div>" +
          field("Track", "ps-track", inputEl("ps-track", s.track || "")) +
          field("Day", "ps-day", selectEl("ps-day", "Day", DAY_OPTIONS, String(s.dayNumber || 1))) +
          field("Starts (IST)", "ps-starts", inputEl("ps-starts", toIstInput(s.startsAt), { type: "datetime-local", required: true }), "", true) +
          field("Ends (IST)", "ps-ends", inputEl("ps-ends", toIstInput(s.endsAt), { type: "datetime-local", required: true }), "", true) +
          field("Venue", "ps-venue", selectEl("ps-venue", "Venue", [["", "— No venue yet —"]].concat(venues.map(function (v) { return [v.id, v.name + " (" + v.confirmationStatus + ")"]; })), s.venueId || "")) +
          field("Status", "ps-status", selectEl("ps-status", "Status", PROGRAMME_STATUSES.map(function (x) { return [x, x]; }), s.status || "DRAFT"), "DRAFT and PROPOSED sessions are not confirmed.") +
          field("Volunteers needed", "ps-vneeded", inputEl("ps-vneeded", s.volunteersNeeded || 0, { type: "number", attrs: 'min="0"' })) +
          field("SLIs needed", "ps-sneeded", inputEl("ps-sneeded", s.slisNeeded || 0, { type: "number", attrs: 'min="0"' })) +
          "</div>" +
          '<div class="flex flex-wrap gap-4">' + checkboxEl("ps-isl", "ISL interpretation required", s.islRequired) + checkboxEl("ps-caption", "Captioning available", s.captioningAvailable) + checkboxEl("ps-wheel", "Wheelchair accessible", s.wheelchairAccessible) + "</div>" +
          field("Description", "ps-desc", textareaEl("ps-desc", s.description || "", 3)) +
          notifyBlock(editing) +
          formFooter(editing ? "Save session" : "Create session") + "</form>"
        );
      },
    });
  }
  FORMS["session-form"] = async function (form) {
    const m = modalOf(form);
    const existing = m && m.s.session;
    setFormError(form, "");
    const starts = val("ps-starts");
    const ends = val("ps-ends");
    if (!val("ps-title")) return setFormError(form, "Title is required.");
    if (!starts || !ends || ends <= starts) return setFormError(form, "End time must be after the start time.");
    const payload = {
      entity: "session",
      title: val("ps-title"),
      track: val("ps-track"),
      dayNumber: parseInt(val("ps-day"), 10) || 1,
      startsAt: fromIstInput(starts),
      endsAt: fromIstInput(ends),
      venueId: val("ps-venue") || null,
      status: val("ps-status"),
      islRequired: checked("ps-isl"),
      captioningAvailable: checked("ps-caption"),
      wheelchairAccessible: checked("ps-wheel"),
      description: val("ps-desc"),
      volunteersNeeded: Math.max(0, parseInt(val("ps-vneeded"), 10) || 0),
      slisNeeded: Math.max(0, parseInt(val("ps-sneeded"), 10) || 0),
    };
    if (existing) payload.id = existing.id;
    if (!notifyPayload(form, payload)) return;
    const v = payload.venueId ? venueById(payload.venueId) : null;
    if ((payload.status === "CONFIRMED" || payload.status === "PUBLISHED") && v && v.confirmationStatus !== "CONFIRMED") {
      const ok = await promptDialog({ title: "Venue not confirmed", message: "This session will be " + payload.status + " but its venue \"" + v.name + "\" is " + v.confirmationStatus + ". Continue anyway?", input: false, confirmLabel: "Continue" });
      if (!ok) return;
    }
    formBusy(form, true);
    const r = await api("/api/admin/programme", existing ? "PATCH" : "POST", payload);
    formBusy(form, false);
    if (!r.ok) return setFormError(form, r.data.error || "Could not save the session.");
    closeModal(m);
    progToast(r, existing ? "Session updated." : "Session created.");
    fetchProgramme();
  };
  ACTIONS["session-new"] = function () { openSessionModal(null); };
  ACTIONS["session-edit"] = function (el) {
    const s = state.prog.sessions.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (s) openSessionModal(s);
  };

  // ---- Programme item modal ----
  function openItemModal(item) {
    const editing = Boolean(item);
    const it = item || { dayNumber: 1, status: "DRAFT", displayOrder: 0 };
    openModal({
      title: editing ? "Edit programme item" : "Add programme item",
      size: "lg",
      s: { item: item },
      body: function () {
        return (
          '<form data-form="item-form" class="space-y-4"><div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
          '<div class="sm:col-span-2">' + field("Title", "pi-title", inputEl("pi-title", it.title || "", { required: true }), "", true) + "</div>" +
          field("Session", "pi-session", selectEl("pi-session", "Linked session", [["", "— Not linked to a session —"]].concat(state.prog.sessions.map(function (s) { return [s.id, "Day " + s.dayNumber + " · " + s.title]; })), it.sessionId || "")) +
          field("Day", "pi-day", selectEl("pi-day", "Day", DAY_OPTIONS, String(it.dayNumber || 1))) +
          field("Time slot", "pi-slot", inputEl("pi-slot", it.timeSlot || "", { required: true, placeholder: "e.g. 10:00 – 11:00" }), "", true) +
          field("Category", "pi-cat", inputEl("pi-cat", it.category || "")) +
          field("Venue name", "pi-venue", inputEl("pi-venue", it.venueName || "")) +
          field("Display order", "pi-order", inputEl("pi-order", it.displayOrder || 0, { type: "number", attrs: 'min="0"' })) +
          field("Status", "pi-status", selectEl("pi-status", "Status", PROGRAMME_STATUSES.map(function (x) { return [x, x]; }), it.status || "DRAFT"), "DRAFT and PROPOSED items are not confirmed.") +
          "</div>" +
          field("Speakers", "pi-speakers", textareaEl("pi-speakers", (it.speakers || []).join("\n"), 2), "One per line or comma separated.") +
          field("Accessibility services", "pi-access", textareaEl("pi-access", (it.accessibilityServices || []).join("\n"), 2), "e.g. ISL interpretation, live captioning.") +
          field("Description", "pi-desc", textareaEl("pi-desc", it.description || "", 3)) +
          notifyBlock(editing) +
          formFooter(editing ? "Save item" : "Create item") + "</form>"
        );
      },
    });
  }
  FORMS["item-form"] = async function (form) {
    const m = modalOf(form);
    const existing = m && m.s.item;
    setFormError(form, "");
    if (!val("pi-title") || !val("pi-slot")) return setFormError(form, "Title and time slot are required.");
    const payload = {
      entity: "item",
      title: val("pi-title"),
      sessionId: val("pi-session") || null,
      dayNumber: parseInt(val("pi-day"), 10) || 1,
      timeSlot: val("pi-slot"),
      category: val("pi-cat"),
      venueName: val("pi-venue"),
      displayOrder: parseInt(val("pi-order"), 10) || 0,
      status: val("pi-status"),
      speakers: splitList(val("pi-speakers")),
      accessibilityServices: splitList(val("pi-access")),
      description: val("pi-desc"),
    };
    if (existing) payload.id = existing.id;
    if (!notifyPayload(form, payload)) return;
    formBusy(form, true);
    const r = await api("/api/admin/programme", existing ? "PATCH" : "POST", payload);
    formBusy(form, false);
    if (!r.ok) return setFormError(form, r.data.error || "Could not save the programme item.");
    closeModal(m);
    progToast(r, existing ? "Programme item updated." : "Programme item created.");
    fetchProgramme();
  };
  ACTIONS["item-new"] = function () { openItemModal(null); };
  ACTIONS["item-edit"] = function (el) {
    const it = state.prog.items.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (it) openItemModal(it);
  };

  // ---- Venue modal ----
  function openVenueModal(venue) {
    const editing = Boolean(venue);
    const v = venue || { confirmationStatus: "PENDING" };
    openModal({
      title: editing ? "Edit venue" : "Add venue",
      size: "lg",
      s: { venue: venue },
      body: function () {
        return (
          '<form data-form="venue-form" class="space-y-4"><div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
          '<div class="sm:col-span-2">' + field("Venue name", "pv-name", inputEl("pv-name", v.name || "", { required: true }), "", true) + "</div>" +
          '<div class="sm:col-span-2">' + field("Address", "pv-address", inputEl("pv-address", v.address || "")) + "</div>" +
          field("Confirmation status", "pv-status", selectEl("pv-status", "Confirmation status", VENUE_STATUSES.map(function (x) { return [x, x]; }), v.confirmationStatus || "PENDING"), "PENDING and PROPOSED venues are not confirmed.") +
          field("Capacity", "pv-capacity", inputEl("pv-capacity", v.capacity !== null && v.capacity !== undefined ? v.capacity : "", { type: "number", attrs: 'min="0"' })) +
          '<div class="sm:col-span-2">' + field("Map URL", "pv-map", inputEl("pv-map", v.mapUrl || "", { type: "url", placeholder: "https://…" }), "Must start with http:// or https://") + "</div>" +
          "</div>" +
          field("Accessibility features", "pv-features", textareaEl("pv-features", (v.accessibilityFeatures || []).join("\n"), 3), "One per line or comma separated (e.g. step-free entry, accessible restrooms, induction loop).") +
          field("Operational notes", "pv-notes", textareaEl("pv-notes", v.operationalNotes || "", 3)) +
          formFooter(editing ? "Save venue" : "Create venue") + "</form>"
        );
      },
    });
  }
  FORMS["venue-form"] = async function (form) {
    const m = modalOf(form);
    const existing = m && m.s.venue;
    setFormError(form, "");
    const map = val("pv-map");
    if (map && !safeHref(map)) return setFormError(form, "Map URL must start with http:// or https://");
    if (!val("pv-name")) return setFormError(form, "Venue name is required.");
    const cap = val("pv-capacity");
    const payload = {
      name: val("pv-name"),
      address: val("pv-address"),
      confirmationStatus: val("pv-status"),
      capacity: cap === "" ? null : Math.max(0, parseInt(cap, 10) || 0),
      mapUrl: map || null,
      accessibilityFeatures: splitList(val("pv-features")),
      operationalNotes: val("pv-notes"),
    };
    if (existing) payload.id = existing.id;
    formBusy(form, true);
    const r = await api("/api/admin/venues", existing ? "PATCH" : "POST", payload);
    formBusy(form, false);
    if (!r.ok) return setFormError(form, r.data.error || "Could not save the venue.");
    closeModal(m);
    progToast(r, existing ? "Venue updated." : "Venue created.");
    fetchVenues();
  };
  ACTIONS["venue-new"] = function () { openVenueModal(null); };
  ACTIONS["venue-edit"] = function (el) {
    const v = venueById(el.getAttribute("data-id"));
    if (v) openVenueModal(v);
  };


  // =========================================================================
  // DUTY ASSIGNMENTS
  // =========================================================================

  function allowedDutyCategories() {
    const list = [];
    if (can("volunteers.review")) list.push("VOLUNTEER");
    if (can("sli.review")) list.push("SLI");
    return list;
  }

  function renderAssignmentsTab() {
    const st = state.asg;
    const cats = allowedDutyCategories();
    let html = '<div class="space-y-4">';
    html += pageHeader(
      "Duty Assignments",
      "Assign approved volunteers and SLI professionals to shifts, venues and sessions. Overlapping duties are flagged and need an override reason.",
      '<button type="button" data-act="asg-new" class="' + C.btn + '">+ New assignment</button>'
    );
    html +=
      '<form data-form="asg-filter" class="' + C.card + ' p-4 flex flex-wrap items-end gap-3" role="search" aria-label="Assignment filters">' +
      '<div class="w-44">' + field("Category", "asg-category", selectEl("asg-category", "Filter by category", [["", "All categories"]].concat(cats.map(function (c) { return [c, c]; })), st.category, 'data-change="asg-filter-change"')) + "</div>" +
      '<div class="w-44">' + field("Status", "asg-status", selectEl("asg-status", "Filter by status", [["", "All statuses"], "ASSIGNED", "COMPLETED", "CANCELLED"], st.status, 'data-change="asg-filter-change"')) + "</div>" +
      '<div class="w-44">' + field("Duty date", "asg-date", inputEl("asg-date", st.date, { type: "date", attrs: 'data-change="asg-filter-change"' })) + "</div>" +
      (state.prog.sessions.length
        ? '<div class="w-64">' + field("Session", "asg-session", selectEl("asg-session", "Filter by session", [["", "All sessions"]].concat(state.prog.sessions.map(function (s) { return [s.id, "Day " + s.dayNumber + " · " + s.title]; })), st.sessionId, 'data-change="asg-filter-change"')) + "</div>"
        : "") +
      '<div class="w-28">' + field("Rows", "asg-pagesize", selectEl("asg-pagesize", "Rows per page", PAGE_SIZES.map(function (n) { return [String(n), String(n)]; }), String(st.pageSize), 'data-change="asg-filter-change"')) + "</div>" +
      '<button type="button" data-act="asg-reset" class="' + C.btn2 + '">Reset</button></form>';
    if (st.error) html += errorBox(st.error);
    html +=
      '<div class="' + C.card + ' overflow-hidden" aria-busy="' + (st.loading ? "true" : "false") + '"><div class="overflow-x-auto"><table class="w-full text-sm text-left"><caption class="sr-only">Duty assignments</caption>' +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Date &amp; shift</th><th scope="col" class="' + C.th + '">Participant</th><th scope="col" class="' + C.th + '">Duty</th><th scope="col" class="' + C.th + '">Where</th><th scope="col" class="' + C.th + '">Reporting</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
    if (st.loading && !st.items.length) html += emptyRow(7, "Loading assignments…");
    else if (!st.items.length) html += emptyRow(7, "No assignments match the current filters.");
    else {
      html += st.items.map(function (a) {
        const active = a.status === "ASSIGNED";
        return (
          '<tr class="align-top"><td class="' + C.td + ' text-xs whitespace-nowrap"><strong>' + esc(a.dutyDateIst) + "</strong><br>" + esc(a.shiftCode) + " " + esc(a.startTime) + "–" + esc(a.endTime) + "</td>" +
          '<td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(a.fullName) + '</p><p class="font-mono text-[11px] text-[#563A80]">' + esc(a.registrationCode) + " • " + esc(a.participantCategory) + "</p>" +
          (a.pairingGroupId ? '<p class="text-[11px] text-[#5B3E87]">Paired SLI team</p>' : "") + "</td>" +
          '<td class="' + C.td + ' text-xs">' + esc(a.roleTitle) + (a.department ? "<br>" + esc(a.department) : "") + "</td>" +
          '<td class="' + C.td + ' text-xs">' + esc(a.venueName || "Venue TBD") + (a.sessionTitle ? "<br>" + esc(a.sessionTitle) : "") + "</td>" +
          '<td class="' + C.td + ' text-xs">' + esc(a.reportingTime || "—") + (a.reportingSupervisor ? "<br>" + esc(a.reportingSupervisor) : "") + (a.overrideReason ? '<p class="text-[11px] text-amber-800">Override: ' + esc(a.overrideReason) + "</p>" : "") + "</td>" +
          '<td class="' + C.td + '">' + badge(a.status) + "</td>" +
          '<td class="' + C.td + ' text-right whitespace-nowrap">' +
          (active
            ? '<button type="button" data-act="asg-edit" data-id="' + esc(a.id) + '" aria-label="Edit duty for ' + esc(a.fullName) + '" class="' + C.btn2 + '">Edit</button> ' +
              '<button type="button" data-act="asg-status" data-id="' + esc(a.id) + '" data-status="COMPLETED" aria-label="Mark duty complete for ' + esc(a.fullName) + '" class="' + C.btn2 + '">Complete</button> ' +
              '<button type="button" data-act="asg-status" data-id="' + esc(a.id) + '" data-status="CANCELLED" aria-label="Cancel duty for ' + esc(a.fullName) + '" class="' + C.danger + '">Cancel</button>'
            : a.status === "CANCELLED"
              ? '<button type="button" data-act="asg-status" data-id="' + esc(a.id) + '" data-status="ASSIGNED" aria-label="Re-activate duty for ' + esc(a.fullName) + '" class="' + C.btn2 + '">Re-activate</button>'
              : "") +
          "</td></tr>"
        );
      }).join("");
    }
    html += "</tbody></table></div>" + renderPager("asg", st.pagination, "Assignments pagination") + "</div></div>";
    return html;
  }

  function applyAsgFilters() {
    fetchAssignments({
      page: 1,
      category: val("asg-category"),
      status: val("asg-status"),
      date: val("asg-date"),
      sessionId: val("asg-session"),
      pageSize: parseInt(val("asg-pagesize"), 10) || 25,
    });
  }
  FORMS["asg-filter"] = applyAsgFilters;
  CHANGES["asg-filter-change"] = applyAsgFilters;
  ACTIONS["asg-reset"] = function () {
    fetchAssignments({ page: 1, category: "", status: "", date: "", sessionId: "", registrationId: "" });
  };
  ACTIONS["asg-new"] = function () { openAssignmentModal({}); };
  ACTIONS["asg-edit"] = function (el) {
    const a = state.asg.items.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (a) openAssignmentModal({ assignment: a });
  };
  ACTIONS["asg-status"] = async function (el) {
    const id = el.getAttribute("data-id");
    const status = el.getAttribute("data-status");
    const a = state.asg.items.find(function (x) { return String(x.id) === id; });
    const verb = status === "CANCELLED" ? "Cancel" : status === "ASSIGNED" ? "Re-activate" : "Complete";
    const ans = await promptDialog({
      title: verb + " duty assignment",
      message: verb + " the duty for " + (a ? a.fullName : "this participant") + (a ? " on " + a.dutyDateIst : "") + "?",
      input: false,
      confirmLabel: verb + " duty",
      danger: status === "CANCELLED",
    });
    if (!ans) return;
    const r = await api("/api/admin/assignments", "PATCH", { id: id, status: status });
    if (!r.ok) return notify("error", r.data.error || "Could not update the assignment.");
    const warns = r.data.warnings || [];
    notify(warns.length ? "warning" : "success", "Assignment " + (status === "CANCELLED" ? "cancelled" : status === "ASSIGNED" ? "re-activated" : "marked complete") + "." + (warns.length ? " Warnings: " + warns.join(" ") : ""));
    fetchAssignments();
  };

  // ---- Assignment modal (create / edit, candidate picker, SLI pairing, overlap override) ----

  function sessionOptionsHtml(selected) {
    return opts([["", "— No session —"]].concat(state.prog.sessions.map(function (s) { return [s.id, "Day " + s.dayNumber + " · " + s.title]; })), selected || "");
  }
  function venueOptionsHtml(selected) {
    return opts([["", "— No venue —"]].concat(state.prog.venues.filter(function (v) { return v.confirmationStatus !== "ARCHIVED" || String(v.id) === String(selected); }).map(function (v) { return [v.id, v.name + " (" + v.confirmationStatus + ")"]; })), selected || "");
  }
  function fillAsgSelects(m) {
    const sSel = document.getElementById("am-session");
    const vSel = document.getElementById("am-venue");
    if (sSel && modalStack.indexOf(m) >= 0) sSel.innerHTML = sessionOptionsHtml(sSel.value || (m.s.assignment && m.s.assignment.sessionId));
    if (vSel && modalStack.indexOf(m) >= 0) vSel.innerHTML = venueOptionsHtml(vSel.value || (m.s.assignment && m.s.assignment.venueId));
  }

  function openAssignmentModal(o) {
    const a = o.assignment || null;
    const cats = allowedDutyCategories();
    const pre = o.prefill || null;
    const m = openModal({
      title: a ? "Edit duty assignment" : "New duty assignment",
      size: "xl",
      s: {
        assignment: a,
        category: a ? a.participantCategory : pre ? pre.participantCategory : cats[0] || "VOLUNTEER",
        selected: pre ? [pre] : [],
        candidates: [],
        candLoading: false,
        candError: "",
        conflicts: null,
        needsOverride: Boolean(a && a.overrideReason),
        pairingGroupId: a ? a.pairingGroupId || null : null,
      },
      body: asgModalBody,
      bind: function (c, mm) {
        const si = c.querySelector("#am-search");
        if (si) {
          si.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
              e.preventDefault();
              searchCandidates(mm);
            }
          });
        }
        if (!a) paintCandidates(mm);
        paintConflicts(mm);
      },
    });
    if (!a) searchCandidates(m);
    {
      if (!state.prog.sessions.length) {
        api("/api/admin/programme").then(function (r) {
          if (r.ok) {
            state.prog.sessions = r.data.sessions || [];
            fillAsgSelects(m);
          }
        });
      }
      if (!state.prog.venues.length) fetchVenues().then(function () { fillAsgSelects(m); });
    }
  }

  function asgModalBody(m) {
    const s = m.s;
    const a = s.assignment || {};
    const editing = Boolean(s.assignment);
    const cats = allowedDutyCategories();
    let html = '<form data-form="asg-form" class="space-y-4">';
    if (editing) {
      html += '<div class="p-3 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] text-sm"><p class="font-semibold">' + esc(a.fullName) + '</p><p class="font-mono text-[11px] text-[#563A80]">' + esc(a.registrationCode) + " • " + esc(a.participantCategory) + "</p></div>";
    } else {
      html +=
        '<fieldset class="space-y-2 p-3 rounded-xl border border-[#E5DFEF]"><legend class="px-1 text-xs font-bold text-[#4A415C]">1. Choose participant(s)</legend>' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">' +
        field("Category", "am-cat", selectEl("am-cat", "Participant category", cats.map(function (c) { return [c, c]; }), s.category, 'data-change="am-cat"')) +
        '<div class="sm:col-span-2">' + field("Search approved " + s.category.toLowerCase() + "s", "am-search", inputEl("am-search", "", { type: "search", placeholder: "Name, email or PFG code…" })) + "</div></div>" +
        '<button type="button" data-act="am-search" class="' + C.btn2 + '">Search candidates</button>' +
        '<p class="text-[11px] text-[#645C75]">Only APPROVED participants can be assigned. ' + (s.category === "SLI" ? "Select two SLI professionals to assign them as a paired team." : "") + "</p>" +
        '<div id="am-selected" aria-live="polite"></div><div id="am-cand-list" class="max-h-48 overflow-y-auto space-y-1"></div></fieldset>';
    }
    html +=
      '<fieldset class="space-y-3 p-3 rounded-xl border border-[#E5DFEF]"><legend class="px-1 text-xs font-bold text-[#4A415C]">' + (editing ? "Duty details" : "2. Duty details") + "</legend>" +
      '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
      field("Role title", "am-role", inputEl("am-role", a.roleTitle || "", { required: true, placeholder: "e.g. ISL Interpreter" }), "", true) +
      field("Department", "am-dept", inputEl("am-dept", a.department || "", { placeholder: "e.g. Accessibility Desk" })) +
      field("Duty date (IST)", "am-date", inputEl("am-date", a.dutyDateIst || "", { type: "date", required: true }), "", true) +
      field("Shift", "am-shift", inputEl("am-shift", a.shiftCode || "", { required: true, attrs: 'list="am-shift-list"', placeholder: "e.g. MORNING" }), "", true) +
      '<datalist id="am-shift-list"><option value="MORNING"></option><option value="AFTERNOON"></option><option value="EVENING"></option><option value="FULL_DAY"></option></datalist>' +
      field("Start time", "am-start", inputEl("am-start", (a.startTime || "").slice(0, 5), { type: "time", required: true }), "", true) +
      field("End time", "am-end", inputEl("am-end", (a.endTime || "").slice(0, 5), { type: "time", required: true }), "", true) +
      field("Session", "am-session", '<select id="am-session" class="' + C.input + '" data-change="am-session">' + sessionOptionsHtml(a.sessionId) + "</select>") +
      field("Venue", "am-venue", '<select id="am-venue" class="' + C.input + '">' + venueOptionsHtml(a.venueId) + "</select>") +
      field("Reporting time", "am-rtime", inputEl("am-rtime", (a.reportingTime || "").slice(0, 5), { type: "time" })) +
      '<div class="sm:col-span-3">' + field("Reporting supervisor", "am-sup", inputEl("am-sup", a.reportingSupervisor || "")) + "</div>" +
      '<div class="sm:col-span-3">' + field("Reporting instructions", "am-instr", textareaEl("am-instr", a.reportingInstructions || "", 2)) + "</div>" +
      "</div>" + checkboxEl("am-notify", editing ? "Notify the participant of this change by email" : "Notify the participant by email", !editing) + "</fieldset>" +
      '<div id="am-conflicts" aria-live="assertive"></div>' +
      '<div id="am-override-wrap" class="' + (s.needsOverride ? "" : "hidden") + '">' +
      field("Override reason (required to double-book)", "am-override", textareaEl("am-override", a.overrideReason || "", 2, { placeholder: "Why is it acceptable for this person to have overlapping duties?" }), "Recorded in the audit log.") + "</div>" +
      formFooter(editing ? "Save changes" : "Create assignment") + "</form>";
    return html;
  }

  function paintCandidates(m) {
    const s = m.s;
    const selEl = document.getElementById("am-selected");
    const listEl = document.getElementById("am-cand-list");
    if (selEl) {
      selEl.innerHTML = s.selected.length
        ? '<p class="text-xs font-semibold text-[#4A415C] mb-1">Selected (' + s.selected.length + "):</p>" +
          '<ul class="flex flex-wrap gap-2">' + s.selected.map(function (p) {
            return '<li class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#EFE9F8] border border-[#DDD1F0] text-xs font-semibold text-[#563A80]">' + esc(p.fullName) + " (" + esc(p.registrationCode) + ')<button type="button" data-act="am-cand-toggle" data-id="' + esc(p.registrationId) + '" class="px-1.5 rounded-full hover:bg-white/60" aria-label="Remove ' + esc(p.fullName) + '">&times;</button></li>';
          }).join("") + "</ul>" +
          (s.category === "SLI" && s.selected.length === 2 ? '<p class="text-[11px] text-[#5B3E87] mt-1">These two SLI professionals will be assigned as a paired team.</p>' : "")
        : '<p class="text-xs text-amber-800">No participant selected yet.</p>';
    }
    if (listEl) {
      if (s.candLoading) listEl.innerHTML = '<p class="text-xs text-[#6E6580]" role="status">Searching…</p>';
      else if (s.candError) listEl.innerHTML = errorBox(s.candError);
      else if (!s.candidates.length) listEl.innerHTML = '<p class="text-xs text-[#6E6580]">No approved candidates found.</p>';
      else {
        listEl.innerHTML = s.candidates.map(function (c) {
          const isSel = s.selected.some(function (p) { return p.registrationId === c.registrationId; });
          return (
            '<div class="flex flex-wrap items-center justify-between gap-2 p-2 rounded-lg bg-[#FAF8FD] border border-[#ECE7F5] text-xs"><div><p class="font-semibold text-[#17151C]">' + esc(c.fullName) + ' <span class="font-mono text-[#563A80]">' + esc(c.registrationCode) + "</span></p>" +
            '<p class="text-[#645C75]">' + (c.availabilityDays && c.availabilityDays.length ? "Available: " + esc(c.availabilityDays.join(", ")) + " • " : "") + esc(c.activeAssignments || 0) + " active duties</p></div>" +
            '<div class="flex items-center gap-2">' + (c.assignmentStatus ? badge(c.assignmentStatus === "UNASSIGNED" ? "PENDING" : c.assignmentStatus, c.assignmentStatus) : "") +
            '<button type="button" data-act="am-cand-toggle" data-id="' + esc(c.registrationId) + '" aria-pressed="' + (isSel ? "true" : "false") + '" class="' + (isSel ? C.ok : C.btn2) + '">' + (isSel ? "Selected" : "Select") + "</button></div></div>"
          );
        }).join("");
      }
    }
  }

  function paintConflicts(m) {
    const el = document.getElementById("am-conflicts");
    const wrap = document.getElementById("am-override-wrap");
    if (wrap) wrap.classList.toggle("hidden", !m.s.needsOverride);
    if (!el) return;
    const conflicts = m.s.conflicts;
    if (!conflicts) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML =
      '<div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-3 space-y-2 text-xs text-amber-900"><p class="font-bold">' + esc(m.s.conflictMessage || "This duty overlaps with existing assignments.") + "</p>" +
      conflicts.map(function (c) { return '<div class="p-2 rounded-lg bg-white/70 border border-amber-200">' + kvList(c) + "</div>"; }).join("") +
      "<p>Enter an override reason below and submit again to double-book on purpose, or change the time.</p></div>";
  }

  async function searchCandidates(m) {
    const s = m.s;
    s.candLoading = true;
    s.candError = "";
    paintCandidates(m);
    const q = (document.getElementById("am-search") || {}).value || "";
    const params = new URLSearchParams({ view: "candidates", category: s.category });
    if (q.trim()) params.set("search", q.trim());
    const r = await api("/api/admin/assignments?" + params.toString());
    s.candLoading = false;
    if (r.ok) s.candidates = r.data.items || [];
    else {
      s.candidates = [];
      s.candError = r.data.error || "Could not search candidates.";
    }
    if (modalStack.indexOf(m) >= 0) paintCandidates(m);
  }

  ACTIONS["am-search"] = function (el) {
    const m = modalOf(el);
    if (m) searchCandidates(m);
  };
  CHANGES["am-cat"] = function (el) {
    const m = modalOf(el);
    if (!m) return;
    m.s.category = el.value;
    m.s.selected = [];
    m.s.pairingGroupId = null;
    m.s.candidates = [];
    const si = document.getElementById("am-search");
    if (si) si.value = "";
    searchCandidates(m);
  };
  ACTIONS["am-cand-toggle"] = function (el) {
    const m = modalOf(el);
    if (!m) return;
    const s = m.s;
    const id = el.getAttribute("data-id");
    const idx = s.selected.findIndex(function (p) { return p.registrationId === id; });
    if (idx >= 0) {
      s.selected.splice(idx, 1);
    } else {
      const c = s.candidates.find(function (x) { return x.registrationId === id; });
      if (!c) return;
      const max = s.category === "SLI" ? 2 : 1;
      if (s.selected.length >= max) {
        if (max === 1) s.selected = [];
        else return notify("error", "At most two SLI professionals can be paired in one assignment.");
      }
      s.selected.push({ registrationId: c.registrationId, registrationCode: c.registrationCode, fullName: c.fullName, participantCategory: c.participantCategory || s.category });
    }
    s.pairingGroupId = null;
    paintCandidates(m);
  };
  CHANGES["am-session"] = function (el) {
    const sess = state.prog.sessions.find(function (x) { return String(x.id) === el.value; });
    if (!sess) return;
    const set = function (id, v) {
      const e = document.getElementById(id);
      if (e && !e.value && v) e.value = v;
    };
    const venue = document.getElementById("am-venue");
    if (venue && !venue.value && sess.venueId) venue.value = sess.venueId;
    const st = toIstInput(sess.startsAt);
    const en = toIstInput(sess.endsAt);
    set("am-date", st.slice(0, 10));
    set("am-start", st.slice(11, 16));
    set("am-end", en.slice(11, 16));
  };

  FORMS["asg-form"] = async function (form) {
    const m = modalOf(form);
    if (!m) return;
    const s = m.s;
    const editing = Boolean(s.assignment);
    setFormError(form, "");
    const base = {
      sessionId: val("am-session") || null,
      venueId: val("am-venue") || null,
      roleTitle: val("am-role"),
      department: val("am-dept"),
      dutyDateIst: val("am-date"),
      shiftCode: val("am-shift"),
      startTime: val("am-start"),
      endTime: val("am-end"),
      reportingSupervisor: val("am-sup"),
      reportingTime: val("am-rtime") || null,
      reportingInstructions: val("am-instr"),
      notify: checked("am-notify"),
    };
    if (!base.roleTitle || !base.dutyDateIst || !base.shiftCode || !base.startTime || !base.endTime) return setFormError(form, "Role, date, shift, start and end time are required.");
    if (base.endTime <= base.startTime) return setFormError(form, "End time must be after the start time.");
    const override = val("am-override");
    if (s.needsOverride && override.length < 10) return setFormError(form, "An override reason of at least 10 characters is required to save overlapping duties.");
    if (s.needsOverride && override) base.overrideReason = override;

    const targets = editing ? [null] : s.selected.slice();
    if (!editing && !targets.length) return setFormError(form, "Select at least one participant to assign.");
    if (!editing && s.category === "SLI" && targets.length === 2) {
      s.pairingGroupId = s.pairingGroupId || uuid();
      base.pairingGroupId = s.pairingGroupId;
    }

    formBusy(form, true);
    const warnings = [];
    let done = 0;
    let failure = null;
    for (let i = 0; i < targets.length; i++) {
      const t = targets[i];
      const r = editing ? await api("/api/admin/assignments", "PATCH", Object.assign({ id: s.assignment.id }, base)) : await api("/api/admin/assignments", "POST", Object.assign({ registrationId: t.registrationId }, base));
      if (r.ok) {
        done++;
        (r.data.warnings || []).forEach(function (w) { warnings.push(w); });
        if (t) s.selected = s.selected.filter(function (p) { return p.registrationId !== t.registrationId; });
      } else {
        failure = { r: r, target: t };
        break;
      }
    }
    formBusy(form, false);

    if (done) fetchAssignments();
    if (failure) {
      const r = failure.r;
      if (!editing) paintCandidates(m);
      if (r.status === 409 && r.data.code === "DUTY_OVERLAP") {
        s.conflicts = r.data.conflicts || [];
        s.conflictMessage = (r.data.error || "This duty overlaps with an existing assignment.") + (failure.target ? " (" + failure.target.fullName + ")" : "");
        if (!can("registrations.write")) {
          paintConflicts(m);
          setFormError(form, "This duty overlaps an existing assignment. Overriding a double-booking requires the registrations.write permission: change the time or ask a manager.");
          return;
        }
        const already = s.needsOverride && override;
        s.needsOverride = true;
        paintConflicts(m);
        setFormError(form, already ? "The server still rejected the overlap. " + (r.data.error || "") : "Overlap detected — review the conflicts and provide an override reason to continue.");
        const ov = document.getElementById("am-override");
        if (ov && !already) ov.focus();
      } else {
        setFormError(form, (r.data.error || "Could not save the assignment.") + (done ? " (" + done + " saved before the error.)" : ""));
      }
      return;
    }
    closeModal(m);
    notify(warnings.length ? "warning" : "success", (editing ? "Assignment updated." : done + " assignment" + (done === 1 ? "" : "s") + " created.") + (warnings.length ? " Warnings: " + warnings.join(" ") : ""));
  };


  // =========================================================================
  // COMMUNICATIONS CENTER (notification queue + announcements)
  // =========================================================================

  function snake(k) {
    return k.replace(/([A-Z])/g, function (c) { return "_" + c.toLowerCase(); });
  }
  function g(o, camel) {
    if (!o) return undefined;
    return o[camel] !== undefined ? o[camel] : o[snake(camel)];
  }

  function renderCommsTab() {
    const cm = state.comms;
    const tabs = [["queue", "Notification queue"]];
    if (canAnnounce()) tabs.push(["announcements", "Announcements"]);
    let html = '<div class="space-y-4">';
    html += pageHeader("Communications Center", "Monitor outgoing email, retry failures and publish festival announcements. Retrying or resending never changes an application's approval status.");
    html += subTabs("comms", cm.sub, tabs);
    html += cm.sub === "announcements" && canAnnounce() ? renderAnnouncementsPane() : renderQueuePane();
    html += "</div>";
    return html;
  }

  function renderQueuePane() {
    const cm = state.comms;
    const canWrite = can("registrations.write");
    let html =
      '<div class="' + C.card + ' p-4 space-y-3">' +
      '<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by delivery status">' +
      [["", "All"]].concat(NOTIF_STATUSES.map(function (x) { return [x, x.replace("_", " ")]; })).map(function (p) {
        const on = cm.status === p[0];
        const failed = p[0] === "FAILED";
        return '<button type="button" data-act="comms-status" data-status="' + esc(p[0]) + '" aria-pressed="' + (on ? "true" : "false") + '" class="px-3 py-1.5 rounded-full text-xs font-semibold border ' +
          (on ? (failed ? "bg-[#6B4E96] text-white border-[#DDD5EA]" : "bg-[#6B4E96] text-white border-[#6B4E96]") : failed ? "bg-rose-50 text-rose-900 border-rose-200 hover:bg-rose-200" : "bg-white text-[#4D4263] border-[#DDD5EA] hover:bg-[#F3EEF9]") + '">' + esc(p[1]) + "</button>";
      }).join("") + "</div>" +
      '<form data-form="comms-filter" class="flex flex-wrap items-end gap-3" role="search" aria-label="Notification filters">' +
      '<div class="w-64">' + field("Event type", "comms-event", inputEl("comms-event", cm.eventType, { placeholder: "e.g. REGISTRATION_APPROVED" })) + "</div>" +
      '<button type="submit" class="' + C.btn + '">Apply</button></form></div>';
    if (cm.error) html += errorBox(cm.error);
    html +=
      '<div class="' + C.card + ' overflow-hidden" aria-busy="' + (cm.loading ? "true" : "false") + '"><div class="overflow-x-auto"><table class="w-full text-xs text-left"><caption class="sr-only">Notification delivery queue</caption>' +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Created (IST)</th><th scope="col" class="' + C.th + '">Template / event</th><th scope="col" class="' + C.th + '">Recipient</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">Attempts</th><th scope="col" class="' + C.th + '">Last error</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
    if (cm.loading && !cm.items.length) html += emptyRow(7, "Loading queue…");
    else if (!cm.items.length) html += emptyRow(7, "No notifications match this filter.");
    else {
      html += cm.items.map(function (n) {
        const st = String(g(n, "status") || "").toUpperCase();
        const failed = st === "FAILED";
        const inFlight = st === "QUEUED" || st === "PROCESSING";
        const id = esc(g(n, "id"));
        return (
          '<tr class="align-top"><td class="' + C.td + ' whitespace-nowrap">' + esc(formatDateShort(g(n, "createdAt"))) + "</td>" +
          '<td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(g(n, "templateCode")) + '</p><p class="text-[11px] text-[#645C75]">' + esc(g(n, "eventType")) + "</p></td>" +
          '<td class="' + C.td + '"><p>' + esc(g(n, "recipientEmail")) + '</p><p class="text-[11px] text-[#645C75] max-w-xs truncate">' + esc(g(n, "subject")) + "</p></td>" +
          '<td class="' + C.td + '">' + badge(st) + "</td>" +
          '<td class="' + C.td + ' tabular-nums">' + esc(g(n, "attempts") || 0) + " / " + esc(g(n, "maxAttempts") || "—") + "</td>" +
          '<td class="' + C.td + ' max-w-xs text-rose-800">' + esc(g(n, "lastError") || "") + "</td>" +
          '<td class="' + C.td + ' text-right whitespace-nowrap"><button type="button" data-act="notif-detail" data-id="' + id + '" class="' + C.btn2 + '">Details</button>' +
          (canWrite && failed ? ' <button type="button" data-act="notif-action" data-do="retry" data-id="' + id + '" class="' + C.ok + '">Retry</button>' : "") +
          (canWrite && !failed && !inFlight ? ' <button type="button" data-act="notif-action" data-do="resend" data-id="' + id + '" class="' + C.btn2 + '">Resend</button>' : "") +
          "</td></tr>"
        );
      }).join("");
    }
    html += "</tbody></table></div>" + renderPager("notif", state.comms.pagination, "Notification queue pagination") + "</div>";
    if (!canWrite) html += '<p class="' + C.muted + '">Retry and resend require the <code>registrations.write</code> permission.</p>';
    return html;
  }

  ACTIONS["comms-status"] = function (el) {
    fetchNotifications({ page: 1, status: el.getAttribute("data-status") });
  };
  FORMS["comms-filter"] = function () {
    fetchNotifications({ page: 1, eventType: val("comms-event") });
  };
  ACTIONS["notif-detail"] = async function (el) {
    const id = el.getAttribute("data-id");
    const m = openModal({
      title: "Notification details",
      size: "lg",
      s: { loading: true, error: "", item: null, attempts: [] },
      body: function (mm) {
        const s = mm.s;
        if (s.loading) return '<p class="text-sm text-[#6E6580]" role="status">Loading…</p>';
        if (s.error) return errorBox(s.error);
        return (
          kvList(s.item, ["body", "bodyHtml", "body_html", "bodyText", "body_text", "payload"]) +
          section("Delivery attempts (" + s.attempts.length + ")", s.attempts.length ? s.attempts.map(function (a) { return '<div class="p-3 rounded-xl bg-[#FAF8FD] border border-[#ECE7F5] mb-2">' + kvList(a) + "</div>"; }).join("") : '<p class="' + C.muted + '">No attempts recorded.</p>')
        );
      },
    });
    const r = await api("/api/admin/notifications?id=" + encodeURIComponent(id));
    m.s.loading = false;
    if (r.ok) {
      m.s.item = r.data.item || {};
      m.s.attempts = r.data.attempts || [];
    } else m.s.error = r.data.error || "Could not load details.";
    if (modalStack.indexOf(m) >= 0) refreshModal(m);
  };
  ACTIONS["notif-action"] = async function (el) {
    const id = el.getAttribute("data-id");
    const action = el.getAttribute("data-do");
    const ans = await promptDialog({
      title: action === "retry" ? "Retry failed notification" : "Resend notification",
      message: action === "retry" ? "The failed message will be put back in the delivery queue." : "A new copy of this message will be queued for the recipient.",
      label: "Reason (recorded in the audit log)",
      required: true,
      confirmLabel: action === "retry" ? "Retry" : "Resend",
    });
    if (!ans) return;
    const r = await api("/api/admin/notifications", "POST", { action: action, queueId: id, reason: ans.value });
    if (!r.ok) return notify("error", r.data.error || "The action could not be completed.");
    notify("success", action === "retry" ? "Notification re-queued." : "Notification resend queued.");
    fetchNotifications();
  };

  // ---- Announcements ----
  function renderAnnouncementsPane() {
    const an = state.ann;
    let html =
      '<div class="flex justify-end"><button type="button" data-act="ann-new" class="' + C.btn + '">+ New announcement</button></div>';
    if (an.error) html += errorBox(an.error);
    if (an.loading && !an.items.length) html += '<div class="' + C.card + ' p-8 text-center text-sm text-[#6E6580]" role="status">Loading announcements…</div>';
    else if (!an.items.length) html += '<div class="' + C.card + ' p-8 text-center text-sm text-[#6E6580]">No announcements yet.</div>';
    else {
      html += '<ul class="space-y-3">' + an.items.map(function (a) {
        return (
          '<li class="' + C.card + ' p-4 space-y-2"><div class="flex flex-wrap items-start justify-between gap-3"><div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><h4 class="font-bold text-[#17151C]">' + esc(a.title) + "</h4>" + badge(a.severity) + badge(a.status) + "</div>" +
          '<p class="text-xs text-[#4A415C] whitespace-pre-line">' + esc(a.message) + "</p>" +
          '<p class="text-[11px] text-[#645C75]">' + (a.publishedAt ? "Published " + esc(formatDateShort(a.publishedAt)) + " • " : "Not published • ") + (a.expiresAt ? "Expires " + esc(formatDateShort(a.expiresAt)) : "No expiry") + "</p></div>" +
          '<button type="button" data-act="ann-edit" data-id="' + esc(a.id) + '" aria-label="Edit announcement ' + esc(a.title) + '" class="' + C.btn2 + '">Edit</button></div></li>'
        );
      }).join("") + "</ul>";
    }
    return html;
  }
  function openAnnouncementModal(ann) {
    const editing = Boolean(ann);
    const a = ann || { severity: "INFO", status: "DRAFT" };
    openModal({
      title: editing ? "Edit announcement" : "New announcement",
      size: "lg",
      s: { ann: ann },
      body: function () {
        return (
          '<form data-form="ann-form" class="space-y-4">' +
          field("Title", "an-title", inputEl("an-title", a.title || "", { required: true }), "", true) +
          field("Message", "an-message", textareaEl("an-message", a.message || "", 5, { required: true }), "Plain text. Shown to participants on the festival site once PUBLISHED.", true) +
          '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
          field("Severity", "an-severity", selectEl("an-severity", "Severity", ["INFO", "WARNING", "CRITICAL"], a.severity || "INFO")) +
          field("Status", "an-status", selectEl("an-status", "Status", ["DRAFT", "PUBLISHED", "ARCHIVED"], a.status || "DRAFT")) +
          field("Expires (IST, optional)", "an-expires", inputEl("an-expires", toIstInput(a.expiresAt), { type: "datetime-local" })) +
          "</div>" +
          '<div class="p-3 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] space-y-2">' +
          checkboxEl("an-broadcast", "Also email this announcement (requires status PUBLISHED)", false) +
          checkboxEl("an-resend", "Force a fresh send even if it was already broadcast to these recipients", false) +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
          field("Email template", "an-template", selectEl("an-template", "Email template", [["FESTIVAL_IMPORTANT_INFO", "Important information"], ["FESTIVAL_REMINDER", "Festival reminder"]], "FESTIVAL_IMPORTANT_INFO")) +
          field("Audience", "an-audience", selectEl("an-audience", "Audience", [["ALL", "Everyone"], ["ATTENDEE", "Attendees"], ["VOLUNTEER", "Volunteers"], ["SLI", "SLI professionals"]], "ALL")) +
          "</div></div>" +
          formFooter(editing ? "Save announcement" : "Create announcement") + "</form>"
        );
      },
    });
  }
  FORMS["ann-form"] = async function (form) {
    const m = modalOf(form);
    const existing = m && m.s.ann;
    setFormError(form, "");
    if (!val("an-title") || !val("an-message")) return setFormError(form, "Title and message are required.");
    const payload = { title: val("an-title"), message: val("an-message"), severity: val("an-severity"), status: val("an-status"), expiresAt: fromIstInput(val("an-expires")) };
    if (existing) payload.id = existing.id;
    if (checked("an-broadcast")) {
      if (payload.status !== "PUBLISHED") return setFormError(form, "Set the status to PUBLISHED before sending an email broadcast.");
      payload.broadcast = { templateCode: val("an-template"), audience: val("an-audience") };
      if (checked("an-resend")) payload.broadcast.resend = true;
      const ok = await promptDialog({ title: "Send email broadcast?", message: "This will queue an email to " + (payload.broadcast.audience === "ALL" ? "ALL participants" : "all " + payload.broadcast.audience + " participants") + ". It cannot be recalled once sent.", input: false, confirmLabel: "Publish & queue emails" });
      if (!ok) return;
    }
    formBusy(form, true);
    const r = await api("/api/admin/announcements", existing ? "PATCH" : "POST", payload);
    formBusy(form, false);
    if (!r.ok) return setFormError(form, r.data.error || "Could not save the announcement.");
    closeModal(m);
    notify("success", (existing ? "Announcement updated." : "Announcement created.") + (r.data.broadcast && r.data.broadcast.queued !== undefined ? " " + r.data.broadcast.queued + " email(s) queued" + (r.data.broadcast.deduped ? ", " + r.data.broadcast.deduped + " skipped (already sent)" : "") + (r.data.broadcast.failed ? ", " + r.data.broadcast.failed + " failed" : "") + "." : ""));
    fetchAnnouncements();
  };
  ACTIONS["ann-new"] = function () { openAnnouncementModal(null); };
  ACTIONS["ann-edit"] = function (el) {
    const a = state.ann.items.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (a) openAnnouncementModal(a);
  };


  // =========================================================================
  // STAFF & PERMISSION PROFILES (SUPERADMIN only; backend remains authoritative)
  // =========================================================================

  function permPicker(id, available, selected, readonly) {
    const list = available && available.length ? available : selected || [];
    const groups = {};
    list.forEach(function (p) {
      const k = String(p).split(".")[0];
      (groups[k] = groups[k] || []).push(p);
    });
    return (
      '<div id="' + id + '" class="space-y-2 max-h-64 overflow-y-auto p-2 border border-[#ECE7F5] rounded-lg">' +
      Object.keys(groups).sort().map(function (gk) {
        return (
          '<fieldset class="space-y-1"><legend class="text-[11px] font-bold uppercase text-[#4A415C]">' + esc(humanize(gk)) + '</legend><div class="grid grid-cols-1 sm:grid-cols-2 gap-1">' +
          groups[gk].map(function (p) {
            return '<label class="flex items-center gap-2 text-xs font-mono"><input type="checkbox" data-perm="' + esc(p) + '"' + ((selected || []).indexOf(p) >= 0 ? " checked" : "") + (readonly ? " disabled" : "") + " /> " + esc(p) + "</label>";
          }).join("") +
          "</div></fieldset>"
        );
      }).join("") +
      "</div>"
    );
  }
  function withoutForbidden(list, forbidden) {
    const bad = forbidden || [];
    return (list || []).filter(function (p) { return bad.indexOf(p) < 0; });
  }
  function collectPerms(id) {
    const root = document.getElementById(id);
    if (!root) return [];
    return Array.prototype.map.call(root.querySelectorAll("input[data-perm]:checked"), function (el) { return el.getAttribute("data-perm"); });
  }
  function sameSet(a, b) {
    const x = (a || []).slice().sort().join("|");
    const y = (b || []).slice().sort().join("|");
    return x === y;
  }

  function renderUsersTab() {
    const sf = state.staff;
    let html = '<div class="space-y-4">';
    html += pageHeader("Staff & Permission Profiles", "Create staff accounts, assign roles and permission profiles, and reset MFA. All changes are audit-logged; the server enforces every rule.");
    html += subTabs("staff", sf.sub, [["staff", "Staff accounts"], ["profiles", "Permission profiles"]]);
    html += sf.sub === "profiles" ? renderProfilesPane() : renderStaffPane();
    html += "</div>";
    return html;
  }

  function renderStaffPane() {
    const st = state.staff.users;
    let html =
      '<form data-form="staff-filter" class="' + C.card + ' p-4 flex flex-wrap items-end gap-3" role="search" aria-label="Staff filters">' +
      '<div class="w-44">' + field("Role", "staff-role", selectEl("staff-role", "Filter by role", [["", "All roles"], "SUPERADMIN", "ADMIN", "RGST"], st.role, 'data-change="staff-filter-change"')) + "</div>" +
      '<div class="flex-1 min-w-[200px]">' + field("Search", "staff-search", inputEl("staff-search", st.search, { type: "search", placeholder: "Name, username or email…" })) + "</div>" +
      '<button type="submit" class="' + C.btn + '">Apply</button>' +
      '<button type="button" data-act="staff-new" class="' + C.btn + '">+ New staff account</button></form>';
    if (st.error) html += errorBox(st.error);
    html +=
      '<div class="' + C.card + ' overflow-hidden" aria-busy="' + (st.loading ? "true" : "false") + '"><div class="overflow-x-auto"><table class="w-full text-xs text-left"><caption class="sr-only">Staff accounts</caption>' +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Staff member</th><th scope="col" class="' + C.th + '">Role</th><th scope="col" class="' + C.th + '">Permission profile</th><th scope="col" class="' + C.th + '">MFA</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + '">Last login</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
    if (st.loading && !st.items.length) html += emptyRow(7, "Loading staff accounts…");
    else if (!st.items.length) html += emptyRow(7, "No staff accounts match the filters.");
    else {
      html += st.items.map(function (u) {
        const self = String(u.id) === String(currentUser.id);
        return (
          '<tr class="align-top"><td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(u.fullName) + (self ? ' <span class="text-[#5B3E87]">(you)</span>' : "") + '</p><p class="text-[#645C75]">' + esc(u.email) + " • @" + esc(u.username) + "</p></td>" +
          '<td class="' + C.td + '">' + esc((u.roles || []).join(", ")) + "</td>" +
          '<td class="' + C.td + '">' + esc(u.permissionProfileName || "— none —") + '<p class="text-[11px] text-[#645C75]">' + esc((u.effectivePermissions || []).length) + " effective permissions" + ((u.customPermissions || []).length ? " (+" + esc(u.customPermissions.length) + " custom)" : "") + "</p></td>" +
          '<td class="' + C.td + '">' + badge(u.mfaEnabled ? "ACTIVE" : "PENDING", u.mfaEnabled ? "Enabled" : "Not enrolled") + "</td>" +
          '<td class="' + C.td + '">' + badge(u.isActive ? "ACTIVE" : "REVOKED", u.isActive ? "Active" : "Inactive") + "</td>" +
          '<td class="' + C.td + ' whitespace-nowrap">' + esc(u.lastLoginAt ? formatDateShort(u.lastLoginAt) : "Never") + "</td>" +
          '<td class="' + C.td + ' text-right"><button type="button" data-act="staff-edit" data-id="' + esc(u.id) + '" aria-label="Edit ' + esc(u.fullName) + '" class="' + C.btn2 + '">Edit</button></td></tr>'
        );
      }).join("");
    }
    html += "</tbody></table></div>" + renderPager("staff", st.pagination, "Staff pagination") + "</div>";
    return html;
  }

  function applyStaffFilters() {
    fetchStaffUsers({ page: 1, role: val("staff-role"), search: val("staff-search") });
  }
  FORMS["staff-filter"] = applyStaffFilters;
  CHANGES["staff-filter-change"] = applyStaffFilters;
  ACTIONS["staff-new"] = function () { openStaffModal(null); };
  ACTIONS["staff-edit"] = function (el) {
    const u = state.staff.users.items.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (u) openStaffModal(u);
  };

  function staffRoleSections(roleCode, u, creating) {
    const sf = state.staff;
    if (roleCode !== "ADMIN") {
      return '<p class="text-xs text-[#645C75] p-3 rounded-lg bg-[#F8F6FC] border border-[#E5DFEF]">' + (roleCode === "SUPERADMIN" ? "SUPERADMIN has every permission." : "RGST accounts cannot have permission profiles or custom permissions.") + "</p>";
    }
    return (
      field("Permission profile", "su-profile", selectEl("su-profile", "Permission profile", [["", "— No profile (default ADMIN permissions) —"]].concat((sf.profileOptions || []).map(function (p) { return [p.id, p.name]; })), (u && u.permissionProfileId) || "")) +
      '<div class="space-y-1"><p class="' + C.label + '" id="su-custom-label">Additional custom permissions</p><p class="text-[11px] text-[#645C75]">Granted on top of the profile. Prefer profiles for repeatable roles.</p>' +
      '<div role="group" aria-labelledby="su-custom-label">' + permPicker("su-perms", withoutForbidden(sf.availablePermissions, sf.forbiddenForAdmin), (u && u.customPermissions) || [], false) + "</div></div>"
    );
  }

  function openStaffModal(user) {
    const creating = !user;
    const u = user || {};
    const self = !creating && String(u.id) === String(currentUser.id);
    const currentRole = creating ? "ADMIN" : (u.roles || []).indexOf("SUPERADMIN") >= 0 ? "SUPERADMIN" : (u.roles || []).indexOf("ADMIN") >= 0 ? "ADMIN" : "RGST";
    openModal({
      title: creating ? "New staff account" : "Edit " + (u.fullName || "staff account"),
      size: "lg",
      s: { user: user, role: currentRole, originalRole: currentRole },
      body: function (m) {
        return (
          '<form data-form="staff-form" class="space-y-4" autocomplete="off">' +
          (creating
            ? '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
              field("Full name", "su-name", inputEl("su-name", "", { required: true }), "", true) +
              field("Username", "su-username", inputEl("su-username", "", { required: true, autocomplete: "off" }), "", true) +
              field("Email", "su-email", inputEl("su-email", "", { type: "email", required: true, autocomplete: "off" }), "", true) +
              field("Phone", "su-phone", inputEl("su-phone", "", { type: "tel" })) +
              '<div class="sm:col-span-2">' + field("Temporary password", "su-password", inputEl("su-password", "", { type: "password", required: true, autocomplete: "new-password" }), "Share it securely. It is never displayed again.", true) + "</div></div>"
            : '<div class="p-3 rounded-xl bg-[#F8F6FC] border border-[#E5DFEF] text-xs space-y-0.5"><p class="font-semibold text-sm">' + esc(u.fullName) + "</p><p>" + esc(u.email) + " • @" + esc(u.username) + " • " + esc(u.phone || "no phone") + "</p><p>MFA: " + (u.mfaEnabled ? "enabled" : "not enrolled") + "</p></div>") +
          field("Role", "su-role", selectEl("su-role", "Role", ["ADMIN", "RGST", "SUPERADMIN"], m.s.role, 'data-change="su-role"' + (self ? " disabled" : "")), self ? "You cannot change your own role." : "") +
          '<div id="su-role-sections" class="space-y-3">' + staffRoleSections(m.s.role, u, creating) + "</div>" +
          (creating ? "" : '<div class="space-y-3">' + checkboxEl("su-active", "Account is active (can sign in)", u.isActive !== false).replace("<input ", self ? "<input disabled " : "<input ") +
            field("Set new password (optional)", "su-newpw", inputEl("su-newpw", "", { type: "password", autocomplete: "new-password" }), "Leave blank to keep the current password.") +
            (Array.isArray(u.effectivePermissions) ? '<details class="text-xs"><summary class="cursor-pointer font-semibold text-[#4A415C]">Effective permissions (' + u.effectivePermissions.length + ')</summary><p class="font-mono text-[11px] text-[#4A415C] mt-1 break-words">' + esc(u.effectivePermissions.join(", ") || "none") + "</p></details>" : "") +
            (u.mfaEnabled && !self ? '<div><button type="button" data-act="staff-reset-mfa" class="' + C.danger + '">Reset MFA…</button><p class="text-[11px] text-[#645C75] mt-1">Forces re-enrolment at next login.</p></div>' : "") + "</div>") +
          formFooter(creating ? "Create account" : "Save changes") + "</form>"
        );
      },
    });
  }
  CHANGES["su-role"] = function (el) {
    const m = modalOf(el);
    if (!m) return;
    m.s.role = el.value;
    const box = document.getElementById("su-role-sections");
    if (box) box.innerHTML = staffRoleSections(m.s.role, m.s.user, !m.s.user);
  };
  FORMS["staff-form"] = async function (form) {
    const m = modalOf(form);
    const u = m.s.user;
    setFormError(form, "");
    const role = val("su-role") || m.s.role;
    const profileEl = document.getElementById("su-profile");
    const profileId = role === "ADMIN" && profileEl ? profileEl.value : "";
    const perms = role === "ADMIN" ? collectPerms("su-perms") : [];
    let r;
    formBusy(form, true);
    if (!u) {
      if (!val("su-name") || !val("su-username") || !val("su-email") || !document.getElementById("su-password").value) {
        formBusy(form, false);
        return setFormError(form, "Name, username, email and password are required.");
      }
      const body = { fullName: val("su-name"), username: val("su-username"), email: val("su-email"), phone: val("su-phone"), password: document.getElementById("su-password").value, roleCode: role };
      if (role === "ADMIN") {
        if (profileId) body.permissionProfileId = profileId;
        if (perms.length) body.customPermissions = perms;
      }
      r = await api("/api/admin/users", "POST", body);
      document.getElementById("su-password") && (document.getElementById("su-password").value = "");
    } else {
      const body = { userId: u.id };
      const activeEl = document.getElementById("su-active");
      if (activeEl && !activeEl.disabled && activeEl.checked !== (u.isActive !== false)) body.isActive = activeEl.checked;
      const pw = (document.getElementById("su-newpw") || {}).value || "";
      if (pw) body.newPassword = pw;
      if (role !== m.s.originalRole) body.roleCode = role;
      if (role === "ADMIN") {
        if ((profileId || null) !== (u.permissionProfileId || null)) body.permissionProfileId = profileId || null;
        if (!sameSet(perms, u.customPermissions || [])) body.customPermissions = perms;
      }
      if (Object.keys(body).length === 1) {
        formBusy(form, false);
        return setFormError(form, "No changes to save.");
      }
      r = await api("/api/admin/users", "PATCH", body);
      const npw = document.getElementById("su-newpw");
      if (npw) npw.value = "";
    }
    formBusy(form, false);
    if (!r.ok) {
      const msg = r.data.error || "Could not save the account.";
      setFormError(form, msg);
      notify("error", msg);
      return;
    }
    closeModal(m);
    notify("success", (u ? "Staff account updated." : "Staff account created.") + (r.data.sessionsRevoked ? " " + r.data.sessionsRevoked + " active session(s) revoked." : ""));
    fetchStaffUsers();
  };
  ACTIONS["staff-reset-mfa"] = async function (el) {
    const m = modalOf(el);
    const u = m && m.s.user;
    if (!u) return;
    const ans = await promptDialog({ title: "Reset MFA for " + u.fullName, message: "Their authenticator will be removed and they must enrol again at next sign-in.", input: false, confirmLabel: "Reset MFA", danger: true });
    if (!ans) return;
    const r = await api("/api/admin/users", "PATCH", { userId: u.id, resetMfa: true });
    if (!r.ok) return notify("error", r.data.error || "Could not reset MFA.");
    notify("success", "MFA reset for " + u.fullName + ".");
    closeModal(m);
    fetchStaffUsers();
  };

  // ---- Permission profiles ----
  function renderProfilesPane() {
    const sf = state.staff;
    let html = '<div class="flex justify-end"><button type="button" data-act="profile-new" class="' + C.btn + '">+ New profile</button></div>';
    if (sf.profilesError) html += errorBox(sf.profilesError);
    html +=
      '<div class="' + C.card + ' overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-xs text-left"><caption class="sr-only">Permission profiles</caption>' +
      '<thead class="' + C.thead + '"><tr><th scope="col" class="' + C.th + '">Profile</th><th scope="col" class="' + C.th + '">Permissions</th><th scope="col" class="' + C.th + '">Assigned staff</th><th scope="col" class="' + C.th + '">Status</th><th scope="col" class="' + C.th + ' text-right">Actions</th></tr></thead><tbody class="' + C.tbody + '">';
    if (sf.profilesLoading && !sf.profiles.length) html += emptyRow(5, "Loading profiles…");
    else if (!sf.profiles.length) html += emptyRow(5, "No permission profiles yet.");
    else {
      html += sf.profiles.map(function (p) {
        const locked = p.isSystem || (p.assignedCount || 0) > 0;
        return (
          '<tr class="align-top"><td class="' + C.td + '"><p class="font-semibold text-[#17151C]">' + esc(p.name) + (p.isSystem ? ' <span class="ml-1 px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 text-[10px]">SYSTEM</span>' : "") + '</p><p class="font-mono text-[11px] text-[#645C75]">' + esc(p.slug) + "</p>" + (p.description ? '<p class="text-[11px] text-[#645C75]">' + esc(p.description) + "</p>" : "") + "</td>" +
          '<td class="' + C.td + ' max-w-md"><details><summary class="cursor-pointer font-semibold text-[#4C3273]">' + esc((p.permissions || []).length) + ' permissions</summary><p class="font-mono text-[11px] text-[#4A415C] mt-1 break-words">' + esc((p.permissions || []).join(", ")) + "</p></details></td>" +
          '<td class="' + C.td + ' tabular-nums">' + esc(p.assignedCount || 0) + "</td>" +
          '<td class="' + C.td + '">' + badge(p.isActive ? "ACTIVE" : "REVOKED", p.isActive ? "Active" : "Inactive") + "</td>" +
          '<td class="' + C.td + ' text-right whitespace-nowrap"><button type="button" data-act="profile-edit" data-id="' + esc(p.id) + '" aria-label="Edit profile ' + esc(p.name) + '" class="' + C.btn2 + '">Edit</button> ' +
          '<button type="button" data-act="profile-delete" data-id="' + esc(p.id) + '" ' + (locked ? 'disabled title="System or assigned profiles cannot be deleted" ' : "") + 'aria-label="Delete profile ' + esc(p.name) + '" class="' + C.danger + '">Delete</button></td></tr>'
        );
      }).join("");
    }
    html += "</tbody></table></div></div>";
    return html;
  }
  function openProfileModal(profile) {
    const editing = Boolean(profile);
    const p = profile || {};
    openModal({
      title: editing ? "Edit profile — " + p.name : "New permission profile",
      size: "lg",
      s: { profile: profile },
      body: function () {
        return (
          '<form data-form="profile-form" class="space-y-4">' +
          (p.isSystem ? '<p class="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">This is a system profile. The server may restrict changes to it.</p>' : "") +
          field("Name", "pf-name", inputEl("pf-name", p.name || "", { required: true }), "", true) +
          field("Description", "pf-desc", textareaEl("pf-desc", p.description || "", 2)) +
          '<div class="space-y-1"><p class="' + C.label + '" id="pf-perms-label">Permissions</p><div role="group" aria-labelledby="pf-perms-label">' + permPicker("pf-perms", withoutForbidden(state.staff.availablePermissions, state.staff.forbiddenPermissions), p.permissions || [], false) + "</div></div>" +
          (editing ? checkboxEl("pf-active", "Profile is active", p.isActive !== false) : "") +
          formFooter(editing ? "Save profile" : "Create profile") + "</form>"
        );
      },
    });
  }
  FORMS["profile-form"] = async function (form) {
    const m = modalOf(form);
    const p = m.s.profile;
    setFormError(form, "");
    if (!val("pf-name")) return setFormError(form, "Profile name is required.");
    const body = { name: val("pf-name"), description: val("pf-desc"), permissions: collectPerms("pf-perms") };
    if (p) {
      body.id = p.id;
      body.isActive = checked("pf-active");
    }
    formBusy(form, true);
    const r = await api("/api/admin/permission-profiles", p ? "PATCH" : "POST", body);
    formBusy(form, false);
    if (!r.ok) return setFormError(form, r.data.error || "Could not save the profile.");
    closeModal(m);
    notify("success", (p ? "Profile updated." : "Profile created.") + (r.data.sessionsRevoked ? " " + r.data.sessionsRevoked + " active session(s) revoked." : ""));
    fetchProfiles();
  };
  ACTIONS["profile-new"] = function () { openProfileModal(null); };
  ACTIONS["profile-edit"] = function (el) {
    const p = state.staff.profiles.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (p) openProfileModal(p);
  };
  ACTIONS["profile-delete"] = async function (el) {
    const p = state.staff.profiles.find(function (x) { return String(x.id) === el.getAttribute("data-id"); });
    if (!p) return;
    const ans = await promptDialog({ title: "Delete profile", message: "Delete the permission profile \"" + p.name + "\"? This cannot be undone.", input: false, confirmLabel: "Delete profile", danger: true });
    if (!ans) return;
    const r = await api("/api/admin/permission-profiles", "DELETE", { id: p.id });
    if (!r.ok) return notify("error", r.data.error || "Could not delete the profile.");
    notify("success", "Profile deleted.");
    fetchProfiles();
  };

  function renderAttendanceTabBase() {
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
      '<a href="/rgst" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-[#7E61A8] hover:bg-[#6D5096] text-white">Open Registration Desk</a>' +
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
      renderPager("audit", state.auditPagination, "Audit log pagination") +
      "</div></div>";

    return html;
  }


  // =========================================================================
  // ATTENDANCE EXTRAS (per-day unique people vs marks, per-category)
  // =========================================================================

  function renderAttendanceExtras(ov) {
    const att = (ov && ov.attendance) || {};
    const byDay = att.byDay || [];
    const byCat = att.byCategory || [];
    return (
      '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">' +
      '<div class="' + C.card + ' p-4"><h4 class="text-sm font-bold mb-3">Attendance by day (IST)</h4><table class="w-full text-xs text-left"><caption class="sr-only">Unique people and total marks per day</caption>' +
      '<thead><tr class="border-b border-[#E5DFEF] text-[#5C536E]"><th scope="col" class="py-2">Date</th><th scope="col" class="py-2 text-right">Unique people</th><th scope="col" class="py-2 text-right">Marks</th></tr></thead><tbody>' +
      (byDay.length
        ? byDay.map(function (d) { return '<tr class="border-b border-[#F2EEF9]"><td class="py-2 font-medium">' + esc(d.date) + '</td><td class="py-2 text-right tabular-nums font-semibold">' + esc(d.uniquePeople) + '</td><td class="py-2 text-right tabular-nums">' + esc(d.marks) + "</td></tr>"; }).join("")
        : '<tr><td colspan="3" class="py-4 text-center text-[#6E6580]">No attendance recorded yet.</td></tr>') +
      "</tbody></table></div>" +
      '<div class="' + C.card + ' p-4"><h4 class="text-sm font-bold mb-3">Unique attendees by category</h4><table class="w-full text-xs text-left"><caption class="sr-only">Unique checked-in people per category</caption>' +
      '<thead><tr class="border-b border-[#E5DFEF] text-[#5C536E]"><th scope="col" class="py-2">Category</th><th scope="col" class="py-2 text-right">Unique people</th></tr></thead><tbody>' +
      (byCat.length
        ? byCat.map(function (c) { return '<tr class="border-b border-[#F2EEF9]"><td class="py-2 font-medium">' + esc(c.categoryCode) + '</td><td class="py-2 text-right tabular-nums font-semibold">' + esc(c.uniquePeople) + "</td></tr>"; }).join("")
        : '<tr><td colspan="2" class="py-4 text-center text-[#6E6580]">No attendance recorded yet.</td></tr>') +
      "</tbody></table></div></div>"
    );
  }
  function renderAttendanceTab() {
    return '<div class="space-y-6">' + renderAttendanceTabBase() + renderAttendanceExtras(state.overview) + "</div>";
  }

  // =========================================================================
  // MAIN RENDER & EVENT BINDING
  // =========================================================================

  function focusSelector(el) {
    if (!el) return "";
    if (el.id) return "#" + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id);
    if (!el.hasAttribute || !el.hasAttribute("data-act")) return "";
    let s = "";
    ["data-act", "data-pager", "data-page", "data-status", "data-key", "data-group", "data-id", "data-do", "data-rid"].forEach(function (a) {
      if (el.hasAttribute(a)) s += "[" + a + '="' + String(el.getAttribute(a)).replace(/"/g, '\\"') + '"]';
    });
    return s;
  }

  function renderMain() {
    if (!mainEl) return;
    const ae = document.activeElement;
    const inMain = ae && mainEl.contains(ae);
    const sel = inMain ? focusSelector(ae) : "";
    let selStart = null;
    let selEnd = null;
    if (inMain) {
      try {
        selStart = ae.selectionStart;
        selEnd = ae.selectionEnd;
      } catch (e) {
        selStart = null;
      }
    }
    const tab = state.activeTab;
    let html;
    if (tab === "OVERVIEW") html = renderOverviewTab();
    else if (PEOPLE_TABS[tab]) html = renderPeopleTab(tab);
    else if (tab === "ACCOMMODATIONS") html = renderAccommodationsTab();
    else if (tab === "ATTENDANCE") html = renderAttendanceTab();
    else if (tab === "PROGRAMME") html = renderProgrammeTab();
    else if (tab === "ASSIGNMENTS") html = renderAssignmentsTab();
    else if (tab === "COMMS") html = renderCommsTab();
    else if (tab === "COUPONS") html = renderCouponsTab();
    else if (tab === "CMS") html = renderCmsTab();
    else if (tab === "USERS") html = renderUsersTab();
    else if (tab === "SECURITY") html = renderSecurityTab();
    else if (tab === "AUDIT_LOGS") html = renderAuditLogsTab();
    else html = renderOverviewTab();
    mainEl.innerHTML = html;

    if (window.PFGQrCode && typeof window.PFGQrCode.renderAll === "function") {
      window.PFGQrCode.renderAll(mainEl);
    }

    bindTabEvents();

    if (sel) {
      const el = mainEl.querySelector(sel);
      if (el && typeof el.focus === "function") {
        el.focus();
        if (selStart !== null && selStart !== undefined) {
          try {
            el.setSelectionRange(selStart, selEnd);
          } catch (e) {
            /* not a text input */
          }
        }
      }
    }
  }

  function bindTabEvents() {
    // Overview card jumps
    mainEl.querySelectorAll("[data-overview-card]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const act = btn.getAttribute("data-overview-card");
        if (act === "ALL_REGS") jumpToFilteredRegistrations({ category: "ATTENDEE" });
        else if (act === "CONFIRMED_REGS") jumpToFilteredRegistrations({ category: "ATTENDEE", status: "CONFIRMED,APPROVED" });
        else if (act === "PENDING_REGS") jumpToFilteredRegistrations({ category: "ATTENDEE", status: "SUBMITTED,UNDER_REVIEW,ADDITIONAL_INFO_REQUIRED" });
        else if (act === "DELEGATE_REGS") jumpToFilteredRegistrations({ category: "DELEGATE" });
        else if (act === "APPROVED_VOLS") jumpToFilteredRegistrations({ category: "VOLUNTEER", status: "APPROVED" });
        else if (act === "APPROVED_SLI") jumpToFilteredRegistrations({ category: "SLI", status: "APPROVED" });
        else if (act === "ALLY_REGS") jumpToFilteredRegistrations({ category: "ALLY" });
        else if (act === "NOT_ARRIVED") jumpToFilteredRegistrations({ category: "ATTENDEE", status: "CONFIRMED,APPROVED", checkinStatus: "NOT_CHECKED_IN" });
        else if (act === "UNASSIGNED_VOLS") jumpToFilteredRegistrations({ category: "VOLUNTEER", status: "APPROVED", assignmentStatus: "UNASSIGNED" });
        else if (act === "UNASSIGNED_SLI") jumpToFilteredRegistrations({ category: "SLI", status: "APPROVED", assignmentStatus: "UNASSIGNED" });
        else if (act === "COMMS_FAILED") {
          state.comms.status = "FAILED";
          state.comms.sub = "queue";
          state.comms.page = 1;
          switchTab("COMMS");
        }
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
