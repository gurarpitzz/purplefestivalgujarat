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
    var prevBtn = document.getElementById('pfg-btn-prev');
    var nextBtn = document.getElementById('pfg-btn-next');
    var nextBtnLabel = document.getElementById('pfg-btn-next-label');
    var cancelBtn = document.getElementById('pfg-btn-cancel');
    var reviewPanel = document.getElementById('pfg-review-panel');
    var confirmationPanel = document.getElementById('pfg-confirmation-panel');

    var waveStepper = document.getElementById('pfg-wave-stepper');
    var waveProgressPath = document.getElementById('pfg-wave-progress-path');
    var waveStepCounter = document.getElementById('pfg-wave-step-counter');
    var waveStepTitle = document.getElementById('pfg-wave-step-title');
    var wizardSteps = Array.prototype.slice.call(form.querySelectorAll('[data-wizard-step]'));
    var totalSteps = wizardSteps.length;
    var currentStep = 1;
    var wavePathLen = 0;

    if (waveProgressPath && typeof waveProgressPath.getTotalLength === 'function') {
      try {
        wavePathLen = waveProgressPath.getTotalLength();
        waveProgressPath.style.strokeDasharray = wavePathLen + ' ' + wavePathLen;
        waveProgressPath.style.strokeDashoffset = String(wavePathLen);
      } catch (e) {
        wavePathLen = 0;
      }
    }

    function getStepTitle(stepNum) {
      var panel = wizardSteps[stepNum - 1];
      return panel ? (panel.getAttribute('data-step-title') || ('Step ' + stepNum)) : ('Step ' + stepNum);
    }

    function clearFieldWrapError(wrap) {
      if (!wrap) return;
      wrap.classList.remove('pfg-has-error');
      wrap.querySelectorAll('[aria-invalid="true"]').forEach(function (ctrl) {
        ctrl.removeAttribute('aria-invalid');
      });
      wrap.querySelectorAll('.pfg-field-error').forEach(function (msg) {
        if (msg.parentNode) msg.parentNode.removeChild(msg);
      });
    }

    function clearInlineFieldErrors() {
      form.querySelectorAll('[data-field-wrap]').forEach(clearFieldWrapError);
    }

    function renderInlineFieldErrors(errs) {
      clearInlineFieldErrors();
      var firstControl = null;
      Object.keys(errs || {}).forEach(function (fieldKey) {
        var wrap = form.querySelector('[data-field-wrap="' + fieldKey + '"]');
        if (!wrap) return;
        wrap.classList.add('pfg-has-error');
        var errId = 'pfg-inline-err-' + fieldKey;
        var p = document.createElement('p');
        p.id = errId;
        p.className = 'pfg-field-error';
        p.setAttribute('role', 'alert');
        p.textContent = errs[fieldKey];
        wrap.appendChild(p);

        var ctrl = wrap.querySelector('input:not([type="hidden"]), select, textarea');
        if (ctrl) {
          ctrl.setAttribute('aria-invalid', 'true');
          var existingDesc = (ctrl.getAttribute('aria-describedby') || '').trim();
          if (existingDesc.indexOf(errId) === -1) {
            ctrl.setAttribute('aria-describedby', (existingDesc ? existingDesc + ' ' : '') + errId);
          }
          if (!firstControl) firstControl = ctrl;
        }
      });
      return firstControl;
    }

    form.addEventListener('input', function (ev) {
      var wrap = ev.target && ev.target.closest ? ev.target.closest('[data-field-wrap]') : null;
      if (wrap && wrap.classList.contains('pfg-has-error')) {
        clearFieldWrapError(wrap);
      }
    });
    form.addEventListener('change', function (ev) {
      var wrap = ev.target && ev.target.closest ? ev.target.closest('[data-field-wrap]') : null;
      if (wrap && wrap.classList.contains('pfg-has-error')) {
        clearFieldWrapError(wrap);
      }
    });

    function validateStep(stepNum) {
      var errs = {};
      if (stepNum === 1) {
        var fn = getFieldValue(form, 'fullName');
        if (!fn || fn.length < 2) errs.fullName = 'Please enter the participant’s full name.';
        var em = getFieldValue(form, 'email');
        if (!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
          errs.email = 'Please enter a valid email address.';
        }
        var ph = getFieldValue(form, 'phone');
        var digits = ph.replace(/\D/g, '');
        if (!ph || digits.length < 7) {
          errs.phone = 'Please enter a valid mobile or WhatsApp number.';
        }
        var ctry = getFieldValue(form, 'country');
        if (!ctry || ctry.length < 2) {
          errs.country = 'Please enter your country.';
        }
      } else if (stepNum === 2) {
        if (categoryCode === 'ATTENDEE') {
          var ag = getRadioValue(form, 'ageGroup');
          if (!ag) errs.ageGroup = 'Please select an age group.';
          var behalf = !!(document.getElementById('pfg-registering-behalf') && document.getElementById('pfg-registering-behalf').checked);
          if (ag === 'UNDER_18' || behalf) {
            if (!getFieldValue(form, 'guardianName')) errs.guardianName = 'Please enter the guardian or representative’s full name.';
            if (!getFieldValue(form, 'guardianRelationship')) errs.guardianRelationship = 'Please select a relationship.';
          }
          if (getCheckedValues(form, 'selfIdentificationTags[]').length === 0) {
            errs.selfIdentificationTags = 'Please select at least one option.';
          }
        } else if (categoryCode === 'SLI') {
          var sliAge = Number(getFieldValue(form, 'age'));
          if (!sliAge || isNaN(sliAge) || sliAge < 18 || sliAge > 85) {
            errs.age = 'Please enter a valid age (minimum 18 years).';
          }
          if (!getFieldValue(form, 'cityDistrict')) errs.cityDistrict = 'Please enter your city or district.';
          if (!getFieldValue(form, 'state')) errs.state = 'Please select your state or union territory.';
          if (!getRadioValue(form, 'basedInGujarat')) errs.basedInGujarat = 'Please indicate whether you are currently based in Gujarat.';
        } else if (categoryCode === 'VOLUNTEER') {
          var volAge = Number(getFieldValue(form, 'age'));
          if (!volAge || isNaN(volAge) || volAge < 16 || volAge > 85) {
            errs.age = 'Please enter a valid age (minimum 16 years).';
          }
          var msuAff = getRadioValue(form, 'msuAffiliation');
          if (!msuAff) {
            errs.msuAffiliation = 'Please select your MSU Baroda affiliation status.';
          } else if ((msuAff === 'CURRENT_STUDENT' || msuAff === 'ALUMNI') && !getFieldValue(form, 'msuFacultyDepartment')) {
            errs.msuFacultyDepartment = 'Please enter your MSU faculty or department name.';
          }
        }
      } else if (stepNum === 3) {
        if (categoryCode === 'ATTENDEE') {
          if (getCheckedValues(form, 'attendingDays[]').length === 0) {
            errs.attendingDays = 'Please select at least one festival day you will attend.';
          }
        } else if (categoryCode === 'SLI') {
          var qCat = getRadioValue(form, 'qualificationCategory');
          if (!qCat) errs.qualificationCategory = 'Please select your Sign Language qualification tier.';
          if (qCat === 'OTHER' && !getFieldValue(form, 'qualifications')) {
            errs.qualifications = 'Please specify your qualification or interpreting background.';
          }
          if (!getRadioValue(form, 'experienceBand')) errs.experienceBand = 'Please select your years of interpretation experience.';
          var vUrl = getFieldValue(form, 'videoDemoUrl');
          if (vUrl && !/^https?:\/\//i.test(vUrl)) {
            errs.videoDemoUrl = 'Video demonstration link must start with http:// or https://.';
          }
          if (!getRadioValue(form, 'comfortablePublicStage')) errs.comfortablePublicStage = 'Please indicate whether you are comfortable interpreting on public stages.';
          if (getCheckedValues(form, 'availabilityShifts[]').length === 0) {
            errs.availabilityShifts = 'Please select at least one availability shift.';
          }
        } else if (categoryCode === 'VOLUNTEER') {
          var pRole = getRadioValue(form, 'primaryRole');
          if (!pRole) errs.primaryRole = 'Please select a primary role.';
          if (pRole === 'Stage Anchoring / Hosting' && getFieldValue(form, 'anchoringExperience').length < 10) {
            errs.anchoringExperience = 'Please describe your public speaking or stage hosting experience (at least 10 characters).';
          }
          if (getCheckedValues(form, 'languagesSpoken[]').length === 0) {
            errs.languagesSpoken = 'Please select at least one language you can communicate in.';
          }
          if (getCheckedValues(form, 'availabilityShifts[]').length === 0) {
            errs.availabilityShifts = 'Please select at least one shift in the availability grid.';
          }
        }
      }
      return errs;
    }

    function goToStep(stepNum, options) {
      if (totalSteps === 0) return;
      var opts = options || {};
      var target = Math.max(1, Math.min(totalSteps, Number(stepNum) || 1));
      currentStep = target;

      wizardSteps.forEach(function (panel) {
        var n = Number(panel.getAttribute('data-wizard-step'));
        panel.hidden = (n !== currentStep);
      });

      if (waveStepper) {
        var nodeItems = waveStepper.querySelectorAll('[data-wave-node-item]');
        nodeItems.forEach(function (li) {
          var n = Number(li.getAttribute('data-wave-node-item'));
          li.classList.toggle('is-active', n === currentStep);
          li.classList.toggle('is-complete', n < currentStep);
          var btn = li.querySelector('[data-wave-step-btn]');
          if (btn) {
            btn.setAttribute('aria-current', n === currentStep ? 'step' : 'false');
          }
        });
      }

      if (waveProgressPath && wavePathLen > 0) {
        var fraction = totalSteps > 1 ? (currentStep - 1) / (totalSteps - 1) : 1;
        waveProgressPath.style.strokeDashoffset = String(Math.max(0, wavePathLen * (1 - fraction)));
      }

      if (waveStepCounter) {
        waveStepCounter.textContent = 'Step ' + currentStep + ' of ' + totalSteps + ': ' + getStepTitle(currentStep);
      }
      if (waveStepTitle) {
        waveStepTitle.textContent = getStepTitle(currentStep);
      }

      if (prevBtn) prevBtn.hidden = (currentStep <= 1);
      if (cancelBtn) cancelBtn.hidden = (currentStep > 1);
      if (nextBtn) {
        nextBtn.hidden = (currentStep >= totalSteps);
        if (nextBtnLabel && currentStep < totalSteps) {
          nextBtnLabel.textContent = 'Next: ' + getStepTitle(currentStep + 1);
        }
      }
      if (reviewBtn) reviewBtn.hidden = (currentStep < totalSteps);
      if (submitBtn) submitBtn.hidden = (currentStep < totalSteps);

      if (!opts.keepErrors) {
        clearErrors();
        clearInlineFieldErrors();
      }

      if (opts.scroll !== false) {
        var scrollTarget = form.closest('.pfg-reg-card') || waveStepper || form;
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        scrollTarget.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
      }

      if (opts.focusStep) {
        var activePanel = wizardSteps[currentStep - 1];
        if (activePanel) {
          var firstHeading = activePanel.querySelector('h2');
          if (firstHeading) {
            if (!firstHeading.hasAttribute('tabindex')) firstHeading.setAttribute('tabindex', '-1');
            firstHeading.focus({ preventScroll: true });
          }
        }
      }
    }

    function attemptStepAdvance(targetStep) {
      if (targetStep <= currentStep) {
        goToStep(targetStep, { focusStep: true });
        return;
      }
      // Validate each step sequentially up to targetStep - 1
      for (var s = currentStep; s < targetStep; s += 1) {
        var errs = validateStep(s);
        if (Object.keys(errs).length > 0) {
          if (s !== currentStep) {
            goToStep(s, { keepErrors: true, scroll: false });
          }
          var firstInvalid = renderInlineFieldErrors(errs);
          showErrors('Please check the highlighted fields in ' + getStepTitle(s) + ' before continuing.', errs);
          if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus({ preventScroll: false });
          }
          return;
        }
      }
      goToStep(targetStep, { focusStep: true });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        attemptStepAdvance(currentStep + 1);
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goToStep(currentStep - 1, { focusStep: true });
      });
    }
    if (waveStepper) {
      waveStepper.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-wave-step-btn]') : null;
        if (!btn) return;
        var target = Number(btn.getAttribute('data-wave-step-btn'));
        if (target >= 1 && target <= totalSteps) {
          attemptStepAdvance(target);
        }
      });
    }

    if (totalSteps > 0) {
      goToStep(1, { scroll: false, focusStep: false });
    }

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
      if (waveStepper) waveStepper.hidden = true;
      reviewPanel.hidden = false;
      reviewPanel.focus();
      reviewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

      var editBtn = document.getElementById('pfg-btn-edit-details');
      var confirmBtn = document.getElementById('pfg-btn-confirm-submit');
      if (editBtn) {
        editBtn.addEventListener('click', function () {
          reviewPanel.hidden = true;
          form.hidden = false;
          if (waveStepper) waveStepper.hidden = false;
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
      if (waveStepper) waveStepper.hidden = true;
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
            if (waveStepper) waveStepper.hidden = false;
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
            if (waveStepper) waveStepper.hidden = false;
          }
          showErrors('A network error occurred while submitting. Please try again.');
        });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (totalSteps > 0 && currentStep < totalSteps) {
        attemptStepAdvance(currentStep + 1);
        return;
      }
      submitRegistration(submitBtn);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
