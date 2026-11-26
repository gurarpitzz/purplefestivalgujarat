/**
 * Purple Festival Gujarat 2027 — Participant Dashboard Controller
 * Phase 10 (Vanilla JS, zero frameworks, strict DOM escaping, WCAG 2.2 AA).
 */
(function () {
  "use strict";

  var cfg = window.PFG_DASHBOARD_CONFIG || {};
  var csrfToken = cfg.csrfToken || (typeof window.csrfToken === "string" ? window.csrfToken : "");
  var endpoints = cfg.endpoints || {
    dashboard: "/api/account/dashboard",
    notifications: "/api/account/notifications",
    profile: "/api/account/profile",
    accommodations: "/api/account/accommodations",
    registrations: "/api/account/registrations",
    claimLegacy: "/api/auth/claim-legacy",
    mfa: "/api/auth/mfa",
    logout: "/api/auth/logout"
  };

  var state = {
    data: null,
    activeTab: "overview",
    notifFilter: "all",
    notifPage: 1,
    notifPagination: null,
    notifItems: [],
    progDayFilter: "all",
    lastModalTrigger: null,
    openModalId: null
  };

  var CATEGORY_META = {
    ATTENDEE: {
      title: "Attendee",
      applyUrl: "/apply/attendee",
      desc: "Join festival sessions, cultural showcases, assistive tech expo, and para-sports in Vadodara."
    },
    VOLUNTEER: {
      title: "Volunteer",
      applyUrl: "/apply/volunteer",
      desc: "Support hospitality, accessibility guidance, stage operations, and inclusion desks."
    },
    SLI: {
      title: "Sign Language Interpreter (SLI)",
      applyUrl: "/apply/sli",
      desc: "Provide Indian Sign Language interpretation across plenary stages and parallel sessions."
    }
  };

  var DAY_LABELS = {
    DAY_1: "Day 1 (21 Jan 2027)",
    DAY_2: "Day 2 (22 Jan 2027)",
    DAY_3: "Day 3 (23 Jan 2027)",
    "2027-01-21": "Day 1 (21 Jan 2027)",
    "2027-01-22": "Day 2 (22 Jan 2027)",
    "2027-01-23": "Day 3 (23 Jan 2027)"
  };

  // =========================================================================
  // 1. SAFE DOM & FORMATTING HELPERS
  // =========================================================================

  function escapeHtml(val) {
    return String(val == null ? "" : val)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function safeUrl(url) {
    if (typeof url !== "string") return "";
    var trimmed = url.trim();
    if (!trimmed) return "";
    if (/[\x00-\x1F\x7F\\]/.test(trimmed)) return "";
    if (trimmed.charAt(0) === "/" && trimmed.charAt(1) !== "/") return trimmed;
    if (/^https?:\/\/[^\s]+$/i.test(trimmed)) return trimmed;
    return "";
  }

  function formatStatusLabel(status) {
    return String(status || "").replace(/_/g, " ");
  }

  function statusBadgeClasses(status) {
    switch (String(status || "").toUpperCase()) {
      case "CONFIRMED":
      case "APPROVED":
      case "ARRANGED":
      case "COMPLETED":
      case "PUBLISHED":
      case "PRESENT":
        return "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30";
      case "SUBMITTED":
      case "UNDER_REVIEW":
      case "RESUBMITTED":
      case "REQUESTED":
      case "IN_PROGRESS":
      case "ASSIGNED":
        return "bg-[#59436D]/15 text-[#432F56] border-[#59436D]/30";
      case "WAITLISTED":
      case "ADDITIONAL_INFO_REQUIRED":
      case "PROPOSED":
      case "PENDING":
        return "bg-[#7A5C1F]/15 text-[#5E4514] border-[#7A5C1F]/35";
      case "REJECTED":
      case "CANCELLED":
      case "WITHDRAWN":
      case "DECLINED":
        return "bg-[#B42332]/15 text-[#8A1824] border-[#B42332]/30";
      default:
        return "bg-[#E8DFF0] text-[#432F56] border-[#59436D]/20";
    }
  }

  function formatDays(days) {
    if (!Array.isArray(days) || days.length === 0) {
      return "All Festival Days (21–23 Jan 2027)";
    }
    return days
      .map(function (d) {
        return DAY_LABELS[String(d)] || String(d);
      })
      .join(", ");
  }

  function formatDateTime(ts) {
    if (!ts) return "";
    var str = String(ts).replace(" ", "T");
    var dt = new Date(str);
    if (isNaN(dt.getTime())) return String(ts);
    try {
      return dt.toLocaleString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
    } catch (e) {
      return String(ts);
    }
  }

  function showBanner(el, type, message) {
    if (!el) return;
    el.classList.remove("hidden");
    el.setAttribute("role", type === "error" ? "alert" : "status");
    el.className =
      "rounded-xl p-3.5 text-sm font-medium " +
      (type === "error"
        ? "bg-[#B42332]/10 border border-[#B42332] text-[#7A121D]"
        : "bg-[#19734B]/10 border border-[#19734B] text-[#145C3B]");
    el.textContent = message;
  }

  function hideBanner(el) {
    if (!el) return;
    el.classList.add("hidden");
    el.textContent = "";
  }

  function apiFetch(url, options) {
    var opts = options || {};
    var headers = Object.assign({ Accept: "application/json" }, opts.headers || {});
    if (opts.method && opts.method.toUpperCase() !== "GET") {
      headers["X-CSRF-Token"] = csrfToken;
      if (opts.body && !headers["Content-Type"]) {
        headers["Content-Type"] = "application/json";
      }
    }
    return fetch(url, {
      method: opts.method || "GET",
      credentials: "same-origin",
      headers: headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (res) {
      return res
        .json()
        .catch(function () {
          return {};
        })
        .then(function (json) {
          if (res.status === 401) {
            window.location.href = "/login?next=" + encodeURIComponent("/dashboard");
          }
          return { ok: res.ok, status: res.status, data: json };
        });
    });
  }

  // =========================================================================
  // 2. ACCESSIBLE TAB CONTROLLER (ARIA + ARROW KEY NAVIGATION)
  // =========================================================================

  var tabButtons = Array.prototype.slice.call(document.querySelectorAll("[data-dash-tab]"));
  var tabPanels = Array.prototype.slice.call(document.querySelectorAll("[data-dash-panel]"));

  function activateTab(tabId, focusTab) {
    state.activeTab = tabId;
    tabButtons.forEach(function (btn) {
      var isSelected = btn.getAttribute("data-dash-tab") === tabId;
      btn.setAttribute("aria-selected", isSelected ? "true" : "false");
      btn.setAttribute("tabindex", isSelected ? "0" : "-1");
      btn.className =
        "min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors " +
        (isSelected
          ? "bg-[#F6F1F8] text-[#17151C]"
          : "bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]");
      if (isSelected && focusTab) {
        btn.focus();
      }
    });

    tabPanels.forEach(function (panel) {
      var pId = panel.getAttribute("data-dash-panel");
      var show = pId === tabId;
      if (show) {
        panel.classList.remove("hidden");
        if (pId === "accommodations" || pId === "settings") {
          panel.classList.add("grid");
        }
      } else {
        panel.classList.add("hidden");
        panel.classList.remove("grid");
      }
    });
  }

  tabButtons.forEach(function (btn, idx) {
    btn.addEventListener("click", function () {
      activateTab(btn.getAttribute("data-dash-tab"), false);
    });
    btn.addEventListener("keydown", function (e) {
      var nextIdx = -1;
      if (e.key === "ArrowRight") nextIdx = (idx + 1) % tabButtons.length;
      else if (e.key === "ArrowLeft") nextIdx = (idx - 1 + tabButtons.length) % tabButtons.length;
      else if (e.key === "Home") nextIdx = 0;
      else if (e.key === "End") nextIdx = tabButtons.length - 1;
      if (nextIdx >= 0) {
        e.preventDefault();
        activateTab(tabButtons[nextIdx].getAttribute("data-dash-tab"), true);
      }
    });
  });

  // =========================================================================
  // 3. ACCESSIBLE MODAL MANAGEMENT (ESCAPE CLOSE + FOCUS RESTORATION)
  // =========================================================================

  function openModal(modalId, triggerEl) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    state.lastModalTrigger = triggerEl || document.activeElement;
    state.openModalId = modalId;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    var focusable = modal.querySelector(
      'button:not([disabled]), [href], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
    );
    if (focusable) {
      focusable.focus();
    }
  }

  function closeModal(modalId) {
    var modal = document.getElementById(modalId || state.openModalId);
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    state.openModalId = null;
    if (state.lastModalTrigger && typeof state.lastModalTrigger.focus === "function") {
      state.lastModalTrigger.focus();
    }
    state.lastModalTrigger = null;
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && state.openModalId) {
      e.preventDefault();
      closeModal(state.openModalId);
    }
  });

  document.addEventListener("click", function (e) {
    var closeBtn = e.target.closest("[data-close-modal]");
    if (closeBtn) {
      closeModal(closeBtn.getAttribute("data-close-modal"));
      return;
    }
    if (state.openModalId && e.target && e.target.id === state.openModalId) {
      closeModal(state.openModalId);
    }
  });

  // =========================================================================
  // 4. RENDERERS
  // =========================================================================

  function renderHeaderAndMetrics(data) {
    var profile = data.profile || {};
    var regs = Array.isArray(data.registrations) ? data.registrations : [];
    var accs = Array.isArray(data.accommodations) ? data.accommodations : [];
    var unreadCount = Number(data.unreadCount || 0);

    var welcome = document.getElementById("dash-welcome-heading");
    if (welcome && profile.fullName) {
      welcome.textContent = "Welcome, " + profile.fullName;
    }

    var sub = document.getElementById("dash-header-subtitle");
    if (sub && profile.email) {
      sub.textContent = profile.email + " • " + (cfg.festivalCity || "Vadodara, Gujarat");
    }

    var regCountEl = document.getElementById("dash-tab-reg-count");
    if (regCountEl) regCountEl.textContent = String(regs.length);

    var accCountEl = document.getElementById("dash-tab-acc-count");
    if (accCountEl) accCountEl.textContent = String(accs.length);

    updateUnreadBadge(unreadCount);

    // Legacy claim banner
    var claimBanner = document.getElementById("dash-legacy-claim-banner");
    var claimText = document.getElementById("dash-legacy-claim-text");
    var claimCount = Number(data.legacyClaimableCount || 0);
    if (claimBanner) {
      if (claimCount > 0) {
        claimBanner.classList.remove("hidden");
        if (claimText) {
          claimText.textContent =
            "We found " +
            claimCount +
            " unlinked registration record" +
            (claimCount === 1 ? "" : "s") +
            " matching your verified email address. Claim to view them in your dashboard.";
        }
      } else {
        claimBanner.classList.add("hidden");
      }
    }

    // Overview KPI strip
    var totalDuties = 0;
    var attendedDaysMap = {};
    var activePrimaryCount = 0;
    regs.forEach(function (r) {
      if (!r.isLegacy && ["CANCELLED", "WITHDRAWN", "REJECTED"].indexOf(r.status) === -1) {
        activePrimaryCount++;
      }
      if (Array.isArray(r.dutyAssignments)) {
        totalDuties += r.dutyAssignments.length;
      }
      if (r.attendanceSummary && Array.isArray(r.attendanceSummary.days)) {
        r.attendanceSummary.days.forEach(function (d) {
          if (d && d.dateIst) attendedDaysMap[d.dateIst] = true;
        });
      }
    });
    var attendedDaysCount = Object.keys(attendedDaysMap).length;

    var metricsEl = document.getElementById("dash-overview-metrics");
    if (metricsEl) {
      metricsEl.innerHTML =
        metricCardHtml("Active Registrations", String(activePrimaryCount), "Across Attendee, Volunteer & SLI") +
        metricCardHtml("Duty Assignments", String(totalDuties), "Confirmed shift & venue postings") +
        metricCardHtml("Accommodations", String(accs.length), "Accessibility support requests") +
        metricCardHtml("Days Attended", String(attendedDaysCount) + " / 3", "Recorded at Registration Desk");
    }

    // Announcements
    var annWrap = document.getElementById("dash-announcements-wrap");
    var anns = Array.isArray(data.announcements) ? data.announcements : [];
    if (annWrap) {
      if (anns.length === 0) {
        annWrap.classList.add("hidden");
        annWrap.innerHTML = "";
      } else {
        annWrap.classList.remove("hidden");
        annWrap.innerHTML = anns
          .map(function (a) {
            return (
              '<div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-4 space-y-1">' +
              '<p class="text-xs font-bold uppercase tracking-wider text-[#432F56]">Festival Announcement</p>' +
              '<h3 class="font-display text-base font-semibold text-[#17151C]">' + escapeHtml(a.title) + "</h3>" +
              '<p class="text-sm text-[#4B4257]">' + escapeHtml(a.body) + "</p>" +
              "</div>"
            );
          })
          .join("");
      }
    }
  }

  function metricCardHtml(label, value, hint) {
    return (
      '<div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-4 space-y-1">' +
      '<p class="text-xs font-semibold uppercase tracking-wider text-[#4B4257]">' + escapeHtml(label) + "</p>" +
      '<p class="font-display text-2xl font-bold text-[#17151C]">' + escapeHtml(value) + "</p>" +
      '<p class="text-xs text-[#4B4257]">' + escapeHtml(hint) + "</p>" +
      "</div>"
    );
  }

  function updateUnreadBadge(count) {
    var badge = document.getElementById("dash-tab-unread-badge");
    if (!badge) return;
    if (count > 0) {
      badge.textContent = String(count);
      badge.classList.remove("hidden");
      badge.setAttribute("aria-label", count + " unread notification" + (count === 1 ? "" : "s"));
    } else {
      badge.textContent = "0";
      badge.classList.add("hidden");
    }
  }

  function renderDutyAssignmentsHtml(reg) {
    var duties = Array.isArray(reg.dutyAssignments) ? reg.dutyAssignments : [];
    var isVol = reg.categoryCode === "VOLUNTEER";
    var isSli = reg.categoryCode === "SLI";

    if (duties.length > 0) {
      var cards = duties
        .map(function (d) {
          var venueBadge =
            d.venueConfirmationStatus === "CONFIRMED"
              ? '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#19734B]/25 text-[#9CE5C0]">Confirmed Venue</span>'
              : d.venueName
                ? '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#D5B77B]/20 text-[#F2DCA9]">Proposed / Subject to Confirmation</span>'
                : "";
          var pairingBadge = d.isPairedInterpretation
            ? '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#9273B5]/30 text-[#E8DFF0]">2-SLI Paired Team (' +
              escapeHtml(String(d.pairedInterpretersCount || 2)) +
              " Interpreters)</span>"
            : "";
          var mapLink = safeUrl(d.venueMapUrl || "");
          return (
            '<div class="rounded-xl bg-[#25212B] border border-[#383242] p-4 space-y-2.5">' +
            '<div class="flex flex-wrap items-center justify-between gap-2">' +
            '<div class="flex flex-wrap items-center gap-2">' +
            '<span class="font-semibold text-[#F9F7FC] text-sm">' + escapeHtml(d.roleTitle || (isSli ? "ISL Interpretation" : "Volunteer Duty")) + "</span>" +
            venueBadge +
            pairingBadge +
            "</div>" +
            '<span class="text-xs font-mono text-[#D5B77B]">' +
            escapeHtml(DAY_LABELS[d.dutyDateIst] || d.dutyDateIst) +
            " • " +
            escapeHtml((d.startTime || "") + (d.endTime ? "–" + d.endTime : "")) +
            "</span>" +
            "</div>" +
            '<dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 text-xs">' +
            (d.department
              ? '<div><dt class="text-[#D7CCE2]">Department</dt><dd class="font-semibold text-[#F9F7FC]">' + escapeHtml(d.department) + "</dd></div>"
              : "") +
            (d.venueName
              ? '<div><dt class="text-[#D7CCE2]">Venue</dt><dd class="font-semibold text-[#F9F7FC]">' +
                escapeHtml(d.venueName) +
                (mapLink
                  ? ' <a href="' + escapeHtml(mapLink) + '" target="_blank" rel="noopener noreferrer" class="underline text-[#D5B77B] ml-1">Map</a>'
                  : "") +
                "</dd></div>"
              : "") +
            (d.sessionTitle
              ? '<div><dt class="text-[#D7CCE2]">Assigned Session</dt><dd class="font-semibold text-[#F9F7FC]">' +
                escapeHtml(d.sessionTitle) +
                (d.sessionTrack ? " (" + escapeHtml(d.sessionTrack) + ")" : "") +
                "</dd></div>"
              : "") +
            (d.shiftCode
              ? '<div><dt class="text-[#D7CCE2]">Shift</dt><dd class="font-semibold text-[#F9F7FC]">' + escapeHtml(formatStatusLabel(d.shiftCode)) + "</dd></div>"
              : "") +
            (d.reportingSupervisor
              ? '<div><dt class="text-[#D7CCE2]">Reporting Supervisor</dt><dd class="font-semibold text-[#F9F7FC]">' + escapeHtml(d.reportingSupervisor) + "</dd></div>"
              : "") +
            (d.reportingTime
              ? '<div><dt class="text-[#D7CCE2]">Reporting Time</dt><dd class="font-semibold text-[#F9F7FC]">' + escapeHtml(d.reportingTime) + "</dd></div>"
              : "") +
            "</dl>" +
            (d.reportingInstructions
              ? '<p class="text-xs text-[#E8DFF0] border-t border-[#383242] pt-2"><strong class="text-[#D5B77B]">Reporting Instructions:</strong> ' +
                escapeHtml(d.reportingInstructions) +
                "</p>"
              : "") +
            "</div>"
          );
        })
        .join("");

      return (
        '<div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-3" data-surface="dark">' +
        '<h3 class="font-display text-base font-semibold text-[#D5B77B]">' +
        (isSli ? "Assigned Interpretation Sessions &amp; Duty Schedule" : "Assigned Volunteer Duty Schedule") +
        "</h3>" +
        cards +
        "</div>"
      );
    }

    // Legacy fallback fields on volunteer_applications / sli_applications
    if (isVol && reg.volunteerDetails) {
      var v = reg.volunteerDetails;
      if (v.assignedDepartment || v.assignedVenue || v.assignedShift) {
        return (
          '<div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-2" data-surface="dark">' +
          '<h3 class="font-display text-base font-semibold text-[#D5B77B]">Volunteer Duty Assignment</h3>' +
          '<dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">' +
          (v.assignedDepartment ? '<div><dt class="text-[#D7CCE2]">Department</dt><dd class="font-semibold">' + escapeHtml(v.assignedDepartment) + "</dd></div>" : "") +
          (v.assignedVenue ? '<div><dt class="text-[#D7CCE2]">Venue</dt><dd class="font-semibold">' + escapeHtml(v.assignedVenue) + "</dd></div>" : "") +
          (v.assignedShift
            ? '<div><dt class="text-[#D7CCE2]">Shift</dt><dd class="font-semibold">' +
              escapeHtml(v.assignedShift + (v.reportingTime ? " (" + v.reportingTime + ")" : "")) +
              "</dd></div>"
            : "") +
          (v.assignedSupervisor ? '<div><dt class="text-[#D7CCE2]">Supervisor</dt><dd class="font-semibold">' + escapeHtml(v.assignedSupervisor) + "</dd></div>" : "") +
          (v.assignedResponsibility ? '<div class="sm:col-span-2"><dt class="text-[#D7CCE2]">Responsibility</dt><dd class="font-semibold">' + escapeHtml(v.assignedResponsibility) + "</dd></div>" : "") +
          "</dl></div>"
        );
      }
    }

    if (isSli && reg.sliDetails) {
      var s = reg.sliDetails;
      var sessList = Array.isArray(s.assignedSessions) ? s.assignedSessions : [];
      if (s.assignedVenue || s.dutyTimes || sessList.length > 0) {
        return (
          '<div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-5 space-y-2.5" data-surface="dark">' +
          '<h3 class="font-display text-base font-semibold text-[#D5B77B]">SLI Interpretation Assignment</h3>' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">' +
          (s.assignedVenue ? '<div><p class="text-[#D7CCE2]">Venue</p><p class="font-semibold">' + escapeHtml(s.assignedVenue) + "</p></div>" : "") +
          (s.dutyTimes ? '<div><p class="text-[#D7CCE2]">Duty Schedule</p><p class="font-semibold">' + escapeHtml(s.dutyTimes) + "</p></div>" : "") +
          "</div>" +
          (sessList.length > 0
            ? '<ul class="space-y-1 text-xs border-t border-[#383242] pt-2">' +
              sessList
                .map(function (item) {
                  return (
                    '<li class="text-[#E8DFF0]"><strong>' +
                    escapeHtml(item.day || "Session") +
                    ":</strong> " +
                    escapeHtml(item.sessionTitle || "") +
                    (item.venue ? " — " + escapeHtml(item.venue) : "") +
                    (item.dutyTime ? " (" + escapeHtml(item.dutyTime) + ")" : "") +
                    "</li>"
                  );
                })
                .join("") +
              "</ul>"
            : "") +
          "</div>"
        );
      }
    }

    if ((isVol || isSli) && reg.status === "APPROVED") {
      return (
        '<div class="rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 text-xs text-[#4B4257]">' +
        "Your application is <strong>Approved</strong>. Coordinators are finalizing venue and shift postings — your duty assignment will appear here automatically once published." +
        "</div>"
      );
    }

    return "";
  }

  function renderCategoryDetailsHtml(reg) {
    if (reg.categoryCode === "ATTENDEE") {
      var ad = reg.attendeeDetails;
      var items = [];
      items.push({ label: "Check-In Method", value: "Manual Registration Desk (Registration ID: " + reg.registrationCode + ")" });
      items.push({ label: "Confirmed Festival Days", value: formatDays(reg.attendingDays) });
      if (ad) {
        if (ad.ageGroup) items.push({ label: "Age Group", value: formatStatusLabel(ad.ageGroup) });
        if (ad.foodPreference) items.push({ label: "Food Preference", value: formatStatusLabel(ad.foodPreference) });
        if (ad.paraSportsAthlete) {
          var evts = Array.isArray(ad.paraSportsEvents) && ad.paraSportsEvents.length ? ad.paraSportsEvents.join(", ") : "Registered";
          items.push({ label: "Para-Sports", value: evts });
        }
        if (ad.outstationTravelRequired) {
          items.push({ label: "Outstation Travel", value: (ad.travelMode ? formatStatusLabel(ad.travelMode) : "Yes") + (ad.lodgingNeeded ? " • Lodging Requested" : "") });
        }
        if (ad.dietaryRequirements) {
          items.push({ label: "Dietary Note (Private)", value: ad.dietaryRequirements });
        }
      }
      return renderDlGridHtml(items);
    }

    if (reg.categoryCode === "VOLUNTEER" && reg.volunteerDetails) {
      var v = reg.volunteerDetails;
      var vItems = [];
      if (v.primaryRole) vItems.push({ label: "Primary Role Preference", value: formatStatusLabel(v.primaryRole) });
      if (v.msuAffiliationStatus) vItems.push({ label: "MSU Affiliation", value: formatStatusLabel(v.msuAffiliationStatus) });
      if (v.institutionName) vItems.push({ label: "Institution", value: v.institutionName });
      if (Array.isArray(v.languagesSpoken) && v.languagesSpoken.length) {
        vItems.push({ label: "Languages", value: v.languagesSpoken.join(", ") });
      }
      if (Array.isArray(v.availabilityShifts) && v.availabilityShifts.length) {
        vItems.push({ label: "Preferred Shifts", value: v.availabilityShifts.map(formatStatusLabel).join(", ") });
      }
      vItems.push({ label: "Assignment Status", value: formatStatusLabel(reg.assignmentStatus || "UNASSIGNED") });
      return renderDlGridHtml(vItems);
    }

    if (reg.categoryCode === "SLI" && reg.sliDetails) {
      var s = reg.sliDetails;
      var sItems = [];
      if (s.qualificationCategory) sItems.push({ label: "Qualification", value: formatStatusLabel(s.qualificationCategory) });
      if (s.certificationLevel) sItems.push({ label: "Certification", value: s.certificationLevel });
      if (s.experienceBand || s.yearsOfExperience != null) {
        sItems.push({
          label: "Experience",
          value: s.experienceBand ? formatStatusLabel(s.experienceBand) : String(s.yearsOfExperience) + " year(s)"
        });
      }
      sItems.push({ label: "Based in Gujarat", value: s.basedInGujarat ? "Yes" : "Outside Gujarat" });
      if (Array.isArray(s.areasOfExpertise) && s.areasOfExpertise.length) {
        sItems.push({ label: "Areas of Expertise", value: s.areasOfExpertise.join(", ") });
      }
      sItems.push({ label: "Assignment Status", value: formatStatusLabel(reg.assignmentStatus || "UNASSIGNED") });
      return renderDlGridHtml(sItems);
    }

    return "";
  }

  function renderDlGridHtml(items) {
    if (!items.length) return "";
    return (
      '<dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 rounded-xl bg-[#F6F1F8]/70 border border-[#59436D]/15 p-4 text-xs">' +
      items
        .map(function (it) {
          return (
            '<div><dt class="text-[#4B4257] font-medium">' +
            escapeHtml(it.label) +
            '</dt><dd class="font-semibold text-[#17151C] mt-0.5">' +
            escapeHtml(it.value) +
            "</dd></div>"
          );
        })
        .join("") +
      "</dl>"
    );
  }

  function renderAttendanceSummaryHtml(reg) {
    var summary = reg.attendanceSummary || { totalDaysPresent: 0, days: [] };
    var days = Array.isArray(summary.days) ? summary.days : [];
    if (days.length === 0) {
      return (
        '<div class="rounded-xl border border-[#59436D]/15 bg-white px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-xs text-[#4B4257]">' +
        '<span><strong class="text-[#17151C]">On-Site Attendance:</strong> No check-ins recorded yet. Show Registration ID <strong class="font-mono text-[#432F56]">' +
        escapeHtml(reg.registrationCode) +
        "</strong> at the Vadodara Registration Desk.</span>" +
        "</div>"
      );
    }
    return (
      '<div class="rounded-xl border border-[#19734B]/30 bg-[#19734B]/5 px-4 py-3 space-y-2 text-xs">' +
      '<p class="font-semibold text-[#145C3B]">Verified On-Site Attendance (' + escapeHtml(String(summary.totalDaysPresent)) + " day" + (summary.totalDaysPresent === 1 ? "" : "s") + " present)</p>" +
      '<ul class="flex flex-wrap gap-2">' +
      days
        .map(function (d) {
          return (
            '<li class="px-2.5 py-1 rounded-lg bg-white border border-[#19734B]/25 text-[#145C3B] font-semibold">' +
            escapeHtml(DAY_LABELS[d.dateIst] || d.dateIst) +
            (d.venueLabel ? " • " + escapeHtml(d.venueLabel) : "") +
            "</li>"
          );
        })
        .join("") +
      "</ul></div>"
    );
  }

  function renderStatusHistoryHtml(reg) {
    var history = Array.isArray(reg.statusHistory) ? reg.statusHistory : [];
    if (history.length === 0) return "";
    return (
      '<details class="rounded-xl border border-[#59436D]/15 bg-[#F6F1F8]/40 px-4 py-3 text-xs">' +
      '<summary class="cursor-pointer font-semibold text-[#432F56] min-h-[32px] flex items-center">Application Status History (' +
      escapeHtml(String(history.length)) +
      ")</summary>" +
      '<ol class="mt-3 space-y-2 border-l-2 border-[#9273B5]/50 pl-3">' +
      history
        .map(function (h) {
          return (
            '<li><div class="flex flex-wrap items-center gap-2">' +
            '<span class="font-bold text-[#17151C]">' + escapeHtml(formatStatusLabel(h.newStatus)) + "</span>" +
            '<span class="text-[#4B4257]">' + escapeHtml(formatDateTime(h.createdAt)) + "</span>" +
            "</div>" +
            (h.publicMessage ? '<p class="text-[#4B4257] mt-0.5">' + escapeHtml(h.publicMessage) + "</p>" : "") +
            "</li>"
          );
        })
        .join("") +
      "</ol></details>"
    );
  }

  function renderRegistrations(data) {
    var regs = Array.isArray(data.registrations) ? data.registrations : [];
    var primaryRegs = regs.filter(function (r) {
      return !r.isLegacy;
    });
    var legacyRegs = regs.filter(function (r) {
      return r.isLegacy;
    });

    var container = document.getElementById("dash-registrations-container");
    if (container) {
      if (primaryRegs.length === 0) {
        container.innerHTML =
          '<div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-8 text-center space-y-4">' +
          '<h3 class="font-display text-2xl font-semibold text-[#17151C]">No Active Registrations Yet</h3>' +
          '<p class="text-sm text-[#4B4257] max-w-lg mx-auto">' +
          "Register as an Attendee, Volunteer, or Indian Sign Language Interpreter for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara)." +
          "</p>" +
          '<a href="/apply" class="btn-primary-pfg inline-flex items-center justify-center min-h-[48px] px-6 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm">' +
          "Start Your Registration</a></div>";
      } else {
        container.innerHTML = primaryRegs
          .map(function (reg) {
            var infoNeededBanner =
              reg.status === "ADDITIONAL_INFO_REQUIRED"
                ? '<div role="alert" class="rounded-xl bg-[#FFF9EB] border-2 border-[#D5B77B] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">' +
                  '<div class="space-y-1 text-sm">' +
                  '<p class="font-bold text-[#5E4514]">Additional Information Requested by Coordinator</p>' +
                  '<p class="text-[#4B4257]">' +
                  escapeHtml(reg.publicReviewNote || "Please review and update your application details, then resubmit for review.") +
                  "</p></div>" +
                  '<button type="button" data-action="edit-reg" data-reg-id="' + escapeHtml(reg.id) + '" class="min-h-[44px] px-4 py-2 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-white text-xs font-bold shrink-0">' +
                  "Update &amp; Resubmit</button></div>"
                : "";

            var reviewNoteBox =
              reg.publicReviewNote && reg.status !== "ADDITIONAL_INFO_REQUIRED"
                ? '<div class="rounded-xl bg-[#F6F1F8] border border-[#9273B5]/40 p-3.5 text-sm text-[#211B29]">' +
                  '<span class="font-bold text-[#432F56]">Coordinator Update: </span>' +
                  escapeHtml(reg.publicReviewNote) +
                  "</div>"
                : "";

            var actionsHtml = "";
            if (reg.canEdit) {
              actionsHtml +=
                '<button type="button" data-action="edit-reg" data-reg-id="' +
                escapeHtml(reg.id) +
                '" class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white hover:bg-[#F6F1F8] text-[#432F56] text-xs font-bold transition-colors">' +
                (reg.status === "ADDITIONAL_INFO_REQUIRED" ? "Update &amp; Resubmit" : "Edit Details") +
                "</button>";
            }
            if (reg.canWithdraw) {
              var cancelLabel = reg.categoryCode === "ATTENDEE" ? "Cancel Registration" : "Withdraw Application";
              actionsHtml +=
                '<button type="button" data-action="withdraw-reg" data-reg-id="' +
                escapeHtml(reg.id) +
                '" class="min-h-[44px] px-4 py-2 rounded-xl border border-[#B42332]/40 bg-white hover:bg-[#B42332]/10 text-[#8A1824] text-xs font-bold transition-colors">' +
                cancelLabel +
                "</button>";
            }

            return (
              '<article class="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-xs overflow-hidden">' +
              '<div class="p-6 border-b border-[#59436D]/15 flex flex-wrap items-start justify-between gap-4">' +
              '<div class="space-y-1.5">' +
              '<div class="flex flex-wrap items-center gap-2">' +
              '<span class="font-mono text-sm font-bold text-[#432F56] bg-[#E8DFF0] px-2.5 py-1 rounded-lg">' +
              escapeHtml(reg.registrationCode) +
              "</span>" +
              '<span class="px-2.5 py-1 rounded-lg text-xs font-bold border ' +
              statusBadgeClasses(reg.status) +
              '">' +
              escapeHtml(formatStatusLabel(reg.status)) +
              "</span>" +
              (reg.assignmentStatus && reg.assignmentStatus !== "UNASSIGNED"
                ? '<span class="px-2.5 py-1 rounded-lg text-xs font-bold border ' +
                  statusBadgeClasses(reg.assignmentStatus) +
                  '">Duty: ' +
                  escapeHtml(formatStatusLabel(reg.assignmentStatus)) +
                  "</span>"
                : "") +
              "</div>" +
              '<h3 class="font-display text-xl font-semibold text-[#17151C]">' +
              escapeHtml(reg.categoryName || reg.categoryCode) +
              "</h3>" +
              '<p class="text-xs text-[#4B4257]">Festival Days: ' +
              escapeHtml(formatDays(reg.attendingDays)) +
              (reg.submittedAt ? " • Submitted " + escapeHtml(formatDateTime(reg.submittedAt)) : "") +
              "</p>" +
              "</div>" +
              (actionsHtml ? '<div class="flex flex-wrap items-center gap-2">' + actionsHtml + "</div>" : "") +
              "</div>" +
              '<div class="p-6 space-y-4">' +
              infoNeededBanner +
              reviewNoteBox +
              renderCategoryDetailsHtml(reg) +
              renderDutyAssignmentsHtml(reg) +
              renderAttendanceSummaryHtml(reg) +
              renderStatusHistoryHtml(reg) +
              "</div></article>"
            );
          })
          .join("");
      }
    }

    // Eligible remaining categories CTA
    var eligible = Array.isArray(data.eligibleApplyCategories) ? data.eligibleApplyCategories : [];
    var eligWrap = document.getElementById("dash-eligible-apply-wrap");
    var eligGrid = document.getElementById("dash-eligible-apply-grid");
    if (eligWrap && eligGrid) {
      if (eligible.length === 0) {
        eligWrap.classList.add("hidden");
      } else {
        eligWrap.classList.remove("hidden");
        eligGrid.innerHTML = eligible
          .map(function (code) {
            var meta = CATEGORY_META[code] || { title: code, applyUrl: "/apply", desc: "" };
            return (
              '<div class="rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-4 flex flex-col justify-between gap-3">' +
              '<div class="space-y-1">' +
              '<h3 class="font-display text-base font-semibold text-[#17151C]">' + escapeHtml(meta.title) + "</h3>" +
              '<p class="text-xs text-[#4B4257]">' + escapeHtml(meta.desc) + "</p>" +
              "</div>" +
              '<a href="' + escapeHtml(meta.applyUrl) + '" class="min-h-[44px] px-4 py-2 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] text-xs font-bold inline-flex items-center justify-center transition-colors">' +
              "Apply as " + escapeHtml(meta.title) +
              "</a></div>"
            );
          })
          .join("");
      }
    }

    // Legacy read-only archive registrations
    var legacyWrap = document.getElementById("dash-legacy-regs-wrap");
    var legacyList = document.getElementById("dash-legacy-regs-list");
    if (legacyWrap && legacyList) {
      if (legacyRegs.length === 0) {
        legacyWrap.classList.add("hidden");
      } else {
        legacyWrap.classList.remove("hidden");
        legacyList.innerHTML = legacyRegs
          .map(function (lr) {
            return (
              '<article class="rounded-2xl bg-white border border-[#59436D]/20 p-5 flex flex-wrap items-center justify-between gap-3">' +
              '<div class="space-y-1">' +
              '<div class="flex flex-wrap items-center gap-2">' +
              '<span class="font-mono text-xs font-bold bg-[#E8DFF0] text-[#432F56] px-2 py-0.5 rounded">' + escapeHtml(lr.registrationCode) + "</span>" +
              '<span class="px-2 py-0.5 rounded text-xs font-bold border ' + statusBadgeClasses(lr.status) + '">' + escapeHtml(formatStatusLabel(lr.status)) + "</span>" +
              '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-[#F6F1F8] text-[#4B4257] border border-[#59436D]/20">Historical Read-Only</span>' +
              "</div>" +
              '<h3 class="font-display text-base font-semibold text-[#17151C]">' + escapeHtml(lr.categoryName || lr.categoryCode) + "</h3>" +
              '<p class="text-xs text-[#4B4257]">Submitted ' + escapeHtml(formatDateTime(lr.createdAt)) + "</p>" +
              "</div></article>"
            );
          })
          .join("");
      }
    }
  }

  function renderAccommodations(data) {
    var regs = Array.isArray(data.registrations) ? data.registrations : [];
    var accs = Array.isArray(data.accommodations) ? data.accommodations : [];
    var types = Array.isArray(data.accommodationTypes) ? data.accommodationTypes : [];

    var typeMap = {};
    types.forEach(function (t) {
      typeMap[t.code] = t.label;
    });

    var noRegs = document.getElementById("dash-acc-no-regs");
    var form = document.getElementById("dash-acc-form");
    var selectEl = document.getElementById("acc-reg-select");
    var typesList = document.getElementById("dash-acc-types-list");

    if (regs.length === 0) {
      if (noRegs) noRegs.classList.remove("hidden");
      if (form) form.classList.add("hidden");
    } else {
      if (noRegs) noRegs.classList.add("hidden");
      if (form) form.classList.remove("hidden");
      if (selectEl) {
        selectEl.innerHTML = regs
          .map(function (r) {
            return (
              '<option value="' + escapeHtml(r.id) + '">' +
              escapeHtml(r.registrationCode + " — " + (r.categoryName || r.categoryCode)) +
              "</option>"
            );
          })
          .join("");
      }
      if (typesList) {
        typesList.innerHTML = types
          .map(function (t) {
            return (
              '<label class="cursor-pointer rounded-xl border border-[#59436D]/20 hover:bg-[#F6F1F8] p-2.5 flex items-center gap-2.5 text-sm text-[#17151C]">' +
              '<input type="checkbox" name="selectedTypes[]" value="' + escapeHtml(t.code) + '" class="w-4 h-4 accent-[#59436D]" />' +
              "<span>" + escapeHtml(t.label) + "</span></label>"
            );
          })
          .join("");
      }
    }

    var listEl = document.getElementById("dash-acc-list");
    if (listEl) {
      if (accs.length === 0) {
        listEl.innerHTML =
          '<p class="text-sm text-[#4B4257]">No accommodation requests submitted yet. Use the form to request any accessibility support you need.</p>';
      } else {
        listEl.innerHTML = accs
          .map(function (req) {
            var pills =
              Array.isArray(req.selectedTypes) && req.selectedTypes.length > 0
                ? '<ul class="flex flex-wrap gap-1.5">' +
                  req.selectedTypes
                    .map(function (code) {
                      return (
                        '<li class="px-2 py-0.5 rounded bg-white border border-[#59436D]/20 text-xs font-medium text-[#211B29]">' +
                        escapeHtml(typeMap[code] || code) +
                        "</li>"
                      );
                    })
                    .join("") +
                  "</ul>"
                : "";

            return (
              '<div class="rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-4 space-y-2">' +
              '<div class="flex items-center justify-between gap-2">' +
              '<span class="font-mono text-xs font-bold text-[#432F56]">' + escapeHtml(req.registrationCode) + "</span>" +
              '<span class="px-2.5 py-0.5 rounded-md text-xs font-bold border ' + statusBadgeClasses(req.status) + '">' +
              escapeHtml(formatStatusLabel(req.status)) +
              "</span></div>" +
              pills +
              (req.freeTextDetails ? '<p class="text-sm text-[#4B4257]">' + escapeHtml(req.freeTextDetails) + "</p>" : "") +
              (req.escortAccompanying
                ? '<p class="text-xs text-[#432F56]"><strong>Companion / Escort:</strong> ' +
                  escapeHtml(req.escortName || "Accompanying person noted") +
                  "</p>"
                : "") +
              (req.coordinatorNotes
                ? '<p class="text-xs font-semibold text-[#145C3B] border-t border-[#59436D]/15 pt-1.5">Coordinator Note: ' +
                  escapeHtml(req.coordinatorNotes) +
                  "</p>"
                : "") +
              '<p class="text-[11px] text-[#4B4257]">Submitted ' + escapeHtml(formatDateTime(req.createdAt)) + "</p>" +
              "</div>"
            );
          })
          .join("");
      }
    }
  }

  function renderNotificationsList(items, unreadCount, pagination) {
    state.notifItems = Array.isArray(items) ? items : [];
    state.notifPagination = pagination || null;
    updateUnreadBadge(Number(unreadCount || 0));

    var markAllBtn = document.getElementById("dash-notif-mark-all-btn");
    if (markAllBtn) {
      markAllBtn.disabled = Number(unreadCount || 0) <= 0;
    }

    var summary = document.getElementById("dash-notif-summary");
    if (summary) {
      summary.textContent =
        unreadCount > 0
          ? "You have " + unreadCount + " unread notification" + (unreadCount === 1 ? "" : "s") + "."
          : "You are all caught up — no unread notifications.";
    }

    var listEl = document.getElementById("dash-notif-list");
    if (listEl) {
      if (state.notifItems.length === 0) {
        listEl.innerHTML =
          '<p class="text-sm text-[#4B4257] py-4 text-center">' +
          (state.notifFilter === "unread" ? "No unread notifications." : "No notifications yet.") +
          "</p>";
      } else {
        listEl.innerHTML = state.notifItems
          .map(function (n) {
            var linkHref = safeUrl(n.actionUrl || "");
            var unreadClass = n.isRead
              ? "border-[#59436D]/15 bg-white"
              : "border-[#9273B5] bg-[#F6F1F8]";
            return (
              '<article class="rounded-xl border p-4 flex flex-col sm:flex-row sm:items-start justify-between gap-3 ' + unreadClass + '">' +
              '<div class="space-y-1">' +
              '<div class="flex flex-wrap items-center gap-2">' +
              (!n.isRead
                ? '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#59436D] text-white">Unread</span>'
                : '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-[#E8DFF0] text-[#432F56]">Read</span>') +
              '<h3 class="font-semibold text-sm text-[#17151C]">' + escapeHtml(n.title) + "</h3>" +
              "</div>" +
              '<p class="text-sm text-[#4B4257]">' + escapeHtml(n.message) + "</p>" +
              '<p class="text-xs text-[#4B4257]">' + escapeHtml(formatDateTime(n.createdAt)) + "</p>" +
              "</div>" +
              '<div class="flex flex-wrap items-center gap-2 shrink-0">' +
              (linkHref && linkHref !== "/dashboard"
                ? '<a href="' + escapeHtml(linkHref) + '" class="min-h-[40px] px-3 py-1.5 rounded-lg border border-[#59436D]/30 bg-white text-xs font-bold text-[#432F56] inline-flex items-center">Open</a>'
                : "") +
              (!n.isRead
                ? '<button type="button" data-action="mark-notif-read" data-notif-id="' + escapeHtml(n.id) + '" class="min-h-[40px] px-3 py-1.5 rounded-lg bg-[#59436D] hover:bg-[#432F56] text-white text-xs font-bold">Mark as Read</button>'
                : "") +
              "</div></article>"
            );
          })
          .join("");
      }
    }

    var pagEl = document.getElementById("dash-notif-pagination");
    if (pagEl) {
      if (pagination && pagination.totalPages > 1) {
        pagEl.classList.remove("hidden");
        pagEl.innerHTML =
          '<span class="text-xs text-[#4B4257]">Page ' +
          escapeHtml(String(pagination.page)) +
          " of " +
          escapeHtml(String(pagination.totalPages)) +
          " (" +
          escapeHtml(String(pagination.total)) +
          " total)</span>" +
          '<div class="flex gap-2">' +
          '<button type="button" data-notif-page="' + escapeHtml(String(Math.max(1, pagination.page - 1))) + '" ' +
          (pagination.page <= 1 ? "disabled" : "") +
          ' class="min-h-[40px] px-3 py-1 rounded-lg border border-[#59436D]/30 bg-white text-xs font-bold disabled:opacity-40">Previous</button>' +
          '<button type="button" data-notif-page="' + escapeHtml(String(Math.min(pagination.totalPages, pagination.page + 1))) + '" ' +
          (pagination.page >= pagination.totalPages ? "disabled" : "") +
          ' class="min-h-[40px] px-3 py-1 rounded-lg border border-[#59436D]/30 bg-white text-xs font-bold disabled:opacity-40">Next</button>' +
          "</div>";
      } else {
        pagEl.classList.add("hidden");
        pagEl.innerHTML = "";
      }
    }
  }

  function renderProgramme(data) {
    var allItems = Array.isArray(data.confirmedProgramme) ? data.confirmedProgramme : [];
    var filtered =
      state.progDayFilter === "all"
        ? allItems
        : allItems.filter(function (it) {
            return String(it.dayNumber) === String(state.progDayFilter);
          });

    var listEl = document.getElementById("dash-programme-list");
    if (!listEl) return;

    if (filtered.length === 0) {
      listEl.innerHTML =
        '<div class="rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-5 text-sm text-[#4B4257]">' +
        "Confirmed sessions for this selection will appear here as soon as they are published by the Festival Secretariat." +
        "</div>";
      return;
    }

    listEl.innerHTML = filtered
      .map(function (it) {
        var venueStatusLabel =
          it.venueConfirmationStatus === "CONFIRMED"
            ? '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#19734B]/15 text-[#145C3B]">Confirmed Venue</span>'
            : '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#7A5C1F]/15 text-[#5E4514]">Proposed / Subject to Confirmation</span>';
        var a11yTags =
          Array.isArray(it.accessibilityServices) && it.accessibilityServices.length
            ? '<ul class="flex flex-wrap gap-1.5 pt-1">' +
              it.accessibilityServices
                .map(function (srv) {
                  return (
                    '<li class="px-2 py-0.5 rounded bg-[#E8DFF0] text-[#432F56] text-[11px] font-semibold">' +
                    escapeHtml(srv) +
                    "</li>"
                  );
                })
                .join("") +
              "</ul>"
            : "";

        return (
          '<article class="rounded-xl border border-[#59436D]/20 bg-[#F6F1F8]/40 p-4 space-y-1.5">' +
          '<div class="flex flex-wrap items-center justify-between gap-2">' +
          '<span class="text-xs font-bold text-[#432F56]">Day ' + escapeHtml(String(it.dayNumber)) + " • " + escapeHtml(it.timeSlot) + "</span>" +
          '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-white border border-[#59436D]/20 text-[#432F56]">' + escapeHtml(it.category) + "</span>" +
          "</div>" +
          '<h3 class="font-display text-base font-semibold text-[#17151C]">' + escapeHtml(it.title) + "</h3>" +
          (it.description ? '<p class="text-xs text-[#4B4257]">' + escapeHtml(it.description) + "</p>" : "") +
          '<div class="flex flex-wrap items-center gap-2 text-xs text-[#211B29] pt-1">' +
          '<span><strong>Venue:</strong> ' + escapeHtml(it.venueName) + "</span>" +
          venueStatusLabel +
          "</div>" +
          a11yTags +
          "</article>"
        );
      })
      .join("");
  }

  function renderProfileSettings(data) {
    var p = data.profile || {};
    var setVal = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.value = val == null ? "" : String(val);
    };

    setVal("prof-name", p.fullName || "");
    setVal("prof-phone", p.phone || "");
    setVal("prof-language", p.preferredLanguage || "en");
    setVal("prof-city", p.cityDistrict || "");
    setVal("prof-state", p.state || "");
    setVal("prof-country", p.country || "India");
    setVal("prof-ec-name", p.emergencyContactName || "");
    setVal("prof-ec-rel", p.emergencyContactRelationship || "");
    setVal("prof-ec-phone", p.emergencyContactPhone || "");

    var curEmailEl = document.getElementById("dash-current-email");
    if (curEmailEl && p.email) curEmailEl.textContent = p.email;

    var verBadge = document.getElementById("dash-email-verified-badge");
    if (verBadge) {
      verBadge.textContent = p.emailVerified ? "Verified" : "Verification Pending";
      verBadge.className =
        "px-2.5 py-1 rounded-lg text-xs font-bold border " +
        (p.emailVerified
          ? "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30"
          : "bg-[#7A5C1F]/15 text-[#5E4514] border-[#7A5C1F]/35");
    }

    var pendingBox = document.getElementById("dash-pending-email-box");
    if (pendingBox) {
      if (p.pendingEmail) {
        pendingBox.classList.remove("hidden");
        pendingBox.textContent =
          "Pending email change to " +
          p.pendingEmail +
          ". Please check that inbox and click the verification link to complete the change.";
      } else {
        pendingBox.classList.add("hidden");
        pendingBox.textContent = "";
      }
    }

    updateMfaUi(Boolean(p.mfaEnabled));
  }

  function updateMfaUi(enabled) {
    var badge = document.getElementById("dash-mfa-status-badge");
    var startWrap = document.getElementById("dash-mfa-start-wrap");
    var setupBox = document.getElementById("dash-mfa-setup-box");
    var disableBox = document.getElementById("dash-mfa-disable-box");

    if (badge) {
      badge.textContent = enabled ? "Enabled" : "Not Enabled";
      badge.className =
        "px-2.5 py-1 rounded-lg text-xs font-bold border " +
        (enabled
          ? "bg-[#19734B]/15 text-[#145C3B] border-[#19734B]/30"
          : "bg-[#F6F1F8] text-[#4B4257] border-[#59436D]/20");
    }
    if (enabled) {
      if (startWrap) startWrap.classList.add("hidden");
      if (setupBox) setupBox.classList.add("hidden");
      if (disableBox) disableBox.classList.remove("hidden");
    } else {
      if (startWrap) startWrap.classList.remove("hidden");
      if (setupBox) setupBox.classList.add("hidden");
      if (disableBox) disableBox.classList.add("hidden");
    }
  }

  // =========================================================================
  // 5. DATA LOADING
  // =========================================================================

  function loadDashboard() {
    var loading = document.getElementById("dash-loading-state");
    var globalMsg = document.getElementById("dash-global-status");
    return apiFetch(endpoints.dashboard)
      .then(function (res) {
        if (!res.ok) {
          showBanner(globalMsg, "error", (res.data && res.data.error) || "Unable to load your dashboard right now.");
          return;
        }
        state.data = res.data;
        if (loading) loading.classList.add("hidden");
        renderHeaderAndMetrics(res.data);
        renderRegistrations(res.data);
        renderAccommodations(res.data);
        renderNotificationsList(res.data.notifications || [], res.data.unreadCount || 0, null);
        renderProgramme(res.data);
        renderProfileSettings(res.data);
        activateTab(state.activeTab || "overview", false);
      })
      .catch(function () {
        if (loading) loading.classList.add("hidden");
        showBanner(globalMsg, "error", "Network error loading your dashboard. Please refresh the page.");
      });
  }

  function loadNotificationsPage(page, filter) {
    state.notifPage = page || 1;
    if (filter) state.notifFilter = filter;
    var url =
      endpoints.notifications +
      "?page=" +
      encodeURIComponent(String(state.notifPage)) +
      "&limit=20" +
      (state.notifFilter === "unread" ? "&unreadOnly=1" : "");

    return apiFetch(url).then(function (res) {
      if (!res.ok) {
        showBanner(document.getElementById("dash-notif-message"), "error", (res.data && res.data.error) || "Could not load notifications.");
        return;
      }
      renderNotificationsList(res.data.notifications || [], res.data.unreadCount || 0, res.data.pagination || null);
    });
  }

  // =========================================================================
  // 6. EVENT HANDLERS
  // =========================================================================

  // Logout (CSRF-protected POST)
  var logoutBtn = document.getElementById("dash-logout-btn");
  var logoutLabel = document.getElementById("dash-logout-label");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logoutBtn.disabled = true;
      if (logoutLabel) logoutLabel.textContent = "Signing out...";
      apiFetch(endpoints.logout, { method: "POST" })
        .catch(function () {})
        .finally(function () {
          window.location.href = "/login";
        });
    });
  }

  // Claim Legacy Registration
  var claimBtn = document.getElementById("dash-claim-legacy-btn");
  if (claimBtn) {
    claimBtn.addEventListener("click", function () {
      var globalMsg = document.getElementById("dash-global-status");
      hideBanner(globalMsg);
      claimBtn.disabled = true;
      claimBtn.textContent = "Claiming...";
      apiFetch(endpoints.claimLegacy, { method: "POST", body: {} })
        .then(function (res) {
          if (!res.ok) {
            showBanner(globalMsg, "error", (res.data && res.data.error) || "Unable to claim legacy registrations.");
            return;
          }
          showBanner(globalMsg, "success", res.data.message || "Legacy registration(s) linked to your account.");
          return loadDashboard();
        })
        .catch(function () {
          showBanner(globalMsg, "error", "Network error while claiming legacy registration.");
        })
        .finally(function () {
          claimBtn.disabled = false;
          claimBtn.textContent = "Claim Legacy Registration";
        });
    });
  }

  // Delegated clicks for Edit / Withdraw Registration, Notification Read, Pagination, Programme Day Filter
  document.addEventListener("click", function (e) {
    var editBtn = e.target.closest('[data-action="edit-reg"]');
    if (editBtn) {
      var regId = editBtn.getAttribute("data-reg-id");
      openEditRegistrationModal(regId, editBtn);
      return;
    }

    var withdrawBtn = e.target.closest('[data-action="withdraw-reg"]');
    if (withdrawBtn) {
      var wRegId = withdrawBtn.getAttribute("data-reg-id");
      openWithdrawModal(wRegId, withdrawBtn);
      return;
    }

    var markReadBtn = e.target.closest('[data-action="mark-notif-read"]');
    if (markReadBtn) {
      var nId = markReadBtn.getAttribute("data-notif-id");
      markNotificationRead(nId, markReadBtn);
      return;
    }

    var notifFilterBtn = e.target.closest("[data-notif-filter]");
    if (notifFilterBtn) {
      var f = notifFilterBtn.getAttribute("data-notif-filter");
      document.querySelectorAll("[data-notif-filter]").forEach(function (b) {
        var active = b.getAttribute("data-notif-filter") === f;
        b.setAttribute("aria-pressed", active ? "true" : "false");
        b.className = active
          ? "min-h-[36px] px-3 py-1 rounded-lg text-xs font-bold bg-white text-[#17151C] shadow-xs"
          : "min-h-[36px] px-3 py-1 rounded-lg text-xs font-bold text-[#4B4257] hover:text-[#17151C]";
      });
      loadNotificationsPage(1, f);
      return;
    }

    var notifPageBtn = e.target.closest("[data-notif-page]");
    if (notifPageBtn && !notifPageBtn.disabled) {
      var p = parseInt(notifPageBtn.getAttribute("data-notif-page"), 10) || 1;
      loadNotificationsPage(p, state.notifFilter);
      return;
    }

    var progDayBtn = e.target.closest("[data-prog-day]");
    if (progDayBtn) {
      state.progDayFilter = progDayBtn.getAttribute("data-prog-day") || "all";
      document.querySelectorAll("[data-prog-day]").forEach(function (b) {
        var active = b.getAttribute("data-prog-day") === state.progDayFilter;
        b.setAttribute("aria-pressed", active ? "true" : "false");
        b.className = active
          ? "min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#59436D] text-white"
          : "min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#F6F1F8] text-[#432F56] border border-[#59436D]/20";
      });
      if (state.data) renderProgramme(state.data);
    }
  });

  function findRegistrationById(id) {
    if (!state.data || !Array.isArray(state.data.registrations)) return null;
    for (var i = 0; i < state.data.registrations.length; i++) {
      if (state.data.registrations[i].id === id) return state.data.registrations[i];
    }
    return null;
  }

  function openEditRegistrationModal(regId, triggerEl) {
    var reg = findRegistrationById(regId);
    if (!reg) return;

    hideBanner(document.getElementById("dash-edit-message"));
    document.getElementById("edit-reg-id").value = reg.id;
    document.getElementById("dash-edit-modal-code").textContent = reg.registrationCode + " • " + (reg.categoryName || reg.categoryCode);
    document.getElementById("dash-edit-modal-title").textContent =
      reg.status === "ADDITIONAL_INFO_REQUIRED" ? "Update & Resubmit Application" : "Edit Registration Details";

    var callout = document.getElementById("dash-edit-review-callout");
    var resubmitWrap = document.getElementById("edit-resubmit-note-wrap");
    var submitBtn = document.getElementById("dash-edit-submit-btn");
    if (reg.status === "ADDITIONAL_INFO_REQUIRED") {
      callout.classList.remove("hidden");
      callout.textContent = "Coordinator request: " + (reg.publicReviewNote || "Please update the requested information and resubmit.");
      resubmitWrap.classList.remove("hidden");
      submitBtn.textContent = "Save & Resubmit";
    } else {
      callout.classList.add("hidden");
      callout.textContent = "";
      resubmitWrap.classList.add("hidden");
      submitBtn.textContent = "Save Updates";
    }

    var days = Array.isArray(reg.attendingDays) ? reg.attendingDays : [];
    document.querySelectorAll('input[name="editAttendingDays"]').forEach(function (cb) {
      cb.checked = days.indexOf(cb.value) !== -1;
    });

    document.getElementById("edit-phone").value = reg.phone || "";
    document.getElementById("edit-org").value = reg.organizationName || "";
    document.getElementById("edit-city").value = reg.cityDistrict || "";
    document.getElementById("edit-state").value = reg.state || "";
    document.getElementById("edit-ec-name").value = reg.emergencyContactName || "";
    document.getElementById("edit-ec-phone").value = reg.emergencyContactPhone || "";
    document.getElementById("edit-resubmit-note").value = "";

    openModal("dash-edit-modal", triggerEl);
  }

  var editForm = document.getElementById("dash-edit-form");
  if (editForm) {
    editForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msgEl = document.getElementById("dash-edit-message");
      var submitBtn = document.getElementById("dash-edit-submit-btn");
      hideBanner(msgEl);

      var regId = document.getElementById("edit-reg-id").value;
      var selectedDays = [];
      document.querySelectorAll('input[name="editAttendingDays"]:checked').forEach(function (cb) {
        selectedDays.push(cb.value);
      });

      var payload = {
        registrationId: regId,
        attendingDays: selectedDays,
        phone: document.getElementById("edit-phone").value.trim(),
        organizationName: document.getElementById("edit-org").value.trim(),
        cityDistrict: document.getElementById("edit-city").value.trim(),
        state: document.getElementById("edit-state").value.trim(),
        emergencyContactName: document.getElementById("edit-ec-name").value.trim(),
        emergencyContactPhone: document.getElementById("edit-ec-phone").value.trim()
      };
      var noteVal = document.getElementById("edit-resubmit-note").value.trim();
      if (noteVal) {
        payload.resubmissionNote = noteVal;
      }

      submitBtn.disabled = true;
      apiFetch(endpoints.registrations, { method: "PATCH", body: payload })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to update registration.");
            return;
          }
          closeModal("dash-edit-modal");
          showBanner(
            document.getElementById("dash-global-status"),
            "success",
            res.data.message || "Your registration has been updated."
          );
          return loadDashboard();
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error while saving changes.");
        })
        .finally(function () {
          submitBtn.disabled = false;
        });
    });
  }

  function openWithdrawModal(regId, triggerEl) {
    var reg = findRegistrationById(regId);
    if (!reg) return;

    hideBanner(document.getElementById("dash-withdraw-message"));
    var isAttendee = reg.categoryCode === "ATTENDEE";
    document.getElementById("withdraw-reg-id").value = reg.id;
    document.getElementById("withdraw-action").value = isAttendee ? "cancel" : "withdraw";
    document.getElementById("dash-withdraw-modal-code").textContent = reg.registrationCode + " • " + (reg.categoryName || reg.categoryCode);
    document.getElementById("dash-withdraw-modal-title").textContent = isAttendee ? "Cancel Attendee Registration" : "Withdraw Application";
    document.getElementById("dash-withdraw-submit-btn").textContent = isAttendee ? "Confirm Cancellation" : "Confirm Withdrawal";
    document.getElementById("withdraw-reason").value = "";

    openModal("dash-withdraw-modal", triggerEl);
  }

  var withdrawForm = document.getElementById("dash-withdraw-form");
  if (withdrawForm) {
    withdrawForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msgEl = document.getElementById("dash-withdraw-message");
      var submitBtn = document.getElementById("dash-withdraw-submit-btn");
      hideBanner(msgEl);

      var payload = {
        registrationId: document.getElementById("withdraw-reg-id").value,
        action: document.getElementById("withdraw-action").value,
        reason: document.getElementById("withdraw-reason").value.trim()
      };

      submitBtn.disabled = true;
      apiFetch(endpoints.registrations, { method: "POST", body: payload })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to complete cancellation.");
            return;
          }
          closeModal("dash-withdraw-modal");
          showBanner(
            document.getElementById("dash-global-status"),
            "success",
            res.data.message || "Your registration status has been updated."
          );
          return loadDashboard();
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error while processing request.");
        })
        .finally(function () {
          submitBtn.disabled = false;
        });
    });
  }

  // Submit Accommodation Request
  var accForm = document.getElementById("dash-acc-form");
  if (accForm) {
    accForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var accMsg = document.getElementById("dash-acc-message");
      var submitBtn = document.getElementById("dash-acc-submit-btn");
      hideBanner(accMsg);

      var regId = document.getElementById("acc-reg-select").value;
      var freeText = document.getElementById("acc-notes").value.trim();
      var selectedTypes = [];
      accForm.querySelectorAll('input[name="selectedTypes[]"]:checked').forEach(function (cb) {
        selectedTypes.push(cb.value);
      });

      if (!regId) {
        showBanner(accMsg, "error", "Please select a registration first.");
        return;
      }
      if (selectedTypes.length === 0 && !freeText) {
        showBanner(accMsg, "error", "Please select at least one accommodation or describe your requirement.");
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Submitting...";

      apiFetch(endpoints.accommodations, {
        method: "POST",
        body: {
          registrationId: regId,
          selectedTypes: selectedTypes,
          freeTextDetails: freeText
        }
      })
        .then(function (res) {
          if (!res.ok) {
            showBanner(accMsg, "error", (res.data && res.data.error) || "Unable to submit accommodation request.");
            return;
          }
          accForm.querySelectorAll('input[name="selectedTypes[]"]:checked').forEach(function (cb) {
            cb.checked = false;
          });
          document.getElementById("acc-notes").value = "";
          showBanner(accMsg, "success", res.data.message || "Your accommodation request has been submitted.");
          return loadDashboard();
        })
        .catch(function () {
          showBanner(accMsg, "error", "Network error while submitting request.");
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = "Submit Accommodation Request";
        });
    });
  }

  // Mark Single Notification Read
  function markNotificationRead(notificationId, btnEl) {
    var msgEl = document.getElementById("dash-notif-message");
    hideBanner(msgEl);
    if (btnEl) btnEl.disabled = true;

    apiFetch(endpoints.notifications, {
      method: "PATCH",
      body: { notificationId: notificationId }
    })
      .then(function (res) {
        if (!res.ok) {
          showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to mark notification as read.");
          if (btnEl) btnEl.disabled = false;
          return;
        }
        return loadNotificationsPage(state.notifPage, state.notifFilter);
      })
      .catch(function () {
        showBanner(msgEl, "error", "Network error updating notification.");
        if (btnEl) btnEl.disabled = false;
      });
  }

  // Mark All Notifications Read
  var markAllNotifBtn = document.getElementById("dash-notif-mark-all-btn");
  if (markAllNotifBtn) {
    markAllNotifBtn.addEventListener("click", function () {
      var msgEl = document.getElementById("dash-notif-message");
      hideBanner(msgEl);
      markAllNotifBtn.disabled = true;

      apiFetch(endpoints.notifications, {
        method: "PATCH",
        body: { markAllRead: true }
      })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to update notifications.");
            return;
          }
          showBanner(msgEl, "success", res.data.message || "All notifications marked as read.");
          return loadNotificationsPage(1, state.notifFilter);
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error updating notifications.");
        });
    });
  }

  // Save Profile & Emergency Contact
  var profileForm = document.getElementById("dash-profile-form");
  if (profileForm) {
    profileForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msgEl = document.getElementById("dash-profile-message");
      var submitBtn = document.getElementById("dash-profile-submit-btn");
      hideBanner(msgEl);

      var fullName = document.getElementById("prof-name").value.trim();
      if (fullName.length < 2) {
        showBanner(msgEl, "error", "Full name must be at least 2 characters.");
        return;
      }

      var payload = {
        fullName: fullName,
        phone: document.getElementById("prof-phone").value.trim(),
        preferredLanguage: document.getElementById("prof-language").value,
        cityDistrict: document.getElementById("prof-city").value.trim(),
        state: document.getElementById("prof-state").value.trim(),
        country: document.getElementById("prof-country").value.trim() || "India",
        emergencyContactName: document.getElementById("prof-ec-name").value.trim(),
        emergencyContactRelationship: document.getElementById("prof-ec-rel").value.trim(),
        emergencyContactPhone: document.getElementById("prof-ec-phone").value.trim()
      };

      submitBtn.disabled = true;
      submitBtn.textContent = "Saving...";

      apiFetch(endpoints.profile, { method: "PATCH", body: payload })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to update profile.");
            return;
          }
          showBanner(msgEl, "success", res.data.message || "Your profile details have been updated.");
          return loadDashboard();
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error while saving profile.");
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = "Save Profile Details";
        });
    });
  }

  // Request Email Change
  var emailForm = document.getElementById("dash-email-form");
  if (emailForm) {
    emailForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msgEl = document.getElementById("dash-email-message");
      var submitBtn = document.getElementById("dash-email-submit-btn");
      var newEmailInput = document.getElementById("prof-new-email");
      var currPwInput = document.getElementById("prof-email-curr-pw");
      hideBanner(msgEl);

      var newEmail = newEmailInput ? newEmailInput.value.trim() : "";
      var currentPassword = currPwInput ? currPwInput.value : "";
      if (!newEmail || newEmail.indexOf("@") === -1) {
        showBanner(msgEl, "error", "Please enter a valid new email address.");
        return;
      }
      if (!currentPassword) {
        showBanner(msgEl, "error", "Please enter your current password to confirm this email change.");
        if (currPwInput) currPwInput.focus();
        return;
      }

      submitBtn.disabled = true;
      apiFetch(endpoints.profile, {
        method: "PATCH",
        body: { email: newEmail, currentPassword: currentPassword }
      })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to request email change.");
            return;
          }
          if (newEmailInput) newEmailInput.value = "";
          if (currPwInput) currPwInput.value = "";
          showBanner(
            msgEl,
            "success",
            res.data.message || "Verification link sent to your new email address."
          );
          return loadDashboard();
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error requesting email change.");
        })
        .finally(function () {
          submitBtn.disabled = false;
        });
    });
  }

  // Change Password
  var passwordForm = document.getElementById("dash-password-form");
  if (passwordForm) {
    passwordForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var msgEl = document.getElementById("dash-password-message");
      var submitBtn = document.getElementById("dash-password-submit-btn");
      hideBanner(msgEl);

      var currPw = document.getElementById("prof-curr-pw").value;
      var newPw = document.getElementById("prof-new-pw").value;
      var confirmPw = document.getElementById("prof-confirm-pw").value;

      if (!newPw || newPw.length < 8) {
        showBanner(msgEl, "error", "New password must be at least 8 characters.");
        return;
      }
      if (newPw !== confirmPw) {
        showBanner(msgEl, "error", "New password and confirmation do not match.");
        return;
      }

      submitBtn.disabled = true;
      apiFetch(endpoints.profile, {
        method: "PATCH",
        body: {
          currentPassword: currPw,
          newPassword: newPw
        }
      })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Unable to update password.");
            return;
          }
          document.getElementById("prof-curr-pw").value = "";
          document.getElementById("prof-new-pw").value = "";
          document.getElementById("prof-confirm-pw").value = "";
          showBanner(msgEl, "success", res.data.message || "Your password has been updated.");
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error while updating password.");
        })
        .finally(function () {
          submitBtn.disabled = false;
        });
    });
  }

  // Optional MFA Setup / Enable / Disable (text setup key — zero QR pass imagery)
  var mfaStartBtn = document.getElementById("dash-mfa-start-btn");
  var mfaEnableBtn = document.getElementById("dash-mfa-enable-btn");
  var mfaDisableBtn = document.getElementById("dash-mfa-disable-btn");
  var mfaCopyCodesBtn = document.getElementById("dash-mfa-copy-codes-btn");
  var mfaDismissCodesBtn = document.getElementById("dash-mfa-dismiss-codes-btn");
  var lastDisplayedRecoveryCodes = [];

  function showMfaRecoveryCodes(codes) {
    var box = document.getElementById("dash-mfa-recovery-box");
    var list = document.getElementById("dash-mfa-recovery-codes");
    if (!box || !list) return;

    if (!Array.isArray(codes) || codes.length === 0) {
      lastDisplayedRecoveryCodes = [];
      list.innerHTML = "";
      box.classList.add("hidden");
      return;
    }

    lastDisplayedRecoveryCodes = codes.map(function (c) {
      return String(c);
    });
    list.innerHTML = lastDisplayedRecoveryCodes
      .map(function (c) {
        return (
          '<li class="rounded-lg bg-white border border-[#59436D]/25 px-2.5 py-2 text-center font-semibold tracking-wider select-all">' +
          escapeHtml(c) +
          "</li>"
        );
      })
      .join("");
    box.classList.remove("hidden");
    try {
      box.focus();
    } catch (e) {}
  }

  if (mfaCopyCodesBtn) {
    mfaCopyCodesBtn.addEventListener("click", function () {
      if (!lastDisplayedRecoveryCodes.length) return;
      var text = lastDisplayedRecoveryCodes.join("\n");
      var msgEl = document.getElementById("dash-mfa-message");
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(text)
          .then(function () {
            showBanner(msgEl, "success", "Recovery codes copied to clipboard. Store them in a secure location.");
          })
          .catch(function () {
            showBanner(msgEl, "error", "Could not copy automatically. Please select and copy the codes manually.");
          });
      } else {
        showBanner(msgEl, "error", "Clipboard access unavailable. Please select and copy the codes manually.");
      }
    });
  }

  if (mfaDismissCodesBtn) {
    mfaDismissCodesBtn.addEventListener("click", function () {
      showMfaRecoveryCodes([]);
    });
  }

  if (mfaStartBtn) {
    mfaStartBtn.addEventListener("click", function () {
      var msgEl = document.getElementById("dash-mfa-message");
      hideBanner(msgEl);
      mfaStartBtn.disabled = true;
      apiFetch(endpoints.mfa, { method: "POST", body: { action: "setup" } })
        .then(function (res) {
          if (!res.ok) {
            showBanner(msgEl, "error", (res.data && res.data.error) || "Could not initialize MFA setup.");
            return;
          }
          var secretEl = document.getElementById("dash-mfa-secret-code");
          if (secretEl) secretEl.textContent = res.data.secret || "";
          document.getElementById("dash-mfa-start-wrap").classList.add("hidden");
          document.getElementById("dash-mfa-setup-box").classList.remove("hidden");
        })
        .catch(function () {
          showBanner(msgEl, "error", "Network error initializing MFA.");
        })
        .finally(function () {
          mfaStartBtn.disabled = false;
        });
    });
  }

  function submitMfaToggle(action) {
    var msgEl = document.getElementById("dash-mfa-message");
    hideBanner(msgEl);
    var inputEl = document.getElementById(action === "enable" ? "mfa-verify-code" : "mfa-disable-code");
    var pwEl = action === "disable" ? document.getElementById("mfa-disable-password") : null;
    var code = inputEl ? inputEl.value.trim() : "";
    var payload = { action: action, code: code };

    if (action === "disable") {
      var currentPassword = pwEl ? pwEl.value : "";
      if (!currentPassword) {
        showBanner(msgEl, "error", "Please enter your current password to disable two-factor authentication.");
        if (pwEl) pwEl.focus();
        return;
      }
      payload.currentPassword = currentPassword;
    }

    apiFetch(endpoints.mfa, { method: "POST", body: payload })
      .then(function (res) {
        if (!res.ok) {
          showBanner(msgEl, "error", (res.data && res.data.error) || "Verification failed.");
          return;
        }
        if (inputEl) inputEl.value = "";
        if (pwEl) pwEl.value = "";
        var enabled = Boolean(res.data.mfaEnabled);
        updateMfaUi(enabled);
        if (enabled && Array.isArray(res.data.recoveryCodes) && res.data.recoveryCodes.length > 0) {
          showMfaRecoveryCodes(res.data.recoveryCodes);
        } else if (!enabled) {
          showMfaRecoveryCodes([]);
        }
        showBanner(
          msgEl,
          "success",
          enabled
            ? (res.data.message || "Two-factor authentication is now enabled. Save your single-use recovery codes below.")
            : "Two-factor authentication has been disabled."
        );
      })
      .catch(function () {
        showBanner(msgEl, "error", "Network error verifying code.");
      });
  }

  if (mfaEnableBtn) {
    mfaEnableBtn.addEventListener("click", function () {
      submitMfaToggle("enable");
    });
  }
  if (mfaDisableBtn) {
    mfaDisableBtn.addEventListener("click", function () {
      submitMfaToggle("disable");
    });
  }

  // Initial load
  loadDashboard();
})();
