/**
 * Purple Festival Gujarat 2027 — 60-Character Animated Inclusive Crowd Footer
 * Exact Vanilla JS dual-canvas port of src/components/footer/CrowdMarquee.tsx & src/components/illustrations/characters/index.ts
 */
(function () {
  "use strict";

  var CROWD_SHEETS = [
    "/assets/footer/crowd-sheet-01.png?v=3",
    "/assets/footer/crowd-sheet-02.png?v=3",
    "/assets/footer/crowd-sheet-03.png?v=3",
    "/assets/footer/crowd-sheet-04.png?v=3",
  ];

  var COLS = 5;
  var ROWS = 3;
  var CHARS_PER_SHEET = COLS * ROWS; // 15
  var TOTAL_CAST = CROWD_SHEETS.length * CHARS_PER_SHEET; // 60 unique illustrated characters
  var CELL_ASPECT = 440 / 580;

  var WELCOMING_GROUP_CHARACTERS = [
    {
      id: "welcome-gujarati-namaste",
      category: "welcoming-group",
      description:
        "Young Indian woman in a royal-purple and lavender Gujarati chaniya choli and embroidered dupatta offering a respectful Namaste gesture.",
    },
    {
      id: "welcome-wheelchair-host",
      category: "welcoming-group",
      description:
        "Confident Indian man in a royal-purple overshirt seated naturally in a purple-accented modern manual wheelchair, smiling and waving welcome.",
    },
    {
      id: "welcome-prosthetic-host",
      category: "welcoming-group",
      description:
        "Smiling young Indian man in a deep royal-purple Nehru jacket with a visible carbon-fiber lower-limb prosthetic, gesturing welcome.",
    },
  ];

  var CROWD_CHARACTER_REGISTRY = [
    // Sheet 0 (0..14 — People with visible disabilities & assistive devices)
    { id: "char-01", cellIndex: 0, sheetIndex: 0, col: 0, row: 0, category: "wheelchair-users", description: "Young woman in manual wheelchair smiling confidently", scale: 1.0 },
    { id: "char-02", cellIndex: 1, sheetIndex: 0, col: 1, row: 0, category: "white-cane-users", description: "Bearded man with sunglasses navigating with a long white cane", scale: 1.01 },
    { id: "char-03", cellIndex: 2, sheetIndex: 0, col: 2, row: 0, category: "prosthetic-users", description: "Young woman with bionic prosthetic forearm holding a notebook", scale: 0.96 },
    { id: "char-04", cellIndex: 3, sheetIndex: 0, col: 3, row: 0, category: "wheelchair-users", description: "Older gentleman in kurta and glasses seated in a manual wheelchair", scale: 1.0 },
    { id: "char-05", cellIndex: 4, sheetIndex: 0, col: 4, row: 0, category: "mobility-aid-users", description: "Young man walking with a forearm crutch and backpack", scale: 1.01 },
    { id: "char-06", cellIndex: 5, sheetIndex: 0, col: 0, row: 1, category: "white-cane-users", description: "Woman in traditional saree holding a long white navigation cane", scale: 0.99 },
    { id: "char-07", cellIndex: 6, sheetIndex: 0, col: 1, row: 1, category: "hearing-aid-users", description: "Smiling teenager with a visible behind-the-ear cochlear implant / hearing aid", scale: 0.93 },
    { id: "char-08", cellIndex: 7, sheetIndex: 0, col: 2, row: 1, category: "sign-language-users", description: "Two friends communicating warmly using Indian Sign Language-inspired gestures", scale: 0.98 },
    { id: "char-09", cellIndex: 8, sheetIndex: 0, col: 3, row: 1, category: "prosthetic-users", description: "Student in hoodie with prosthetic forearm and festival lanyard", scale: 0.96 },
    { id: "char-10", cellIndex: 9, sheetIndex: 0, col: 4, row: 1, category: "elderly", description: "Elderly Gujarati grandfather with cap and curved wooden walking stick", scale: 0.96 },
    { id: "char-11", cellIndex: 10, sheetIndex: 0, col: 0, row: 2, category: "wheelchair-users", description: "College student in modern wheelchair wearing headphones and festival pass", scale: 1.0 },
    { id: "char-12", cellIndex: 11, sheetIndex: 0, col: 1, row: 2, category: "hearing-aid-users", description: "Middle-aged teacher with glasses and behind-the-ear hearing aid holding a book", scale: 0.95 },
    { id: "char-13", cellIndex: 12, sheetIndex: 0, col: 2, row: 2, category: "mobility-aid-users", description: "Older woman in salwar kameez using a two-handled walker frame", scale: 0.97 },
    { id: "char-14", cellIndex: 13, sheetIndex: 0, col: 3, row: 2, category: "volunteers", description: "Young man with dark glasses carrying a sling bag", scale: 0.96 },
    { id: "char-15", cellIndex: 14, sheetIndex: 0, col: 4, row: 2, category: "mobility-aid-users", description: "Cheerful young woman using dual forearm crutches in contemporary kurta", scale: 0.98 },

    // Sheet 1 (15..29 — Students, elderly, families, volunteers, cultural participants)
    { id: "char-16", cellIndex: 15, sheetIndex: 1, col: 0, row: 0, category: "students", description: "College student with backpack and skateboard", scale: 0.96 },
    { id: "char-17", cellIndex: 16, sheetIndex: 1, col: 1, row: 0, category: "families", description: "Young Gujarati woman in patterned saree and bindi", scale: 0.94 },
    { id: "char-18", cellIndex: 17, sheetIndex: 1, col: 2, row: 0, category: "volunteers", description: "Laughing bearded man in open casual shirt", scale: 0.98 },
    { id: "char-19", cellIndex: 18, sheetIndex: 1, col: 3, row: 0, category: "students", description: "Young woman with ponytail checking her smartphone", scale: 0.95 },
    { id: "char-20", cellIndex: 19, sheetIndex: 1, col: 4, row: 0, category: "families", description: "Smiling Sikh man in neat turban and collared shirt", scale: 0.98 },
    { id: "char-21", cellIndex: 20, sheetIndex: 1, col: 0, row: 1, category: "elderly", description: "Grandmother with round glasses and traditional shawl", scale: 0.93 },
    { id: "char-22", cellIndex: 21, sheetIndex: 1, col: 1, row: 1, category: "students", description: "Excited child in hoodie holding a small toy robot", scale: 0.89 },
    { id: "char-23", cellIndex: 22, sheetIndex: 1, col: 2, row: 1, category: "volunteers", description: "Festival volunteer wearing lanyard badge and waving", scale: 0.96 },
    { id: "char-24", cellIndex: 23, sheetIndex: 1, col: 3, row: 1, category: "students", description: "Young woman wearing hijab and denim jacket", scale: 0.95 },
    { id: "char-25", cellIndex: 24, sheetIndex: 1, col: 4, row: 1, category: "families", description: "Middle-aged man with moustache and pocket pen", scale: 0.96 },
    { id: "char-26", cellIndex: 25, sheetIndex: 1, col: 0, row: 2, category: "students", description: "Young woman with wavy hair and round glasses", scale: 0.94 },
    { id: "char-27", cellIndex: 26, sheetIndex: 1, col: 1, row: 2, category: "families", description: "Father holding a happy toddler on his shoulder", scale: 1.02 },
    { id: "char-28", cellIndex: 27, sheetIndex: 1, col: 2, row: 2, category: "volunteers", description: "Young folk artist in embroidered Gujarati vest", scale: 0.95 },
    { id: "char-29", cellIndex: 28, sheetIndex: 1, col: 3, row: 2, category: "students", description: "Schoolgirl with braided pigtails holding a sketchbook", scale: 0.92 },
    { id: "char-30", cellIndex: 29, sheetIndex: 1, col: 4, row: 2, category: "elderly", description: "Senior gentleman with white beard and waistcoat", scale: 0.96 },

    // Sheet 2 (30..44 — Additional unique Indian & Gujarati festival participants)
    { id: "char-31", cellIndex: 30, sheetIndex: 2, col: 0, row: 0, category: "volunteers", description: "Young woman with bob haircut and cat-eye glasses", scale: 0.94 },
    { id: "char-32", cellIndex: 31, sheetIndex: 2, col: 1, row: 0, category: "families", description: "Cheerful Gujarati man in traditional embroidered kediyu kurta", scale: 0.97 },
    { id: "char-33", cellIndex: 32, sheetIndex: 2, col: 2, row: 0, category: "students", description: "College student with over-ear headphones and striped hoodie", scale: 0.96 },
    { id: "char-34", cellIndex: 33, sheetIndex: 2, col: 3, row: 0, category: "elderly", description: "Older woman with silver braided hair in floral-bordered saree", scale: 0.94 },
    { id: "char-35", cellIndex: 34, sheetIndex: 2, col: 4, row: 0, category: "white-cane-users", description: "Young woman with round sunglasses and denim vest using a white cane", scale: 0.96 },
    { id: "char-36", cellIndex: 35, sheetIndex: 2, col: 0, row: 1, category: "volunteers", description: "Young photographer with man-bun holding a DSLR camera", scale: 0.97 },
    { id: "char-37", cellIndex: 36, sheetIndex: 2, col: 1, row: 1, category: "students", description: "Cheerful young girl with twin puffs hairstyle waving", scale: 0.89 },
    { id: "char-38", cellIndex: 37, sheetIndex: 2, col: 2, row: 1, category: "wheelchair-users", description: "Para-athlete in wheelchair wearing sports jersey and headband", scale: 0.99 },
    { id: "char-39", cellIndex: 38, sheetIndex: 2, col: 3, row: 1, category: "volunteers", description: "Healthcare professional with stethoscope and ID badge", scale: 0.95 },
    { id: "char-40", cellIndex: 39, sheetIndex: 2, col: 4, row: 1, category: "prosthetic-users", description: "Young man with prosthetic forearm holding a rolled festival poster", scale: 0.97 },
    { id: "char-41", cellIndex: 40, sheetIndex: 2, col: 0, row: 2, category: "elderly", description: "Elderly gentleman with round spectacles and draped shawl", scale: 0.94 },
    { id: "char-42", cellIndex: 41, sheetIndex: 2, col: 1, row: 2, category: "families", description: "Young woman in traditional bandhani head-dupatta and jhumkas", scale: 0.95 },
    { id: "char-43", cellIndex: 42, sheetIndex: 2, col: 2, row: 2, category: "hearing-aid-users", description: "Teenager with glasses and behind-the-ear hearing aid giving thumbs up", scale: 0.92 },
    { id: "char-44", cellIndex: 43, sheetIndex: 2, col: 3, row: 2, category: "volunteers", description: "Visual artist with beret and paintbrushes in apron pocket", scale: 0.95 },
    { id: "char-45", cellIndex: 44, sheetIndex: 2, col: 4, row: 2, category: "students", description: "Young musician with curly hair and guitar strap over shoulder", scale: 0.97 },

    // Sheet 3 (45..59 — Additional unique delegates, families, elders & youth)
    { id: "char-46", cellIndex: 45, sheetIndex: 3, col: 0, row: 0, category: "volunteers", description: "Young delegate in blazer with festival credential badge", scale: 0.97 },
    { id: "char-47", cellIndex: 46, sheetIndex: 3, col: 1, row: 0, category: "students", description: "Woman with long curly hair and rectangular glasses holding a book", scale: 0.96 },
    { id: "char-48", cellIndex: 47, sheetIndex: 3, col: 2, row: 0, category: "elderly", description: "Grandmother with nose ring and patterned pallu over head", scale: 0.94 },
    { id: "char-49", cellIndex: 48, sheetIndex: 3, col: 3, row: 0, category: "students", description: "Young boy in cap holding a cricket bat over his shoulder", scale: 0.92 },
    { id: "char-50", cellIndex: 49, sheetIndex: 3, col: 4, row: 0, category: "wheelchair-users", description: "Young woman in wheelchair wearing embroidered kurti and necklace", scale: 0.98 },
    { id: "char-51", cellIndex: 50, sheetIndex: 3, col: 0, row: 1, category: "students", description: "Bearded young man in beanie cap and hoodie", scale: 0.96 },
    { id: "char-52", cellIndex: 51, sheetIndex: 3, col: 1, row: 1, category: "families", description: "Middle-aged woman with wavy hair and pearl earrings gesturing in conversation", scale: 0.94 },
    { id: "char-53", cellIndex: 52, sheetIndex: 3, col: 2, row: 1, category: "families", description: "Man with handlebar moustache and traditional waistcoat", scale: 0.97 },
    { id: "char-54", cellIndex: 53, sheetIndex: 3, col: 3, row: 1, category: "hearing-aid-users", description: "Young woman with visible cochlear implant holding a coffee cup", scale: 0.95 },
    { id: "char-55", cellIndex: 54, sheetIndex: 3, col: 4, row: 1, category: "mobility-aid-users", description: "College student using a forearm crutch in graphic t-shirt", scale: 0.95 },
    { id: "char-56", cellIndex: 55, sheetIndex: 3, col: 0, row: 2, category: "families", description: "Young woman in salwar suit and scarf carrying a tote bag", scale: 0.95 },
    { id: "char-57", cellIndex: 56, sheetIndex: 3, col: 1, row: 2, category: "elderly", description: "Older gentleman with salt-and-pepper beard and square glasses", scale: 0.97 },
    { id: "char-58", cellIndex: 57, sheetIndex: 3, col: 2, row: 2, category: "sign-language-users", description: "Young man signing with an expressive two-handed gesture", scale: 0.95 },
    { id: "char-59", cellIndex: 58, sheetIndex: 3, col: 3, row: 2, category: "students", description: "Little girl with headband and bow holding a pinwheel", scale: 0.88 },
    { id: "char-60", cellIndex: 59, sheetIndex: 3, col: 4, row: 2, category: "volunteers", description: "Young woman with high bun and hoop earrings in structured blazer", scale: 0.96 },
  ];

  window.PFG27_CROWD_REGISTRY = {
    WELCOMING_GROUP_CHARACTERS: WELCOMING_GROUP_CHARACTERS,
    CROWD_CHARACTER_REGISTRY: CROWD_CHARACTER_REGISTRY,
  };

  function seededRandom(seed) {
    var x = Math.sin(seed * 127.1 + 311.7) * 43758.5453;
    return x - Math.floor(x);
  }

  /**
   * Deterministic Fisher-Yates shuffle of [0..TOTAL_CAST-1] so every active actor
   * receives a strictly unique character with zero duplicates on screen.
   */
  function createShuffledCastPool() {
    var pool = [];
    for (var k = 0; k < TOTAL_CAST; k++) {
      pool.push(k);
    }
    for (var i = pool.length - 1; i > 0; i--) {
      var j = Math.floor(seededRandom(i * 19 + 73) * (i + 1));
      var tmp = pool[i];
      pool[i] = pool[j];
      pool[j] = tmp;
    }
    return pool;
  }

  function initCrowdFooter() {
    var sceneEl = document.getElementById("pfg-crowd-scene");
    var backCanvas = document.getElementById("pfg-crowd-back-canvas");
    var frontCanvas = document.getElementById("pfg-crowd-front-canvas");
    if (!sceneEl || !backCanvas || !frontCanvas) return;

    var backCtx = backCanvas.getContext("2d");
    var frontCtx = frontCanvas.getContext("2d");
    if (!backCtx || !frontCtx) return;

    var pauseBtn = document.getElementById("pfg-crowd-pause-btn");
    var pauseIcon = document.getElementById("pfg-crowd-icon-pause");
    var playIcon = document.getElementById("pfg-crowd-icon-play");
    var pauseLabel = document.getElementById("pfg-crowd-pause-label");

    var ctxs = [backCtx, frontCtx];
    var imgs = CROWD_SHEETS.map(function (src) {
      var im = new Image();
      im.decoding = "async";
      im.src = src;
      return im;
    });

    var paused = false;
    var reducedMotion = false;
    var assetsReady = false;
    var inViewport = false;
    var width = 0;
    var height = 0;
    var dpr = 1;
    var elapsedSec = 0;
    var lastTimestamp = 0;
    var rafId = 0;
    var maxActorWidth = 0;
    var totalTrackSpan = 0;
    var actors = [];
    var reserveQueue = [];

    function checkReducedMotion() {
      var osReduced = false;
      try {
        osReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      } catch (e) {
        osReduced = false;
      }
      var attrReduced =
        document.documentElement.getAttribute("data-reduced-motion") === "true" ||
        (window.PFG27_A11Y && window.PFG27_A11Y.getPrefs().reducedMotion);
      return Boolean(osReduced || attrReduced);
    }

    function updateControlsUi() {
      reducedMotion = checkReducedMotion();
      var isStopped = paused || reducedMotion;

      sceneEl.disabled = reducedMotion;
      sceneEl.setAttribute("aria-pressed", isStopped ? "true" : "false");
      sceneEl.setAttribute(
        "aria-label",
        isStopped
          ? "PURPLE FESTIVAL. Resume inclusive crowd animation"
          : "PURPLE FESTIVAL. Pause inclusive crowd animation"
      );

      if (pauseBtn) {
        pauseBtn.disabled = reducedMotion;
        pauseBtn.setAttribute("aria-pressed", isStopped ? "true" : "false");
        pauseBtn.setAttribute(
          "aria-label",
          reducedMotion
            ? "Crowd animation paused due to reduced motion preference"
            : isStopped
              ? "Resume animated footer crowd"
              : "Pause animated footer crowd"
        );
      }

      if (pauseIcon && playIcon) {
        if (isStopped) {
          pauseIcon.classList.add("hidden");
          playIcon.classList.remove("hidden");
        } else {
          pauseIcon.classList.remove("hidden");
          playIcon.classList.add("hidden");
        }
      }

      if (pauseLabel) {
        pauseLabel.textContent = isStopped
          ? reducedMotion
            ? "Motion Reduced"
            : "Resume Animation"
          : "Pause Animation";
      }
    }

    function drawFrame() {
      for (var c = 0; c < ctxs.length; c++) {
        ctxs[c].setTransform(dpr, 0, 0, dpr, 0, 0);
        ctxs[c].clearRect(0, 0, width, height);
      }

      if (!assetsReady || totalTrackSpan <= 0) return;

      var centerX = width / 2;
      var centerClearanceRadius = Math.min(240, width * 0.24);

      for (var i = 0; i < actors.length; i++) {
        var actor = actors[i];
        var rawProgress =
          actor.phase + (elapsedSec * actor.speedPxPerSec) / totalTrackSpan;
        var wrapCount = Math.floor(rawProgress);
        var norm = rawProgress - wrapCount;

        // When an actor wraps off-screen, swap its character with an off-screen reserve character
        // so two actors on screen NEVER share the same character!
        if (wrapCount !== actor.lastWrapCount) {
          actor.lastWrapCount = wrapCount;
          if (reserveQueue.length > 0) {
            var nextChar = reserveQueue.shift();
            reserveQueue.push(actor.charIdx);
            actor.charIdx = nextChar;
          }
        }

        var charIdx = actor.charIdx;
        var meta = CROWD_CHARACTER_REGISTRY[charIdx];
        var charScale = meta && typeof meta.scale === "number" ? meta.scale : 1.0;

        var sheetIdx = Math.floor(charIdx / CHARS_PER_SHEET);
        var sheet = imgs[sheetIdx];
        if (!sheet || !sheet.naturalWidth) continue;

        var cellW = sheet.naturalWidth / COLS;
        var cellH = sheet.naturalHeight / ROWS;
        var localIdx = charIdx % CHARS_PER_SHEET;
        var sx = (localIdx % COLS) * cellW;
        var sy = Math.floor(localIdx / COLS) * cellH;

        var x =
          (actor.direction === 1 ? norm : 1 - norm) * totalTrackSpan -
          maxActorWidth;

        var bob = reducedMotion
          ? 0
          : (1 -
              Math.cos(
                (elapsedSec * actor.bobFreq + actor.phase * 10) * Math.PI * 2
              )) *
            actor.bobAmp;

        // Smooth courtesy dip behind the central welcoming trio
        var distFromCenter = Math.abs(x - centerX);
        var centerDip =
          distFromCenter < centerClearanceRadius
            ? Math.cos((distFromCenter / centerClearanceRadius) * (Math.PI / 2)) *
              34
            : 0;

        var drawW = actor.w * charScale;
        var drawH = actor.h * charScale;
        var yPos = actor.baseline + (actor.h - drawH) + centerDip - bob;

        var ctx = ctxs[actor.canvasLayer];
        ctx.save();
        ctx.translate(x, yPos);
        ctx.scale(actor.direction, 1);
        ctx.drawImage(
          sheet,
          sx,
          sy,
          cellW,
          cellH,
          -drawW / 2,
          0,
          drawW,
          drawH
        );
        ctx.restore();
      }
    }

    function tick(now) {
      if (lastTimestamp) {
        elapsedSec += Math.min((now - lastTimestamp) / 1000, 0.05);
      }
      lastTimestamp = now;
      drawFrame();
      rafId = requestAnimationFrame(tick);
    }

    function syncAnimation() {
      if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = 0;
      }
      lastTimestamp = 0;
      updateControlsUi();
      if (!assetsReady) return;

      drawFrame();

      var shouldAnimate =
        inViewport && !document.hidden && !paused && !reducedMotion;

      if (shouldAnimate) {
        rafId = requestAnimationFrame(tick);
      }
    }

    function rebuildLayout() {
      if (!assetsReady) return;
      var newW = sceneEl.clientWidth;
      var newH = sceneEl.clientHeight;
      if (!newW || !newH) return;
      if (newW === width && newH === height) return;

      width = newW;
      height = newH;
      dpr = Math.min(window.devicePixelRatio || 1, 2);

      backCanvas.width = Math.round(width * dpr);
      backCanvas.height = Math.round(height * dpr);
      frontCanvas.width = Math.round(width * dpr);
      frontCanvas.height = Math.round(height * dpr);

      var isDesktop = width >= 960;
      var isTablet = width >= 600 && width < 960;
      var scale = isDesktop ? 1 : isTablet ? 0.82 : 0.66;

      // Total active actors is always <= 34 (well below TOTAL_CAST = 60), guaranteeing 100% unique characters on screen!
      var counts = isDesktop
        ? [11, 11, 12]
        : isTablet
          ? [8, 8, 8]
          : [5, 5, 5];

      var shuffledPool = createShuffledCastPool();
      var totalActive = counts[0] + counts[1] + counts[2];
      reserveQueue = shuffledPool.slice(totalActive);

      var nextActors = [];
      var globalIdx = 0;

      for (var tier = 0; tier < 3; tier++) {
        var tierCount = counts[tier];
        for (var i = 0; i < tierCount; i++) {
          var r1 = seededRandom(globalIdx + 7);
          var r2 = seededRandom(globalIdx + 23);
          var r3 = seededRandom(globalIdx + 59);
          var r4 = seededRandom(globalIdx + 97);

          var charH;
          var charW;
          var baseline;
          var speedPxPerSec;
          var direction;
          var canvasLayer;
          var bobAmp;

          if (tier === 0) {
            // BACKGROUND CROWD: Smaller head scale, higher head position, moves slowly Right -> Left (17–24 px/s)
            charH = (325 + 18 * r1) * scale;
            charW = charH * CELL_ASPECT * 0.86;
            baseline = height - charH + (26 + 12 * r2) * scale;
            speedPxPerSec = 17 + 7 * r3;
            direction = -1;
            canvasLayer = 0;
            bobAmp = 2.2 * scale;
          } else if (tier === 1) {
            // MIDGROUND CROWD: Medium head scale, middle head position, moves Left -> Right (27–36 px/s)
            charH = (318 + 18 * r1) * scale;
            charW = charH * CELL_ASPECT * 0.94;
            baseline = height - charH + (64 + 12 * r2) * scale;
            speedPxPerSec = 27 + 9 * r3;
            direction = 1;
            canvasLayer = 0;
            bobAmp = 3.2 * scale;
          } else {
            // FOREGROUND CROWD: Larger head scale, front row position, moves Right -> Left (38–48 px/s)
            charH = (312 + 20 * r1) * scale;
            charW = charH * CELL_ASPECT * 1.02;
            baseline = height - charH + (104 + 10 * r2) * scale;
            speedPxPerSec = 38 + 10 * r3;
            direction = -1;
            canvasLayer = 1;
            bobAmp = 4.0 * scale;
          }

          nextActors.push({
            actorId: globalIdx,
            charIdx: shuffledPool[globalIdx],
            lastWrapCount: 0,
            tier: tier,
            canvasLayer: canvasLayer,
            h: charH,
            w: charW,
            baseline: baseline,
            speedPxPerSec: speedPxPerSec,
            phase: (i + 0.45 * r4) / tierCount,
            direction: direction,
            bobAmp: bobAmp,
            bobFreq: 0.72 + 0.32 * r3,
          });
          globalIdx++;
        }
      }

      nextActors.sort(function (a, b) {
        return a.baseline - b.baseline;
      });
      actors = nextActors;

      var maxW = 180;
      for (var m = 0; m < actors.length; m++) {
        if (actors[m].w > maxW) maxW = actors[m].w;
      }
      maxActorWidth = maxW;
      totalTrackSpan = width + 2 * maxActorWidth;
      syncAnimation();
    }

    function togglePause() {
      if (checkReducedMotion()) return;
      paused = !paused;
      syncAnimation();
    }

    sceneEl.addEventListener("click", function (e) {
      e.preventDefault();
      togglePause();
    });

    if (pauseBtn) {
      pauseBtn.addEventListener("click", function (e) {
        e.preventDefault();
        togglePause();
      });
    }

    window.addEventListener("pfg27:a11y-changed", function () {
      syncAnimation();
    });

    try {
      var mq = window.matchMedia("(prefers-reduced-motion: reduce)");
      if (mq && typeof mq.addEventListener === "function") {
        mq.addEventListener("change", syncAnimation);
      }
    } catch (e) {
      // Ignore
    }

    if (typeof IntersectionObserver !== "undefined") {
      var io = new IntersectionObserver(
        function (entries) {
          inViewport = Boolean(entries[0] && entries[0].isIntersecting);
          syncAnimation();
        },
        { rootMargin: "100px" }
      );
      io.observe(sceneEl);
    } else {
      inViewport = true;
    }

    if (typeof ResizeObserver !== "undefined") {
      var ro = new ResizeObserver(rebuildLayout);
      ro.observe(sceneEl);
    } else {
      window.addEventListener("resize", rebuildLayout);
    }

    document.addEventListener("visibilitychange", syncAnimation);

    var loadedCount = 0;
    function onSheetReady() {
      loadedCount++;
      if (!assetsReady) {
        assetsReady = true;
        rebuildLayout();
      } else {
        syncAnimation();
      }
    }

    for (var s = 0; s < imgs.length; s++) {
      (function (im) {
        if (im.decode) {
          im.decode()
            .then(onSheetReady)
            .catch(function () {
              onSheetReady();
            });
        } else {
          im.onload = onSheetReady;
          im.onerror = onSheetReady;
        }
      })(imgs[s]);
    }

    updateControlsUi();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCrowdFooter);
  } else {
    initCrowdFooter();
  }
})();
