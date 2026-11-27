/**
 * Purple Festival Gujarat 2027 — Multilingual Translation Engine (EN / हिन्दी / ગુજરાતી)
 * Zero-dependency, CSP-compliant, lazy-loading, reversible WeakMap DOM translator.
 */
(function () {
  "use strict";

  var STORAGE_KEY = "pfg27_lang";
  var SUPPORTED = {
    en: {
      code: "en",
      htmlLang: "en-IN",
      scriptUrl: null,
      fontUrl: null,
      fontId: null,
      announceMsg: "Website language changed to English.",
    },
    hi: {
      code: "hi",
      htmlLang: "hi-IN",
      scriptUrl: "/assets/js/i18n-hi.js?v=1",
      fontUrl:
        "https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap",
      fontId: "pfg-font-noto-devanagari",
      announceMsg: "वेबसाइट की भाषा हिन्दी में बदल दी गई है।",
    },
    gu: {
      code: "gu",
      htmlLang: "gu-IN",
      scriptUrl: "/assets/js/i18n-gu.js?v=1",
      fontUrl:
        "https://fonts.googleapis.com/css2?family=Noto+Sans+Gujarati:wght@400;500;600;700;800&display=swap",
      fontId: "pfg-font-noto-gujarati",
      announceMsg: "વેબસાઇટની ભાષા ગુજરાતીમાં બદલાઈ ગઈ છે.",
    },
  };

  var TRANSLATABLE_ATTRS = [
    { attr: "placeholder", i18nAttr: "data-i18n-placeholder" },
    { attr: "aria-label", i18nAttr: "data-i18n-aria" },
    { attr: "title", i18nAttr: "data-i18n-title" },
  ];

  var SKIP_ATTR_TAGS = {
    SCRIPT: 1,
    STYLE: 1,
    NOSCRIPT: 1,
    SVG: 1,
    CODE: 1,
    PRE: 1,
    TEMPLATE: 1,
  };

  var SKIP_TEXT_TAGS = {
    SCRIPT: 1,
    STYLE: 1,
    NOSCRIPT: 1,
    SVG: 1,
    CODE: 1,
    PRE: 1,
    TEXTAREA: 1,
    INPUT: 1,
    TEMPLATE: 1,
  };

  // Built-in fallback phrases for core TalkBack / Language chrome (external dicts take precedence)
  var CORE_FALLBACKS = {
    hi: {
      "TalkBack: Off": "टॉकबैक: बंद",
      "TalkBack: On": "टॉकबैक: चालू",
      "Toggle TalkBack Screen Reader": "टॉकबैक स्क्रीन रीडर चालू या बंद करें",
      "Screen Reader / TalkBack": "स्क्रीन रीडर / टॉकबैक",
      "Speaks focused, clicked, or hovered content and reads the entire page aloud.":
        "फ़ोकस, क्लिक या होवर की गई सामग्री को बोलकर सुनाता है और पूरा पेज पढ़ता है।",
      "Read Entire Page": "पूरा पेज पढ़ें",
      "Read Page": "पेज पढ़ें",
      "Pause": "रोकें",
      "Resume": "जारी रखें",
      "Stop": "बंद करें",
      "Prev": "पिछला",
      "Next": "अगला",
      "Off": "बंद",
      "TalkBack": "टॉकबैक",
      "Speech Speed": "बोलने की गति",
      "Speech reading speed": "पढ़ने की गति",
      "Website Language": "वेबसाइट की भाषा",
      "Ready - tap, hover, or Tab any item":
        "तैयार — किसी भी आइटम पर टैप, होवर या Tab करें",
      "TalkBack Screen Reader Controls": "टॉकबैक स्क्रीन रीडर नियंत्रण",
      "Previous block": "पिछला अनुभाग",
      "Next block": "अगला अनुभाग",
      "Read page aloud or pause": "पेज जोर से पढ़ें या रोकें",
      "Stop reading": "पढ़ना बंद करें",
      "Change reading speed": "पढ़ने की गति बदलें",
      "Turn off TalkBack": "टॉकबैक बंद करें",
      "Paused": "रुका हुआ",
      "Stopped": "बंद",
      "Finished reading page": "पेज पढ़ना पूरा हुआ",
      "Signing out...": "साइन आउट हो रहा है...",
      "Purple Festival Gujarat 2027": "पर्पल फेस्टिवल गुजरात 2027",
      "Purple Festival Gujarat 2027 | Inclusion, celebrated.":
        "पर्पल फेस्टिवल गुजरात 2027 | समावेशन का उत्सव।",
    },
    gu: {
      "TalkBack: Off": "ટૉકબૅક: બંધ",
      "TalkBack: On": "ટૉકબૅક: ચાલુ",
      "Toggle TalkBack Screen Reader": "ટૉકબૅક સ્ક્રીન રીડર ચાલુ કે બંધ કરો",
      "Screen Reader / TalkBack": "સ્ક્રીન રીડર / ટૉકબૅક",
      "Speaks focused, clicked, or hovered content and reads the entire page aloud.":
        "ફોકસ, ક્લિક અથવા હોવર કરેલી સામગ્રી બોલીને સંભળાવે છે અને આખું પેજ વાંચે છે.",
      "Read Entire Page": "આખું પેજ વાંચો",
      "Read Page": "પેજ વાંચો",
      "Pause": "થોભાવો",
      "Resume": "ચાલુ રાખો",
      "Stop": "બંધ કરો",
      "Prev": "પાછલું",
      "Next": "આગળનું",
      "Off": "બંધ",
      "TalkBack": "ટૉકબૅક",
      "Speech Speed": "વાંચવાની ઝડપ",
      "Speech reading speed": "વાંચવાની ઝડપ",
      "Website Language": "વેબસાઇટની ભાષા",
      "Ready - tap, hover, or Tab any item":
        "તૈયાર — કોઈપણ આઇટમ પર ટૅપ, હોવર અથવા Tab કરો",
      "TalkBack Screen Reader Controls": "ટૉકબૅક સ્ક્રીન રીડર નિયંત્રણો",
      "Previous block": "પાછલો ભાગ",
      "Next block": "આગળનો ભાગ",
      "Read page aloud or pause": "પેજ મોટેથી વાંચો અથવા થોભાવો",
      "Stop reading": "વાંચવાનું બંધ કરો",
      "Change reading speed": "વાંચવાની ઝડપ બદલો",
      "Turn off TalkBack": "ટૉકબૅક બંધ કરો",
      "Paused": "થોભાવેલ",
      "Stopped": "બંધ",
      "Finished reading page": "પેજ વાંચવાનું પૂર્ણ થયું",
      "Signing out...": "સાઇન આઉટ થઈ રહ્યું છે...",
      "Purple Festival Gujarat 2027": "પર્પલ ફેસ્ટિવલ ગુજરાત 2027",
      "Purple Festival Gujarat 2027 | Inclusion, celebrated.":
        "પર્પલ ફેસ્ટિવલ ગુજરાત 2027 | સમાવેશની ઉજવણી.",
    },
  };

  // Reversible snapshot stores
  var origTextMap = new WeakMap();
  var appliedTextMap = new WeakMap();
  var origElTextMap = new WeakMap();
  var appliedElTextMap = new WeakMap();
  var origAttrMap = new WeakMap();
  var appliedAttrMap = new WeakMap();

  var origDocTitle = null;
  var appliedDocTitle = null;

  // Runtime state
  var currentLang = "en";
  var activeRequestSeq = 0;
  var isApplying = false;
  var loadedFonts = Object.create(null);
  var scriptLoadPromises = Object.create(null);
  var indexCache = Object.create(null);

  // MutationObserver state
  var observer = null;
  var pendingRoots = new Set();
  var mutationTimer = 0;

  // Ensure dictionary namespace exists
  window.PFG27_I18N_DICT = window.PFG27_I18N_DICT || {};

  function normalizeLangCode(raw) {
    if (typeof raw !== "string") return null;
    var code = raw.trim().toLowerCase();
    if (code === "en" || code === "en-in" || code === "en-us") return "en";
    if (code === "hi" || code === "hi-in") return "hi";
    if (code === "gu" || code === "gu-in") return "gu";
    return null;
  }

  function normKey(str) {
    if (typeof str !== "string") return "";
    return str
      .replace(/[\u00A0\s]+/g, " ")
      .replace(/[\u2018\u2019\u201A\u201B\u2032]/g, "'")
      .replace(/[\u201C\u201D\u201E\u201F\u2033]/g, '"')
      .replace(/[\u2010\u2011\u2012\u2013\u2014\u2015\u2212]/g, "-")
      .replace(/\u2026/g, "...")
      .trim();
  }

  function shouldSkipString(trimmed) {
    if (!trimmed) return true;
    // Pure numbers, symbols, punctuation, percentages, multipliers (e.g. 100%, 1.25×, ·, |)
    if (/^[\d\s.,:%+\-\u2013\u2014/×x·|()•#@*]+$/i.test(trimmed) && !/[a-zA-Z]{2,}/.test(trimmed)) {
      return true;
    }
    // Standalone email address
    if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
      return true;
    }
    // URL / mailto / tel
    if (/^(?:https?:\/\/|www\.|mailto:|tel:)/i.test(trimmed)) {
      return true;
    }
    // Pure phone number (+91 99743 83920)
    if (/^\+?\d[\d\s\-()]{6,}$/.test(trimmed)) {
      return true;
    }
    // Registration IDs (PFG-2027-...)
    if (/^PFG-2027-[A-Z0-9-]+$/i.test(trimmed)) {
      return true;
    }
    return false;
  }

  function getUrlLangParam() {
    try {
      if (typeof URLSearchParams === "function") {
        var params = new URLSearchParams(window.location.search || "");
        return normalizeLangCode(params.get("lang"));
      }
      var match = (window.location.search || "").match(/[?&]lang=([^&]+)/i);
      if (match && match[1]) {
        return normalizeLangCode(decodeURIComponent(match[1]));
      }
    } catch (e) {
      // Ignore malformed query
    }
    return null;
  }

  function readStoredLang() {
    try {
      return normalizeLangCode(window.localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      return null;
    }
  }

  function writeStoredLang(lang) {
    try {
      window.localStorage.setItem(STORAGE_KEY, lang);
    } catch (e) {
      // Ignore storage quota or private mode errors
    }
  }

  function injectFontForLang(lang) {
    var cfg = SUPPORTED[lang];
    if (!cfg || !cfg.fontUrl || loadedFonts[lang]) return;
    if (cfg.fontId && document.getElementById(cfg.fontId)) {
      loadedFonts[lang] = true;
      return;
    }
    var head = document.head || document.getElementsByTagName("head")[0];
    if (!head) return;
    var link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = cfg.fontUrl;
    if (cfg.fontId) link.id = cfg.fontId;
    head.appendChild(link);
    loadedFonts[lang] = true;
  }

  function loadDictionaryScript(lang) {
    if (lang === "en") {
      return Promise.resolve(null);
    }
    if (window.PFG27_I18N_DICT && window.PFG27_I18N_DICT[lang]) {
      return Promise.resolve(window.PFG27_I18N_DICT[lang]);
    }
    if (scriptLoadPromises[lang]) {
      return scriptLoadPromises[lang];
    }

    var cfg = SUPPORTED[lang];
    if (!cfg || !cfg.scriptUrl) {
      return Promise.resolve(null);
    }

    scriptLoadPromises[lang] = new Promise(function (resolve) {
      var script = document.createElement("script");
      script.src = cfg.scriptUrl;
      script.async = true;
      script.onload = function () {
        delete scriptLoadPromises[lang];
        resolve((window.PFG27_I18N_DICT && window.PFG27_I18N_DICT[lang]) || null);
      };
      script.onerror = function () {
        delete scriptLoadPromises[lang];
        resolve(null);
      };
      var target = document.head || document.body || document.documentElement;
      if (target) {
        target.appendChild(script);
      } else {
        delete scriptLoadPromises[lang];
        resolve(null);
      }
    });

    return scriptLoadPromises[lang];
  }

  function buildLookupIndex(lang) {
    if (lang === "en") return null;
    var dict = (window.PFG27_I18N_DICT && window.PFG27_I18N_DICT[lang]) || null;
    var cached = indexCache[lang];
    if (cached && cached.sourceRef === dict) {
      return cached;
    }

    var keysMap = new Map();
    var exactMap = new Map();
    var normMap = new Map();
    var lowerNormMap = new Map();

    function addPhrase(src, dst) {
      if (typeof src !== "string" || typeof dst !== "string") return;
      var trimmed = src.trim();
      if (!trimmed) return;
      if (!exactMap.has(trimmed)) exactMap.set(trimmed, dst);
      var nk = normKey(trimmed);
      if (nk && !normMap.has(nk)) normMap.set(nk, dst);
      var lnk = nk.toLowerCase();
      if (lnk && !lowerNormMap.has(lnk)) lowerNormMap.set(lnk, dst);
    }

    if (dict && typeof dict === "object") {
      // Structured keys map
      if (dict.keys && typeof dict.keys === "object") {
        var keyNames = Object.keys(dict.keys);
        for (var k = 0; k < keyNames.length; k++) {
          var kn = keyNames[k];
          var kv = dict.keys[kn];
          if (typeof kv === "string") {
            keysMap.set(kn, kv);
          }
        }
      }

      // Structured phrases map
      if (dict.phrases && typeof dict.phrases === "object") {
        var phraseKeys = Object.keys(dict.phrases);
        for (var p = 0; p < phraseKeys.length; p++) {
          var pk = phraseKeys[p];
          addPhrase(pk, dict.phrases[pk]);
        }
      }

      // Graceful support for flat string entries directly on window.PFG27_I18N_DICT[lang]
      var topKeys = Object.keys(dict);
      for (var t = 0; t < topKeys.length; t++) {
        var tk = topKeys[t];
        if (tk === "keys" || tk === "phrases") continue;
        var tv = dict[tk];
        if (typeof tv === "string") {
          if (!keysMap.has(tk)) keysMap.set(tk, tv);
          addPhrase(tk, tv);
        }
      }
    }

    // Add built-in core chrome fallbacks ONLY if not already supplied by external dictionary
    var fallbacks = CORE_FALLBACKS[lang];
    if (fallbacks) {
      var fbKeys = Object.keys(fallbacks);
      for (var f = 0; f < fbKeys.length; f++) {
        var fk = fbKeys[f];
        addPhrase(fk, fallbacks[fk]);
      }
    }

    var built = {
      sourceRef: dict,
      keysMap: keysMap,
      exactMap: exactMap,
      normMap: normMap,
      lowerNormMap: lowerNormMap,
    };
    indexCache[lang] = built;
    return built;
  }

  function translateDynamicPattern(text, lang, index) {
    var nk = normKey(text);
    if (!nk) return null;

    var m;

    // 1. "Showing X of Y specified disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016."
    m = nk.match(
      /^Showing\s+(\d+)\s+of\s+(\d+)\s+specified disabilities under the Rights of Persons with Disabilities \(RPwD\) Act,\s*2016\.?$/i
    );
    if (m) {
      if (lang === "hi") {
        return (
          "दिव्यांगजन अधिकार (RPwD) अधिनियम, 2016 के अंतर्गत " +
          m[2] +
          " में से " +
          m[1] +
          " निर्दिष्ट दिव्यांगताएँ दिखाई जा रही हैं।"
        );
      }
      if (lang === "gu") {
        return (
          "દિવ્યાંગજન અધિકાર (RPwD) અધિનિયમ, 2016 હેઠળ " +
          m[2] +
          " માંથી " +
          m[1] +
          " નિર્દિષ્ટ દિવ્યાંગતાઓ દર્શાવાઈ રહી છે."
        );
      }
    }

    // 2. "Step X of Y"
    m = nk.match(/^Step\s+(\d+)\s+of\s+(\d+)$/i);
    if (m) {
      if (lang === "hi") return "चरण " + m[1] + " / " + m[2];
      if (lang === "gu") return "પગલું " + m[1] + " / " + m[2];
    }

    // 3. Contact lines: "Radha Tandon: +91 99743 83920" / "Satish Kapoor: +91 92117 60202"
    m = nk.match(/^Radha Tandon:\s*(\+?\d[\d\s\-()]+)$/i);
    if (m) {
      if (lang === "hi") return "राधा टंडन: " + m[1].trim();
      if (lang === "gu") return "રાધા ટંડન: " + m[1].trim();
    }

    m = nk.match(/^Satish Kapoor:\s*(\+?\d[\d\s\-()]+)$/i);
    if (m) {
      if (lang === "hi") return "सतीश कपूर: " + m[1].trim();
      if (lang === "gu") return "સતીશ કપૂર: " + m[1].trim();
    }

    // 4. Copyright: "© 2027 Purple Festival Gujarat. All rights reserved."
    m = nk.match(
      /^(?:©|\(c\))\s*(\d{4})\s*Purple Festival Gujarat\.\s*All rights reserved\.?$/i
    );
    if (m) {
      if (lang === "hi") {
        return "© " + m[1] + " पर्पल फेस्टिवल गुजरात। सर्वाधिकार सुरक्षित।";
      }
      if (lang === "gu") {
        return "© " + m[1] + " પર્પલ ફેસ્ટિવલ ગુજરાત. સર્વહક સુરક્ષિત.";
      }
    }

    // 5. TalkBack status bar: "Reading X of Y..."
    m = nk.match(/^Reading\s+(\d+)\s+of\s+(\d+)(?:\.\.\.)?$/i);
    if (m) {
      if (lang === "hi") return "पढ़ा जा रहा है " + m[1] + " / " + m[2] + "...";
      if (lang === "gu") return "વાંચી રહ્યા છીએ " + m[1] + " / " + m[2] + "...";
    }

    // 6. Compound page titles: "<Page Title> | Purple Festival Gujarat 2027"
    if (nk.indexOf(" | Purple Festival Gujarat 2027") !== -1) {
      var parts = nk.split(" | ");
      if (parts.length === 2) {
        var leftTranslated = lookupPhraseOnly(parts[0], lang, index) || parts[0];
        var rightTranslated =
          lookupPhraseOnly(parts[1], lang, index) ||
          (lang === "hi"
            ? "पर्पल फेस्टिवल गुजरात 2027"
            : lang === "gu"
              ? "પર્પલ ફેસ્ટિવલ ગુજરાત 2027"
              : parts[1]);
        if (leftTranslated !== parts[0] || rightTranslated !== parts[1]) {
          return leftTranslated + " | " + rightTranslated;
        }
      }
    }

    return null;
  }

  function lookupPhraseOnly(englishStr, lang, index) {
    if (lang === "en" || !index || typeof englishStr !== "string") return null;
    var trimmed = englishStr.trim();
    if (!trimmed || shouldSkipString(trimmed)) return null;

    if (index.exactMap.has(trimmed)) {
      return index.exactMap.get(trimmed);
    }
    var nk = normKey(trimmed);
    if (nk && index.normMap.has(nk)) {
      return index.normMap.get(nk);
    }
    var lnk = nk.toLowerCase();
    if (lnk && index.lowerNormMap.has(lnk)) {
      return index.lowerNormMap.get(lnk);
    }
    return null;
  }

  function resolvePhrase(englishStr, lang, index) {
    if (lang === "en" || !index || typeof englishStr !== "string") return null;
    var trimmed = englishStr.trim();
    if (!trimmed || shouldSkipString(trimmed)) return null;

    var direct = lookupPhraseOnly(trimmed, lang, index);
    if (direct !== null) return direct;

    return translateDynamicPattern(trimmed, lang, index);
  }

  function resolveKey(key, fallbackEnglish, lang, index) {
    if (lang === "en" || !index) return null;
    if (key && index.keysMap.has(key)) {
      return index.keysMap.get(key);
    }
    if (typeof fallbackEnglish === "string" && fallbackEnglish.trim()) {
      return resolvePhrase(fallbackEnglish, lang, index);
    }
    return null;
  }

  function isElementNoTranslate(el) {
    if (!el || el.nodeType !== 1) return false;
    var tag = el.tagName ? el.tagName.toUpperCase() : "";
    if (SKIP_ATTR_TAGS[tag]) return true;
    if (el.namespaceURI === "http://www.w3.org/2000/svg") return true;
    if (
      el.getAttribute &&
      (el.getAttribute("data-no-translate") === "true" ||
        el.getAttribute("translate") === "no")
    ) {
      return true;
    }
    if (el.id === "a11y-live-status" || el.id === "a11y-live-assertive") {
      return true;
    }
    return false;
  }

  function hasSkippedAncestor(node, checkTextTags) {
    var el = node && node.nodeType === 1 ? node : node ? node.parentElement : null;
    while (el) {
      if (isElementNoTranslate(el)) return true;
      if (checkTextTags) {
        var tag = el.tagName ? el.tagName.toUpperCase() : "";
        if (SKIP_TEXT_TAGS[tag]) return true;
      }
      el = el.parentElement;
    }
    return false;
  }

  function processElementAttributes(el, lang, index) {
    var origAttrs = origAttrMap.get(el);
    var appliedAttrs = appliedAttrMap.get(el);

    for (var i = 0; i < TRANSLATABLE_ATTRS.length; i++) {
      var spec = TRANSLATABLE_ATTRS[i];
      var attrName = spec.attr;
      var i18nAttrName = spec.i18nAttr;
      var hasAttr = el.hasAttribute(attrName);
      var i18nKey = el.getAttribute(i18nAttrName);

      if (!hasAttr && !i18nKey) continue;

      var currentVal = hasAttr ? el.getAttribute(attrName) : "";

      if (lang === "en") {
        if (
          origAttrs &&
          Object.prototype.hasOwnProperty.call(origAttrs, attrName) &&
          appliedAttrs &&
          appliedAttrs[attrName] === currentVal
        ) {
          el.setAttribute(attrName, origAttrs[attrName]);
          delete appliedAttrs[attrName];
        }
        continue;
      }

      // Snapshot original English attribute once, or refresh if externally modified
      if (!origAttrs) {
        origAttrs = Object.create(null);
        origAttrMap.set(el, origAttrs);
      }
      if (
        !Object.prototype.hasOwnProperty.call(origAttrs, attrName) ||
        !appliedAttrs ||
        appliedAttrs[attrName] !== currentVal
      ) {
        origAttrs[attrName] = currentVal;
      }

      var sourceEnglish = origAttrs[attrName];
      var translated = i18nKey
        ? resolveKey(i18nKey, sourceEnglish, lang, index)
        : resolvePhrase(sourceEnglish, lang, index);

      if (translated !== null && translated !== currentVal) {
        el.setAttribute(attrName, translated);
        if (!appliedAttrs) {
          appliedAttrs = Object.create(null);
          appliedAttrMap.set(el, appliedAttrs);
        }
        appliedAttrs[attrName] = translated;
      } else if (translated === null && sourceEnglish !== currentVal) {
        // Restore English when switching hi -> gu if phrase not available in gu
        el.setAttribute(attrName, sourceEnglish);
        if (appliedAttrs) delete appliedAttrs[attrName];
      }
    }
  }

  function processDataI18nElement(el, lang, index) {
    var key = el.getAttribute("data-i18n");
    var currentVal = el.textContent || "";

    if (lang === "en") {
      if (origElTextMap.has(el) && appliedElTextMap.get(el) === currentVal) {
        el.textContent = origElTextMap.get(el);
        appliedElTextMap.delete(el);
      }
      return;
    }

    if (!origElTextMap.has(el) || appliedElTextMap.get(el) !== currentVal) {
      origElTextMap.set(el, currentVal);
    }

    var sourceEnglish = origElTextMap.get(el);
    var translated = resolveKey(key, sourceEnglish, lang, index);

    if (translated !== null) {
      var ws = sourceEnglish.match(/^(\s*)([\s\S]*?)(\s*)$/);
      var nextVal = (ws ? ws[1] : "") + translated + (ws ? ws[3] : "");
      if (nextVal !== currentVal) {
        el.textContent = nextVal;
      }
      appliedElTextMap.set(el, nextVal);
    } else if (sourceEnglish !== currentVal) {
      el.textContent = sourceEnglish;
      appliedElTextMap.delete(el);
    }
  }

  function processTextNode(node, lang, index) {
    var currentVal = node.nodeValue;
    if (typeof currentVal !== "string") return;

    if (lang === "en") {
      if (origTextMap.has(node) && appliedTextMap.get(node) === currentVal) {
        node.nodeValue = origTextMap.get(node);
        appliedTextMap.delete(node);
      }
      return;
    }

    // If this text node isn't currently holding our own translation, treat its value as the fresh English source
    if (!origTextMap.has(node) || appliedTextMap.get(node) !== currentVal) {
      origTextMap.set(node, currentVal);
    }

    var sourceEnglish = origTextMap.get(node);
    var match = sourceEnglish.match(/^(\s*)([\s\S]*?)(\s*)$/);
    var leading = match ? match[1] : "";
    var core = match ? match[2] : sourceEnglish;
    var trailing = match ? match[3] : "";

    if (!core || shouldSkipString(core)) return;

    var translated = resolvePhrase(core, lang, index);
    if (translated !== null) {
      var nextVal = leading + translated + trailing;
      if (nextVal !== currentVal) {
        node.nodeValue = nextVal;
      }
      appliedTextMap.set(node, nextVal);
    } else if (sourceEnglish !== currentVal) {
      // Restore English if switching between non-English languages and phrase lacks translation
      node.nodeValue = sourceEnglish;
      appliedTextMap.delete(node);
    }
  }

  function walkNode(node, lang, index) {
    if (!node) return;
    var type = node.nodeType;

    if (type === 3) {
      processTextNode(node, lang, index);
      return;
    }

    if (type === 1) {
      if (isElementNoTranslate(node)) return;

      processElementAttributes(node, lang, index);

      if (node.hasAttribute("data-i18n")) {
        processDataI18nElement(node, lang, index);
        return;
      }

      var tag = node.tagName ? node.tagName.toUpperCase() : "";
      if (SKIP_TEXT_TAGS[tag]) return;

      var child = node.firstChild;
      while (child) {
        var next = child.nextSibling;
        walkNode(child, lang, index);
        child = next;
      }
      return;
    }

    if (type === 9 || type === 11) {
      var c = node.firstChild;
      while (c) {
        var n = c.nextSibling;
        walkNode(c, lang, index);
        c = n;
      }
    }
  }

  function translateDocumentTitle(lang, index) {
    var currentTitle = document.title || "";
    if (lang === "en") {
      if (origDocTitle !== null && appliedDocTitle === currentTitle) {
        document.title = origDocTitle;
        appliedDocTitle = null;
      }
      return;
    }

    if (origDocTitle === null || appliedDocTitle !== currentTitle) {
      origDocTitle = currentTitle;
    }

    var translated = resolvePhrase(origDocTitle, lang, index);
    if (translated !== null) {
      if (document.title !== translated) {
        document.title = translated;
      }
      appliedDocTitle = translated;
    } else if (document.title !== origDocTitle) {
      document.title = origDocTitle;
      appliedDocTitle = null;
    }
  }

  function translateTree(rootNode) {
    var target = rootNode || document.body;
    if (!target) return;

    var lang = currentLang;
    var index = lang === "en" ? null : buildLookupIndex(lang);

    // Check ancestor safety when called on an inner node
    if (target !== document.body && target !== document.documentElement) {
      if (target.nodeType === 3) {
        if (hasSkippedAncestor(target, true)) return;
      } else if (target.nodeType === 1) {
        if (hasSkippedAncestor(target.parentElement, false)) return;
      }
    }

    var prevApplying = isApplying;
    isApplying = true;
    try {
      walkNode(target, lang, index);
      if (target === document.body || target === document.documentElement) {
        translateDocumentTitle(lang, index);
      }
    } finally {
      if (observer && typeof observer.takeRecords === "function") {
        observer.takeRecords();
      }
      isApplying = prevApplying;
    }
  }

  function syncLanguageControls(lang) {
    var cfg = SUPPORTED[lang] || SUPPORTED.en;
    var root = document.documentElement;
    if (root) {
      root.lang = cfg.htmlLang;
      root.setAttribute("data-lang", cfg.code);
    }

    var btns = document.querySelectorAll("[data-lang-switch]");
    for (var i = 0; i < btns.length; i++) {
      var btn = btns[i];
      var code = normalizeLangCode(btn.getAttribute("data-lang-switch"));
      var isActive = code === cfg.code;
      btn.setAttribute("aria-pressed", isActive ? "true" : "false");
      if (btn.classList) {
        btn.classList.toggle("is-active", isActive);
      }
    }
  }

  function dispatchLangChanged(lang) {
    var cfg = SUPPORTED[lang] || SUPPORTED.en;
    try {
      window.dispatchEvent(
        new CustomEvent("pfg27:lang-changed", {
          detail: {
            lang: cfg.code,
            htmlLang: cfg.htmlLang,
          },
        })
      );
    } catch (e) {
      // Ignore in older environments
    }
  }

  function stopMutationObserver() {
    if (mutationTimer) {
      window.clearTimeout(mutationTimer);
      mutationTimer = 0;
    }
    pendingRoots.clear();
    if (observer) {
      observer.disconnect();
    }
  }

  function flushPendingMutations() {
    mutationTimer = 0;
    if (currentLang === "en" || pendingRoots.size === 0) {
      pendingRoots.clear();
      return;
    }

    var roots = Array.from(pendingRoots);
    pendingRoots.clear();

    var index = buildLookupIndex(currentLang);
    isApplying = true;
    try {
      for (var i = 0; i < roots.length; i++) {
        var node = roots[i];
        if (!node) continue;
        if (node.nodeType === 1 && !document.contains(node)) continue;
        if (node.nodeType === 3 && (!node.parentElement || !document.contains(node.parentElement))) {
          continue;
        }
        if (node.nodeType === 3) {
          if (!hasSkippedAncestor(node, true)) {
            processTextNode(node, currentLang, index);
          }
        } else if (node.nodeType === 1) {
          if (!hasSkippedAncestor(node, false)) {
            walkNode(node, currentLang, index);
          }
        }
      }
    } finally {
      if (observer && typeof observer.takeRecords === "function") {
        observer.takeRecords();
      }
      isApplying = false;
    }
  }

  function startMutationObserver() {
    if (typeof MutationObserver !== "function" || !document.body) return;
    if (!observer) {
      observer = new MutationObserver(function (mutations) {
        if (isApplying || currentLang === "en") return;
        for (var i = 0; i < mutations.length; i++) {
          var rec = mutations[i];
          if (rec.type === "childList") {
            for (var a = 0; a < rec.addedNodes.length; a++) {
              var added = rec.addedNodes[a];
              if (added && (added.nodeType === 1 || added.nodeType === 3)) {
                pendingRoots.add(added);
              }
            }
          } else if (rec.type === "characterData" && rec.target) {
            pendingRoots.add(rec.target);
          } else if (rec.type === "attributes" && rec.target) {
            pendingRoots.add(rec.target);
          }
        }
        if (pendingRoots.size > 0 && !mutationTimer) {
          mutationTimer = window.setTimeout(flushPendingMutations, 60);
        }
      });
    } else {
      observer.disconnect();
    }

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ["placeholder", "aria-label", "title"],
    });
  }

  function applyLanguageNow(lang, options) {
    var cfg = SUPPORTED[lang] || SUPPORTED.en;
    currentLang = cfg.code;

    syncLanguageControls(currentLang);

    if (currentLang === "en") {
      stopMutationObserver();
      if (document.body) {
        translateTree(document.body);
      }
    } else {
      if (document.body) {
        translateTree(document.body);
        startMutationObserver();
      }
    }

    var shouldAnnounce = Boolean(options && options.announce === true);
    if (shouldAnnounce && typeof window.pfgAnnounce === "function") {
      window.pfgAnnounce(cfg.announceMsg, "polite");
    }

    dispatchLangChanged(currentLang);
    return currentLang;
  }

  function setLang(requestedLang, options) {
    var normalized = normalizeLangCode(requestedLang);
    if (!normalized) {
      return Promise.resolve(currentLang);
    }

    var opts = options && typeof options === "object" ? options : {};
    var shouldPersist = opts.persist !== false;
    var shouldAnnounce = opts.announce !== false;

    if (shouldPersist) {
      writeStoredLang(normalized);
    }

    var seq = ++activeRequestSeq;

    // Instant synchronous restore for English — never loads dictionary scripts
    if (normalized === "en") {
      applyLanguageNow("en", { announce: shouldAnnounce });
      return Promise.resolve("en");
    }

    // Immediately reflect selection on buttons & html attributes while dictionary loads
    syncLanguageControls(normalized);
    injectFontForLang(normalized);

    // If dictionary is already loaded, apply synchronously
    if (window.PFG27_I18N_DICT && window.PFG27_I18N_DICT[normalized]) {
      applyLanguageNow(normalized, { announce: shouldAnnounce });
      return Promise.resolve(normalized);
    }

    return loadDictionaryScript(normalized).then(function () {
      if (seq !== activeRequestSeq) {
        return currentLang;
      }
      applyLanguageNow(normalized, { announce: shouldAnnounce });
      return normalized;
    });
  }

  function getLang() {
    return currentLang;
  }

  function t(keyOrEnglish, fallback) {
    var defaultStr =
      typeof fallback === "string"
        ? fallback
        : typeof keyOrEnglish === "string"
          ? keyOrEnglish
          : "";

    if (currentLang === "en") {
      return defaultStr;
    }

    var index = buildLookupIndex(currentLang);
    if (!index) return defaultStr;

    if (typeof keyOrEnglish === "string" && keyOrEnglish) {
      if (index.keysMap.has(keyOrEnglish)) {
        return index.keysMap.get(keyOrEnglish);
      }
      var fromKeyAsPhrase = resolvePhrase(keyOrEnglish, currentLang, index);
      if (fromKeyAsPhrase !== null) {
        return fromKeyAsPhrase;
      }
    }

    if (typeof fallback === "string" && fallback) {
      var fromFallback = resolvePhrase(fallback, currentLang, index);
      if (fromFallback !== null) {
        return fromFallback;
      }
    }

    return defaultStr;
  }

  // Delegate clicks on any [data-lang-switch] button (header utility bar, a11y drawer, etc.)
  document.addEventListener("click", function (e) {
    var target = e.target;
    if (!target || typeof target.closest !== "function") return;
    var btn = target.closest("[data-lang-switch]");
    if (!btn) return;
    var lang = normalizeLangCode(btn.getAttribute("data-lang-switch"));
    if (!lang) return;
    e.preventDefault();
    setLang(lang, { announce: true, persist: true });
  });

  // Export public API immediately so sibling scripts (screen-reader.js, etc.) can call it
  window.PFG27_I18N = {
    getLang: getLang,
    setLang: setLang,
    translateTree: translateTree,
    t: t,
  };

  function boot() {
    var urlLang = getUrlLangParam();
    var initialLang = urlLang || readStoredLang() || "en";

    if (urlLang) {
      writeStoredLang(urlLang);
    }

    if (initialLang === "en") {
      currentLang = "en";
      syncLanguageControls("en");
      return;
    }

    // Silent initial application (never calls pfgAnnounce on page load)
    setLang(initialLang, { announce: false, persist: Boolean(urlLang) });
  }

  if (document.readyState === "loading") {
    // Pre-fetch font & dictionary early if non-English is already selected
    var earlyLang = getUrlLangParam() || readStoredLang();
    if (earlyLang === "hi" || earlyLang === "gu") {
      injectFontForLang(earlyLang);
      loadDictionaryScript(earlyLang);
    }
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
