/**
 * Purple Festival Gujarat 2027 — Shared Accessibility & Accommodations Module JS (CAD-07)
 * Zero-framework Vanilla JS, WCAG 2.2 AA focus-preserving search & conditional disclosure.
 */
(function () {
  'use strict';

  function initSharedAccessibility(root) {
    var container = root || document.querySelector('[data-shared-accessibility="true"]');
    if (!container) return;

    var pwdRadios = container.querySelectorAll('input[name="isPersonWithDisability"]');
    var disclosurePanel = container.querySelector('#pfg-disability-disclosure-panel');
    var searchInput = container.querySelector('#pfg-a11y-disability-search');
    var searchStatus = container.querySelector('#pfg-a11y-search-status');
    var groupsContainer = container.querySelector('#pfg-a11y-disability-groups');
    var escortCheckbox = container.querySelector('#pfg-escort-accompanying');
    var escortWrap = container.querySelector('#pfg-escort-name-wrap');
    var escortInput = container.querySelector('#pfg-escort-name');

    function syncDisabilityDisclosure() {
      var selected = container.querySelector('input[name="isPersonWithDisability"]:checked');
      var isYes = selected && selected.value === 'YES';
      if (disclosurePanel) {
        disclosurePanel.hidden = !isYes;
      }
      pwdRadios.forEach(function (r) {
        if (r.value === 'YES') {
          r.setAttribute('aria-expanded', isYes ? 'true' : 'false');
        }
      });
    }

    pwdRadios.forEach(function (radio) {
      radio.addEventListener('change', syncDisabilityDisclosure);
    });
    syncDisabilityDisclosure();

    // Focus-preserving live search across the 21 RPwD disabilities
    if (searchInput && groupsContainer) {
      var allItems = Array.prototype.slice.call(groupsContainer.querySelectorAll('[data-disability-item="true"]'));
      var allGroups = Array.prototype.slice.call(groupsContainer.querySelectorAll('[data-disability-group]'));
      var totalCount = allItems.length;

      function applyFilter() {
        var q = (searchInput.value || '').trim().toLowerCase();
        var visibleCount = 0;

        allItems.forEach(function (item) {
          var haystack = (item.getAttribute('data-search-text') || '').toLowerCase();
          var matches = q === '' || haystack.indexOf(q) !== -1;
          item.hidden = !matches;
          if (matches) visibleCount++;
        });

        allGroups.forEach(function (grp) {
          var grpItems = grp.querySelectorAll('[data-disability-item="true"]');
          var hasAny = false;
          for (var i = 0; i < grpItems.length; i++) {
            if (!grpItems[i].hidden) {
              hasAny = true;
              break;
            }
          }
          grp.hidden = !hasAny;
        });

        if (searchStatus) {
          if (q === '') {
            searchStatus.textContent = 'Showing all ' + totalCount + ' categories';
          } else {
            searchStatus.textContent = 'Showing ' + visibleCount + ' of ' + totalCount + ' matching categories';
          }
        }
      }

      searchInput.addEventListener('input', applyFilter);
    }

    // Personal Assistant / Escort conditional name input
    function syncEscort() {
      if (!escortCheckbox || !escortWrap) return;
      var checked = !!escortCheckbox.checked;
      escortWrap.hidden = !checked;
      if (!checked && escortInput) {
        escortInput.value = '';
      }
    }
    if (escortCheckbox) {
      escortCheckbox.addEventListener('change', syncEscort);
      syncEscort();
    }

    // Live character counters
    var counterSpans = container.querySelectorAll('[data-char-count-for]');
    counterSpans.forEach(function (span) {
      var targetId = span.getAttribute('data-char-count-for');
      var el = targetId ? container.querySelector('#' + targetId) : null;
      if (!el) return;
      var updateCount = function () {
        span.textContent = String((el.value || '').length);
      };
      el.addEventListener('input', updateCount);
      updateCount();
    });
  }

  function readPayload(formEl) {
    var root = formEl || document;
    var pwdChecked = root.querySelector('input[name="isPersonWithDisability"]:checked');
    var isPwd = pwdChecked ? pwdChecked.value : 'PREFER_NOT_TO_SAY';

    var disCodes = [];
    if (isPwd === 'YES') {
      root.querySelectorAll('input[name="disabilityCategoryCodes[]"]:checked').forEach(function (cb) {
        disCodes.push(cb.value);
      });
    }

    var disSelfEl = root.querySelector('textarea[name="disabilitySelfDescription"]');
    var disSelf = isPwd === 'YES' && disSelfEl ? (disSelfEl.value || '').trim() : '';

    var accTypes = [];
    root.querySelectorAll('input[name="accommodationTypes[]"]:checked').forEach(function (cb) {
      accTypes.push(cb.value);
    });

    var accFreeEl = root.querySelector('textarea[name="accommodationFreeText"]');
    var accFree = accFreeEl ? (accFreeEl.value || '').trim() : '';

    var escortCb = root.querySelector('input[name="escortAccompanying"]');
    var escortAcc = !!(escortCb && escortCb.checked);
    var escortNameEl = root.querySelector('input[name="escortName"]');
    var escortName = escortAcc && escortNameEl ? (escortNameEl.value || '').trim() : '';

    return {
      isPersonWithDisability: isPwd,
      disabilityCategoryCodes: disCodes,
      disabilitySelfDescription: disSelf,
      accommodationTypes: accTypes,
      accommodationFreeText: accFree,
      escortAccompanying: escortAcc,
      escortName: escortName
    };
  }

  function applyPayload(formEl, data) {
    if (!formEl || !data || typeof data !== 'object') return;
    if (data.isPersonWithDisability) {
      var r = formEl.querySelector('input[name="isPersonWithDisability"][value="' + CSS.escape(String(data.isPersonWithDisability)) + '"]');
      if (r) {
        r.checked = true;
        r.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
    if (Array.isArray(data.disabilityCategoryCodes)) {
      data.disabilityCategoryCodes.forEach(function (code) {
        var cb = formEl.querySelector('input[name="disabilityCategoryCodes[]"][value="' + CSS.escape(String(code)) + '"]');
        if (cb) cb.checked = true;
      });
    }
    var disSelfEl = formEl.querySelector('textarea[name="disabilitySelfDescription"]');
    if (disSelfEl && typeof data.disabilitySelfDescription === 'string') {
      disSelfEl.value = data.disabilitySelfDescription;
      disSelfEl.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (Array.isArray(data.accommodationTypes)) {
      data.accommodationTypes.forEach(function (code) {
        var cb = formEl.querySelector('input[name="accommodationTypes[]"][value="' + CSS.escape(String(code)) + '"]');
        if (cb) cb.checked = true;
      });
    }
    var accFreeEl = formEl.querySelector('textarea[name="accommodationFreeText"]');
    if (accFreeEl && typeof data.accommodationFreeText === 'string') {
      accFreeEl.value = data.accommodationFreeText;
      accFreeEl.dispatchEvent(new Event('input', { bubbles: true }));
    }
    var escortCb = formEl.querySelector('input[name="escortAccompanying"]');
    if (escortCb && typeof data.escortAccompanying === 'boolean') {
      escortCb.checked = data.escortAccompanying;
      escortCb.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var escortNameEl = formEl.querySelector('input[name="escortName"]');
    if (escortNameEl && typeof data.escortName === 'string') {
      escortNameEl.value = data.escortName;
    }
  }

  window.PfgSharedAccessibility = {
    init: initSharedAccessibility,
    readPayload: readPayload,
    applyPayload: applyPayload
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initSharedAccessibility(); });
  } else {
    initSharedAccessibility();
  }
})();
