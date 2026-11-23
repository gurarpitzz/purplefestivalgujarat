/**
 * Purple Festival Gujarat 2027 — Accessible Multi-Step Registration Wizard
 * Exact Vanilla JS port of src/components/RegistrationWizard.tsx
 */
(function () {
  "use strict";

  var DRAFT_STORAGE_KEY = "pfg2027_registration_draft_v1";
  var FESTIVAL_NAME = "Purple Festival Gujarat 2027";
  var FESTIVAL_DATES_DISPLAY = "21–23 January 2027";
  var FESTIVAL_LOCATION_DISPLAY = "Vadodara, Gujarat";

  var FESTIVAL_DAYS = [
    { code: "DAY_1", label: "Day 1 — 21 January 2027", isoDate: "2027-01-21" },
    { code: "DAY_2", label: "Day 2 — 22 January 2027", isoDate: "2027-01-22" },
    { code: "DAY_3", label: "Day 3 — 23 January 2027", isoDate: "2027-01-23" },
  ];

  var OCCUPATION_OPTIONS = [
    "Student",
    "Teacher",
    "Academic",
    "Researcher",
    "NGO Professional",
    "Government Official",
    "Healthcare Professional",
    "Corporate Professional",
    "Accessibility Professional",
    "Artist",
    "Entrepreneur",
    "Other",
  ];

  var VOLUNTEER_ROLE_OPTIONS = [
    "Accessibility & Inclusion Desk",
    "Stage & Cultural Coordination",
    "Para-Sports Support",
    "Registration & Hospitality",
    "Sign Language / Communication Support",
    "Media & Documentation",
  ];

  var LANGUAGE_OPTIONS = [
    "Gujarati",
    "Hindi",
    "English",
    "Indian Sign Language (ISL)",
    "Marathi",
  ];

  var PARA_SPORTS_OPTIONS = [
    "Wheelchair Basketball",
    "Para Athletics",
    "Blind Cricket",
    "Para Badminton",
    "Boccia",
    "Para Table Tennis",
    "Chess",
  ];

  var SLI_EXPERTISE_OPTIONS = [
    "Plenary & Keynote Sessions",
    "Panel Discussions & Policy",
    "Workshops & Education",
    "Cultural & Stage Performances",
    "Tactile / Deafblind Interpretation",
    "Para-Sports & Community Events",
  ];

  var SLI_PREF_OPTIONS = [
    "ISL ↔ English",
    "ISL ↔ Gujarati",
    "ISL ↔ Hindi",
    "Team Interpreting (20-min rotations)",
    "Platform / Stage Interpreting",
  ];

  var STEPS = [
    { num: 1, title: "Personal Details" },
    { num: 2, title: "Category & Role" },
    { num: 3, title: "Accessibility" },
    { num: 4, title: "Declarations" },
    { num: 5, title: "Review & Submit" },
  ];

  var PATHWAYS = [
    {
      id: "ATTENDEE",
      title: "General Attendee",
      badge: "Instant QR Pass",
      categoryCode: "ATTENDEE",
      preselectPwd: false,
    },
    {
      id: "ACCESSIBLE",
      title: "Person with Disability / Accessible Registration",
      badge: "Any Role + Full Support",
      categoryCode: "ATTENDEE",
      preselectPwd: true,
    },
    {
      id: "DELEGATE",
      title: "Delegate (3-Day Conference)",
      badge: "All-Access Track",
      categoryCode: "DELEGATE",
      preselectPwd: false,
    },
    {
      id: "VOLUNTEER",
      title: "Festival Volunteer",
      badge: "Coordinator Review",
      categoryCode: "VOLUNTEER",
      preselectPwd: false,
    },
    {
      id: "SLI",
      title: "SLI / Indian Sign Language Professional",
      badge: "Verification & Assignment",
      categoryCode: "SLI",
      preselectPwd: false,
    },
  ];

  function esc(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function createEmptyDraft(user) {
    return {
      fullName: (user && user.fullName) || "",
      email: (user && user.email) || "",
      phone: (user && user.phone) || "",
      country: (user && user.country) || "India",
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
      // Delegate
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
      // Volunteer
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
      // SLI
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

  async function apiFetch(endpoint, fallbackPhpEndpoint, options) {
    var res = await fetch(endpoint, options);
    var ct = res.headers.get("content-type") || "";
    if (res.status === 404 && !ct.includes("application/json") && fallbackPhpEndpoint) {
      res = await fetch(fallbackPhpEndpoint, options);
    }
    return res;
  }

  function initWizard() {
    var root = document.getElementById("pfg-registration-wizard-root");
    if (!root) return;

    var boot = window.__PFG_WIZARD_BOOT__ || {};
    var initialCategories = Array.isArray(boot.initialCategories) && boot.initialCategories.length
      ? boot.initialCategories
      : [
          {
            id: "ATTENDEE",
            code: "ATTENDEE",
            name: "General Attendee / Visitor",
            description: "Free entry to cultural stages, exhibitions, para-sports & festival zones.",
            requiresApproval: false,
            requiresPayment: false,
            feeAmountInr: 0,
          },
          {
            id: "DELEGATE",
            code: "DELEGATE",
            name: "Conference Delegate",
            description: "Full access to policy panels, NEP 2020 & UDL symposiums, and delegate kit.",
            requiresApproval: false,
            requiresPayment: false,
            feeAmountInr: 0,
          },
          {
            id: "VOLUNTEER",
            code: "VOLUNTEER",
            name: "Festival Volunteer",
            description: "Join the accessibility & hospitality volunteer corps (requires approval).",
            requiresApproval: true,
            requiresPayment: false,
            feeAmountInr: 0,
          },
          {
            id: "SLI",
            code: "SLI",
            name: "Sign Language Interpreter (SLI)",
            description: "Certified Indian Sign Language interpreter supporting festival stages & panels.",
            requiresApproval: true,
            requiresPayment: false,
            feeAmountInr: 0,
          },
        ];

    var disabilityCategories = Array.isArray(boot.disabilityCategories)
      ? boot.disabilityCategories
      : [];
    var accommodationTypes = Array.isArray(boot.accommodationTypes)
      ? boot.accommodationTypes
      : [];
    var currentUser = boot.currentUser || null;
    var initialCategoryParam = boot.initialCategoryParam || null;

    var state = {
      showHub: !initialCategoryParam,
      selectedPathwayId: "ATTENDEE",
      step: 1,
      draft: createEmptyDraft(currentUser),
      password: "",
      disabilitySearch: "",
      draftNotice: null,
      errors: {},
      submitError: null,
      submitting: false,
      result: null,
      couponChecking: false,
      couponResult: null,
    };

    // Restore saved draft from localStorage
    try {
      var saved = window.localStorage.getItem(DRAFT_STORAGE_KEY);
      if (saved) {
        var parsed = JSON.parse(saved);
        if (parsed && typeof parsed === "object") {
          state.draft = Object.assign({}, state.draft, parsed, {
            fullName: (currentUser && currentUser.fullName) || parsed.fullName || state.draft.fullName,
            email: (currentUser && currentUser.email) || parsed.email || state.draft.email,
          });
          state.draftNotice = "Saved registration draft restored.";
        }
      }
    } catch (e) {
      // Ignore storage error
    }

    // Apply URL parameter if provided
    if (initialCategoryParam) {
      var upper = String(initialCategoryParam).toUpperCase();
      if (upper === "ACCESSIBLE" || upper === "PWD") {
        state.draft.isPersonWithDisability = "YES";
        state.selectedPathwayId = "ACCESSIBLE";
        state.showHub = false;
      } else if (
        initialCategories.some(function (c) {
          return c.code === upper;
        })
      ) {
        state.draft.categoryCode = upper;
        state.selectedPathwayId = upper;
        state.showHub = false;
      }
    }

    function saveDraftToStorage() {
      if (state.result) return;
      try {
        window.localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(state.draft));
      } catch (e) {
        // Ignore quota errors
      }
    }

    function getSelectedCategory() {
      for (var i = 0; i < initialCategories.length; i++) {
        if (initialCategories[i].code === state.draft.categoryCode) {
          return initialCategories[i];
        }
      }
      return initialCategories[0];
    }

    function getFilteredDisabilities() {
      var q = state.disabilitySearch.trim().toLowerCase();
      if (!q) return disabilityCategories;
      return disabilityCategories.filter(function (d) {
        return (
          String(d.name || "").toLowerCase().includes(q) ||
          String(d.rpwdGroup || "").toLowerCase().includes(q)
        );
      });
    }

    function toggleArrayValue(key, value) {
      var current = Array.isArray(state.draft[key]) ? state.draft[key] : [];
      var idx = current.indexOf(value);
      if (idx >= 0) {
        state.draft[key] = current.filter(function (v) {
          return v !== value;
        });
      } else {
        state.draft[key] = current.concat([value]);
      }
      if (state.errors[key]) {
        delete state.errors[key];
      }
      saveDraftToStorage();
    }

    function startPathway(categoryCode, preselectPwd) {
      state.draft.categoryCode = categoryCode;
      if (preselectPwd) {
        state.draft.isPersonWithDisability = "YES";
      }
      state.couponResult = null;
      state.showHub = false;
      state.step = 1;
      saveDraftToStorage();
      render();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function validateStep(stepNum) {
      var nextErrors = {};
      var d = state.draft;

      if (stepNum === 1) {
        if (d.fullName.trim().length < 2) {
          nextErrors.fullName = "Please enter your full name (at least 2 characters).";
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email.trim())) {
          nextErrors.email = "Please enter a valid email address.";
        }
        if (!/^\+?[0-9\s\-()]{7,20}$/.test(d.phone.trim())) {
          nextErrors.phone = "Please enter a valid phone number (e.g., +91 98250 12345).";
        }
        if (d.country.trim().length < 2) {
          nextErrors.country = "Please enter your country.";
        }
        if (!currentUser && state.password.length > 0 && state.password.length < 8) {
          nextErrors.password = "Password must be at least 8 characters long.";
        }
      }

      if (stepNum === 2) {
        if (!d.categoryCode) {
          nextErrors.categoryCode = "Please choose a registration category.";
        }
        if (!d.occupation) {
          nextErrors.occupation = "Please select your occupation or affiliation.";
        }
        if (!Array.isArray(d.attendingDays) || d.attendingDays.length === 0) {
          nextErrors.attendingDays = "Please select at least one festival day.";
        }
        if (
          d.categoryCode === "SLI" &&
          d.sliVideoDemoUrl.trim() &&
          !/^https?:\/\/.+/.test(d.sliVideoDemoUrl.trim())
        ) {
          nextErrors.sliVideoDemoUrl = "Video demo link must start with https:// or http://.";
        }
      }

      if (stepNum === 4) {
        if (!d.consentZeroTolerance) {
          nextErrors.consentZeroTolerance =
            "You must acknowledge the zero-tolerance policy towards harassment.";
        }
        if (!d.consentDataProcessing) {
          nextErrors.consentDataProcessing =
            "You must consent to the processing of your registration data under the DPDP Act.";
        }
      }

      state.errors = nextErrors;
      if (Object.keys(nextErrors).length > 0) {
        render();
        setTimeout(function () {
          var banner = document.getElementById("wizard-error-banner");
          if (banner) banner.focus();
        }, 20);
        return false;
      }
      return true;
    }

    async function handleVerifyCoupon() {
      var code = state.draft.couponCode.trim();
      if (!code) {
        state.couponResult = {
          valid: false,
          message: "Please enter a coupon code first.",
        };
        render();
        return;
      }
      state.couponChecking = true;
      state.couponResult = null;
      render();

      try {
        var res = await apiFetch(
          "/api/coupons/validate",
          "/api/coupons/validate.php",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              code: code,
              categoryCode: state.draft.categoryCode,
            }),
          }
        );
        var data = await res.json().catch(function () {
          return {};
        });
        if (!res.ok || !data.valid) {
          state.couponResult = {
            valid: false,
            message: data.error || "Coupon code is not valid.",
          };
        } else {
          state.couponResult = {
            valid: true,
            message:
              "Coupon " +
              data.coupon.code +
              " applied! Discount: ₹" +
              data.pricing.discountInr +
              " (Net payable: ₹" +
              data.pricing.netAmountInr +
              ").",
            discountInr: data.pricing.discountInr,
            netAmountInr: data.pricing.netAmountInr,
            baseAmountInr: data.pricing.baseAmountInr,
          };
        }
      } catch (e) {
        state.couponResult = {
          valid: false,
          message: "Unable to verify coupon right now.",
        };
      } finally {
        state.couponChecking = false;
        render();
      }
    }

    async function handleSubmit() {
      state.submitError = null;
      if (!validateStep(1) || !validateStep(2) || !validateStep(4)) {
        state.submitError = "Please review the required fields before submitting.";
        render();
        return;
      }

      state.submitting = true;
      render();

      var d = state.draft;
      var payload = {
        fullName: d.fullName.trim(),
        email: d.email.trim(),
        phone: d.phone.trim(),
        country: d.country.trim() || "India",
        categoryCode: d.categoryCode,
        occupation: d.occupation,
        organizationName: d.organizationName.trim(),
        designation: d.designation.trim(),
        isPersonWithDisability: d.isPersonWithDisability,
        disabilityCategoryCodes:
          d.isPersonWithDisability === "YES" ? d.disabilityCategoryCodes : [],
        disabilitySelfDescription:
          d.isPersonWithDisability === "YES" ? d.disabilitySelfDescription.trim() : "",
        accommodationTypes: d.accommodationTypes,
        accommodationFreeText: d.accommodationFreeText.trim(),
        attendingDays: d.attendingDays,
        interestedTracks: [],
        couponCode: d.couponCode.trim(),
        consentZeroTolerance: d.consentZeroTolerance,
        consentDataProcessing: d.consentDataProcessing,
        consentMediaPhotography: d.consentMediaPhotography,
      };

      if (state.password.trim()) {
        payload.password = state.password.trim();
      }

      if (d.categoryCode === "DELEGATE") {
        payload.delegateDetails = {
          gender: d.delegateGender || undefined,
          age: d.delegateAge ? Number(d.delegateAge) : undefined,
          cityState: d.delegateCityState || undefined,
          paraSportsInterests: d.delegateParaSports,
          outstationTravelRequired: Boolean(d.delegateOutstation),
          accommodationNeeded: Boolean(d.delegateAccommodation),
          foodPreference: d.delegateFood || undefined,
          emergencyContactName: d.delegateEmergencyName || undefined,
          emergencyContactPhone: d.delegateEmergencyPhone || undefined,
          guardianName: d.delegateGuardianName || undefined,
        };
      }

      if (d.categoryCode === "VOLUNTEER") {
        payload.volunteerDetails = {
          age: d.volAge ? Number(d.volAge) : undefined,
          gender: d.volGender || undefined,
          cityState: d.volCityState || undefined,
          institutionName: d.volInstitution || undefined,
          isMsuStudentOrAlumni: Boolean(d.volMsuAlumni),
          crrNumber: d.volCrrNumber || undefined,
          preferredRoles: d.volPreferredRoles,
          availabilityDays: d.attendingDays,
          languagesSpoken: d.volLanguages,
          relevantExperience: d.volExperience || undefined,
          emergencyContactName: d.volEmergencyName || undefined,
          emergencyContactPhone: d.volEmergencyPhone || undefined,
        };
      }

      if (d.categoryCode === "SLI") {
        payload.sliDetails = {
          cityState: d.sliCityState || undefined,
          basedInGujarat: Boolean(d.sliBasedInGujarat),
          crrNumber: d.sliCrrNumber || undefined,
          qualifications: d.sliQualifications || undefined,
          trainingInstitute: d.sliTrainingInstitute || undefined,
          certificationLevel: d.sliCertificationLevel || undefined,
          yearsOfExperience: d.sliYearsExperience ? Number(d.sliYearsExperience) : undefined,
          professionalAffiliation: d.sliAffiliation || undefined,
          areasOfExpertise: d.sliAreasExpertise,
          interpretationPreferences: d.sliInterpretPrefs,
          availabilityDays: d.attendingDays,
          videoDemoUrl: d.sliVideoDemoUrl.trim() || "",
          honorariumOrTravelNotes: d.sliHonorariumNotes || undefined,
        };
      }

      try {
        var res = await apiFetch(
          "/api/registrations",
          "/api/registrations/index.php",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          }
        );
        var data = await res.json().catch(function () {
          return {};
        });

        if (!res.ok) {
          state.submitError =
            data.error ||
            "Registration could not be completed. Please review your details.";
          state.submitting = false;
          render();
          return;
        }

        try {
          window.localStorage.removeItem(DRAFT_STORAGE_KEY);
        } catch (e) {
          // ignore
        }

        state.result = data;
        state.submitting = false;
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
      } catch (e) {
        state.submitError =
          "Network error while submitting registration. Please try again.";
        state.submitting = false;
        render();
      }
    }

    function renderDisabilitiesChecklistHtml() {
      var list = getFilteredDisabilities();
      if (!list.length) {
        return '<p class="col-span-2 text-xs text-[#4B4257] py-2">No matching RPwD disability categories found.</p>';
      }
      return list
        .map(function (d) {
          var checked = state.draft.disabilityCategoryCodes.indexOf(d.code) >= 0;
          return (
            '<label class="cursor-pointer rounded-xl border p-2.5 flex items-start gap-2.5 text-sm ' +
            (checked
              ? "bg-white border-[#59436D] font-semibold"
              : "bg-white/70 border-[#59436D]/20") +
            '">' +
            '<input type="checkbox" data-toggle-array="disabilityCategoryCodes" value="' +
            esc(d.code) +
            '" ' +
            (checked ? "checked" : "") +
            ' class="mt-0.5 w-4 h-4 accent-[#59436D]" />' +
            "<span>" +
            esc(d.name) +
            '<span class="block text-xs font-normal text-[#4B4257]">' +
            esc(d.rpwdGroup) +
            "</span>" +
            "</span>" +
            "</label>"
          );
        })
        .join("");
    }

    function renderConfirmationView() {
      var r = state.result;
      var reg = r.registration || {};
      var cat = r.category || {};
      var qrPass = r.qrPass || null;

      var qrImgSrc = "";
      if (qrPass) {
        if (qrPass.qrDataUrl && String(qrPass.qrDataUrl).startsWith("data:")) {
          qrImgSrc = qrPass.qrDataUrl;
        } else if (
          window.PFGQrCode &&
          typeof window.PFGQrCode.toDataUrl === "function"
        ) {
          qrImgSrc = window.PFGQrCode.toDataUrl(
            qrPass.rawToken || qrPass.credentialCode || reg.registrationCode,
            { width: 320 }
          );
        }
      }

      var statusBadgeText = qrPass
        ? "Registration Confirmed"
        : cat.requiresApproval
          ? "Application Submitted for Review"
          : "Registration Received";

      var headingText = qrPass
        ? "Welcome to " + FESTIVAL_NAME + "!"
        : "Thank you for applying as " + (cat.name || reg.categoryCode);

      var passPrintHref =
        "/dashboard/pass-print.php?registrationId=" +
        encodeURIComponent(reg.id || "");

      var html =
        '<div class="min-h-screen editorial-mesh-light py-10 px-4 sm:px-6 lg:px-8">' +
        '<div class="max-w-3xl mx-auto">' +
        '<div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-xl overflow-hidden">' +
        '<div class="bg-[#17151C] text-[#F9F7FC] p-6 sm:p-8">' +
        '<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#19734B]/25 border border-[#56C28E]/50 text-[#9FE8C4] text-xs font-semibold uppercase tracking-wider mb-3">' +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
        esc(statusBadgeText) +
        "</div>" +
        '<h1 class="font-display text-2xl sm:text-3xl font-semibold">' +
        esc(headingText) +
        "</h1>" +
        '<p class="mt-2 text-sm text-[#D7CCE2]">' +
        esc(FESTIVAL_DATES_DISPLAY) +
        " • " +
        esc(FESTIVAL_LOCATION_DISPLAY) +
        "</p>" +
        "</div>" +
        '<div class="p-6 sm:p-8 space-y-6 text-[#211B29]">' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-[#F6F1F8] border border-[#59436D]/15 p-4">' +
        "<div>" +
        '<p class="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">Registration Code</p>' +
        '<p class="text-lg font-mono font-bold text-[#432F56]">' +
        esc(reg.registrationCode) +
        "</p>" +
        "</div>" +
        "<div>" +
        '<p class="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">Category &amp; Status</p>' +
        '<p class="text-base font-semibold text-[#211B29]">' +
        esc(cat.name || reg.categoryCode) +
        ' <span class="inline-block ml-1 px-2 py-0.5 rounded-md text-xs font-bold bg-[#E8DFF0] text-[#432F56]">' +
        esc(reg.status) +
        "</span></p>" +
        "</div>" +
        "<div>" +
        '<p class="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">Participant</p>' +
        '<p class="text-sm font-medium">' +
        esc(reg.fullName) +
        "</p>" +
        "</div>" +
        "<div>" +
        '<p class="text-xs uppercase tracking-wider text-[#4B4257] font-semibold">Email</p>' +
        '<p class="text-sm font-medium">' +
        esc(reg.email) +
        "</p>" +
        "</div>" +
        "</div>";

      if (qrPass) {
        html +=
          '<div class="rounded-2xl border-2 border-[#9273B5] bg-[#F9F7FC] p-6 flex flex-col sm:flex-row items-center gap-6">' +
          '<div class="shrink-0 bg-white p-3 rounded-xl border border-[#59436D]/20 shadow-sm">' +
          '<img src="' +
          esc(qrImgSrc) +
          '" alt="' +
          esc(
            "Digital QR entry pass for " +
              reg.fullName +
              ", registration code " +
              qrPass.credentialCode
          ) +
          '" class="w-48 h-48 object-contain" />' +
          "</div>" +
          '<div class="space-y-3 text-center sm:text-left">' +
          '<h2 class="font-display text-xl font-semibold text-[#17151C]">Your Digital QR Entry Pass</h2>' +
          '<p class="text-sm text-[#4B4257]">Present this QR code or your Registration ID <strong class="font-mono text-[#211B29]">' +
          esc(qrPass.credentialCode) +
          "</strong> at any festival reception desk. Your QR code contains zero personal or medical information.</p>" +
          '<div class="flex flex-wrap gap-3 justify-center sm:justify-start pt-1">' +
          '<a href="' +
          esc(passPrintHref) +
          '" target="_blank" rel="noopener" class="btn-primary-pfg inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-semibold text-sm transition-colors">' +
          '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
          "Download Accessible PDF" +
          "</a>" +
          '<a href="/dashboard" class="inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-xl border-2 border-[#59436D] text-[#432F56] font-semibold text-sm hover:bg-[#E8DFF0]">' +
          "Open Participant Dashboard" +
          "</a>" +
          "</div>" +
          "</div>" +
          "</div>";
      } else if (cat.requiresApproval) {
        html +=
          '<div class="rounded-xl bg-[#E8DFF0]/60 border border-[#9273B5] p-5 space-y-2">' +
          '<h2 class="font-semibold text-[#432F56] flex items-center gap-2">' +
          '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' +
          "Coordinator Review Required" +
          "</h2>" +
          '<p class="text-sm text-[#211B29]">Volunteer and Indian Sign Language Interpreter applications are reviewed by our festival coordinators before duty assignment. Once approved, your official Digital QR Credential and duty schedule will appear automatically in your Participant Dashboard.</p>' +
          "</div>";
      }

      var dashHref = r.sessionCreated || currentUser ? "/dashboard" : "/login";
      var dashText =
        r.sessionCreated || currentUser
          ? "Go to Participant Dashboard"
          : "Sign In to Manage Registration";

      html +=
        '<div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-[#59436D]/15">' +
        '<button type="button" id="btn-register-another" class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 text-sm font-semibold text-[#432F56] hover:bg-[#F6F1F8]">Register for Another Role</button>' +
        '<a href="' +
        esc(dashHref) +
        '" class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-semibold hover:bg-[#25212B] inline-flex items-center gap-2">' +
        esc(dashText) +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>' +
        "</a>" +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>";

      return html;
    }

    function renderHubView() {
      var optionsHtml = PATHWAYS.map(function (p) {
        var isSelected = state.selectedPathwayId === p.id;
        return (
          '<button type="button" role="radio" aria-checked="' +
          (isSelected ? "true" : "false") +
          '" data-pathway-id="' +
          esc(p.id) +
          '" class="touch-target flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors ' +
          (isSelected
            ? "bg-[#CFCFF8] ring-2 ring-[#202D59]"
            : "bg-[#DDDDF9] hover:bg-[#D2D2F7]") +
          '">' +
          '<div class="min-w-0">' +
          '<span class="block text-[17px] font-semibold leading-snug text-[#202D59]">' +
          esc(p.title) +
          "</span>" +
          '<span class="mt-0.5 block text-xs font-medium text-[#46537C]">' +
          esc(p.badge) +
          "</span>" +
          "</div>" +
          (isSelected
            ? '<svg class="h-5 w-5 shrink-0 text-[#202D59]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>'
            : "") +
          "</button>"
        );
      }).join("");

      return (
        '<div class="min-h-[calc(100vh-76px)] bg-white text-[#202D59]">' +
        '<div class="mx-auto flex max-w-[560px] flex-col px-5 pb-20 pt-14 sm:pt-20">' +
        '<h1 class="text-[clamp(1.65rem,3.4vw,2.3rem)] font-bold leading-[1.2] tracking-[-0.02em] text-[#202D59]">' +
        '<span class="sr-only">Register &amp; Apply — </span>What type of registration are you interested in for Purple Fest Gujarat?' +
        "</h1>" +
        '<div role="radiogroup" aria-label="Registration pathway options" class="mt-8 flex flex-col gap-3">' +
        optionsHtml +
        "</div>" +
        '<div class="mt-6 flex items-center justify-between gap-4">' +
        '<a href="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-[#46537C] hover:text-[#202D59] hover:underline">' +
        '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>' +
        "Back to Home" +
        "</a>" +
        '<button type="button" id="btn-hub-next" class="touch-target inline-flex items-center justify-center border-2 border-[#202D59] bg-[#DDDDF9] px-7 py-2.5 text-[16px] font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white">' +
        "Next" +
        "</button>" +
        "</div>" +
        "</div>" +
        "</div>"
      );
    }

    function renderStep1Html() {
      var d = state.draft;
      var e = state.errors;
      var html =
        '<div class="space-y-5">' +
        "<div>" +
        '<h1 class="font-display text-2xl font-semibold text-[#17151C]">Step 1: Personal Details</h1>' +
        '<p class="mt-1 text-sm text-[#4B4257]">Enter your contact details for your official festival credential.</p>' +
        "</div>";

      if (currentUser) {
        html +=
          '<div class="rounded-xl bg-[#E8DFF0]/70 border border-[#9273B5] p-3.5 text-sm text-[#432F56] flex items-center gap-2">' +
          '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>' +
          "<span>Signed in as <strong>" +
          esc(currentUser.email) +
          "</strong>. This registration will be linked to your single festival account.</span>" +
          "</div>";
      }

      html +=
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">' +
        '<div class="sm:col-span-2">' +
        '<label for="reg-fullName" class="block text-sm font-semibold text-[#211B29] mb-1.5">Full Name <span aria-hidden="true" class="text-[#B42332]">*</span></label>' +
        '<input id="reg-fullName" data-draft-field="fullName" type="text" required autocomplete="name" aria-invalid="' +
        (e.fullName ? "true" : "false") +
        '" value="' +
        esc(d.fullName) +
        '" placeholder="e.g., Meera Deshmukh" class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
        (e.fullName
          ? '<p class="mt-1 text-xs font-medium text-[#B42332]">' +
            esc(e.fullName) +
            "</p>"
          : "") +
        "</div>" +
        "<div>" +
        '<label for="reg-email" class="block text-sm font-semibold text-[#211B29] mb-1.5">Email Address <span aria-hidden="true" class="text-[#B42332]">*</span></label>' +
        '<input id="reg-email" data-draft-field="email" type="email" required autocomplete="email" ' +
        (currentUser ? "disabled" : "") +
        ' aria-invalid="' +
        (e.email ? "true" : "false") +
        '" value="' +
        esc(d.email) +
        '" placeholder="you@example.org" class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29] disabled:bg-[#F6F1F8]" />' +
        (e.email
          ? '<p class="mt-1 text-xs font-medium text-[#B42332]">' +
            esc(e.email) +
            "</p>"
          : "") +
        "</div>" +
        "<div>" +
        '<label for="reg-phone" class="block text-sm font-semibold text-[#211B29] mb-1.5">Phone Number <span aria-hidden="true" class="text-[#B42332]">*</span></label>' +
        '<input id="reg-phone" data-draft-field="phone" type="tel" required autocomplete="tel" aria-invalid="' +
        (e.phone ? "true" : "false") +
        '" value="' +
        esc(d.phone) +
        '" placeholder="+91 98250 12345" class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
        (e.phone
          ? '<p class="mt-1 text-xs font-medium text-[#B42332]">' +
            esc(e.phone) +
            "</p>"
          : "") +
        "</div>" +
        "<div>" +
        '<label for="reg-country" class="block text-sm font-semibold text-[#211B29] mb-1.5">Country <span aria-hidden="true" class="text-[#B42332]">*</span></label>' +
        '<input id="reg-country" data-draft-field="country" type="text" required autocomplete="country-name" value="' +
        esc(d.country) +
        '" class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
        (e.country
          ? '<p class="mt-1 text-xs font-medium text-[#B42332]">' +
            esc(e.country) +
            "</p>"
          : "") +
        "</div>";

      if (!currentUser) {
        html +=
          "<div>" +
          '<label for="reg-password" class="block text-sm font-semibold text-[#211B29] mb-1.5">Create Portal Password (Optional)</label>' +
          '<input id="reg-password" type="password" autocomplete="new-password" value="' +
          esc(state.password) +
          '" placeholder="Min. 8 characters" class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
          '<p class="mt-1 text-xs text-[#4B4257]">Set a password to immediately sign in to your Participant Dashboard and download your QR pass.</p>' +
          (e.password
            ? '<p class="mt-1 text-xs font-medium text-[#B42332]">' +
              esc(e.password) +
              "</p>"
            : "") +
          "</div>";
      }

      html += "</div></div>";
      return html;
    }

    function renderStep2Html() {
      var d = state.draft;
      var catsHtml = initialCategories
        .map(function (cat) {
          var checked = d.categoryCode === cat.code;
          return (
            '<label class="cursor-pointer rounded-xl border-2 p-3.5 flex items-start gap-3 transition-colors ' +
            (checked
              ? "border-[#59436D] bg-[#F6F1F8]"
              : "border-[#59436D]/20 hover:border-[#9273B5]") +
            '">' +
            '<input type="radio" name="categoryCode" data-category-radio="' +
            esc(cat.code) +
            '" value="' +
            esc(cat.code) +
            '" ' +
            (checked ? "checked" : "") +
            ' class="mt-1 w-4 h-4 accent-[#59436D]" />' +
            '<div class="text-sm">' +
            '<div class="font-semibold text-[#17151C]">' +
            esc(cat.name) +
            "</div>" +
            '<div class="mt-0.5 flex flex-wrap gap-1.5 text-xs">' +
            '<span class="px-2 py-0.5 rounded bg-[#E8DFF0] text-[#432F56] font-semibold">' +
            (cat.feeAmountInr > 0 ? "₹" + esc(cat.feeAmountInr) : "Free") +
            "</span>" +
            (cat.requiresApproval
              ? '<span class="px-2 py-0.5 rounded bg-[#17151C] text-[#D5B77B] font-semibold">Review Required</span>'
              : "") +
            "</div>" +
            "</div>" +
            "</label>"
          );
        })
        .join("");

      var occHtml = OCCUPATION_OPTIONS.map(function (opt) {
        return (
          '<option value="' +
          esc(opt) +
          '" ' +
          (d.occupation === opt ? "selected" : "") +
          ">" +
          esc(opt) +
          "</option>"
        );
      }).join("");

      var daysHtml = FESTIVAL_DAYS.map(function (day) {
        var checked = d.attendingDays.indexOf(day.code) >= 0;
        return (
          '<label class="cursor-pointer min-h-[44px] rounded-xl border p-3 flex items-center gap-2.5 text-sm font-medium ' +
          (checked
            ? "border-[#59436D] bg-[#F6F1F8] text-[#17151C]"
            : "border-[#59436D]/25 text-[#4B4257]") +
          '">' +
          '<input type="checkbox" data-toggle-array="attendingDays" value="' +
          esc(day.code) +
          '" ' +
          (checked ? "checked" : "") +
          ' class="w-4 h-4 accent-[#59436D]" />' +
          esc(day.label) +
          "</label>"
        );
      }).join("");

      var html =
        '<div class="space-y-6">' +
        "<div>" +
        '<h1 class="font-display text-2xl font-semibold text-[#17151C]">Step 2: Registration Category &amp; Role Details</h1>' +
        '<p class="mt-1 text-sm text-[#4B4257]">Select your participation category. Disability disclosure is handled separately in Step 3 and never limits your category choice.</p>' +
        "</div>" +
        "<fieldset>" +
        '<legend class="text-sm font-semibold text-[#211B29] mb-2">Participation Category <span aria-hidden="true" class="text-[#B42332]">*</span></legend>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
        catsHtml +
        "</div>" +
        "</fieldset>" +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">' +
        "<div>" +
        '<label for="reg-occupation" class="block text-sm font-semibold text-[#211B29] mb-1.5">Occupation <span aria-hidden="true" class="text-[#B42332]">*</span></label>' +
        '<select id="reg-occupation" data-draft-field="occupation" class="w-full min-h-[44px] px-3 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]">' +
        occHtml +
        "</select>" +
        "</div>" +
        "<div>" +
        '<label for="reg-org" class="block text-sm font-semibold text-[#211B29] mb-1.5">Organization / Institution</label>' +
        '<input id="reg-org" data-draft-field="organizationName" type="text" value="' +
        esc(d.organizationName) +
        '" placeholder="University, NGO, or Company" class="w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
        "</div>" +
        "<div>" +
        '<label for="reg-designation" class="block text-sm font-semibold text-[#211B29] mb-1.5">Designation / Role</label>' +
        '<input id="reg-designation" data-draft-field="designation" type="text" value="' +
        esc(d.designation) +
        '" placeholder="e.g., B.A. Student, Coordinator" class="w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border border-[#59436D]/30 bg-white text-[#211B29]" />' +
        "</div>" +
        "</div>" +
        "<fieldset>" +
        '<legend class="text-sm font-semibold text-[#211B29] mb-2">Festival Days Attending <span aria-hidden="true" class="text-[#B42332]">*</span></legend>' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        daysHtml +
        "</div>" +
        "</fieldset>";

      // Conditional DELEGATE fields
      if (d.categoryCode === "DELEGATE") {
        var sportsHtml = PARA_SPORTS_OPTIONS.map(function (sport) {
          var selected = d.delegateParaSports.indexOf(sport) >= 0;
          return (
            '<button type="button" aria-pressed="' +
            (selected ? "true" : "false") +
            '" data-toggle-pill="delegateParaSports" data-pill-value="' +
            esc(sport) +
            '" class="min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ' +
            (selected
              ? "bg-[#59436D] text-white border-[#59436D]"
              : "bg-white text-[#432F56] border-[#59436D]/30") +
            '">' +
            esc(sport) +
            "</button>"
          );
        }).join("");

        html +=
          '<div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">' +
          '<h2 class="font-display text-lg font-semibold text-[#432F56]">Delegate Conference Details</h2>' +
          '<div class="grid grid-cols-1 sm:grid-cols-4 gap-4">' +
          "<div>" +
          '<label for="del-age" class="block text-xs font-semibold mb-1">Age</label>' +
          '<input id="del-age" data-draft-field="delegateAge" type="number" min="1" max="120" value="' +
          esc(d.delegateAge) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="del-gender" class="block text-xs font-semibold mb-1">Gender</label>' +
          '<select id="del-gender" data-draft-field="delegateGender" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white">' +
          '<option value="">Select</option>' +
          '<option value="Female" ' +
          (d.delegateGender === "Female" ? "selected" : "") +
          ">Female</option>" +
          '<option value="Male" ' +
          (d.delegateGender === "Male" ? "selected" : "") +
          ">Male</option>" +
          '<option value="Non-binary" ' +
          (d.delegateGender === "Non-binary" ? "selected" : "") +
          ">Non-binary</option>" +
          '<option value="Prefer not to say" ' +
          (d.delegateGender === "Prefer not to say" ? "selected" : "") +
          ">Prefer not to say</option>" +
          "</select>" +
          "</div>" +
          "<div>" +
          '<label for="del-city" class="block text-xs font-semibold mb-1">City &amp; State</label>' +
          '<input id="del-city" data-draft-field="delegateCityState" type="text" value="' +
          esc(d.delegateCityState) +
          '" placeholder="Vadodara, Gujarat" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="del-food" class="block text-xs font-semibold mb-1">Food Preference</label>' +
          '<select id="del-food" data-draft-field="delegateFood" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white">' +
          '<option value="Vegetarian" ' +
          (d.delegateFood === "Vegetarian" ? "selected" : "") +
          ">Vegetarian</option>" +
          '<option value="Jain" ' +
          (d.delegateFood === "Jain" ? "selected" : "") +
          ">Jain</option>" +
          '<option value="Vegan" ' +
          (d.delegateFood === "Vegan" ? "selected" : "") +
          ">Vegan</option>" +
          '<option value="Non-Vegetarian" ' +
          (d.delegateFood === "Non-Vegetarian" ? "selected" : "") +
          ">Non-Vegetarian</option>" +
          "</select>" +
          "</div>" +
          "</div>" +
          "<div>" +
          '<p class="text-xs font-semibold mb-2">Para-Sports Interests (Optional)</p>' +
          '<div class="flex flex-wrap gap-2">' +
          sportsHtml +
          "</div>" +
          "</div>" +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
          '<label class="flex items-center gap-2.5 text-sm font-medium cursor-pointer">' +
          '<input type="checkbox" data-draft-bool="delegateOutstation" ' +
          (d.delegateOutstation ? "checked" : "") +
          ' class="w-4 h-4 accent-[#59436D]" />' +
          "Outstation travel required" +
          "</label>" +
          '<label class="flex items-center gap-2.5 text-sm font-medium cursor-pointer">' +
          '<input type="checkbox" data-draft-bool="delegateAccommodation" ' +
          (d.delegateAccommodation ? "checked" : "") +
          ' class="w-4 h-4 accent-[#59436D]" />' +
          "Lodging / stay guidance needed" +
          "</label>" +
          "</div>" +
          '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">' +
          "<div>" +
          '<label for="del-emg-name" class="block text-xs font-semibold mb-1">Emergency Contact Name</label>' +
          '<input id="del-emg-name" data-draft-field="delegateEmergencyName" type="text" value="' +
          esc(d.delegateEmergencyName) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="del-emg-phone" class="block text-xs font-semibold mb-1">Emergency Contact Phone</label>' +
          '<input id="del-emg-phone" data-draft-field="delegateEmergencyPhone" type="tel" value="' +
          esc(d.delegateEmergencyPhone) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="del-guardian" class="block text-xs font-semibold mb-1">Escort / Guardian Name (Optional)</label>' +
          '<input id="del-guardian" data-draft-field="delegateGuardianName" type="text" value="' +
          esc(d.delegateGuardianName) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "</div>" +
          "</div>";
      }

      // Conditional VOLUNTEER fields
      if (d.categoryCode === "VOLUNTEER") {
        var volRolesHtml = VOLUNTEER_ROLE_OPTIONS.map(function (role) {
          var checked = d.volPreferredRoles.indexOf(role) >= 0;
          return (
            '<label class="flex items-center gap-2 text-sm bg-white p-2.5 rounded-lg border border-[#59436D]/20 cursor-pointer">' +
            '<input type="checkbox" data-toggle-array="volPreferredRoles" value="' +
            esc(role) +
            '" ' +
            (checked ? "checked" : "") +
            ' class="w-4 h-4 accent-[#59436D]" />' +
            esc(role) +
            "</label>"
          );
        }).join("");

        var volLangsHtml = LANGUAGE_OPTIONS.map(function (lang) {
          var selected = d.volLanguages.indexOf(lang) >= 0;
          return (
            '<button type="button" aria-pressed="' +
            (selected ? "true" : "false") +
            '" data-toggle-pill="volLanguages" data-pill-value="' +
            esc(lang) +
            '" class="min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ' +
            (selected
              ? "bg-[#59436D] text-white border-[#59436D]"
              : "bg-white text-[#432F56] border-[#59436D]/30") +
            '">' +
            esc(lang) +
            "</button>"
          );
        }).join("");

        html +=
          '<div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">' +
          '<h2 class="font-display text-lg font-semibold text-[#432F56]">Volunteer Application Details</h2>' +
          '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">' +
          "<div>" +
          '<label for="vol-age" class="block text-xs font-semibold mb-1">Age (16+)</label>' +
          '<input id="vol-age" data-draft-field="volAge" type="number" min="16" max="100" value="' +
          esc(d.volAge) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="vol-city" class="block text-xs font-semibold mb-1">City &amp; State</label>' +
          '<input id="vol-city" data-draft-field="volCityState" type="text" value="' +
          esc(d.volCityState) +
          '" placeholder="Vadodara, Gujarat" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="vol-crr" class="block text-xs font-semibold mb-1">RCI CRR Number (if applicable)</label>' +
          '<input id="vol-crr" data-draft-field="volCrrNumber" type="text" value="' +
          esc(d.volCrrNumber) +
          '" placeholder="Optional" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "</div>" +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">' +
          "<div>" +
          '<label for="vol-inst" class="block text-xs font-semibold mb-1">Institution / College / Department</label>' +
          '<input id="vol-inst" data-draft-field="volInstitution" type="text" value="' +
          esc(d.volInstitution) +
          '" placeholder="e.g., Faculty of Social Work, MSU Baroda" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="vol-gender" class="block text-xs font-semibold mb-1">Gender</label>' +
          '<select id="vol-gender" data-draft-field="volGender" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white">' +
          '<option value="">Select</option>' +
          '<option value="Female" ' +
          (d.volGender === "Female" ? "selected" : "") +
          ">Female</option>" +
          '<option value="Male" ' +
          (d.volGender === "Male" ? "selected" : "") +
          ">Male</option>" +
          '<option value="Non-binary" ' +
          (d.volGender === "Non-binary" ? "selected" : "") +
          ">Non-binary</option>" +
          '<option value="Prefer not to say" ' +
          (d.volGender === "Prefer not to say" ? "selected" : "") +
          ">Prefer not to say</option>" +
          "</select>" +
          "</div>" +
          "</div>" +
          '<label class="flex items-center gap-2.5 text-sm font-medium cursor-pointer">' +
          '<input type="checkbox" data-draft-bool="volMsuAlumni" ' +
          (d.volMsuAlumni ? "checked" : "") +
          ' class="w-4 h-4 accent-[#59436D]" />' +
          "I am a current student or alumni of The Maharaja Sayajirao University of Baroda (MSU)" +
          "</label>" +
          "<div>" +
          '<p class="text-xs font-semibold mb-2">Preferred Volunteer Roles</p>' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">' +
          volRolesHtml +
          "</div>" +
          "</div>" +
          "<div>" +
          '<p class="text-xs font-semibold mb-2">Languages Spoken</p>' +
          '<div class="flex flex-wrap gap-2">' +
          volLangsHtml +
          "</div>" +
          "</div>" +
          "<div>" +
          '<label for="vol-exp" class="block text-xs font-semibold mb-1">Relevant Experience or Motivation</label>' +
          '<textarea id="vol-exp" data-draft-field="volExperience" rows="3" placeholder="Share any prior volunteering, disability inclusion, or event coordination experience..." class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm">' +
          esc(d.volExperience) +
          "</textarea>" +
          "</div>" +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">' +
          "<div>" +
          '<label for="vol-emg-name" class="block text-xs font-semibold mb-1">Emergency Contact Name</label>' +
          '<input id="vol-emg-name" data-draft-field="volEmergencyName" type="text" value="' +
          esc(d.volEmergencyName) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="vol-emg-phone" class="block text-xs font-semibold mb-1">Emergency Contact Phone</label>' +
          '<input id="vol-emg-phone" data-draft-field="volEmergencyPhone" type="tel" value="' +
          esc(d.volEmergencyPhone) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "</div>" +
          "</div>";
      }

      // Conditional SLI fields
      if (d.categoryCode === "SLI") {
        var sliExpHtml = SLI_EXPERTISE_OPTIONS.map(function (area) {
          var selected = d.sliAreasExpertise.indexOf(area) >= 0;
          return (
            '<button type="button" aria-pressed="' +
            (selected ? "true" : "false") +
            '" data-toggle-pill="sliAreasExpertise" data-pill-value="' +
            esc(area) +
            '" class="min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ' +
            (selected
              ? "bg-[#59436D] text-white border-[#59436D]"
              : "bg-white text-[#432F56] border-[#59436D]/30") +
            '">' +
            esc(area) +
            "</button>"
          );
        }).join("");

        var sliPrefHtml = SLI_PREF_OPTIONS.map(function (pref) {
          var selected = d.sliInterpretPrefs.indexOf(pref) >= 0;
          return (
            '<button type="button" aria-pressed="' +
            (selected ? "true" : "false") +
            '" data-toggle-pill="sliInterpretPrefs" data-pill-value="' +
            esc(pref) +
            '" class="min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-semibold border ' +
            (selected
              ? "bg-[#59436D] text-white border-[#59436D]"
              : "bg-white text-[#432F56] border-[#59436D]/30") +
            '">' +
            esc(pref) +
            "</button>"
          );
        }).join("");

        html +=
          '<div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">' +
          '<h2 class="font-display text-lg font-semibold text-[#432F56]">Indian Sign Language Interpreter (SLI) Credentials</h2>' +
          '<label class="flex items-center gap-2.5 text-sm font-medium cursor-pointer">' +
          '<input type="checkbox" data-draft-bool="sliBasedInGujarat" ' +
          (d.sliBasedInGujarat ? "checked" : "") +
          ' class="w-4 h-4 accent-[#59436D]" />' +
          "I am currently based in Gujarat" +
          "</label>" +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">' +
          "<div>" +
          '<label for="sli-city" class="block text-xs font-semibold mb-1">City &amp; State</label>' +
          '<input id="sli-city" data-draft-field="sliCityState" type="text" value="' +
          esc(d.sliCityState) +
          '" placeholder="Ahmedabad / Vadodara, Gujarat" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-crr" class="block text-xs font-semibold mb-1">RCI CRR Number (Optional)</label>' +
          '<input id="sli-crr" data-draft-field="sliCrrNumber" type="text" value="' +
          esc(d.sliCrrNumber) +
          '" placeholder="e.g., A12345" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-qual" class="block text-xs font-semibold mb-1">Qualifications (DISLI / DTISL / Equivalent)</label>' +
          '<input id="sli-qual" data-draft-field="sliQualifications" type="text" value="' +
          esc(d.sliQualifications) +
          '" placeholder="e.g., DISLI (ISLRTC)" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-inst" class="block text-xs font-semibold mb-1">Training Institute</label>' +
          '<input id="sli-inst" data-draft-field="sliTrainingInstitute" type="text" value="' +
          esc(d.sliTrainingInstitute) +
          '" placeholder="ISLRTC / AYJNISHD / Recognized Centre" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-cert" class="block text-xs font-semibold mb-1">Certification Level</label>' +
          '<input id="sli-cert" data-draft-field="sliCertificationLevel" type="text" value="' +
          esc(d.sliCertificationLevel) +
          '" placeholder="Level A / B / C / Certified" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-exp" class="block text-xs font-semibold mb-1">Years of Interpreting Experience</label>' +
          '<input id="sli-exp" data-draft-field="sliYearsExperience" type="number" min="0" max="70" value="' +
          esc(d.sliYearsExperience) +
          '" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "</div>" +
          "<div>" +
          '<p class="text-xs font-semibold mb-2">Areas of Expertise</p>' +
          '<div class="flex flex-wrap gap-2">' +
          sliExpHtml +
          "</div>" +
          "</div>" +
          "<div>" +
          '<p class="text-xs font-semibold mb-2">Interpretation Preferences</p>' +
          '<div class="flex flex-wrap gap-2">' +
          sliPrefHtml +
          "</div>" +
          "</div>" +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">' +
          "<div>" +
          '<label for="sli-video" class="block text-xs font-semibold mb-1">Video Demo URL (Optional)</label>' +
          '<input id="sli-video" data-draft-field="sliVideoDemoUrl" type="url" value="' +
          esc(d.sliVideoDemoUrl) +
          '" placeholder="https://..." class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "<div>" +
          '<label for="sli-notes" class="block text-xs font-semibold mb-1">Honorarium / Travel Support Notes</label>' +
          '<input id="sli-notes" data-draft-field="sliHonorariumNotes" type="text" value="' +
          esc(d.sliHonorariumNotes) +
          '" placeholder="Optional travel or scheduling notes" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white" />' +
          "</div>" +
          "</div>" +
          "</div>";
      }

      html += "</div>";
      return html;
    }

    function renderStep3Html() {
      var d = state.draft;
      var selectedCat = getSelectedCategory();

      var pwdRadios = [
        { value: "YES", label: "Yes" },
        { value: "NO", label: "No" },
        { value: "PREFER_NOT_TO_SAY", label: "Prefer not to say" },
      ]
        .map(function (opt) {
          var checked = d.isPersonWithDisability === opt.value;
          return (
            '<label class="cursor-pointer min-h-[44px] rounded-xl border-2 px-4 py-2.5 flex items-center gap-2.5 text-sm font-semibold ' +
            (checked
              ? "border-[#59436D] bg-[#F6F1F8] text-[#17151C]"
              : "border-[#59436D]/20 text-[#4B4257]") +
            '">' +
            '<input type="radio" name="isPersonWithDisability" data-pwd-radio="' +
            esc(opt.value) +
            '" value="' +
            esc(opt.value) +
            '" ' +
            (checked ? "checked" : "") +
            ' class="w-4 h-4 accent-[#59436D]" />' +
            esc(opt.label) +
            "</label>"
          );
        })
        .join("");

      var accHtml = accommodationTypes
        .map(function (acc) {
          var checked = d.accommodationTypes.indexOf(acc.code) >= 0;
          return (
            '<label class="cursor-pointer min-h-[44px] rounded-xl border p-3 flex items-center gap-2.5 text-sm ' +
            (checked
              ? "border-[#59436D] bg-[#F6F1F8] font-semibold text-[#17151C]"
              : "border-[#59436D]/20 text-[#4B4257]") +
            '">' +
            '<input type="checkbox" data-toggle-array="accommodationTypes" value="' +
            esc(acc.code) +
            '" ' +
            (checked ? "checked" : "") +
            ' class="w-4 h-4 accent-[#59436D]" />' +
            esc(acc.label) +
            "</label>"
          );
        })
        .join("");

      var html =
        '<div class="space-y-6">' +
        "<div>" +
        '<h1 class="font-display text-2xl font-semibold text-[#17151C]">Step 3: Accessibility &amp; Accommodations</h1>' +
        '<p class="mt-1 text-sm text-[#4B4257]">Disclosure is completely optional and independent of your <strong>' +
        esc(selectedCat ? selectedCat.name : d.categoryCode) +
        "</strong> registration.</p>" +
        "</div>" +
        '<div class="rounded-xl bg-[#17151C] text-[#F9F7FC] p-4 flex items-start gap-3 text-sm">' +
        '<svg class="w-5 h-5 text-[#D5B77B] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>' +
        "<p><strong>Privacy Protection:</strong> Information shared in this step is stored in restricted tables accessible only to authorized accessibility coordinators — never displayed on QR passes, reception scanners, or general exports.</p>" +
        "</div>" +
        "<fieldset>" +
        '<legend class="text-sm font-semibold text-[#211B29] mb-2">Are you a person with disability?</legend>' +
        '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">' +
        pwdRadios +
        "</div>" +
        "</fieldset>";

      if (d.isPersonWithDisability === "YES") {
        html +=
          '<div class="rounded-2xl bg-[#F6F1F8] border border-[#9273B5]/40 p-5 space-y-4">' +
          '<div class="flex flex-wrap items-center justify-between gap-2">' +
          '<h2 class="font-semibold text-[#432F56] text-base">RPwD Act 2016 Specified Disabilities (Optional, Multi-Select)</h2>' +
          '<span id="disability-selected-count" class="text-xs font-semibold text-[#59436D]">' +
          esc(d.disabilityCategoryCodes.length) +
          " selected</span>" +
          "</div>" +
          "<div>" +
          '<label for="disability-filter" class="sr-only">Filter the 21 RPwD disabilities</label>' +
          '<input id="disability-filter" type="search" value="' +
          esc(state.disabilitySearch) +
          '" placeholder="Search 21 RPwD disabilities (e.g., Deaf, Low Vision, Autism)..." class="w-full min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white text-sm" />' +
          "</div>" +
          '<div id="disability-list-container" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">' +
          renderDisabilitiesChecklistHtml() +
          "</div>" +
          "<div>" +
          '<label for="disability-self-desc" class="block text-xs font-semibold text-[#211B29] mb-1">Self-Describe or Additional Context (Optional)</label>' +
          '<textarea id="disability-self-desc" data-draft-field="disabilitySelfDescription" rows="2" placeholder="Optional — share how we can best support your participation" class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm">' +
          esc(d.disabilitySelfDescription) +
          "</textarea>" +
          "</div>" +
          "</div>";
      }

      html +=
        '<fieldset class="space-y-3">' +
        '<legend class="text-sm font-semibold text-[#211B29]">Accessibility Accommodations Needed (Optional — open to all participants)</legend>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">' +
        accHtml +
        "</div>" +
        "</fieldset>" +
        "<div>" +
        '<label for="accomm-free-text" class="block text-sm font-semibold text-[#211B29] mb-1.5">Specific Accommodation Notes (Optional)</label>' +
        '<textarea id="accomm-free-text" data-draft-field="accommodationFreeText" rows="3" placeholder="e.g., Front-row aisle seat near ISL interpreter, dietary allergies, companion seat..." class="w-full p-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm">' +
        esc(d.accommodationFreeText) +
        "</textarea>" +
        "</div>" +
        "</div>";

      return html;
    }

    function renderStep4Html() {
      var d = state.draft;
      var cr = state.couponResult;

      return (
        '<div class="space-y-6">' +
        "<div>" +
        '<h1 class="font-display text-2xl font-semibold text-[#17151C]">Step 4: Declarations &amp; Optional Coupon</h1>' +
        '<p class="mt-1 text-sm text-[#4B4257]">Review our safety and DPDP privacy commitments. Photography consent is completely optional and separate from registration.</p>' +
        "</div>" +
        '<div class="space-y-4">' +
        '<label class="flex items-start gap-3 p-4 rounded-xl border-2 border-[#59436D]/25 bg-[#F6F1F8]/60 cursor-pointer">' +
        '<input type="checkbox" data-draft-bool="consentZeroTolerance" ' +
        (d.consentZeroTolerance ? "checked" : "") +
        ' class="mt-1 w-5 h-5 accent-[#59436D] shrink-0" />' +
        '<span class="text-sm text-[#211B29]"><strong>Zero-Tolerance Harassment &amp; Respect Policy (Required):</strong> I acknowledge and agree to uphold Purple Festival Gujarat 2027\'s zero-tolerance policy towards harassment, discrimination, and ableism across all physical and digital festival spaces.</span>' +
        "</label>" +
        '<label class="flex items-start gap-3 p-4 rounded-xl border-2 border-[#59436D]/25 bg-[#F6F1F8]/60 cursor-pointer">' +
        '<input type="checkbox" data-draft-bool="consentDataProcessing" ' +
        (d.consentDataProcessing ? "checked" : "") +
        ' class="mt-1 w-5 h-5 accent-[#59436D] shrink-0" />' +
        '<span class="text-sm text-[#211B29]"><strong>Data Processing Consent — DPDP Act, 2023 (Required):</strong> I consent to the processing of my registration information solely for festival credentialing, attendance management, and requested accessibility coordination in accordance with the <a href="/legal/privacy" target="_blank" class="text-[#59436D] font-semibold underline">Privacy &amp; Data Protection Policy</a>.</span>' +
        "</label>" +
        '<label class="flex items-start gap-3 p-4 rounded-xl border border-[#59436D]/20 bg-white cursor-pointer">' +
        '<input type="checkbox" data-draft-bool="consentMediaPhotography" ' +
        (d.consentMediaPhotography ? "checked" : "") +
        ' class="mt-1 w-5 h-5 accent-[#59436D] shrink-0" />' +
        '<span class="text-sm text-[#4B4257]"><strong class="text-[#211B29]">Photography &amp; Media Consent (Optional):</strong> I grant permission for non-commercial festival photography and video documentation featuring me to be used in Purple Festival Gujarat awareness materials. (Declining this does not affect your registration.)</span>' +
        "</label>" +
        "</div>" +
        '<div class="rounded-2xl bg-[#F6F1F8] border border-[#59436D]/20 p-5 space-y-3">' +
        '<div class="flex items-center gap-2 font-semibold text-sm text-[#432F56]">' +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>' +
        "Institutional / Student Coupon Code (Optional)" +
        "</div>" +
        '<div class="flex flex-col sm:flex-row gap-2.5">' +
        '<label for="reg-coupon" class="sr-only">Coupon Code</label>' +
        '<input id="reg-coupon" data-draft-field="couponCode" type="text" value="' +
        esc(d.couponCode) +
        '" placeholder="Enter coupon code (if issued)" class="flex-1 min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white uppercase font-mono text-sm" />' +
        '<button type="button" id="btn-verify-coupon" ' +
        (state.couponChecking ? "disabled" : "") +
        ' class="min-h-[44px] px-5 py-2 rounded-xl bg-[#17151C] text-[#F9F7FC] text-sm font-semibold hover:bg-[#25212B] disabled:opacity-60">' +
        (state.couponChecking ? "Verifying..." : "Verify Coupon") +
        "</button>" +
        "</div>" +
        (cr
          ? '<p role="status" aria-live="polite" class="text-sm font-medium ' +
            (cr.valid ? "text-[#19734B]" : "text-[#B42332]") +
            '">' +
            esc(cr.message) +
            "</p>"
          : "") +
        "</div>" +
        "</div>"
      );
    }

    function renderStep5Html() {
      var d = state.draft;
      var selectedCat = getSelectedCategory();
      var cr = state.couponResult;
      var feeSummary = "Free (₹0)";
      if (cr && cr.valid) {
        feeSummary = "₹" + cr.netAmountInr + " (after ₹" + cr.discountInr + " discount)";
      } else if (selectedCat && selectedCat.feeAmountInr > 0) {
        feeSummary = "₹" + selectedCat.feeAmountInr;
      }

      var pwdText =
        d.isPersonWithDisability === "YES"
          ? "Yes"
          : d.isPersonWithDisability === "NO"
            ? "No"
            : "Prefer not to say";

      return (
        '<div class="space-y-6">' +
        "<div>" +
        '<h1 class="font-display text-2xl font-semibold text-[#17151C]">Step 5: Review &amp; Submit</h1>' +
        '<p class="mt-1 text-sm text-[#4B4257]">Please confirm your details below before submitting.</p>' +
        "</div>" +
        '<div class="space-y-4">' +
        '<div class="rounded-xl border border-[#59436D]/20 p-4 space-y-2">' +
        '<div class="flex items-center justify-between">' +
        '<h2 class="font-semibold text-sm uppercase tracking-wider text-[#59436D]">Personal Details</h2>' +
        '<button type="button" data-jump-step="1" class="text-xs font-bold text-[#59436D] underline">Edit</button>' +
        "</div>" +
        '<dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">' +
        '<div><dt class="text-xs text-[#4B4257]">Full Name</dt><dd class="font-semibold">' +
        esc(d.fullName) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Email</dt><dd class="font-semibold">' +
        esc(d.email) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Phone</dt><dd class="font-semibold">' +
        esc(d.phone) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Country</dt><dd class="font-semibold">' +
        esc(d.country) +
        "</dd></div>" +
        "</dl>" +
        "</div>" +
        '<div class="rounded-xl border border-[#59436D]/20 p-4 space-y-2">' +
        '<div class="flex items-center justify-between">' +
        '<h2 class="font-semibold text-sm uppercase tracking-wider text-[#59436D]">Category &amp; Role</h2>' +
        '<button type="button" data-jump-step="2" class="text-xs font-bold text-[#59436D] underline">Edit</button>' +
        "</div>" +
        '<dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">' +
        '<div><dt class="text-xs text-[#4B4257]">Category</dt><dd class="font-semibold">' +
        esc(selectedCat ? selectedCat.name : d.categoryCode) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Occupation</dt><dd class="font-semibold">' +
        esc(d.occupation) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Attending Days</dt><dd class="font-semibold">' +
        esc(d.attendingDays.join(", ")) +
        "</dd></div>" +
        '<div><dt class="text-xs text-[#4B4257]">Fee Summary</dt><dd class="font-semibold">' +
        esc(feeSummary) +
        "</dd></div>" +
        "</dl>" +
        "</div>" +
        '<div class="rounded-xl border border-[#59436D]/20 p-4 space-y-2">' +
        '<div class="flex items-center justify-between">' +
        '<h2 class="font-semibold text-sm uppercase tracking-wider text-[#59436D]">Accessibility &amp; Accommodations</h2>' +
        '<button type="button" data-jump-step="3" class="text-xs font-bold text-[#59436D] underline">Edit</button>' +
        "</div>" +
        '<p class="text-sm"><strong>Person with disability:</strong> ' +
        esc(pwdText) +
        "</p>" +
        (d.accommodationTypes.length > 0
          ? '<p class="text-sm"><strong>Requested accommodations:</strong> ' +
            esc(d.accommodationTypes.length) +
            " selected</p>"
          : "") +
        "</div>" +
        "</div>" +
        '<div class="rounded-xl bg-[#E8DFF0]/60 border border-[#9273B5] p-4 flex items-start gap-3 text-sm text-[#432F56]">' +
        '<svg class="w-5 h-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>' +
        "<p>" +
        (selectedCat && selectedCat.requiresApproval
          ? "Your application will be submitted for coordinator review. You will receive your digital QR pass and duty assignment once approved."
          : "Upon submission, your registration will be confirmed immediately and your unique Digital QR Entry Pass will be generated on screen.") +
        "</p>" +
        "</div>" +
        "</div>"
      );
    }

    function renderWizardView() {
      var errorKeys = Object.keys(state.errors);
      var stepsHtml = STEPS.map(function (s) {
        var active = s.num === state.step;
        var completed = s.num < state.step;
        return (
          '<li class="flex flex-col items-center text-center">' +
          '<button type="button" ' +
          (completed ? 'data-jump-step="' + s.num + '"' : "") +
          " " +
          (s.num > state.step ? "disabled" : "") +
          " " +
          (active ? 'aria-current="step"' : "") +
          ' class="w-9 h-9 rounded-full font-bold text-sm flex items-center justify-center transition-colors ' +
          (active
            ? "bg-[#D5B77B] text-[#17151C]"
            : completed
              ? "bg-[#9273B5] text-[#17151C] hover:bg-[#A58DBB]"
              : "bg-[#25212B] text-[#D7CCE2] border border-[#383242]") +
          '">' +
          (completed ? "✓" : s.num) +
          "</button>" +
          '<span class="mt-1.5 text-[11px] sm:text-xs font-medium ' +
          (active ? "text-[#D5B77B] font-bold" : "text-[#D7CCE2]") +
          '">' +
          esc(s.title) +
          "</span>" +
          "</li>"
        );
      }).join("");

      var errorBannerHtml = "";
      if (errorKeys.length > 0 || state.submitError) {
        var listHtml = errorKeys
          .map(function (k) {
            return "<li>" + esc(state.errors[k]) + "</li>";
          })
          .join("");
        errorBannerHtml =
          '<div id="wizard-error-banner" tabindex="-1" role="alert" class="mb-6 rounded-xl bg-[#B42332]/10 border-2 border-[#B42332] p-4 text-[#7A121D]">' +
          '<div class="flex items-center gap-2 font-bold text-sm">' +
          '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
          "Please correct the following before continuing:" +
          "</div>" +
          (state.submitError
            ? '<p class="mt-1.5 text-sm">' + esc(state.submitError) + "</p>"
            : "") +
          (errorKeys.length > 0
            ? '<ul class="mt-2 list-disc list-inside text-sm space-y-1">' +
              listHtml +
              "</ul>"
            : "") +
          "</div>";
      }

      var stepContentHtml = "";
      if (state.step === 1) stepContentHtml = renderStep1Html();
      else if (state.step === 2) stepContentHtml = renderStep2Html();
      else if (state.step === 3) stepContentHtml = renderStep3Html();
      else if (state.step === 4) stepContentHtml = renderStep4Html();
      else if (state.step === 5) stepContentHtml = renderStep5Html();

      var footerNavHtml =
        '<div class="mt-8 pt-6 border-t border-[#59436D]/15 flex items-center justify-between gap-4">' +
        (state.step > 1
          ? '<button type="button" id="btn-wizard-back" class="min-h-[48px] px-5 py-2.5 rounded-xl border-2 border-[#59436D]/30 text-[#432F56] font-semibold text-sm hover:bg-[#F6F1F8] inline-flex items-center gap-2">' +
            '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>' +
            "Back</button>"
          : "<div />") +
        (state.step < 5
          ? '<button type="button" id="btn-wizard-next" class="btn-primary-pfg min-h-[48px] px-6 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm inline-flex items-center gap-2 transition-colors">' +
            "Next Step" +
            '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>' +
            "</button>"
          : '<button type="button" id="btn-wizard-submit" ' +
            (state.submitting ? "disabled" : "") +
            ' class="btn-primary-pfg min-h-[48px] px-7 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm inline-flex items-center gap-2 transition-colors disabled:opacity-60">' +
            '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/></svg>' +
            (state.submitting ? "Submitting..." : "Submit Registration") +
            "</button>") +
        "</div>";

      return (
        '<div class="min-h-screen editorial-mesh-light py-8 px-4 sm:px-6 lg:px-8">' +
        '<div class="max-w-3xl mx-auto space-y-6">' +
        '<div class="flex flex-wrap items-center justify-between gap-3">' +
        '<button type="button" id="btn-change-pathway" class="inline-flex items-center gap-2 text-sm font-semibold text-[#432F56] hover:underline touch-target">' +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>' +
        "Change Pathway" +
        "</button>" +
        '<div class="flex items-center gap-2">' +
        '<button type="button" id="btn-save-later" class="min-h-[44px] px-3.5 py-2 rounded-xl bg-white border border-[#59436D]/25 text-xs sm:text-sm font-semibold text-[#432F56] hover:bg-[#E8DFF0] inline-flex items-center gap-1.5">' +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' +
        "Save &amp; Continue Later" +
        "</button>" +
        '<button type="button" id="btn-clear-draft" aria-label="Clear saved registration draft" class="min-h-[44px] px-3 py-2 rounded-xl border border-[#59436D]/20 text-xs sm:text-sm font-medium text-[#4B4257] hover:bg-white inline-flex items-center gap-1">' +
        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
        "Clear" +
        "</button>" +
        "</div>" +
        "</div>" +
        (state.draftNotice
          ? '<div role="status" aria-live="polite" class="rounded-xl bg-[#E8DFF0] border border-[#9273B5]/40 px-4 py-2.5 text-sm text-[#432F56] flex items-center justify-between">' +
            "<span>" +
            esc(state.draftNotice) +
            "</span>" +
            '<button type="button" id="btn-dismiss-notice" class="text-xs font-bold underline ml-4">Dismiss</button>' +
            "</div>"
          : "") +
        '<nav aria-label="Registration steps" class="surface-card rounded-2xl bg-[#17151C] text-[#F9F7FC] p-4 sm:p-5">' +
        '<ol class="grid grid-cols-5 gap-2">' +
        stepsHtml +
        "</ol>" +
        "</nav>" +
        '<div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 shadow-lg p-6 sm:p-8 text-[#211B29]">' +
        errorBannerHtml +
        stepContentHtml +
        footerNavHtml +
        "</div>" +
        "</div>" +
        "</div>"
      );
    }

    function bindEvents() {
      // Hub pathway selection & double-click
      root.querySelectorAll("[data-pathway-id]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          state.selectedPathwayId = btn.getAttribute("data-pathway-id");
          render();
        });
        btn.addEventListener("dblclick", function () {
          var pid = btn.getAttribute("data-pathway-id");
          var p =
            PATHWAYS.find(function (item) {
              return item.id === pid;
            }) || PATHWAYS[0];
          startPathway(p.categoryCode, p.preselectPwd);
        });
      });

      var btnHubNext = document.getElementById("btn-hub-next");
      if (btnHubNext) {
        btnHubNext.addEventListener("click", function () {
          var p =
            PATHWAYS.find(function (item) {
              return item.id === state.selectedPathwayId;
            }) || PATHWAYS[0];
          startPathway(p.categoryCode, p.preselectPwd);
        });
      }

      // Confirmation view: Register for another role
      var btnRegAnother = document.getElementById("btn-register-another");
      if (btnRegAnother) {
        btnRegAnother.addEventListener("click", function () {
          state.result = null;
          state.showHub = true;
          state.step = 1;
          state.draft = createEmptyDraft(currentUser);
          state.password = "";
          render();
        });
      }

      // Top bar buttons
      var btnChangePathway = document.getElementById("btn-change-pathway");
      if (btnChangePathway) {
        btnChangePathway.addEventListener("click", function () {
          state.showHub = true;
          render();
        });
      }

      var btnSaveLater = document.getElementById("btn-save-later");
      if (btnSaveLater) {
        btnSaveLater.addEventListener("click", function () {
          try {
            window.localStorage.setItem(
              DRAFT_STORAGE_KEY,
              JSON.stringify(state.draft)
            );
            state.draftNotice =
              "Your progress has been saved on this device. You can return anytime to finish.";
          } catch (e) {
            state.draftNotice = "Could not access browser storage to save draft.";
          }
          render();
        });
      }

      var btnClearDraft = document.getElementById("btn-clear-draft");
      if (btnClearDraft) {
        btnClearDraft.addEventListener("click", function () {
          try {
            window.localStorage.removeItem(DRAFT_STORAGE_KEY);
          } catch (e) {
            // ignore
          }
          state.draft = createEmptyDraft(currentUser);
          state.password = "";
          state.couponResult = null;
          state.errors = {};
          state.draftNotice = "Draft cleared.";
          render();
        });
      }

      var btnDismissNotice = document.getElementById("btn-dismiss-notice");
      if (btnDismissNotice) {
        btnDismissNotice.addEventListener("click", function () {
          state.draftNotice = null;
          render();
        });
      }

      // Step navigation
      root.querySelectorAll("[data-jump-step]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var target = parseInt(btn.getAttribute("data-jump-step"), 10);
          if (!isNaN(target) && target >= 1 && target <= 5) {
            state.errors = {};
            state.step = target;
            render();
            window.scrollTo({ top: 0, behavior: "smooth" });
          }
        });
      });

      var btnBack = document.getElementById("btn-wizard-back");
      if (btnBack) {
        btnBack.addEventListener("click", function () {
          state.errors = {};
          state.step = Math.max(1, state.step - 1);
          render();
          window.scrollTo({ top: 0, behavior: "smooth" });
        });
      }

      var btnNext = document.getElementById("btn-wizard-next");
      if (btnNext) {
        btnNext.addEventListener("click", function () {
          if (validateStep(state.step)) {
            state.step = Math.min(5, state.step + 1);
            render();
            window.scrollTo({ top: 0, behavior: "smooth" });
          }
        });
      }

      var btnSubmit = document.getElementById("btn-wizard-submit");
      if (btnSubmit) {
        btnSubmit.addEventListener("click", function () {
          handleSubmit();
        });
      }

      // Text/select/textarea draft fields (in-place update without re-render so focus is preserved)
      root.querySelectorAll("[data-draft-field]").forEach(function (input) {
        var field = input.getAttribute("data-draft-field");
        var handler = function () {
          var val = input.value;
          if (field === "couponCode") {
            val = val.toUpperCase();
            input.value = val;
            state.couponResult = null;
          }
          state.draft[field] = val;
          if (state.errors[field]) {
            delete state.errors[field];
          }
          saveDraftToStorage();
        };
        input.addEventListener("input", handler);
        input.addEventListener("change", handler);
      });

      // Password field (never saved to localStorage)
      var pwInput = document.getElementById("reg-password");
      if (pwInput) {
        pwInput.addEventListener("input", function () {
          state.password = pwInput.value;
          if (state.errors.password) {
            delete state.errors.password;
          }
        });
      }

      // Boolean checkboxes
      root.querySelectorAll("[data-draft-bool]").forEach(function (cb) {
        var field = cb.getAttribute("data-draft-bool");
        cb.addEventListener("change", function () {
          state.draft[field] = Boolean(cb.checked);
          if (state.errors[field]) {
            delete state.errors[field];
          }
          saveDraftToStorage();
        });
      });

      // Category radios
      root.querySelectorAll("[data-category-radio]").forEach(function (radio) {
        radio.addEventListener("change", function () {
          if (radio.checked) {
            state.draft.categoryCode = radio.getAttribute("data-category-radio");
            state.couponResult = null;
            saveDraftToStorage();
            render();
          }
        });
      });

      // PwD disclosure radios
      root.querySelectorAll("[data-pwd-radio]").forEach(function (radio) {
        radio.addEventListener("change", function () {
          if (radio.checked) {
            state.draft.isPersonWithDisability = radio.getAttribute("data-pwd-radio");
            saveDraftToStorage();
            render();
          }
        });
      });

      // Array checkboxes (attendingDays, volPreferredRoles, disabilityCategoryCodes, accommodationTypes)
      function bindArrayCheckboxes(scope) {
        scope.querySelectorAll("[data-toggle-array]").forEach(function (cb) {
          cb.addEventListener("change", function () {
            var key = cb.getAttribute("data-toggle-array");
            var val = cb.value;
            toggleArrayValue(key, val);
            if (key === "disabilityCategoryCodes") {
              var countEl = document.getElementById("disability-selected-count");
              if (countEl) {
                countEl.textContent =
                  state.draft.disabilityCategoryCodes.length + " selected";
              }
              var parentLabel = cb.closest("label");
              if (parentLabel) {
                if (cb.checked) {
                  parentLabel.classList.add(
                    "bg-white",
                    "border-[#59436D]",
                    "font-semibold"
                  );
                  parentLabel.classList.remove("bg-white/70", "border-[#59436D]/20");
                } else {
                  parentLabel.classList.remove(
                    "bg-white",
                    "border-[#59436D]",
                    "font-semibold"
                  );
                  parentLabel.classList.add("bg-white/70", "border-[#59436D]/20");
                }
              }
            } else {
              render();
            }
          });
        });
      }
      bindArrayCheckboxes(root);

      // Pill toggle buttons (delegateParaSports, volLanguages, sliAreasExpertise, sliInterpretPrefs)
      root.querySelectorAll("[data-toggle-pill]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var key = btn.getAttribute("data-toggle-pill");
          var val = btn.getAttribute("data-pill-value");
          toggleArrayValue(key, val);
          render();
        });
      });

      // Disability search filter (updates list without losing input focus)
      var disFilter = document.getElementById("disability-filter");
      if (disFilter) {
        disFilter.addEventListener("input", function () {
          state.disabilitySearch = disFilter.value;
          var listContainer = document.getElementById("disability-list-container");
          if (listContainer) {
            listContainer.innerHTML = renderDisabilitiesChecklistHtml();
            bindArrayCheckboxes(listContainer);
          }
        });
      }

      // Coupon verification button
      var btnVerifyCoupon = document.getElementById("btn-verify-coupon");
      if (btnVerifyCoupon) {
        btnVerifyCoupon.addEventListener("click", function () {
          handleVerifyCoupon();
        });
      }
    }

    function render() {
      if (state.result) {
        root.innerHTML = renderConfirmationView();
      } else if (state.showHub) {
        root.innerHTML = renderHubView();
      } else {
        root.innerHTML = renderWizardView();
      }
      bindEvents();
    }

    render();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWizard);
  } else {
    initWizard();
  }
})();
