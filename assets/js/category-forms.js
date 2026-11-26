/**
 * Purple Festival Gujarat 2027 — Accountless Category Forms Controller (ATTENDEE, VOLUNTEER, SLI)
 * Zero-framework Vanilla JS handling conditional sections, review-before-submit summary,
 * anonymous session CSRF, accessible honeypot, Idempotency-Key submission, inline validation
 * feedback, and accountless confirmation screens (zero QR pass, zero dashboard links).
 */
(function () {
  'use strict';

  function escHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function generateIdempotencyKey(categoryCode) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return 'pfg-' + categoryCode.toLowerCase() + '-' + window.crypto.randomUUID();
    }
    return 'pfg-' + categoryCode.toLowerCase() + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
  }

  function getCheckedValues(form, name) {
    var out = [];
    form.querySelectorAll('input[name="' + name + '"]:checked').forEach(function (el) {
      out.push(el.value);
    });
    return out;
  }

  function getRadioValue(form, name) {
    var el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function getFieldValue(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? (el.value || '').trim() : '';
  }

  function init() {
    var boot = window.__PFG_FORM_BOOT__;
    var form = document.getElementById('pfg-category-form');
    if (!boot || !form) return;

    var categoryCode = String(boot.categoryCode || '').toUpperCase();
    var categoryName = String(boot.categoryName || categoryCode);
    var csrfToken = String(boot.csrfToken || '');
    var idempotencyKey = generateIdempotencyKey(categoryCode);
    var submitting = false;

    var errorSummary = document.getElementById('pfg-form-error-summary');
    var reviewBtn = document.getElementById('pfg-btn-review');
    var submitBtn = document.getElementById('pfg-btn-submit');
    var reviewPanel = document.getElementById('pfg-review-panel');
    var confirmationPanel = document.getElementById('pfg-confirmation-panel');

    // =========================================================================
    // Conditional UI wiring per category
    // =========================================================================
    function syncAttendeeConditionals() {
      var ageGroup = getRadioValue(form, 'ageGroup');
      var behalfCb = document.getElementById('pfg-registering-behalf');
      var needsGuardian = (ageGroup === 'UNDER_18') || !!(behalfCb && behalfCb.checked);
      var guardianPanel = document.getElementById('pfg-guardian-panel');
      var gName = document.getElementById('pfg-guardian-name');
      var gRel = document.getElementById('pfg-guardian-rel');
      if (guardianPanel) guardianPanel.hidden = !needsGuardian;
      if (gName) gName.required = needsGuardian;
      if (gRel) gRel.required = needsGuardian;

      var ecReq = (ageGroup === 'UNDER_18');
      var ecBadge = document.getElementById('pfg-emergency-requirement-badge');
      var ecName = document.getElementById('pfg-ec-name');
      var ecPhone = document.getElementById('pfg-ec-phone');
      if (ecName) ecName.required = ecReq;
      if (ecPhone) ecPhone.required = ecReq;
      if (ecBadge) {
        ecBadge.textContent = ecReq ? 'Required (Under 18)' : 'Optional for adult attendees';
      }
      document.querySelectorAll('[data-ec-star="true"]').forEach(function (el) {
        el.classList.toggle('hidden', !ecReq);
      });

      var psVal = getRadioValue(form, 'paraSportsAthlete');
      var psPanel = document.getElementById('pfg-parasports-panel');
      if (psPanel) psPanel.hidden = (psVal !== 'yes');

      var outCb = document.getElementById('pfg-outstation-toggle');
      var outPanel = document.getElementById('pfg-outstation-panel');
      if (outPanel) outPanel.hidden = !(outCb && outCb.checked);

      var lodgCb = document.getElementById('pfg-lodging-needed');
      var accRoomWrap = document.getElementById('pfg-accessible-room-wrap');
      if (accRoomWrap) accRoomWrap.hidden = !(lodgCb && lodgCb.checked);
    }

    function syncVolunteerConditionals() {
      var msu = getRadioValue(form, 'msuAffiliation');
      var isMsu = (msu === 'CURRENT_STUDENT' || msu === 'ALUMNI');
      var facWrap = document.getElementById('pfg-msu-faculty-wrap');
      var facInput = document.getElementById('pfg-msu-faculty');
      var extWrap = document.getElementById('pfg-ext-inst-wrap');
      if (facWrap) facWrap.hidden = !isMsu;
      if (facInput) facInput.required = isMsu;
      if (extWrap) extWrap.hidden = (msu !== 'EXTERNAL');

      var role = getRadioValue(form, 'primaryRole');
      var isAnchoring = (role === 'Stage Anchoring / Hosting');
      var anchWrap = document.getElementById('pfg-anchoring-exp-wrap');
      var anchInput = document.getElementById('pfg-anchoring-exp');
      if (anchWrap) anchWrap.hidden = !isAnchoring;
      if (anchInput) anchInput.required = isAnchoring;

      var langs = getCheckedValues(form, 'languagesSpoken[]');
      var otherLangWrap = document.getElementById('pfg-lang-other-wrap');
      if (otherLangWrap) otherLangWrap.hidden = (langs.indexOf('Other') === -1);
    }

    function syncSliConditionals() {
      var guj = getRadioValue(form, 'basedInGujarat');
      var outNote = document.getElementById('pfg-sli-outstation-note');
      if (outNote) outNote.hidden = (guj !== 'no');

      var qual = getRadioValue(form, 'qualificationCategory');
      var qualDetailInput = document.getElementById('pfg-sli-qual-detail');
      var qualDetailLabel = document.getElementById('pfg-sli-qual-detail-label');
      var qualDetailStar = document.getElementById('pfg-sli-qual-detail-star');
      var crrBadge = document.getElementById('pfg-sli-crr-badge');

      var isOther = (qual === 'OTHER');
      if (qualDetailInput) qualDetailInput.required = isOther;
      if (qualDetailStar) qualDetailStar.classList.toggle('hidden', !isOther);
      if (qualDetailLabel) {
        qualDetailLabel.textContent = isOther
          ? 'Please specify your qualification or background'
          : 'Specific Diploma / Course (e.g., DISLI, DTISL) or Background (Optional)';
      }
      if (crrBadge) {
        crrBadge.textContent = qual === 'CERTIFIED_ISL'
          ? '(Recommended if issued — optional)'
          : '(Optional — not required for Deaf interpreters, community signers, or trainees)';
      }
    }

    if (categoryCode === 'ATTENDEE') {
      form.addEventListener('change', syncAttendeeConditionals);
      syncAttendeeConditionals();
    } else if (categoryCode === 'VOLUNTEER') {
      form.addEventListener('change', syncVolunteerConditionals);
      syncVolunteerConditionals();
    } else if (categoryCode === 'SLI') {
      form.addEventListener('change', syncSliConditionals);
      syncSliConditionals();
    }

    // =========================================================================
    // Collect Accountless Payload
    // =========================================================================
    function collectPayload() {
      var access = window.PfgSharedAccessibility
        ? window.PfgSharedAccessibility.readPayload(form)
        : {};

      var base = Object.assign({
        categoryCode: categoryCode,
        websiteHp: getFieldValue(form, 'websiteHp'),
        fullName: getFieldValue(form, 'fullName'),
        email: getFieldValue(form, 'email').toLowerCase(),
        phone: getFieldValue(form, 'phone'),
        preferredLanguage: getFieldValue(form, 'preferredLanguage'),
        cityDistrict: getFieldValue(form, 'cityDistrict'),
        state: getFieldValue(form, 'state'),
        country: getFieldValue(form, 'country') || 'India',
        emergencyContactName: getFieldValue(form, 'emergencyContactName'),
        emergencyContactRelationship: getFieldValue(form, 'emergencyContactRelationship'),
        emergencyContactPhone: getFieldValue(form, 'emergencyContactPhone'),
        consentZeroTolerance: !!(form.querySelector('input[name="consentZeroTolerance"]') && form.querySelector('input[name="consentZeroTolerance"]').checked),
        consentDataProcessing: !!(form.querySelector('input[name="consentDataProcessing"]') && form.querySelector('input[name="consentDataProcessing"]').checked),
        consentAccuracy: !!(form.querySelector('input[name="consentAccuracy"]') && form.querySelector('input[name="consentAccuracy"]').checked),
        consentMediaPhotography: !!(form.querySelector('input[name="consentMediaPhotography"]') && form.querySelector('input[name="consentMediaPhotography"]').checked),
        consentFutureUpdates: !!(form.querySelector('input[name="consentFutureUpdates"]') && form.querySelector('input[name="consentFutureUpdates"]').checked)
      }, access);

      if (categoryCode === 'ATTENDEE') {
        base.organizationName = getFieldValue(form, 'organizationName');
        base.designation = getFieldValue(form, 'designation');
        base.attendingDays = getCheckedValues(form, 'attendingDays[]');
        base.interestedTracks = getCheckedValues(form, 'interestedTracks[]');
        base.attendeeDetails = {
          ageGroup: getRadioValue(form, 'ageGroup'),
          gender: getFieldValue(form, 'gender'),
          registeringOnBehalf: !!(document.getElementById('pfg-registering-behalf') && document.getElementById('pfg-registering-behalf').checked),
          guardianName: getFieldValue(form, 'guardianName'),
          guardianRelationship: getFieldValue(form, 'guardianRelationship'),
          guardianPhone: getFieldValue(form, 'guardianPhone'),
          selfIdentificationTags: getCheckedValues(form, 'selfIdentificationTags[]'),
          paraSportsAthlete: getRadioValue(form, 'paraSportsAthlete') === 'yes',
          paraSportsEvents: getCheckedValues(form, 'paraSportsEvents[]'),
          paraSportsOther: getFieldValue(form, 'paraSportsOther'),
          outstationTravelRequired: !!(document.getElementById('pfg-outstation-toggle') && document.getElementById('pfg-outstation-toggle').checked),
          arrivalAt: getFieldValue(form, 'arrivalAt'),
          departureAt: getFieldValue(form, 'departureAt'),
          travelMode: getFieldValue(form, 'travelMode'),
          lodgingNeeded: !!(document.getElementById('pfg-lodging-needed') && document.getElementById('pfg-lodging-needed').checked),
          accessibleRoomNeeded: !!(document.getElementById('pfg-accessible-room') && document.getElementById('pfg-accessible-room').checked),
          accompanyingCount: getFieldValue(form, 'accompanyingCount'),
          foodPreference: getFieldValue(form, 'foodPreference'),
          dietaryRequirements: getFieldValue(form, 'dietaryRequirements')
        };
      } else if (categoryCode === 'VOLUNTEER') {
        var ageStr = getFieldValue(form, 'age');
        base.volunteerDetails = {
          age: ageStr !== '' ? Number(ageStr) : '',
          gender: getFieldValue(form, 'gender'),
          msuAffiliation: getRadioValue(form, 'msuAffiliation'),
          msuFacultyDepartment: getFieldValue(form, 'msuFacultyDepartment'),
          institutionName: getFieldValue(form, 'institutionName'),
          crrNumber: getFieldValue(form, 'crrNumber'),
          primaryRole: getRadioValue(form, 'primaryRole'),
          secondaryRoles: getCheckedValues(form, 'secondaryRoles[]'),
          anchoringExperience: getFieldValue(form, 'anchoringExperience'),
          relevantExperience: getFieldValue(form, 'relevantExperience'),
          languagesSpoken: getCheckedValues(form, 'languagesSpoken[]'),
          languagesOther: getFieldValue(form, 'languagesOther'),
          availabilityShifts: getCheckedValues(form, 'availabilityShifts[]')
        };
      } else if (categoryCode === 'SLI') {
        var sliAgeStr = getFieldValue(form, 'age');
        var gujVal = getRadioValue(form, 'basedInGujarat');
        var stageVal = getRadioValue(form, 'comfortablePublicStage');
        base.sliDetails = {
          age: sliAgeStr !== '' ? Number(sliAgeStr) : '',
          cityDistrict: base.cityDistrict,
          state: base.state,
          basedInGujarat: gujVal === 'yes' ? true : (gujVal === 'no' ? false : null),
          professionalAffiliation: getFieldValue(form, 'professionalAffiliation'),
          qualificationCategory: getRadioValue(form, 'qualificationCategory'),
          qualifications: getFieldValue(form, 'qualifications'),
          crrNumber: getFieldValue(form, 'crrNumber'),
          experienceBand: getRadioValue(form, 'experienceBand'),
          trainingInstitute: getFieldValue(form, 'trainingInstitute'),
          videoDemoUrl: getFieldValue(form, 'videoDemoUrl'),
          comfortablePublicStage: stageVal === 'yes' ? true : (stageVal === 'no' ? false : null),
          availabilityShifts: getCheckedValues(form, 'availabilityShifts[]'),
          areasOfExpertise: getCheckedValues(form, 'areasOfExpertise[]'),
          interpretationPreferences: getCheckedValues(form, 'interpretationPreferences[]'),
          honorariumOrTravelNotes: getFieldValue(form, 'honorariumOrTravelNotes')
        };
      }

      return base;
    }

    // =========================================================================
    // Error & Review Summary Helpers
    // =========================================================================
    function showErrors(mainMessage, errorsMap) {
      if (!errorSummary) return;
      var itemsHtml = '';
      if (errorsMap && typeof errorsMap === 'object') {
        Object.keys(errorsMap).forEach(function (fieldKey) {
          itemsHtml += '<li>' + escHtml(errorsMap[fieldKey]) + '</li>';
        });
      }
      errorSummary.innerHTML =
        '<p class="font-bold">' + escHtml(mainMessage || 'Please review the highlighted fields below.') + '</p>' +
        (itemsHtml ? '<ul class="mt-2 list-disc pl-5 space-y-1">' + itemsHtml + '</ul>' : '');
      errorSummary.hidden = false;
      errorSummary.focus();
      errorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearErrors() {
      if (errorSummary) {
        errorSummary.hidden = true;
        errorSummary.innerHTML = '';
      }
    }

    function renderReviewPanel() {
      if (!reviewPanel) return;
      clearErrors();
      var p = collectPayload();
      var loc = [p.cityDistrict, p.state, p.country].filter(Boolean).join(', ');
      var accommCount = Array.isArray(p.accommodationTypes) ? p.accommodationTypes.length : 0;

      var rows = [
        ['Category', categoryName],
        ['Full Name', p.fullName || 'Not provided'],
        ['Email Address', p.email || 'Not provided'],
        ['Mobile / WhatsApp', p.phone || 'Not provided'],
        ['Location', loc || 'India']
      ];

      if (categoryCode === 'ATTENDEE' && p.attendeeDetails) {
        rows.push(['Age Group', p.attendeeDetails.ageGroup || 'Not selected']);
        rows.push(['Attending Days', (p.attendingDays || []).join(', ') || 'None selected']);
      } else if (categoryCode === 'VOLUNTEER' && p.volunteerDetails) {
        rows.push(['Age', p.volunteerDetails.age ? String(p.volunteerDetails.age) : 'Not provided']);
        rows.push(['Primary Role', p.volunteerDetails.primaryRole || 'Not selected']);
        rows.push(['Selected Shifts', (p.volunteerDetails.availabilityShifts || []).join(', ') || 'None selected']);
      } else if (categoryCode === 'SLI' && p.sliDetails) {
        rows.push(['Age', p.sliDetails.age ? String(p.sliDetails.age) : 'Not provided']);
        rows.push(['Qualification Tier', p.sliDetails.qualificationCategory || 'Not selected']);
        rows.push(['Selected Shifts', (p.sliDetails.availabilityShifts || []).join(', ') || 'None selected']);
      }

      rows.push(['Accessibility Accommodations', accommCount > 0 ? (accommCount + ' option(s) selected (handled confidentially)') : 'None requested']);
      rows.push(['Required Declarations', (p.consentZeroTolerance && p.consentDataProcessing && p.consentAccuracy) ? 'All accepted' : 'Incomplete — please check declarations']);

      var dlHtml = rows.map(function (pair) {
        return '<div class="rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] px-4 py-3">' +
          '<dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">' + escHtml(pair[0]) + '</dt>' +
          '<dd class="mt-1 text-sm font-bold text-[#202D59] break-words">' + escHtml(pair[1]) + '</dd>' +
        '</div>';
      }).join('');

      reviewPanel.innerHTML =
        '<p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Final Check</p>' +
        '<h2 id="pfg-review-heading" class="mt-1 text-2xl font-bold text-[#202D59]">Review Your ' + escHtml(categoryName) + ' Details</h2>' +
        '<p class="mt-1 text-sm text-[#46537C]">Please check your contact email and details carefully before submitting. Your official Registration ID and updates will be sent to this email address.</p>' +
        '<dl class="mt-5 grid gap-3 sm:grid-cols-2">' + dlHtml + '</dl>' +
        '<div class="mt-6 flex flex-wrap items-center justify-between gap-3">' +
          '<button type="button" id="pfg-btn-edit-details" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">Edit Details</button>' +
          '<button type="button" id="pfg-btn-confirm-submit" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-7 py-3 text-base font-bold text-white hover:bg-[#8D3BF2] disabled:opacity-50">Confirm &amp; Submit</button>' +
        '</div>';

      form.hidden = true;
      reviewPanel.hidden = false;
      reviewPanel.focus();
      reviewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

      var editBtn = document.getElementById('pfg-btn-edit-details');
      var confirmBtn = document.getElementById('pfg-btn-confirm-submit');
      if (editBtn) {
        editBtn.addEventListener('click', function () {
          reviewPanel.hidden = true;
          form.hidden = false;
          form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }
      if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
          submitRegistration(confirmBtn);
        });
      }
    }

    if (reviewBtn) {
      reviewBtn.addEventListener('click', renderReviewPanel);
    }

    // =========================================================================
    // Accessible Confirmation Screen (Honest Status, Zero QR, Zero Dashboard)
    // =========================================================================
    function renderConfirmation(reg, payload) {
      form.hidden = true;
      if (reviewPanel) reviewPanel.hidden = true;
      clearErrors();

      var regCode = reg && reg.registrationCode ? reg.registrationCode : '';
      var status = reg && reg.status ? String(reg.status).toUpperCase() : (categoryCode === 'ATTENDEE' ? 'CONFIRMED' : 'SUBMITTED');
      var isConfirmed = status === 'CONFIRMED';
      var catDisplay = (reg && reg.categoryName) ? reg.categoryName : categoryName;
      var daysList = Array.isArray(reg && reg.attendingDays) ? reg.attendingDays.join(', ') : '';
      var applicantEmail = payload && payload.email ? payload.email : '';

      var statusBadgeText = isConfirmed ? 'Confirmed' : 'Submitted — Pending Coordinator Review';
      var heading = isConfirmed
        ? 'Your Attendee Registration Is Confirmed!'
        : 'Application Submitted — Pending Coordinator Review';

      var desc = isConfirmed
        ? 'Thank you for registering for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). Please save your official Registration ID below and quote it at the Registration Desk for check-in. No website account or QR pass is required.'
        : 'Thank you for applying for Purple Festival Gujarat 2027. Submitting an application does not confirm approval or shift assignment. Our coordinators will review your application and communicate the decision and any assignments by email.';

      var emailNotice = applicantEmail
        ? 'A confirmation email has been queued for delivery to ' + applicantEmail + '. Official updates regarding your registration will be sent to this email address.'
        : 'A confirmation email has been queued for delivery to your provided email address. Official updates regarding your registration will be sent via email.';

      if (confirmationPanel) {
        confirmationPanel.innerHTML =
          '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ' +
            (isConfirmed ? 'bg-emerald-100 text-emerald-900' : 'bg-[#F2E9FE] text-[#5B2A86]') + '">' +
            escHtml(statusBadgeText) +
          '</span>' +
          '<h2 class="mt-3 text-2xl font-bold text-[#202D59]">' + escHtml(heading) + '</h2>' +
          '<p class="mt-2 text-sm leading-relaxed text-[#46537C]">' + escHtml(desc) + '</p>' +
          (regCode
            ? '<div class="mt-5 rounded-xl border-2 border-[#202D59]/15 bg-[#F6F5FE] p-4">' +
                '<p class="text-xs font-bold uppercase tracking-wide text-[#5B2A86]">Official Registration ID</p>' +
                '<p data-registration-code="true" class="mt-1 font-mono text-2xl font-bold tracking-wider text-[#202D59]">' + escHtml(regCode) + '</p>' +
                '<p class="mt-2 text-xs text-[#46537C]">Category: <strong>' + escHtml(catDisplay) + '</strong> &middot; Status: <strong>' + escHtml(status) + '</strong></p>' +
                (daysList ? '<p class="mt-1 text-xs text-[#46537C]">Attending Days: <strong>' + escHtml(daysList) + '</strong></p>' : '') +
              '</div>'
            : '') +
          '<div class="mt-4 rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] p-4 text-sm text-[#202D59]">' +
            '<p class="font-semibold">Email Communication</p>' +
            '<p class="mt-1 text-xs leading-relaxed text-[#46537C]">' + escHtml(emailNotice) + '</p>' +
          '</div>' +
          '<div class="mt-4 rounded-xl border border-[#DCE5F7] bg-white p-4 text-xs leading-relaxed text-[#46537C]">' +
            '<p class="font-bold text-[#202D59]">Festival Secretariat Contact</p>' +
            '<p class="mt-1">Need a correction or have accessibility questions? Email <a href="mailto:purplefestivalvadodara@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalvadodara@gmail.com</a> (quote your Registration ID) or call Radha Tandon (+91 99743 83920) / Satish Kapoor (+91 92117 60202).</p>' +
          '</div>' +
          '<div class="mt-6 flex flex-wrap gap-3">' +
            '<a href="/" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-6 py-3 text-sm font-bold text-white hover:bg-[#5B2A86]">Return to Homepage</a>' +
            '<a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">Register Another Category or Person</a>' +
          '</div>';
        confirmationPanel.hidden = false;
        confirmationPanel.focus();
        confirmationPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function submitRegistration(activeBtn) {
      if (submitting) return;
      submitting = true;
      clearErrors();

      var payload = collectPayload();
      var btn = activeBtn || submitBtn;
      var origLabel = btn ? btn.textContent : '';
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
      }

      fetch('/api/registrations', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          'Idempotency-Key': idempotencyKey
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (json) {
            return { ok: res.ok, status: res.status, json: json };
          });
        })
        .then(function (result) {
          if (result.ok && result.json && result.json.registration) {
            renderConfirmation(result.json.registration, payload);
            return;
          }
          submitting = false;
          if (btn) {
            btn.disabled = false;
            btn.textContent = origLabel;
          }
          if (reviewPanel && !reviewPanel.hidden) {
            reviewPanel.hidden = true;
            form.hidden = false;
          }
          var errMsg = (result.json && result.json.error) || 'Please review the highlighted fields and try again.';
          var errMap = (result.json && result.json.errors) || null;
          showErrors(errMsg, errMap);
        })
        .catch(function () {
          submitting = false;
          if (btn) {
            btn.disabled = false;
            btn.textContent = origLabel;
          }
          if (reviewPanel && !reviewPanel.hidden) {
            reviewPanel.hidden = true;
            form.hidden = false;
          }
          showErrors('A network error occurred while submitting. Please try again.');
        });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitRegistration(submitBtn);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
