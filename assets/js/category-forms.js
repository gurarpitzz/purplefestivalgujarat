/**
 * Purple Festival Gujarat 2027 — Category Forms Controller (ATTENDEE, VOLUNTEER, SLI)
 * Zero-framework Vanilla JS handling conditional sections, draft save/resume, CSRF,
 * Idempotency-Key submission, inline validation feedback, and zero-QR confirmation screens.
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

  function setRadioValue(form, name, val) {
    if (val === null || val === undefined || val === '') return;
    var el = form.querySelector('input[name="' + name + '"][value="' + CSS.escape(String(val)) + '"]');
    if (el) {
      el.checked = true;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function setCheckboxValues(form, name, values) {
    if (!Array.isArray(values)) return;
    values.forEach(function (v) {
      var el = form.querySelector('input[name="' + name + '"][value="' + CSS.escape(String(v)) + '"]');
      if (el) {
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  function setFieldValue(form, name, val) {
    if (val === null || val === undefined) return;
    var el = form.querySelector('[name="' + name + '"]');
    if (el && el.type !== 'radio' && el.type !== 'checkbox') {
      el.value = String(val);
    }
  }

  function init() {
    var boot = window.__PFG_FORM_BOOT__;
    var form = document.getElementById('pfg-category-form');
    if (!boot || !form) return;

    var categoryCode = String(boot.categoryCode || '').toUpperCase();
    var mode = String(boot.mode || 'create');
    var registrationId = boot.registrationId || null;
    var csrfToken = String(boot.csrfToken || '');
    var idempotencyKey = generateIdempotencyKey(categoryCode);

    var errorSummary = document.getElementById('pfg-form-error-summary');
    var draftStatus = document.getElementById('pfg-draft-status');
    var submitBtn = document.getElementById('pfg-btn-submit');
    var saveDraftBtn = document.getElementById('pfg-btn-save-draft');
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

      // Toggle emergency contact required dynamically for Under-18 Attendees
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
    // Build & Hydrate Payload
    // =========================================================================
    function collectPayload() {
      var access = window.PfgSharedAccessibility
        ? window.PfgSharedAccessibility.readPayload(form)
        : {};

      var base = Object.assign({
        categoryCode: categoryCode,
        phone: getFieldValue(form, 'phone'),
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
        base.cityDistrict = getFieldValue(form, 'cityDistrict');
        base.state = getFieldValue(form, 'state');
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

      if (mode === 'edit' && registrationId) {
        base.registrationId = registrationId;
      }
      return base;
    }

    function hydrateDraft(data) {
      if (!data || typeof data !== 'object' || Object.keys(data).length === 0) return;

      setFieldValue(form, 'emergencyContactName', data.emergencyContactName);
      setFieldValue(form, 'emergencyContactRelationship', data.emergencyContactRelationship);
      setFieldValue(form, 'emergencyContactPhone', data.emergencyContactPhone);

      if (window.PfgSharedAccessibility) {
        window.PfgSharedAccessibility.applyPayload(form, data);
      }

      if (categoryCode === 'ATTENDEE') {
        setFieldValue(form, 'organizationName', data.organizationName);
        setFieldValue(form, 'designation', data.designation);
        setCheckboxValues(form, 'attendingDays[]', data.attendingDays);
        setCheckboxValues(form, 'interestedTracks[]', data.interestedTracks);
        var ad = data.attendeeDetails || {};
        setRadioValue(form, 'ageGroup', ad.ageGroup);
        setFieldValue(form, 'gender', ad.gender);
        if (ad.registeringOnBehalf && document.getElementById('pfg-registering-behalf')) {
          document.getElementById('pfg-registering-behalf').checked = true;
        }
        setFieldValue(form, 'guardianName', ad.guardianName);
        setFieldValue(form, 'guardianRelationship', ad.guardianRelationship);
        setFieldValue(form, 'guardianPhone', ad.guardianPhone);
        setCheckboxValues(form, 'selfIdentificationTags[]', ad.selfIdentificationTags);
        if (typeof ad.paraSportsAthlete === 'boolean') {
          setRadioValue(form, 'paraSportsAthlete', ad.paraSportsAthlete ? 'yes' : 'no');
        }
        setCheckboxValues(form, 'paraSportsEvents[]', ad.paraSportsEvents);
        setFieldValue(form, 'paraSportsOther', ad.paraSportsOther);
        if (ad.outstationTravelRequired && document.getElementById('pfg-outstation-toggle')) {
          document.getElementById('pfg-outstation-toggle').checked = true;
        }
        setFieldValue(form, 'arrivalAt', ad.arrivalAt);
        setFieldValue(form, 'departureAt', ad.departureAt);
        setFieldValue(form, 'travelMode', ad.travelMode);
        if (ad.lodgingNeeded && document.getElementById('pfg-lodging-needed')) {
          document.getElementById('pfg-lodging-needed').checked = true;
        }
        if (ad.accessibleRoomNeeded && document.getElementById('pfg-accessible-room')) {
          document.getElementById('pfg-accessible-room').checked = true;
        }
        setFieldValue(form, 'accompanyingCount', ad.accompanyingCount);
        setFieldValue(form, 'foodPreference', ad.foodPreference);
        setFieldValue(form, 'dietaryRequirements', ad.dietaryRequirements);
        syncAttendeeConditionals();
      } else if (categoryCode === 'VOLUNTEER') {
        var vd = data.volunteerDetails || {};
        setFieldValue(form, 'age', vd.age);
        setFieldValue(form, 'gender', vd.gender);
        setRadioValue(form, 'msuAffiliation', vd.msuAffiliation);
        setFieldValue(form, 'msuFacultyDepartment', vd.msuFacultyDepartment);
        setFieldValue(form, 'institutionName', vd.institutionName);
        setFieldValue(form, 'crrNumber', vd.crrNumber);
        setRadioValue(form, 'primaryRole', vd.primaryRole);
        setCheckboxValues(form, 'secondaryRoles[]', vd.secondaryRoles);
        setFieldValue(form, 'anchoringExperience', vd.anchoringExperience);
        setFieldValue(form, 'relevantExperience', vd.relevantExperience);
        setCheckboxValues(form, 'languagesSpoken[]', vd.languagesSpoken);
        setFieldValue(form, 'languagesOther', vd.languagesOther);
        setCheckboxValues(form, 'availabilityShifts[]', vd.availabilityShifts);
        syncVolunteerConditionals();
      } else if (categoryCode === 'SLI') {
        setFieldValue(form, 'cityDistrict', data.cityDistrict);
        setFieldValue(form, 'state', data.state);
        var sd = data.sliDetails || {};
        setFieldValue(form, 'age', sd.age);
        if (sd.basedInGujarat === true || sd.basedInGujarat === 'yes') {
          setRadioValue(form, 'basedInGujarat', 'yes');
        } else if (sd.basedInGujarat === false || sd.basedInGujarat === 'no') {
          setRadioValue(form, 'basedInGujarat', 'no');
        }
        setFieldValue(form, 'professionalAffiliation', sd.professionalAffiliation);
        setRadioValue(form, 'qualificationCategory', sd.qualificationCategory);
        setFieldValue(form, 'qualifications', sd.qualifications);
        setFieldValue(form, 'crrNumber', sd.crrNumber);
        setRadioValue(form, 'experienceBand', sd.experienceBand);
        setFieldValue(form, 'trainingInstitute', sd.trainingInstitute);
        setFieldValue(form, 'videoDemoUrl', sd.videoDemoUrl);
        if (sd.comfortablePublicStage === true || sd.comfortablePublicStage === 'yes') {
          setRadioValue(form, 'comfortablePublicStage', 'yes');
        } else if (sd.comfortablePublicStage === false || sd.comfortablePublicStage === 'no') {
          setRadioValue(form, 'comfortablePublicStage', 'no');
        }
        setCheckboxValues(form, 'availabilityShifts[]', sd.availabilityShifts);
        setCheckboxValues(form, 'areasOfExpertise[]', sd.areasOfExpertise);
        setCheckboxValues(form, 'interpretationPreferences[]', sd.interpretationPreferences);
        setFieldValue(form, 'honorariumOrTravelNotes', sd.honorariumOrTravelNotes);
        syncSliConditionals();
      }
    }

    if (boot.initialDraft && typeof boot.initialDraft === 'object') {
      hydrateDraft(boot.initialDraft);
      if (mode === 'create' && Object.keys(boot.initialDraft).length > 0 && draftStatus) {
        draftStatus.textContent = 'Restored your saved draft.';
      }
    }

    // =========================================================================
    // Save Draft (manual + debounced autosave in create mode)
    // =========================================================================
    function saveDraft(isManual) {
      if (mode !== 'create') return Promise.resolve();
      var payload = collectPayload();
      return fetch('/api/account/drafts', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          categoryCode: categoryCode,
          payload: payload
        })
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (draftStatus && json && json.saved) {
            draftStatus.textContent = isManual
              ? 'Draft saved safely to your account.'
              : 'Draft autosaved.';
          }
        })
        .catch(function () {
          if (isManual && draftStatus) {
            draftStatus.textContent = 'Unable to save draft right now.';
          }
        });
    }

    if (saveDraftBtn) {
      saveDraftBtn.addEventListener('click', function () { saveDraft(true); });
    }

    var draftTimer = null;
    if (mode === 'create') {
      form.addEventListener('change', function () {
        if (draftTimer) clearTimeout(draftTimer);
        draftTimer = setTimeout(function () { saveDraft(false); }, 3500);
      });
    }

    // =========================================================================
    // Validation feedback & Submission
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

    function renderConfirmation(reg, isEdit, message) {
      form.hidden = true;
      if (draftStatus) draftStatus.hidden = true;
      clearErrors();

      var regCode = reg && reg.registrationCode ? reg.registrationCode : '';
      var status = reg && reg.status ? reg.status : (categoryCode === 'ATTENDEE' ? 'CONFIRMED' : 'SUBMITTED');
      var isConfirmed = status === 'CONFIRMED';
      var daysList = Array.isArray(reg && reg.attendingDays) ? reg.attendingDays.join(', ') : '';

      var heading = isEdit
        ? (message || 'Registration Updated Successfully')
        : (isConfirmed ? 'Your Attendee Registration Is Confirmed!' : 'Application Submitted — Pending Coordinator Review');

      var desc = isConfirmed
        ? 'Please save your official Registration ID below. At the Vadodara venue (21–23 January 2027), quote your Registration ID or registered name at the Registration Desk for manual check-in. No QR pass is required.'
        : 'Thank you for applying! Submitting an application does not automatically confirm approval or shift assignment. Our coordinators will review your application and notify you by email and inside your Participant Dashboard.';

      if (confirmationPanel) {
        confirmationPanel.innerHTML =
          '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ' +
            (isConfirmed ? 'bg-emerald-100 text-emerald-900' : 'bg-[#F2E9FE] text-[#5B2A86]') + '">' +
            escHtml(status) +
          '</span>' +
          '<h2 class="mt-3 text-2xl font-bold text-[#202D59]">' + escHtml(heading) + '</h2>' +
          '<p class="mt-2 text-sm leading-relaxed text-[#46537C]">' + escHtml(desc) + '</p>' +
          (regCode
            ? '<div class="mt-5 rounded-xl border-2 border-[#202D59]/15 bg-[#F6F5FE] p-4">' +
                '<p class="text-xs font-bold uppercase tracking-wide text-[#5B2A86]">Official Registration ID</p>' +
                '<p class="mt-1 font-mono text-2xl font-bold tracking-wider text-[#202D59]">' + escHtml(regCode) + '</p>' +
                (daysList ? '<p class="mt-2 text-xs text-[#46537C]">Attending Days: <strong>' + escHtml(daysList) + '</strong></p>' : '') +
              '</div>'
            : '') +
          '<div class="mt-6 flex flex-wrap gap-3">' +
            '<a href="/dashboard" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-6 py-3 text-sm font-bold text-white hover:bg-[#5B2A86]">Go to Participant Dashboard</a>' +
            '<a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">Back to Category Hub</a>' +
          '</div>';
        confirmationPanel.hidden = false;
        confirmationPanel.focus();
        confirmationPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors();

      var payload = collectPayload();
      var origLabel = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = mode === 'edit' ? 'Saving changes...' : 'Submitting...';
      }

      var endpoint = mode === 'edit' ? '/api/account/registrations' : '/api/registrations';
      var httpMethod = mode === 'edit' ? 'PATCH' : 'POST';
      var headers = {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      };
      if (mode === 'create') {
        headers['Idempotency-Key'] = idempotencyKey;
      }

      fetch(endpoint, {
        method: httpMethod,
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (json) {
            return { ok: res.ok, status: res.status, json: json };
          });
        })
        .then(function (result) {
          if (result.ok && result.json && result.json.registration) {
            renderConfirmation(result.json.registration, mode === 'edit', result.json.message || '');
            return;
          }
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = origLabel;
          }
          var errMsg = (result.json && result.json.error) || 'Please review the highlighted fields and try again.';
          var errMap = (result.json && result.json.errors) || null;
          showErrors(errMsg, errMap);
        })
        .catch(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = origLabel;
          }
          showErrors('A network error occurred while submitting. Your progress is safe — please try again.');
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
