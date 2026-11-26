(function () {
  "use strict";

  var bootstrap = window.__PFG_RGST_BOOTSTRAP__ || {};
  var csrfToken = String(bootstrap.csrfToken || "");
  var attendanceScope = String(bootstrap.attendanceScope || "FESTIVAL_DAY");
  var days = Array.isArray(bootstrap.days) && bootstrap.days.length > 0
    ? bootstrap.days
    : [
        { date: "2027-01-21", dayNumber: 1, label: "Day 1", shortDate: "21 Jan", display: "Day 1 (21 Jan)" },
        { date: "2027-01-22", dayNumber: 2, label: "Day 2", shortDate: "22 Jan", display: "Day 2 (22 Jan)" },
        { date: "2027-01-23", dayNumber: 3, label: "Day 3", shortDate: "23 Jan", display: "Day 3 (23 Jan)" }
      ];
  var operator = bootstrap.operator || {
    id: "",
    fullName: "Desk Staff",
    role: "RGST",
    canOverrideAnytime: false,
    undoWindowMinutes: 15
  };

  // DOM References
  var istClockEl = document.getElementById("rgst-ist-clock");
  var stationSelect = document.getElementById("rgst-station-select");
  var logoutBtn = document.getElementById("rgst-logout-btn");
  var filterForm = document.getElementById("rgst-filter-form");
  var searchInput = document.getElementById("rgst-search-input");
  var categoryFilter = document.getElementById("rgst-category-filter");
  var eligibilityFilter = document.getElementById("rgst-eligibility-filter");
  var dayFilter = document.getElementById("rgst-day-filter");
  var perPageSelect = document.getElementById("rgst-perpage-select");
  var clearBtn = document.getElementById("rgst-clear-btn");
  var refreshBtn = document.getElementById("rgst-refresh-btn");
  var tableMetaEl = document.getElementById("rgst-table-meta");
  var tableBody = document.getElementById("rgst-table-body");
  var paginationSummary = document.getElementById("rgst-pagination-summary");
  var prevPageBtn = document.getElementById("rgst-prev-page");
  var nextPageBtn = document.getElementById("rgst-next-page");
  var statusBanner = document.getElementById("rgst-status-banner");
  var errorBanner = document.getElementById("rgst-error-banner");
  var errorText = document.getElementById("rgst-error-text");
  var errorRetryBtn = document.getElementById("rgst-error-retry-btn");
  var errorDismissBtn = document.getElementById("rgst-error-dismiss-btn");

  // KPI Elements
  var kpiEligible = document.getElementById("kpi-eligible");
  var kpiTotalReg = document.getElementById("kpi-total-reg");
  var kpiUniquePresent = document.getElementById("kpi-unique-present");
  var kpiTotalEvents = document.getElementById("kpi-total-events");

  // Correction Modal Elements
  var corrModal = document.getElementById("rgst-correction-modal");
  var corrCloseBtn = document.getElementById("rgst-corr-close-btn");
  var corrCancelBtn = document.getElementById("rgst-corr-cancel-btn");
  var corrDetails = document.getElementById("rgst-corr-details");
  var corrBlockedNotice = document.getElementById("rgst-corr-blocked-notice");
  var corrForm = document.getElementById("rgst-corr-form");
  var corrReasonGroup = document.getElementById("rgst-corr-reason-group");
  var corrReasonInput = document.getElementById("rgst-corr-reason");
  var corrErrorEl = document.getElementById("rgst-corr-error");
  var corrSubmitBtn = document.getElementById("rgst-corr-submit-btn");

  // State
  var state = {
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
    q: "",
    category: "ALL",
    eligibility: "ALL",
    day: "",
    rows: [],
    rowsById: {},
    latestRequestId: 0,
    debounceTimer: null,
    lastRetryFn: null,
    activeCorrection: null,
    lastFocusedEl: null
  };

  function escapeHtml(str) {
    return String(str === null || str === undefined ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function getSelectedStation() {
    if (!stationSelect || stationSelect.selectedIndex < 0) {
      return { id: null, name: "Main Registration Desk" };
    }
    var opt = stationSelect.options[stationSelect.selectedIndex];
    var id = stationSelect.value || null;
    var name = opt ? (opt.getAttribute("data-station-name") || opt.textContent || "").trim() : "Main Registration Desk";
    return { id: id, name: name };
  }

  /**
   * Fetch wrapper with CSRF header on POST and automatic .php fallback on 404
   * (supports both Apache .htaccess clean URLs and PHP built-in server).
   */
  async function apiFetch(path, options) {
    var opts = Object.assign({ credentials: "same-origin", cache: "no-store" }, options || {});
    var headers = Object.assign({ Accept: "application/json" }, opts.headers || {});
    if ((opts.method || "GET").toUpperCase() !== "GET" && csrfToken) {
      headers["X-CSRF-Token"] = csrfToken;
    }
    opts.headers = headers;

    var res = await fetch(path, opts);
    if (res.status === 404 && path.indexOf(".php") === -1) {
      var qIdx = path.indexOf("?");
      var basePath = qIdx === -1 ? path : path.slice(0, qIdx);
      var qs = qIdx === -1 ? "" : path.slice(qIdx);
      var altPath = basePath === "/api/attendance"
        ? "/api/attendance/index.php" + qs
        : basePath + ".php" + qs;
      res = await fetch(altPath, opts);
    }

    var data = null;
    try {
      data = await res.json();
    } catch (e) {
      data = null;
    }
    return { ok: res.ok, status: res.status, data: data };
  }

  function updateIstClock() {
    if (!istClockEl) return;
    try {
      var formatted = new Intl.DateTimeFormat("en-IN", {
        timeZone: "Asia/Kolkata",
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: true
      }).format(new Date());
      istClockEl.textContent = formatted + " IST";
    } catch (e) {}
  }

  function showStatus(msg) {
    if (!statusBanner) return;
    statusBanner.textContent = msg;
    statusBanner.classList.remove("hidden");
  }

  function hideStatus() {
    if (!statusBanner) return;
    statusBanner.classList.add("hidden");
    statusBanner.textContent = "";
  }

  function showError(msg, retryFn) {
    state.lastRetryFn = typeof retryFn === "function" ? retryFn : null;
    if (!errorBanner || !errorText) return;
    errorText.textContent = msg;
    errorBanner.classList.remove("hidden");
    errorBanner.classList.add("flex");
    if (errorRetryBtn) {
      if (state.lastRetryFn) {
        errorRetryBtn.classList.remove("hidden");
      } else {
        errorRetryBtn.classList.add("hidden");
      }
    }
  }

  function hideError() {
    state.lastRetryFn = null;
    if (!errorBanner) return;
    errorBanner.classList.add("hidden");
    errorBanner.classList.remove("flex");
    if (errorText) errorText.textContent = "";
    if (errorRetryBtn) errorRetryBtn.classList.add("hidden");
  }

  function updateMetrics(metrics) {
    if (!metrics) return;
    if (kpiEligible) kpiEligible.textContent = String(metrics.eligibleRegistrations || 0);
    if (kpiTotalReg) kpiTotalReg.textContent = "of " + String(metrics.totalRegistrations || 0) + " registered";
    if (kpiUniquePresent) kpiUniquePresent.textContent = String(metrics.uniquePeoplePresent || 0);
    if (kpiTotalEvents) kpiTotalEvents.textContent = String(metrics.totalAttendanceEvents || 0) + " day check-ins";

    if (metrics.byDay) {
      for (var i = 0; i < days.length; i++) {
        var dKey = days[i].date;
        var el = document.querySelector('[data-kpi-day="' + dKey + '"]');
        if (el) {
          el.textContent = String(metrics.byDay[dKey] || 0);
        }
      }
    }

    if (metrics.byCategory) {
      var cats = ["ATTENDEE", "VOLUNTEER", "SLI"];
      for (var c = 0; c < cats.length; c++) {
        var code = cats[c];
        var catEl = document.getElementById("kpi-cat-" + code);
        var info = metrics.byCategory[code] || { registered: 0, eligible: 0, uniquePresent: 0 };
        if (catEl) {
          catEl.textContent = String(info.uniquePresent || 0) + " / " + String(info.eligible || 0);
        }
      }
    }
  }

  function formatShortTime(markedAtIst) {
    if (!markedAtIst) return "";
    var parts = String(markedAtIst).split(",");
    if (parts.length > 1) {
      return parts[1].trim().replace(/ IST$/i, "");
    }
    return String(markedAtIst);
  }

  function getEligibilityBadgeHtml(row) {
    if (row.isEligible) {
      return '<span class="rgst-badge rgst-badge-eligible">' + escapeHtml(row.status) + "</span>";
    }
    if (row.status === "REJECTED" || row.status === "CANCELLED" || row.status === "WITHDRAWN") {
      return '<span class="rgst-badge rgst-badge-blocked">' + escapeHtml(row.status) + "</span>";
    }
    return '<span class="rgst-badge rgst-badge-pending">' + escapeHtml(row.status) + "</span>";
  }

  function buildDayCellHtml(row, dayInfo) {
    var att = (row.attendance && row.attendance[dayInfo.date]) || { status: "NOT_MARKED" };
    var isPresent = att.status === "PRESENT";
    var isRevoked = att.status === "REVOKED";
    var inputId = "att-" + row.registrationId + "-" + dayInfo.date;

    if (!row.isEligible && !isPresent) {
      return (
        '<td class="px-3 py-3 text-center">' +
          '<span class="rgst-check-wrap rgst-check-disabled" title="Registration must be CONFIRMED or APPROVED">' +
            '<input type="checkbox" class="rgst-day-checkbox" disabled aria-label="' +
              escapeHtml(dayInfo.label + " attendance unavailable for " + row.fullName + " (" + row.registrationCode + ") - not eligible") +
            '" />' +
            '<span class="rgst-check-meta">Not eligible</span>' +
          "</span>" +
        "</td>"
      );
    }

    var ariaLabel = isPresent
      ? "Mark " + dayInfo.label + " attendance for " + row.fullName + " (" + row.registrationCode + ") - currently marked present, uncheck to correct"
      : "Mark " + dayInfo.label + " attendance for " + row.fullName + " (" + row.registrationCode + ")";

    var metaText = "Not marked";
    if (isPresent) {
      var shortTime = formatShortTime(att.markedAtIst || att.markedAt);
      metaText = shortTime ? "Present · " + shortTime : "Present";
    } else if (isRevoked) {
      metaText = "Revoked";
    }

    var tooltip = isPresent
      ? "Marked PRESENT by " + (att.markedBy || "Desk Staff") + (att.markedAtIst ? " at " + att.markedAtIst : "")
      : (isRevoked ? "Previously revoked. Check to reinstate." : "Click to mark " + dayInfo.display + " attendance");

    return (
      '<td class="px-3 py-3 text-center">' +
        '<label for="' + escapeHtml(inputId) + '" class="rgst-check-wrap ' + (isPresent ? "rgst-check-present" : "") + '" title="' + escapeHtml(tooltip) + '">' +
          '<input' +
            ' id="' + escapeHtml(inputId) + '"' +
            ' type="checkbox"' +
            ' class="rgst-day-checkbox"' +
            ' data-reg-id="' + escapeHtml(row.registrationId) + '"' +
            ' data-date="' + escapeHtml(dayInfo.date) + '"' +
            ' data-day-label="' + escapeHtml(dayInfo.display) + '"' +
            ' aria-label="' + escapeHtml(ariaLabel) + '"' +
            (isPresent ? " checked" : "") +
          " />" +
          '<span class="rgst-check-meta">' + escapeHtml(metaText) + "</span>" +
        "</label>" +
      "</td>"
    );
  }

  function renderTable() {
    if (!tableBody) return;
    var colSpan = 4 + days.length;

    if (!state.rows || state.rows.length === 0) {
      tableBody.innerHTML =
        '<tr><td colspan="' + colSpan + '" class="px-4 py-8 text-center text-sm text-[#B6A5C9]">' +
          "No matching participants found. Try clearing filters or searching by Registration ID, full name, or phone digits." +
        "</td></tr>";
      return;
    }

    var html = [];
    for (var i = 0; i < state.rows.length; i++) {
      var row = state.rows[i];
      var subParts = [];
      if (row.cityDistrict) subParts.push(row.cityDistrict);
      if (row.phoneLast4) subParts.push("Phone ••••" + row.phoneLast4);
      var subLine = subParts.length > 0
        ? '<p class="mt-0.5 text-xs text-[#B6A5C9]">' + escapeHtml(subParts.join(" · ")) + "</p>"
        : "";

      var dayCells = "";
      for (var d = 0; d < days.length; d++) {
        dayCells += buildDayCellHtml(row, days[d]);
      }

      html.push(
        '<tr class="hover:bg-[#262030]" data-row-reg-id="' + escapeHtml(row.registrationId) + '">' +
          '<td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-[#D5B77B]">' +
            escapeHtml(row.registrationCode) +
          "</td>" +
          '<td class="px-4 py-3">' +
            '<p class="font-semibold text-white">' + escapeHtml(row.fullName) + "</p>" +
            subLine +
          "</td>" +
          '<td class="whitespace-nowrap px-4 py-3">' +
            '<span class="rgst-badge rgst-badge-cat">' + escapeHtml(row.categoryCode) + "</span>" +
          "</td>" +
          '<td class="whitespace-nowrap px-4 py-3">' +
            getEligibilityBadgeHtml(row) +
          "</td>" +
          dayCells +
        "</tr>"
      );
    }

    tableBody.innerHTML = html.join("");
  }

  function updatePaginationUi() {
    if (paginationSummary) {
      paginationSummary.textContent =
        "Page " + state.page + " of " + state.totalPages + " (" + state.total + " matching)";
    }
    if (tableMetaEl) {
      var startIdx = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
      var endIdx = Math.min(state.total, state.page * state.perPage);
      tableMetaEl.textContent =
        "Showing " + startIdx + "–" + endIdx + " of " + state.total + " registered participants";
    }
    if (prevPageBtn) prevPageBtn.disabled = state.page <= 1;
    if (nextPageBtn) nextPageBtn.disabled = state.page >= state.totalPages;
  }

  async function loadRoster(keepMessages) {
    if (!keepMessages) {
      hideError();
    }
    var reqId = ++state.latestRequestId;

    var params = new URLSearchParams();
    params.set("page", String(state.page));
    params.set("perPage", String(state.perPage));
    params.set("scope", attendanceScope);
    if (state.q) params.set("q", state.q);
    if (state.category && state.category !== "ALL") params.set("category", state.category);
    if (state.eligibility && state.eligibility !== "ALL") params.set("eligibility", state.eligibility);
    if (state.day) params.set("day", state.day);

    if (refreshBtn) refreshBtn.disabled = true;

    try {
      var res = await apiFetch("/api/attendance?" + params.toString(), { method: "GET" });
      if (reqId !== state.latestRequestId) {
        return; // Newer search superseded this response
      }

      if (res.status === 401) {
        window.location.href = "/login?next=/rgst";
        return;
      }

      if (!res.ok || !res.data) {
        var errMsg = (res.data && res.data.error) || "Unable to load participant attendance list.";
        showError(errMsg, function () { loadRoster(false); });
        return;
      }

      var data = res.data;
      if (Array.isArray(data.days) && data.days.length > 0) {
        days = data.days;
      }
      if (data.actor) {
        operator = Object.assign(operator, data.actor);
      }

      state.rows = Array.isArray(data.rows) ? data.rows : [];
      state.rowsById = {};
      for (var i = 0; i < state.rows.length; i++) {
        state.rowsById[state.rows[i].registrationId] = state.rows[i];
      }

      if (data.pagination) {
        state.page = data.pagination.page || 1;
        state.perPage = data.pagination.perPage || state.perPage;
        state.total = data.pagination.total || 0;
        state.totalPages = data.pagination.totalPages || 1;
      }

      updateMetrics(data.metrics);
      renderTable();
      updatePaginationUi();
    } catch (e) {
      if (reqId !== state.latestRequestId) return;
      showError("Network error while loading participants. Check your connection and retry.", function () {
        loadRoster(false);
      });
    } finally {
      if (refreshBtn) refreshBtn.disabled = false;
    }
  }

  function updateSingleRowCell(regId, dateKey, recordData) {
    var row = state.rowsById[regId];
    if (!row) return;
    if (!row.attendance) row.attendance = {};
    row.attendance[dateKey] = {
      recordId: recordData.recordId || recordData.id || null,
      status: recordData.status || "NOT_MARKED",
      markedAt: recordData.markedAt || null,
      markedAtIst: recordData.markedAtIst || null,
      markedBy: recordData.markedBy || operator.fullName,
      markedByUserId: recordData.markedByUserId || operator.id,
      venueLabel: recordData.venueLabel || null,
      correctedAt: recordData.correctedAt || null,
      correctionReason: recordData.correctionReason || null,
      canDeskUndo: Boolean(recordData.canDeskUndo),
      undoSecondsLeft: recordData.undoSecondsLeft || 0
    };
    renderTable();
  }

  async function markAttendance(regId, dateKey, checkboxEl) {
    var row = state.rowsById[regId];
    if (!row) return;

    hideError();
    hideStatus();

    var wrap = checkboxEl ? checkboxEl.closest(".rgst-check-wrap") : null;
    var metaEl = wrap ? wrap.querySelector(".rgst-check-meta") : null;
    var prevMeta = metaEl ? metaEl.textContent : "";

    if (checkboxEl) {
      checkboxEl.disabled = true;
      checkboxEl.setAttribute("aria-busy", "true");
    }
    if (wrap) wrap.classList.add("rgst-check-busy");
    if (metaEl) metaEl.textContent = "Saving...";

    var st = getSelectedStation();

    try {
      var res = await apiFetch("/api/attendance/mark", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          registrationId: regId,
          attendanceDateIst: dateKey,
          attendanceScope: attendanceScope,
          stationId: st.id,
          venueLabel: st.name
        })
      });

      if (res.status === 401) {
        window.location.href = "/login?next=/rgst";
        return;
      }

      if (!res.ok || !res.data || !res.data.success) {
        // Revert checkbox cleanly — NEVER show false green checkmark!
        if (checkboxEl) {
          checkboxEl.checked = false;
          checkboxEl.disabled = false;
          checkboxEl.removeAttribute("aria-busy");
        }
        if (wrap) wrap.classList.remove("rgst-check-busy");
        if (metaEl) metaEl.textContent = prevMeta;

        var errMsg = (res.data && res.data.error) || "Failed to save attendance. Please retry.";
        showError(errMsg, function () {
          var freshCb = document.getElementById("att-" + regId + "-" + dateKey);
          if (freshCb) {
            freshCb.checked = true;
          }
          markAttendance(regId, dateKey, freshCb);
        });
        return;
      }

      if (res.data.metrics) {
        updateMetrics(res.data.metrics);
      }
      if (res.data.record) {
        updateSingleRowCell(regId, dateKey, res.data.record);
      }

      var statusMsg = res.data.alreadyMarked
        ? row.fullName + " (" + row.registrationCode + ") was already marked PRESENT for " + dateKey + "."
        : (res.data.reinstated
            ? "Reinstated PRESENT attendance for " + row.fullName + " (" + row.registrationCode + ") on " + dateKey + "."
            : "Marked PRESENT for " + row.fullName + " (" + row.registrationCode + ") on " + dateKey + ".");
      showStatus(statusMsg);

      // Restore keyboard focus to the updated checkbox
      var updatedCb = document.getElementById("att-" + regId + "-" + dateKey);
      if (updatedCb) updatedCb.focus();
    } catch (e) {
      // Revert checkbox cleanly on network failure
      if (checkboxEl) {
        checkboxEl.checked = false;
        checkboxEl.disabled = false;
        checkboxEl.removeAttribute("aria-busy");
      }
      if (wrap) wrap.classList.remove("rgst-check-busy");
      if (metaEl) metaEl.textContent = prevMeta;

      showError(
        "Network error while marking attendance for " + row.fullName + " (" + row.registrationCode + "). Attendance was NOT saved.",
        function () {
          var freshCb = document.getElementById("att-" + regId + "-" + dateKey);
          if (freshCb) freshCb.checked = true;
          markAttendance(regId, dateKey, freshCb);
        }
      );
    }
  }

  function openCorrectionModal(regId, dateKey, dayLabel, triggerCheckbox) {
    var row = state.rowsById[regId];
    if (!row || !corrModal) return;
    var att = (row.attendance && row.attendance[dateKey]) || {};

    // Keep checkbox checked until server confirms revocation
    if (triggerCheckbox) {
      triggerCheckbox.checked = true;
    }

    state.lastFocusedEl = triggerCheckbox || document.activeElement;
    state.activeCorrection = {
      registrationId: regId,
      registrationCode: row.registrationCode,
      fullName: row.fullName,
      attendanceDateIst: dateKey,
      dayLabel: dayLabel,
      recordId: att.recordId || null,
      canDeskUndo: Boolean(att.canDeskUndo) || Boolean(operator.canOverrideAnytime)
    };

    if (corrDetails) {
      corrDetails.innerHTML =
        '<p><span class="text-[#B6A5C9]">Participant:</span> <strong class="text-white">' +
          escapeHtml(row.fullName) + "</strong> (" +
          '<span class="font-mono text-[#D5B77B]">' + escapeHtml(row.registrationCode) + "</span>)</p>" +
        '<p><span class="text-[#B6A5C9]">Date:</span> <strong class="text-white">' +
          escapeHtml(dayLabel + " — " + dateKey) + "</strong></p>" +
        '<p><span class="text-[#B6A5C9]">Marked By:</span> <strong class="text-white">' +
          escapeHtml(att.markedBy || "Desk Staff") + "</strong>" +
          (att.markedAtIst ? " on " + escapeHtml(att.markedAtIst) : "") +
        "</p>";
    }

    if (corrErrorEl) {
      corrErrorEl.classList.add("hidden");
      corrErrorEl.textContent = "";
    }
    if (corrReasonInput) {
      corrReasonInput.value = "";
    }

    if (!state.activeCorrection.canDeskUndo) {
      if (corrBlockedNotice) {
        corrBlockedNotice.textContent =
          "This attendance record cannot be undone by your desk account because either it was marked by another operator or more than 15 minutes have passed. Please ask an Admin or Superadmin to perform an audited override.";
        corrBlockedNotice.classList.remove("hidden");
      }
      if (corrReasonGroup) corrReasonGroup.classList.add("hidden");
      if (corrSubmitBtn) {
        corrSubmitBtn.disabled = true;
        corrSubmitBtn.classList.add("hidden");
      }
    } else {
      if (corrBlockedNotice) corrBlockedNotice.classList.add("hidden");
      if (corrReasonGroup) corrReasonGroup.classList.remove("hidden");
      if (corrSubmitBtn) {
        corrSubmitBtn.disabled = false;
        corrSubmitBtn.classList.remove("hidden");
        corrSubmitBtn.textContent = "Confirm Revoke Attendance";
      }
    }

    corrModal.classList.remove("hidden");

    if (state.activeCorrection.canDeskUndo && corrReasonInput) {
      corrReasonInput.focus();
    } else if (corrCancelBtn) {
      corrCancelBtn.focus();
    }
  }

  function closeCorrectionModal() {
    if (!corrModal) return;
    corrModal.classList.add("hidden");
    state.activeCorrection = null;
    if (state.lastFocusedEl && typeof state.lastFocusedEl.focus === "function") {
      state.lastFocusedEl.focus();
    }
    state.lastFocusedEl = null;
  }

  async function submitCorrection(e) {
    e.preventDefault();
    if (!state.activeCorrection || !state.activeCorrection.canDeskUndo) return;

    var reason = corrReasonInput ? corrReasonInput.value.trim() : "";
    if (reason.length < 5) {
      if (corrErrorEl) {
        corrErrorEl.textContent = "Please enter a clear correction reason of at least 5 characters.";
        corrErrorEl.classList.remove("hidden");
      }
      if (corrReasonInput) corrReasonInput.focus();
      return;
    }

    if (corrErrorEl) {
      corrErrorEl.classList.add("hidden");
      corrErrorEl.textContent = "";
    }
    if (corrSubmitBtn) {
      corrSubmitBtn.disabled = true;
      corrSubmitBtn.setAttribute("aria-busy", "true");
      corrSubmitBtn.textContent = "Saving Correction...";
    }

    var target = state.activeCorrection;

    try {
      var res = await apiFetch("/api/attendance/correct", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          attendanceRecordId: target.recordId,
          registrationId: target.registrationId,
          attendanceDateIst: target.attendanceDateIst,
          attendanceScope: attendanceScope,
          newStatus: "REVOKED",
          reason: reason
        })
      });

      if (res.status === 401) {
        window.location.href = "/login?next=/rgst";
        return;
      }

      if (!res.ok || !res.data || !res.data.success) {
        var msg = (res.data && res.data.error) || "Failed to save attendance correction.";
        if (corrErrorEl) {
          corrErrorEl.textContent = msg;
          corrErrorEl.classList.remove("hidden");
        }
        return;
      }

      if (res.data.metrics) {
        updateMetrics(res.data.metrics);
      }
      if (res.data.record) {
        updateSingleRowCell(target.registrationId, target.attendanceDateIst, res.data.record);
      }

      closeCorrectionModal();
      showStatus(
        "Revoked attendance for " + target.fullName + " (" + target.registrationCode + ") on " + target.attendanceDateIst + " (audited)."
      );

      var freshCb = document.getElementById("att-" + target.registrationId + "-" + target.attendanceDateIst);
      if (freshCb) freshCb.focus();
    } catch (err) {
      if (corrErrorEl) {
        corrErrorEl.textContent = "Network error while saving correction. Attendance was NOT modified.";
        corrErrorEl.classList.remove("hidden");
      }
    } finally {
      if (corrSubmitBtn) {
        corrSubmitBtn.disabled = false;
        corrSubmitBtn.removeAttribute("aria-busy");
        corrSubmitBtn.textContent = "Confirm Revoke Attendance";
      }
    }
  }

  // Event Listeners
  if (tableBody) {
    tableBody.addEventListener("change", function (e) {
      var target = e.target;
      if (!target || !target.classList || !target.classList.contains("rgst-day-checkbox")) {
        return;
      }
      var regId = target.getAttribute("data-reg-id");
      var dateKey = target.getAttribute("data-date");
      var dayLabel = target.getAttribute("data-day-label") || dateKey;
      if (!regId || !dateKey) return;

      if (target.checked) {
        markAttendance(regId, dateKey, target);
      } else {
        openCorrectionModal(regId, dateKey, dayLabel, target);
      }
    });
  }

  if (filterForm) {
    filterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      state.q = searchInput ? searchInput.value.trim() : "";
      state.page = 1;
      loadRoster(false);
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", function () {
      if (state.debounceTimer) clearTimeout(state.debounceTimer);
      state.debounceTimer = setTimeout(function () {
        state.q = searchInput.value.trim();
        state.page = 1;
        loadRoster(false);
      }, 250);
    });
  }

  if (categoryFilter) {
    categoryFilter.addEventListener("change", function () {
      state.category = categoryFilter.value || "ALL";
      state.page = 1;
      loadRoster(false);
    });
  }

  if (eligibilityFilter) {
    eligibilityFilter.addEventListener("change", function () {
      state.eligibility = eligibilityFilter.value || "ALL";
      state.page = 1;
      loadRoster(false);
    });
  }

  if (dayFilter) {
    dayFilter.addEventListener("change", function () {
      state.day = dayFilter.value || "";
      state.page = 1;
      loadRoster(false);
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      state.perPage = parseInt(perPageSelect.value, 10) || 25;
      state.page = 1;
      loadRoster(false);
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener("click", function () {
      state.q = "";
      state.category = "ALL";
      state.eligibility = "ALL";
      state.day = "";
      state.page = 1;
      if (searchInput) searchInput.value = "";
      if (categoryFilter) categoryFilter.value = "ALL";
      if (eligibilityFilter) eligibilityFilter.value = "ALL";
      if (dayFilter) dayFilter.value = "";
      hideStatus();
      hideError();
      loadRoster(false);
      if (searchInput) searchInput.focus();
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      loadRoster(false);
    });
  }

  if (prevPageBtn) {
    prevPageBtn.addEventListener("click", function () {
      if (state.page > 1) {
        state.page--;
        loadRoster(false);
      }
    });
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener("click", function () {
      if (state.page < state.totalPages) {
        state.page++;
        loadRoster(false);
      }
    });
  }

  if (errorRetryBtn) {
    errorRetryBtn.addEventListener("click", function () {
      var fn = state.lastRetryFn;
      hideError();
      if (typeof fn === "function") fn();
    });
  }

  if (errorDismissBtn) {
    errorDismissBtn.addEventListener("click", hideError);
  }

  if (corrCloseBtn) corrCloseBtn.addEventListener("click", closeCorrectionModal);
  if (corrCancelBtn) corrCancelBtn.addEventListener("click", closeCorrectionModal);
  if (corrForm) corrForm.addEventListener("submit", submitCorrection);

  if (corrModal) {
    corrModal.addEventListener("click", function (e) {
      if (e.target === corrModal) {
        closeCorrectionModal();
      }
    });
  }

  document.addEventListener("keydown", function (e) {
    if (!corrModal || corrModal.classList.contains("hidden")) return;
    if (e.key === "Escape") {
      e.preventDefault();
      closeCorrectionModal();
      return;
    }
    if (e.key === "Tab") {
      var focusables = corrModal.querySelectorAll(
        'button:not([disabled]):not(.hidden), textarea:not([disabled]):not(.hidden), input:not([disabled]):not(.hidden), [tabindex]:not([tabindex="-1"])'
      );
      var visible = [];
      for (var i = 0; i < focusables.length; i++) {
        if (focusables[i].offsetParent !== null) {
          visible.push(focusables[i]);
        }
      }
      if (visible.length === 0) return;
      var first = visible[0];
      var last = visible[visible.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logoutBtn.disabled = true;
      apiFetch("/api/auth/logout", { method: "POST" })
        .catch(function () {})
        .finally(function () {
          window.location.href = "/login";
        });
    });
  }

  // Initialize
  updateIstClock();
  setInterval(updateIstClock, 1000);
  loadRoster(false);
})();
