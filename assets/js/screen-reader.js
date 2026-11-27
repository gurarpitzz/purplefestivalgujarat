/**
 * Purple Festival Gujarat 2027 — Built-In TalkBack / Web Speech Screen Reader
 * WCAG 2.2 AA+ Multilingual Speech Engine (English en-IN, Hindi hi-IN, Gujarati gu-IN)
 * Zero-dependency, strict-CSP-safe Vanilla JS IIFE
 */
(function () {
  "use strict";

  var ALLOWED_RATES = [0.8, 1, 1.25, 1.5];
  var HOVER_DEBOUNCE_MS = 300;
  var MAX_CHUNK_LENGTH = 190;
  var SPEAK_DELAY_MS = 25;

  var HAS_SPEECH =
    typeof window !== "undefined" &&
    "speechSynthesis" in window &&
    typeof window.SpeechSynthesisUtterance === "function";

  var READABLE_HOVER_SELECTOR =
    "h1, h2, h3, h4, p, li, a, button, label, figcaption, td, th, dt, dd";
  var NON_INTERACTIVE_CLICK_SELECTOR =
    "p, h1, h2, h3, h4, li, figcaption, td";
  var INTERACTIVE_SELECTOR =
    'a[href], button, input, select, textarea, summary, [role="button"], [role="link"], [role="tab"], [role="switch"], [role="radio"], [role="checkbox"], [data-crowd-toggle]';
  var DRAWER_INTERACTIVE_SELECTOR =
    'button, a, input, select, textarea, label, [role="radio"], [role="switch"], [role="button"]';
  var PAGE_BLOCK_SELECTOR =
    'h1, h2, h3, h4, h5, h6, p, li, figcaption, blockquote, dt, dd, th, td, summary, [data-sr-readable="true"]';
  var SR_CONTROL_SELECTOR =
    "[data-sr-action], [data-sr-rate], [data-sr-quick-toggle]";
  var HIDDEN_ANCESTOR_SELECTOR =
    '#pfg-preloader, [data-preloader], script, style, noscript, template, svg, [hidden], [aria-hidden="true"]';
  var PAGE_EXCLUDED_ANCESTOR_SELECTOR =
    '#pfg-sr-bar, #a11y-preferences-modal, #pfg-preloader, [data-preloader], script, style, noscript, template, svg, [hidden], [aria-hidden="true"]';

  var STRINGS = {
    en: {
      idleStatus: "TalkBack Active — Tap, hover, or Tab any item",
      pausedStatus: "Paused",
      unsupportedStatus: "Speech not supported in this browser",
      readingStatus: function (current, total) {
        return "Reading " + String(current) + " of " + String(total) + "\u2026";
      },
      finishedStatus: "Finished reading page",
      playRead: "Read Page",
      playPause: "Pause",
      playResume: "Resume",
      quickOn: "TalkBack: On",
      quickOff: "TalkBack",
      enabledSpeak:
        "TalkBack screen reader enabled. Tap, hover, or press Tab to hear items, or choose Read Page.",
      noContentSpeak: "No readable page content found.",
      passwordValue: "password hidden",
      roles: {
        heading: "Heading",
        link: "Link",
        button: "Button",
        switchOn: "Switch, on",
        switchOff: "Switch, off",
        checkboxChecked: "Checkbox, checked",
        checkboxUnchecked: "Checkbox, not checked",
        radioChecked: "Radio button, selected",
        radioUnchecked: "Radio button",
        tab: "Tab",
        tabSelected: "Tab, selected",
        textbox: "Text field",
        select: "Dropdown",
        image: "Image",
        expanded: "expanded",
        collapsed: "collapsed",
        pressed: "pressed",
        disabled: "disabled",
        currentPage: "current page",
      },
    },
    hi: {
      idleStatus: "टॉकबैक सक्रिय — सुनने के लिए टैप, होवर या टैब करें",
      pausedStatus: "रोका गया",
      unsupportedStatus: "इस ब्राउज़र में वॉइस रीडिंग समर्थित नहीं है",
      readingStatus: function (current, total) {
        return "पढ़ा जा रहा है " + String(current) + " / " + String(total) + "\u2026";
      },
      finishedStatus: "पेज पढ़ना समाप्त हुआ",
      playRead: "पेज पढ़ें",
      playPause: "रोकें",
      playResume: "जारी रखें",
      quickOn: "टॉकबैक: चालू",
      quickOff: "टॉकबैक",
      enabledSpeak:
        "टॉकबैक स्क्रीन रीडर चालू है। सुनने के लिए किसी भी तत्व पर टैप, होवर या टैब करें।",
      noContentSpeak: "पढ़ने के लिए कोई सामग्री नहीं मिली।",
      passwordValue: "पासवर्ड छिपा हुआ",
      roles: {
        heading: "शीर्षक",
        link: "लिंक",
        button: "बटन",
        switchOn: "स्विच, चालू",
        switchOff: "स्विच, बंद",
        checkboxChecked: "चेकबॉक्स, चुना गया",
        checkboxUnchecked: "चेकबॉक्स, नहीं चुना गया",
        radioChecked: "रेडियो बटन, चुना गया",
        radioUnchecked: "रेडियो बटन",
        tab: "टैब",
        tabSelected: "टैब, चुना गया",
        textbox: "टेक्स्ट फ़ील्ड",
        select: "ड्रॉपडाउन",
        image: "चित्र",
        expanded: "खुला हुआ",
        collapsed: "बंद",
        pressed: "सक्रिय",
        disabled: "निष्क्रिय",
        currentPage: "वर्तमान पेज",
      },
    },
    gu: {
      idleStatus: "ટૉકબૅક સક્રિય — સાંભળવા માટે ટૅપ, હોવર અથવા ટૅબ કરો",
      pausedStatus: "થોભાવેલ",
      unsupportedStatus: "આ બ્રાઉઝરમાં સ્પીચ સપોર્ટ ઉપલબ્ધ નથી",
      readingStatus: function (current, total) {
        return "વાંચી રહ્યું છે " + String(current) + " / " + String(total) + "\u2026";
      },
      finishedStatus: "પેજ વાંચન પૂર્ણ થયું",
      playRead: "પેજ વાંચો",
      playPause: "થોભાવો",
      playResume: "ચાલુ રાખો",
      quickOn: "ટૉકબૅક: ચાલુ",
      quickOff: "ટૉકબૅક",
      enabledSpeak:
        "ટૉકબૅક સ્ક્રીન રીડર ચાલુ છે. સાંભળવા માટે કોઈપણ વસ્તુ પર ટૅપ, હોવર અથવા ટૅબ કરો.",
      noContentSpeak: "વાંચવા માટે કોઈ સામગ્રી મળી નથી.",
      passwordValue: "પાસવર્ડ છુપાયેલ",
      roles: {
        heading: "શીર્ષક",
        link: "લિંક",
        button: "બટન",
        switchOn: "સ્વિચ, ચાલુ",
        switchOff: "સ્વિચ, બંધ",
        checkboxChecked: "ચેકબોક્સ, પસંદ કરેલ",
        checkboxUnchecked: "ચેકબોક્સ, પસંદ કરેલ નથી",
        radioChecked: "રેડિયો બટન, પસંદ કરેલ",
        radioUnchecked: "રેડિયો બટન",
        tab: "ટૅબ",
        tabSelected: "ટૅબ, પસંદ કરેલ",
        textbox: "ટેક્સ્ટ ફીલ્ડ",
        select: "ડ્રોપડાઉન",
        image: "છબી",
        expanded: "વિસ્તૃત",
        collapsed: "સંકુચિત",
        pressed: "સક્રિય",
        disabled: "નિષ્ક્રિય",
        currentPage: "વર્તમાન પેજ",
      },
    },
  };

  // Runtime state
  var enabled = false;
  var speechRate = 1;
  var speaking = false;
  var paused = false;
  var readingPage = false;

  var pageBlocks = [];
  var currentBlockIndex = -1;
  var currentChunks = [];
  var currentChunkIndex = 0;

  var highlightedEl = null;
  var activeUtterance = null;
  var speechSessionId = 0;
  var speakTimerId = 0;
  var resumeCheckTimerId = 0;

  var hoverTimerId = 0;
  var lastHoveredEl = null;
  var lastTouchTime = 0;
  var suppressEnableGreeting = false;
  var initialized = false;

  var cachedVoices = [];

  function normalizeRate(val) {
    var num = Number(val);
    if (ALLOWED_RATES.indexOf(num) !== -1) {
      return num;
    }
    return 1;
  }

  function formatRateBadge(rate) {
    return String(normalizeRate(rate)) + "\u00D7";
  }

  function getLangInfo() {
    var raw =
      (document.documentElement && document.documentElement.lang) || "en-IN";
    var lower = String(raw).toLowerCase().replace("_", "-");
    if (lower.indexOf("gu") === 0) {
      return { tag: "gu-IN", short: "gu" };
    }
    if (lower.indexOf("hi") === 0) {
      return { tag: "hi-IN", short: "hi" };
    }
    return { tag: "en-IN", short: "en" };
  }

  function getStrings() {
    var info = getLangInfo();
    return STRINGS[info.short] || STRINGS.en;
  }

  function loadVoices() {
    if (!HAS_SPEECH) return [];
    try {
      var list = window.speechSynthesis.getVoices();
      if (list && list.length > 0) {
        cachedVoices = Array.prototype.slice.call(list);
      }
    } catch (e) {
      cachedVoices = [];
    }
    return cachedVoices;
  }

  function normalizeVoiceLang(lang) {
    return String(lang || "")
      .toLowerCase()
      .replace(/_/g, "-");
  }

  function findBestInMatches(matches) {
    if (!matches || matches.length === 0) return null;
    // Prefer default or localService voice within the same language match tier
    for (var i = 0; i < matches.length; i++) {
      if (matches[i].default && matches[i].localService) return matches[i];
    }
    for (var j = 0; j < matches.length; j++) {
      if (matches[j].localService) return matches[j];
    }
    return matches[0];
  }

  function filterVoicesExact(voices, target) {
    var result = [];
    for (var i = 0; i < voices.length; i++) {
      if (normalizeVoiceLang(voices[i].lang) === target) {
        result.push(voices[i]);
      }
    }
    return result;
  }

  function filterVoicesPrefix(voices, prefix) {
    var result = [];
    for (var i = 0; i < voices.length; i++) {
      var vl = normalizeVoiceLang(voices[i].lang);
      if (vl === prefix || vl.indexOf(prefix + "-") === 0) {
        result.push(voices[i]);
      }
    }
    return result;
  }

  function pickVoiceForLang(langInfo) {
    var voices = cachedVoices.length > 0 ? cachedVoices : loadVoices();
    if (!voices || voices.length === 0) return null;

    var shortCode = langInfo.short;
    var match = null;

    if (shortCode === "gu") {
      match =
        findBestInMatches(filterVoicesExact(voices, "gu-in")) ||
        findBestInMatches(filterVoicesPrefix(voices, "gu")) ||
        findBestInMatches(filterVoicesExact(voices, "hi-in")) ||
        findBestInMatches(filterVoicesPrefix(voices, "hi"));
    } else if (shortCode === "hi") {
      match =
        findBestInMatches(filterVoicesExact(voices, "hi-in")) ||
        findBestInMatches(filterVoicesPrefix(voices, "hi"));
    } else {
      match =
        findBestInMatches(filterVoicesExact(voices, "en-in")) ||
        findBestInMatches(filterVoicesExact(voices, "en-gb")) ||
        findBestInMatches(filterVoicesExact(voices, "en-us")) ||
        findBestInMatches(filterVoicesPrefix(voices, "en"));
    }

    if (match) return match;

    for (var k = 0; k < voices.length; k++) {
      if (voices[k].default) return voices[k];
    }
    return voices[0] || null;
  }

  function readA11yPrefs() {
    if (
      window.PFG27_A11Y &&
      typeof window.PFG27_A11Y.getPrefs === "function"
    ) {
      var p = window.PFG27_A11Y.getPrefs();
      return {
        screenReader: Boolean(p && p.screenReader),
        speechRate: normalizeRate(p && p.speechRate),
      };
    }
    var attr =
      document.documentElement &&
      document.documentElement.getAttribute("data-screen-reader");
    return {
      screenReader: attr === "true",
      speechRate: speechRate,
    };
  }

  function updateA11yPrefs(patch) {
    if (
      window.PFG27_A11Y &&
      typeof window.PFG27_A11Y.updatePrefs === "function"
    ) {
      window.PFG27_A11Y.updatePrefs(patch);
      return;
    }
    if (Object.prototype.hasOwnProperty.call(patch, "screenReader")) {
      enabled = Boolean(patch.screenReader);
      if (document.documentElement) {
        document.documentElement.setAttribute(
          "data-screen-reader",
          enabled ? "true" : "false"
        );
      }
    }
    if (Object.prototype.hasOwnProperty.call(patch, "speechRate")) {
      speechRate = normalizeRate(patch.speechRate);
    }
    syncUi();
  }

  function clearHighlight() {
    if (highlightedEl) {
      highlightedEl.classList.remove("pfg-sr-speaking");
      highlightedEl = null;
    }
  }

  function setHighlight(el) {
    if (highlightedEl === el) return;
    clearHighlight();
    if (el && el.classList) {
      el.classList.add("pfg-sr-speaking");
      highlightedEl = el;
    }
  }

  function clearTimers() {
    if (speakTimerId) {
      window.clearTimeout(speakTimerId);
      speakTimerId = 0;
    }
    if (resumeCheckTimerId) {
      window.clearTimeout(resumeCheckTimerId);
      resumeCheckTimerId = 0;
    }
  }

  function clearHoverTimer() {
    if (hoverTimerId) {
      window.clearTimeout(hoverTimerId);
      hoverTimerId = 0;
    }
  }

  function cancelSynthesisOnly() {
    speechSessionId += 1;
    clearTimers();
    activeUtterance = null;
    if (HAS_SPEECH) {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {
        // Ignore browser cancel exceptions
      }
    }
  }

  function isReducedMotionActive() {
    if (typeof window.pfgIsReducedMotion === "function") {
      return Boolean(window.pfgIsReducedMotion());
    }
    if (
      window.PFG27_A11Y &&
      typeof window.PFG27_A11Y.isReducedMotion === "function"
    ) {
      return Boolean(window.PFG27_A11Y.isReducedMotion());
    }
    return false;
  }

  function scrollElementIntoView(el) {
    if (!el || typeof el.scrollIntoView !== "function") return;
    try {
      el.scrollIntoView({
        behavior: isReducedMotionActive() ? "instant" : "smooth",
        block: "center",
        inline: "nearest",
      });
    } catch (e) {
      try {
        el.scrollIntoView(true);
      } catch (e2) {
        // Ignore
      }
    }
  }

  function cleanWhitespace(str) {
    return String(str || "")
      .replace(/[\u200B-\u200D\uFEFF]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function isElementHidden(el, excludeUiChrome) {
    if (!el || el.nodeType !== 1) return true;
    if (el.hidden || el.getAttribute("aria-hidden") === "true") return true;
    var ancestorSelector = excludeUiChrome
      ? PAGE_EXCLUDED_ANCESTOR_SELECTOR
      : HIDDEN_ANCESTOR_SELECTOR;
    if (el.closest && el.closest(ancestorSelector)) return true;

    try {
      var style = window.getComputedStyle(el);
      if (
        !style ||
        style.display === "none" ||
        style.visibility === "hidden" ||
        style.visibility === "collapse" ||
        parseFloat(style.opacity) === 0
      ) {
        return true;
      }
    } catch (e) {
      // Ignore style errors
    }

    if (typeof el.getBoundingClientRect === "function") {
      var rect = el.getBoundingClientRect();
      if (rect.width <= 1 && rect.height <= 1) {
        return true;
      }
    }
    return false;
  }

  function collectNodeText(node, skipSrOnly) {
    if (!node) return "";
    if (node.nodeType === 3) {
      return node.nodeValue || "";
    }
    if (node.nodeType !== 1) return "";

    var tag = node.tagName.toUpperCase();
    if (
      tag === "SCRIPT" ||
      tag === "STYLE" ||
      tag === "NOSCRIPT" ||
      tag === "TEMPLATE" ||
      tag === "SVG" ||
      tag === "CANVAS" ||
      tag === "IFRAME"
    ) {
      return "";
    }
    if (node.hidden || node.getAttribute("aria-hidden") === "true") {
      return "";
    }
    if (skipSrOnly && node.classList && node.classList.contains("sr-only")) {
      return "";
    }
    if (tag === "IMG") {
      var imgAlt = node.getAttribute("alt");
      return imgAlt ? " " + imgAlt + " " : "";
    }

    try {
      var st = window.getComputedStyle(node);
      if (st && (st.display === "none" || st.visibility === "hidden")) {
        return "";
      }
    } catch (e) {
      // Ignore
    }

    var parts = [];
    var children = node.childNodes;
    for (var i = 0; i < children.length; i++) {
      var t = collectNodeText(children[i], skipSrOnly);
      if (t) parts.push(t);
    }
    return parts.join(" ");
  }

  function resolveAriaLabelledBy(el) {
    var idsAttr = el.getAttribute("aria-labelledby");
    if (!idsAttr) return "";
    var ids = idsAttr.trim().split(/\s+/);
    var parts = [];
    for (var i = 0; i < ids.length; i++) {
      if (!ids[i]) continue;
      var refEl = document.getElementById(ids[i]);
      if (refEl) {
        var txt = cleanWhitespace(collectNodeText(refEl, false));
        if (txt) parts.push(txt);
      }
    }
    return cleanWhitespace(parts.join(" "));
  }

  function findFormControlLabel(el) {
    if (el.id) {
      var labels = document.querySelectorAll("label[for]");
      for (var i = 0; i < labels.length; i++) {
        if (labels[i].getAttribute("for") === el.id) {
          var lblTxt = cleanWhitespace(collectNodeText(labels[i], true));
          if (lblTxt) return lblTxt;
        }
      }
    }
    if (el.closest) {
      var parentLabel = el.closest("label");
      if (parentLabel) {
        // Extract label text excluding the input itself
        var cloneText = cleanWhitespace(collectNodeText(parentLabel, true));
        if (cloneText) return cloneText;
      }
    }
    var placeholder = cleanWhitespace(el.getAttribute("placeholder") || "");
    if (placeholder) return placeholder;
    var title = cleanWhitespace(el.getAttribute("title") || "");
    if (title) return title;
    return cleanWhitespace(el.getAttribute("name") || "");
  }

  function getAccessibleName(el) {
    if (!el || el.nodeType !== 1) return "";

    var tag = el.tagName.toUpperCase();

    // 1. aria-label
    var ariaLabel = cleanWhitespace(el.getAttribute("aria-label") || "");
    if (ariaLabel) return ariaLabel;

    // 2. aria-labelledby
    var labelledBy = resolveAriaLabelledBy(el);
    if (labelledBy) return labelledBy;

    // 3. Direct image alt
    if (tag === "IMG") {
      return cleanWhitespace(el.getAttribute("alt") || "");
    }

    // 4. Form inputs / selects / textareas
    if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") {
      var inputType =
        tag === "INPUT"
          ? String(el.getAttribute("type") || "text").toLowerCase()
          : "";
      if (
        inputType === "submit" ||
        inputType === "button" ||
        inputType === "reset"
      ) {
        return cleanWhitespace(el.value || el.getAttribute("title") || "");
      }

      var labelText = findFormControlLabel(el);
      var valueText = "";

      if (tag === "SELECT" && el.options && el.selectedIndex >= 0) {
        var opt = el.options[el.selectedIndex];
        valueText = opt ? cleanWhitespace(opt.textContent || "") : "";
      } else if (tag === "TEXTAREA") {
        valueText = cleanWhitespace(el.value || "");
      } else if (
        tag === "INPUT" &&
        inputType !== "checkbox" &&
        inputType !== "radio" &&
        inputType !== "hidden"
      ) {
        if (inputType === "password") {
          valueText = el.value ? getStrings().passwordValue : "";
        } else {
          valueText = cleanWhitespace(el.value || "");
        }
      }

      if (labelText && valueText && labelText !== valueText) {
        return labelText + ", " + valueText;
      }
      return labelText || valueText;
    }

    // 5. Visible text (excluding .sr-only duplicates and SVG text)
    var visibleText = cleanWhitespace(collectNodeText(el, true));
    if (visibleText) return visibleText;

    // Fallback: .sr-only text inside icon-only controls, child img alt, or title
    var withSrOnly = cleanWhitespace(collectNodeText(el, false));
    if (withSrOnly) return withSrOnly;

    var img = el.querySelector ? el.querySelector("img[alt]") : null;
    if (img) {
      var childAlt = cleanWhitespace(img.getAttribute("alt") || "");
      if (childAlt) return childAlt;
    }

    return cleanWhitespace(el.getAttribute("title") || "");
  }

  function getAccessibleSpeechText(el) {
    if (!el || el.nodeType !== 1) return "";
    var name = getAccessibleName(el);
    if (!name) return "";

    var L = getStrings().roles;
    var tag = el.tagName.toUpperCase();
    var role = String(el.getAttribute("role") || "").toLowerCase();
    var inputType =
      tag === "INPUT"
        ? String(el.getAttribute("type") || "text").toLowerCase()
        : "";

    var prefixParts = [];

    if (/^H[1-6]$/.test(tag) || role === "heading") {
      prefixParts.push(L.heading);
    } else if (role === "switch") {
      prefixParts.push(
        el.getAttribute("aria-checked") === "true" ? L.switchOn : L.switchOff
      );
    } else if (role === "radio" || (tag === "INPUT" && inputType === "radio")) {
      var radioChecked =
        tag === "INPUT"
          ? Boolean(el.checked)
          : el.getAttribute("aria-checked") === "true";
      prefixParts.push(radioChecked ? L.radioChecked : L.radioUnchecked);
    } else if (
      role === "checkbox" ||
      (tag === "INPUT" && inputType === "checkbox")
    ) {
      var cbChecked =
        tag === "INPUT"
          ? Boolean(el.checked)
          : el.getAttribute("aria-checked") === "true";
      prefixParts.push(cbChecked ? L.checkboxChecked : L.checkboxUnchecked);
    } else if (role === "tab") {
      prefixParts.push(
        el.getAttribute("aria-selected") === "true" ? L.tabSelected : L.tab
      );
    } else if (tag === "A" || role === "link") {
      prefixParts.push(L.link);
      if (el.getAttribute("aria-current") === "page") {
        prefixParts.push(L.currentPage);
      }
    } else if (
      tag === "BUTTON" ||
      role === "button" ||
      (tag === "INPUT" &&
        (inputType === "submit" ||
          inputType === "button" ||
          inputType === "reset"))
    ) {
      prefixParts.push(L.button);
    } else if (tag === "SELECT" || role === "combobox" || role === "listbox") {
      prefixParts.push(L.select);
    } else if (
      tag === "TEXTAREA" ||
      role === "textbox" ||
      role === "searchbox" ||
      (tag === "INPUT" && inputType !== "hidden")
    ) {
      prefixParts.push(L.textbox);
    } else if (tag === "IMG") {
      prefixParts.push(L.image);
    }

    var expanded = el.getAttribute("aria-expanded");
    if (expanded === "true") {
      prefixParts.push(L.expanded);
    } else if (expanded === "false") {
      prefixParts.push(L.collapsed);
    }

    if (role !== "switch" && el.getAttribute("aria-pressed") === "true") {
      prefixParts.push(L.pressed);
    }

    if (el.disabled || el.getAttribute("aria-disabled") === "true") {
      prefixParts.push(L.disabled);
    }

    if (prefixParts.length > 0) {
      return prefixParts.join(", ") + ": " + name;
    }
    return name;
  }

  /**
   * Splits long text cleanly into <= maxLen chunks on sentence/clause boundaries
   * so Chrome's Web Speech engine never cuts off mid-paragraph.
   */
  function splitIntoChunks(text, maxLen) {
    var limit = maxLen || MAX_CHUNK_LENGTH;
    var cleaned = cleanWhitespace(text);
    if (!cleaned) return [];
    if (cleaned.length <= limit) return [cleaned];

    var chunks = [];
    var remaining = cleaned;

    while (remaining.length > limit) {
      var slice = remaining.slice(0, limit + 1);
      var breakPos = -1;

      // 1. Prefer sentence endings (. ? ! । ॥) followed by whitespace
      var sentenceRegex = /[.?!\u0964\u0965]+(?=\s|$)/g;
      var m;
      while ((m = sentenceRegex.exec(slice)) !== null) {
        var endIdx = m.index + m[0].length;
        if (endIdx >= Math.floor(limit * 0.35) && endIdx <= limit) {
          breakPos = endIdx;
        }
      }

      // 2. Fall back to clause boundaries (; : , — –)
      if (breakPos === -1) {
        var clauseRegex = /[;:,\u2014\u2013]+(?=\s|$)/g;
        while ((m = clauseRegex.exec(slice)) !== null) {
          var cEndIdx = m.index + m[0].length;
          if (cEndIdx >= Math.floor(limit * 0.35) && cEndIdx <= limit) {
            breakPos = cEndIdx;
          }
        }
      }

      // 3. Fall back to last word space
      if (breakPos === -1) {
        var lastSpace = slice.lastIndexOf(" ");
        if (lastSpace > Math.floor(limit * 0.25)) {
          breakPos = lastSpace;
        }
      }

      // 4. Hard slice fallback if single token exceeds limit
      if (breakPos === -1) {
        breakPos = limit;
      }

      var piece = remaining.slice(0, breakPos).trim();
      if (piece) chunks.push(piece);
      remaining = remaining.slice(breakPos).trim();
    }

    if (remaining) {
      chunks.push(remaining);
    }
    return chunks;
  }

  function hasDirectOwnTextOutsideCandidates(el, candidateSet) {
    function walk(node) {
      var children = node.childNodes;
      for (var i = 0; i < children.length; i++) {
        var child = children[i];
        if (child.nodeType === 3) {
          if (cleanWhitespace(child.nodeValue || "")) {
            return true;
          }
        } else if (child.nodeType === 1) {
          var tag = child.tagName.toUpperCase();
          if (
            tag === "SCRIPT" ||
            tag === "STYLE" ||
            tag === "SVG" ||
            child.hidden ||
            child.getAttribute("aria-hidden") === "true" ||
            (child.classList && child.classList.contains("sr-only"))
          ) {
            continue;
          }
          if (candidateSet.indexOf(child) !== -1) {
            continue;
          }
          if (walk(child)) return true;
        }
      }
      return false;
    }
    return walk(el);
  }

  function collectReadableBlocks() {
    var root =
      document.querySelector("main") ||
      document.getElementById("main-content") ||
      document.body;
    if (!root) return [];

    var rawNodes = root.querySelectorAll(PAGE_BLOCK_SELECTOR);
    var visibleCandidates = [];

    for (var i = 0; i < rawNodes.length; i++) {
      var el = rawNodes[i];
      if (isElementHidden(el, true)) continue;
      if (!getAccessibleName(el)) continue;
      visibleCandidates.push(el);
    }

    // Pass 1: Skip container blocks that wrap child candidate blocks and have no own direct text
    var pass1 = [];
    for (var j = 0; j < visibleCandidates.length; j++) {
      var cand = visibleCandidates[j];
      var hasChildCandidate = false;
      for (var k = j + 1; k < visibleCandidates.length; k++) {
        if (cand.contains(visibleCandidates[k])) {
          hasChildCandidate = true;
          break;
        }
      }
      if (
        hasChildCandidate &&
        !hasDirectOwnTextOutsideCandidates(cand, visibleCandidates)
      ) {
        continue;
      }
      pass1.push(cand);
    }

    // Pass 2: Deduplicate any candidate that is inside an already-kept ancestor
    var blocks = [];
    var lastText = "";
    for (var p = 0; p < pass1.length; p++) {
      var item = pass1[p];
      var nestedInsideKept = false;
      for (var q = blocks.length - 1; q >= 0; q--) {
        if (blocks[q].el.contains(item)) {
          nestedInsideKept = true;
          break;
        }
      }
      if (nestedInsideKept) continue;

      var speech = getAccessibleSpeechText(item);
      if (!speech || speech === lastText) continue;
      lastText = speech;
      blocks.push({ el: item, text: speech });
    }

    return blocks;
  }

  function speakChunkList(chunks, startIdx, options) {
    var opts = options || {};
    if (!HAS_SPEECH) {
      speaking = false;
      paused = false;
      readingPage = false;
      syncUi();
      if (typeof window.pfgAnnounce === "function") {
        window.pfgAnnounce(getStrings().unsupportedStatus, "polite");
      }
      return;
    }

    if (!chunks || chunks.length === 0) {
      if (typeof opts.onComplete === "function") opts.onComplete();
      return;
    }

    cancelSynthesisOnly();
    var mySession = speechSessionId;
    currentChunks = chunks;
    currentChunkIndex = startIdx || 0;
    speaking = true;
    paused = false;

    function playNextChunk() {
      if (mySession !== speechSessionId) return;
      if (currentChunkIndex >= currentChunks.length) {
        activeUtterance = null;
        if (typeof opts.onComplete === "function") {
          opts.onComplete();
        } else {
          speaking = false;
          syncUi();
        }
        return;
      }

      var chunkText = currentChunks[currentChunkIndex];
      var langInfo = getLangInfo();
      var voice = pickVoiceForLang(langInfo);
      var utterance = new window.SpeechSynthesisUtterance(chunkText);

      if (voice) {
        utterance.voice = voice;
        utterance.lang = voice.lang || langInfo.tag;
      } else {
        utterance.lang = langInfo.tag;
      }

      utterance.rate = normalizeRate(speechRate);
      utterance.pitch = 1;
      utterance.volume = 1;

      utterance.onend = function () {
        if (mySession !== speechSessionId) return;
        currentChunkIndex += 1;
        playNextChunk();
      };

      utterance.onerror = function (ev) {
        if (mySession !== speechSessionId) return;
        var errCode = ev && ev.error ? String(ev.error) : "";
        if (errCode === "canceled" || errCode === "interrupted") {
          return;
        }
        currentChunkIndex += 1;
        playNextChunk();
      };

      activeUtterance = utterance;
      try {
        window.speechSynthesis.speak(utterance);
      } catch (e) {
        speaking = false;
        readingPage = false;
        syncUi();
      }
    }

    // Small delay after cancel() avoids Chrome dropping the next utterance
    speakTimerId = window.setTimeout(function () {
      speakTimerId = 0;
      if (mySession !== speechSessionId) return;
      playNextChunk();
    }, SPEAK_DELAY_MS);
  }

  function speakText(text, highlightEl) {
    var cleaned = cleanWhitespace(text);
    if (!cleaned) return;

    readingPage = false;
    paused = false;
    pageBlocks = [];
    currentBlockIndex = -1;

    if (highlightEl) {
      setHighlight(highlightEl);
    } else {
      clearHighlight();
    }

    if (!HAS_SPEECH) {
      syncUi();
      if (typeof window.pfgAnnounce === "function") {
        window.pfgAnnounce(getStrings().unsupportedStatus, "polite");
      }
      return;
    }

    var chunks = splitIntoChunks(cleaned, MAX_CHUNK_LENGTH);
    syncUi();
    speakChunkList(chunks, 0, {
      onComplete: function () {
        speaking = false;
        syncUi();
      },
    });
  }

  function speakPageBlock(index) {
    if (!readingPage || pageBlocks.length === 0) {
      stop();
      return;
    }

    if (index < 0) index = 0;
    if (index >= pageBlocks.length) {
      var doneText = getStrings().finishedStatus;
      stop();
      setStatusText(doneText);
      return;
    }

    currentBlockIndex = index;
    var block = pageBlocks[index];
    // Refresh text dynamically in case language or content updated
    var liveText = getAccessibleSpeechText(block.el) || block.text;
    block.text = liveText;

    setHighlight(block.el);
    scrollElementIntoView(block.el);
    syncUi();

    var chunks = splitIntoChunks(liveText, MAX_CHUNK_LENGTH);
    speakChunkList(chunks, 0, {
      onComplete: function () {
        if (!readingPage || paused) return;
        speakPageBlock(currentBlockIndex + 1);
      },
    });
  }

  function readPage() {
    clearHoverTimer();

    if (!enabled) {
      suppressEnableGreeting = true;
      updateA11yPrefs({ screenReader: true });
      suppressEnableGreeting = false;
    }

    if (!HAS_SPEECH) {
      readingPage = false;
      speaking = false;
      paused = false;
      syncUi();
      if (typeof window.pfgAnnounce === "function") {
        window.pfgAnnounce(getStrings().unsupportedStatus, "polite");
      }
      return;
    }

    pageBlocks = collectReadableBlocks();
    if (pageBlocks.length === 0) {
      readingPage = false;
      speakText(getStrings().noContentSpeak, null);
      return;
    }

    readingPage = true;
    paused = false;
    speaking = true;
    currentBlockIndex = 0;
    speakPageBlock(0);
  }

  function pause() {
    if (!HAS_SPEECH || !speaking || paused) return;
    paused = true;
    clearTimers();
    try {
      window.speechSynthesis.pause();
    } catch (e) {
      // Fallback: cancel and keep chunk index for resume
      cancelSynthesisOnly();
    }
    syncUi();
  }

  function resume() {
    if (!HAS_SPEECH || !paused) return;
    paused = false;
    syncUi();

    try {
      if (window.speechSynthesis.paused) {
        window.speechSynthesis.resume();
      }
    } catch (e) {
      // Handled by fallback timer below
    }

    var resumeSession = speechSessionId;
    resumeCheckTimerId = window.setTimeout(function () {
      resumeCheckTimerId = 0;
      if (paused || resumeSession !== speechSessionId) return;
      var synthBusy = false;
      try {
        synthBusy = Boolean(
          window.speechSynthesis.speaking || window.speechSynthesis.pending
        );
      } catch (e) {
        synthBusy = false;
      }
      if (!synthBusy && readingPage && currentBlockIndex >= 0) {
        var block = pageBlocks[currentBlockIndex];
        if (block) {
          var remainingChunks =
            currentChunks && currentChunks.length > currentChunkIndex
              ? currentChunks
              : splitIntoChunks(block.text, MAX_CHUNK_LENGTH);
          var startChunk =
            currentChunkIndex < remainingChunks.length ? currentChunkIndex : 0;
          speakChunkList(remainingChunks, startChunk, {
            onComplete: function () {
              if (!readingPage || paused) return;
              speakPageBlock(currentBlockIndex + 1);
            },
          });
        }
      }
    }, 250);
  }

  function stop() {
    clearHoverTimer();
    cancelSynthesisOnly();
    speaking = false;
    paused = false;
    readingPage = false;
    pageBlocks = [];
    currentBlockIndex = -1;
    currentChunks = [];
    currentChunkIndex = 0;
    clearHighlight();
    syncUi();
  }

  function next() {
    if (!readingPage || pageBlocks.length === 0) {
      readPage();
      return;
    }
    var targetIndex = currentBlockIndex + 1;
    if (targetIndex >= pageBlocks.length) {
      targetIndex = pageBlocks.length - 1;
    }
    paused = false;
    speakPageBlock(targetIndex);
  }

  function prev() {
    if (!readingPage || pageBlocks.length === 0) {
      readPage();
      return;
    }
    var targetIndex = currentBlockIndex - 1;
    if (targetIndex < 0) {
      targetIndex = 0;
    }
    paused = false;
    speakPageBlock(targetIndex);
  }

  function setRate(nextRate) {
    var valid = normalizeRate(nextRate);
    speechRate = valid;
    updateA11yPrefs({ speechRate: valid });
  }

  function cycleRate() {
    var idx = ALLOWED_RATES.indexOf(normalizeRate(speechRate));
    var nextIdx = (idx + 1) % ALLOWED_RATES.length;
    setRate(ALLOWED_RATES[nextIdx]);
  }

  function toggle(forceState) {
    var nextEnabled =
      typeof forceState === "boolean" ? forceState : !enabled;
    updateA11yPrefs({ screenReader: nextEnabled });
    return nextEnabled;
  }

  function togglePlay() {
    if (readingPage && !paused) {
      pause();
    } else if (readingPage && paused) {
      resume();
    } else {
      readPage();
    }
  }

  function setStatusText(text) {
    var statusEl = document.getElementById("pfg-sr-bar-status");
    if (statusEl) {
      statusEl.textContent = text;
    }
  }

  function syncUi() {
    var S = getStrings();
    var showBar = Boolean(enabled || readingPage || speaking);

    // Floating control bar visibility
    var bar = document.getElementById("pfg-sr-bar");
    if (bar) {
      if (showBar) {
        bar.classList.remove("hidden");
        bar.removeAttribute("hidden");
      } else {
        bar.classList.add("hidden");
      }
    }

    // Status message
    var statusMsg = S.idleStatus;
    if (!HAS_SPEECH) {
      statusMsg = S.unsupportedStatus;
    } else if (readingPage && paused) {
      statusMsg = S.pausedStatus;
    } else if (readingPage && pageBlocks.length > 0 && currentBlockIndex >= 0) {
      statusMsg = S.readingStatus(currentBlockIndex + 1, pageBlocks.length);
    }
    setStatusText(statusMsg);

    // Quick toggle buttons (top utility bar)
    var quickBtns = document.querySelectorAll('[data-sr-quick-toggle="true"]');
    for (var q = 0; q < quickBtns.length; q++) {
      var qBtn = quickBtns[q];
      qBtn.setAttribute("aria-pressed", enabled ? "true" : "false");
      qBtn.classList.toggle("is-active", enabled);
    }

    var quickLabels = document.querySelectorAll("[data-sr-quick-label]");
    for (var ql = 0; ql < quickLabels.length; ql++) {
      quickLabels[ql].textContent = enabled ? S.quickOn : S.quickOff;
    }

    // Play / Pause / Resume label & state
    var playLabel = S.playRead;
    if (readingPage && !paused) {
      playLabel = S.playPause;
    } else if (readingPage && paused) {
      playLabel = S.playResume;
    }

    var playLabels = document.querySelectorAll("[data-sr-play-label]");
    for (var pl = 0; pl < playLabels.length; pl++) {
      playLabels[pl].textContent = playLabel;
    }

    var playBtns = document.querySelectorAll('[data-sr-action="toggle-play"]');
    for (var pb = 0; pb < playBtns.length; pb++) {
      playBtns[pb].setAttribute(
        "aria-pressed",
        readingPage && !paused ? "true" : "false"
      );
    }

    // Rate badges (e.g. "1×")
    var badgeText = formatRateBadge(speechRate);
    var rateBadges = document.querySelectorAll("[data-sr-rate-badge]");
    for (var rb = 0; rb < rateBadges.length; rb++) {
      rateBadges[rb].textContent = badgeText;
    }

    // Direct rate selector buttons ([data-sr-rate])
    var rateBtns = document.querySelectorAll("[data-sr-rate]");
    for (var r = 0; r < rateBtns.length; r++) {
      var rVal = Number(rateBtns[r].getAttribute("data-sr-rate"));
      var rActive = rVal === normalizeRate(speechRate);
      rateBtns[r].setAttribute("aria-pressed", rActive ? "true" : "false");
      rateBtns[r].classList.toggle("is-active", rActive);
    }
  }

  function isInsideSrBar(el) {
    return Boolean(el && el.closest && el.closest("#pfg-sr-bar"));
  }

  function isInsideA11yModal(el) {
    return Boolean(el && el.closest && el.closest("#a11y-preferences-modal"));
  }

  function findHoverReadableElement(target) {
    if (!target || target.nodeType !== 1) return null;
    if (isInsideSrBar(target)) return null;
    if (target.closest && target.closest("#pfg-preloader, [data-preloader]")) {
      return null;
    }

    if (isInsideA11yModal(target)) {
      var ctrl = target.closest
        ? target.closest(DRAWER_INTERACTIVE_SELECTOR)
        : null;
      if (!ctrl || !isInsideA11yModal(ctrl)) return null;
      return isElementHidden(ctrl, false) ? null : ctrl;
    }

    var match = target.closest ? target.closest(READABLE_HOVER_SELECTOR) : null;
    if (!match || isInsideSrBar(match) || isInsideA11yModal(match)) return null;
    if (isElementHidden(match, true)) return null;
    return match;
  }

  function onFocusIn(e) {
    if (!enabled) return;
    var target = e.target;
    if (!target || target.nodeType !== 1) return;

    var isSrControl = Boolean(
      isInsideSrBar(target) ||
        (target.closest && target.closest(SR_CONTROL_SELECTOR))
    );

    if (isSrControl) {
      // Preserve active/paused Read Page state when focusing TalkBack controls
      if (readingPage) return;
      var barText = getAccessibleSpeechText(target);
      if (barText) {
        speakText(barText, null);
      }
      return;
    }

    if (target.closest && target.closest("#pfg-preloader, [data-preloader]")) {
      return;
    }

    // Explicit keyboard focus outside TalkBack controls reads the focused item
    var speech = getAccessibleSpeechText(target);
    if (speech) {
      speakText(speech, target);
    }
  }

  function onMouseOver(e) {
    if (!enabled || readingPage) return;
    // Avoid double-triggering hover immediately after a touch tap
    if (Date.now() - lastTouchTime < 600) return;

    var candidate = findHoverReadableElement(e.target);
    if (!candidate) {
      clearHoverTimer();
      lastHoveredEl = null;
      return;
    }
    if (candidate === lastHoveredEl) return;

    clearHoverTimer();
    lastHoveredEl = candidate;

    hoverTimerId = window.setTimeout(function () {
      hoverTimerId = 0;
      if (!enabled || readingPage || lastHoveredEl !== candidate) return;
      var text = getAccessibleSpeechText(candidate);
      if (text) {
        speakText(text, candidate);
      }
    }, HOVER_DEBOUNCE_MS);
  }

  function onMouseOut(e) {
    if (!lastHoveredEl) return;
    var related = e.relatedTarget;
    if (!related || !lastHoveredEl.contains(related)) {
      clearHoverTimer();
      lastHoveredEl = null;
    }
  }

  function onDocumentClick(e) {
    var target = e.target;
    if (!target || target.nodeType !== 1) return;

    // 1. Top bar quick toggle
    var quickToggleBtn = target.closest
      ? target.closest('[data-sr-quick-toggle="true"]')
      : null;
    if (quickToggleBtn) {
      e.preventDefault();
      toggle();
      return;
    }

    // 2. Direct speed buttons ([data-sr-rate])
    var rateBtn = target.closest ? target.closest("[data-sr-rate]") : null;
    if (rateBtn) {
      e.preventDefault();
      var chosenRate = Number(rateBtn.getAttribute("data-sr-rate"));
      setRate(chosenRate);
      return;
    }

    // 3. Action controls ([data-sr-action])
    var actionBtn = target.closest ? target.closest("[data-sr-action]") : null;
    if (actionBtn) {
      e.preventDefault();
      var action = actionBtn.getAttribute("data-sr-action");
      if (action === "read-page") {
        readPage();
      } else if (action === "toggle-play") {
        togglePlay();
      } else if (action === "prev") {
        prev();
      } else if (action === "next") {
        next();
      } else if (action === "stop") {
        stop();
      } else if (action === "cycle-rate") {
        cycleRate();
      } else if (action === "disable") {
        stop();
        updateA11yPrefs({ screenReader: false });
      }
      return;
    }

    // 4. Explore-by-tap on non-interactive text blocks when TalkBack is active
    if (!enabled) return;
    if (isInsideSrBar(target) || isInsideA11yModal(target)) return;
    if (target.closest && target.closest(INTERACTIVE_SELECTOR)) return;

    var textBlock = target.closest
      ? target.closest(NON_INTERACTIVE_CLICK_SELECTOR)
      : null;
    if (!textBlock || isInsideSrBar(textBlock) || isInsideA11yModal(textBlock)) {
      return;
    }
    if (isElementHidden(textBlock)) return;

    var blockSpeech = getAccessibleSpeechText(textBlock);
    if (blockSpeech) {
      speakText(blockSpeech, textBlock);
    }
  }

  function onKeyDown(e) {
    if (!e.altKey || !e.shiftKey || e.ctrlKey || e.metaKey) return;
    var code = e.code || "";
    var key = e.key ? String(e.key).toLowerCase() : "";

    if (code === "KeyR" || key === "r") {
      e.preventDefault();
      togglePlay();
    } else if (code === "KeyS" || key === "s") {
      e.preventDefault();
      stop();
    }
  }

  function onA11yChanged(e) {
    var detail = (e && e.detail) || readA11yPrefs();
    var prevEnabled = enabled;
    var prevRate = speechRate;

    enabled = Boolean(detail && detail.screenReader);
    speechRate = normalizeRate(detail && detail.speechRate);

    if (prevEnabled && !enabled) {
      stop();
    } else if (!prevEnabled && enabled) {
      syncUi();
      if (!suppressEnableGreeting && initialized) {
        if (HAS_SPEECH) {
          speakText(getStrings().enabledSpeak, null);
        } else if (typeof window.pfgAnnounce === "function") {
          window.pfgAnnounce(getStrings().unsupportedStatus, "polite");
        }
      }
    } else if (prevRate !== speechRate) {
      syncUi();
      // Immediately apply updated speed to the active block during continuous Read Page
      if (readingPage && !paused && currentBlockIndex >= 0) {
        speakPageBlock(currentBlockIndex);
      }
    } else {
      syncUi();
    }
  }

  function onLangChanged() {
    loadVoices();
    syncUi();
    if (readingPage && !paused && currentBlockIndex >= 0) {
      // Allow i18n text updates to settle before re-speaking current block in new language
      var blockIdx = currentBlockIndex;
      window.setTimeout(function () {
        if (readingPage && !paused) {
          speakPageBlock(blockIdx);
        }
      }, 120);
    } else if (speaking) {
      stop();
    }
  }

  function onPageUnload() {
    cancelSynthesisOnly();
    clearHoverTimer();
  }

  function init() {
    if (initialized) return;

    if (HAS_SPEECH) {
      loadVoices();
      try {
        if (typeof window.speechSynthesis.addEventListener === "function") {
          window.speechSynthesis.addEventListener("voiceschanged", loadVoices);
        } else {
          window.speechSynthesis.onvoiceschanged = loadVoices;
        }
      } catch (e) {
        // Ignore
      }
    }

    var initial = readA11yPrefs();
    enabled = initial.screenReader;
    speechRate = initial.speechRate;
    syncUi();

    document.addEventListener("click", onDocumentClick);
    document.addEventListener("focusin", onFocusIn, true);
    document.addEventListener("mouseover", onMouseOver, true);
    document.addEventListener("mouseout", onMouseOut, true);
    document.addEventListener("keydown", onKeyDown);
    document.addEventListener(
      "touchstart",
      function () {
        lastTouchTime = Date.now();
      },
      { passive: true }
    );

    window.addEventListener("pfg27:a11y-changed", onA11yChanged);
    window.addEventListener("pfg27:lang-changed", onLangChanged);
    window.addEventListener("beforeunload", onPageUnload);
    window.addEventListener("pagehide", onPageUnload);

    initialized = true;
  }

  // Export clean global Screen Reader API
  window.PFG27_SR = {
    isEnabled: function () {
      return Boolean(enabled);
    },
    isSpeaking: function () {
      return Boolean(speaking);
    },
    isPaused: function () {
      return Boolean(paused);
    },
    toggle: toggle,
    readPage: readPage,
    pause: pause,
    resume: resume,
    stop: stop,
    next: next,
    prev: prev,
    setRate: setRate,
    speakText: speakText,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
