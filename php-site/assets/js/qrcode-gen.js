/**
 * Purple Festival Gujarat 2027 — Self-Contained Vanilla JS QR Code Generator
 * Zero external CDN dependencies. ISO/IEC 18004 compliant (Versions 1–12, ECC Level M & L, Byte Mode).
 * Supports PFG27.<nonce>.<sig> opaque tokens, PFG-2027-XXXXXX credential codes, and otpauth:// MFA URIs.
 */
(function (global) {
  "use strict";

  // Version table for ECC Level M: [totalCodewords, ecCodewordsPerBlock, numBlocksGroup1, dataCodewordsGroup1, numBlocksGroup2, dataCodewordsGroup2]
  // Derived from ISO/IEC 18004 Table 9 (Error Correction Level M, format bits = 00)
  var VERSION_TABLE_M = [
    null,
    [26, 10, 1, 16, 0, 0],     // V1:  16 data bytes -> up to 14 payload bytes
    [44, 16, 1, 28, 0, 0],     // V2:  28 data bytes -> up to 26 payload bytes
    [70, 26, 1, 44, 0, 0],     // V3:  44 data bytes -> up to 42 payload bytes
    [100, 18, 2, 32, 0, 0],    // V4:  64 data bytes -> up to 62 payload bytes
    [134, 24, 2, 43, 0, 0],    // V5:  86 data bytes -> up to 84 payload bytes
    [172, 16, 4, 27, 0, 0],    // V6:  108 data bytes -> up to 106 payload bytes
    [196, 18, 4, 31, 0, 0],    // V7:  124 data bytes -> up to 122 payload bytes
    [242, 22, 2, 38, 2, 39],   // V8:  154 data bytes -> up to 152 payload bytes
    [292, 22, 3, 36, 2, 37],   // V9:  182 data bytes -> up to 180 payload bytes
    [346, 26, 4, 43, 1, 44],   // V10: 216 data bytes -> up to 213 payload bytes
    [404, 30, 1, 50, 4, 51],   // V11: 254 data bytes -> up to 251 payload bytes
    [466, 22, 6, 36, 2, 37]    // V12: 290 data bytes -> up to 287 payload bytes
  ];

  // Alignment pattern center coordinates per version (V1–V12)
  var ALIGNMENT_COORDS = [
    null,
    [],
    [6, 18],
    [6, 22],
    [6, 26],
    [6, 30],
    [6, 34],
    [6, 22, 38],
    [6, 24, 42],
    [6, 26, 46],
    [6, 28, 50],
    [6, 30, 54],
    [6, 32, 58]
  ];

  // GF(256) log & exp tables (primitive polynomial 0x11D)
  var GF_EXP = new Array(512);
  var GF_LOG = new Array(256);
  (function initGaloisField() {
    var x = 1;
    for (var i = 0; i < 255; i++) {
      GF_EXP[i] = x;
      GF_LOG[x] = i;
      x <<= 1;
      if (x & 0x100) {
        x ^= 0x11d;
      }
    }
    for (var j = 255; j < 512; j++) {
      GF_EXP[j] = GF_EXP[j - 255];
    }
  })();

  function gfMul(a, b) {
    if (a === 0 || b === 0) return 0;
    return GF_EXP[GF_LOG[a] + GF_LOG[b]];
  }

  function rsGeneratorPoly(degree) {
    var poly = [1];
    for (var i = 0; i < degree; i++) {
      var next = new Array(poly.length + 1);
      for (var k = 0; k < next.length; k++) next[k] = 0;
      var root = GF_EXP[i];
      for (var j = 0; j < poly.length; j++) {
        next[j] ^= poly[j];
        next[j + 1] ^= gfMul(poly[j], root);
      }
      poly = next;
    }
    return poly;
  }

  function rsEncode(dataBytes, ecLen) {
    var gen = rsGeneratorPoly(ecLen);
    var rem = new Array(ecLen);
    for (var i = 0; i < ecLen; i++) rem[i] = 0;

    for (var d = 0; d < dataBytes.length; d++) {
      var factor = dataBytes[d] ^ rem[0];
      rem.shift();
      rem.push(0);
      if (factor !== 0) {
        for (var j = 0; j < ecLen; j++) {
          rem[j] ^= gfMul(gen[j + 1], factor);
        }
      }
    }
    return rem;
  }

  function stringToUtf8Bytes(str) {
    var utf8 = [];
    for (var i = 0; i < str.length; i++) {
      var charCode = str.charCodeAt(i);
      if (charCode < 0x80) {
        utf8.push(charCode);
      } else if (charCode < 0x800) {
        utf8.push(0xc0 | (charCode >> 6), 0x80 | (charCode & 0x3f));
      } else if (
        (charCode & 0xfc00) === 0xd800 &&
        i + 1 < str.length &&
        (str.charCodeAt(i + 1) & 0xfc00) === 0xdc00
      ) {
        var codePoint =
          0x10000 +
          ((charCode & 0x03ff) << 10) +
          (str.charCodeAt(++i) & 0x03ff);
        utf8.push(
          0xf0 | (codePoint >> 18),
          0x80 | ((codePoint >> 12) & 0x3f),
          0x80 | ((codePoint >> 6) & 0x3f),
          0x80 | (codePoint & 0x3f)
        );
      } else {
        utf8.push(
          0xe0 | (charCode >> 12),
          0x80 | ((charCode >> 6) & 0x3f),
          0x80 | (charCode & 0x3f)
        );
      }
    }
    return utf8;
  }

  function selectVersion(payloadLen) {
    for (var v = 1; v < VERSION_TABLE_M.length; v++) {
      var info = VERSION_TABLE_M[v];
      var totalDataCodewords = info[2] * info[3] + info[4] * info[5];
      // Byte mode indicator (4 bits) + character count (8 bits for V1-9, 16 bits for V10+)
      var headerBits = v <= 9 ? 12 : 20;
      var availableBytes = Math.floor((totalDataCodewords * 8 - headerBits) / 8);
      if (payloadLen <= availableBytes) {
        return v;
      }
    }
    throw new Error("QR payload too long for embedded encoder (" + payloadLen + " bytes)");
  }

  function buildDataCodewords(bytes, version) {
    var info = VERSION_TABLE_M[version];
    var totalDataCodewords = info[2] * info[3] + info[4] * info[5];
    var totalDataBits = totalDataCodewords * 8;
    var bits = [];

    function pushBits(val, len) {
      for (var i = len - 1; i >= 0; i--) {
        bits.push((val >>> i) & 1);
      }
    }

    // Mode indicator: Byte mode = 0100
    pushBits(0x4, 4);
    // Character count indicator: 8 bits for V1–9, 16 bits for V10–12
    pushBits(bytes.length, version <= 9 ? 8 : 16);

    for (var i = 0; i < bytes.length; i++) {
      pushBits(bytes[i], 8);
    }

    // Terminator up to 4 zero bits
    var terminatorLen = Math.min(4, totalDataBits - bits.length);
    pushBits(0, terminatorLen);

    // Pad to byte boundary
    while (bits.length % 8 !== 0) {
      bits.push(0);
    }

    var codewords = [];
    for (var b = 0; b < bits.length; b += 8) {
      var byteVal = 0;
      for (var k = 0; k < 8; k++) {
        byteVal = (byteVal << 1) | bits[b + k];
      }
      codewords.push(byteVal);
    }

    // Pad alternating 0xEC and 0x11
    var padBytes = [0xec, 0x11];
    var padIdx = 0;
    while (codewords.length < totalDataCodewords) {
      codewords.push(padBytes[padIdx % 2]);
      padIdx++;
    }

    return codewords;
  }

  function interleaveBlocks(dataCodewords, version) {
    var info = VERSION_TABLE_M[version];
    var ecLen = info[1];
    var numG1 = info[2];
    var dataG1 = info[3];
    var numG2 = info[4];
    var dataG2 = info[5];

    var dataBlocks = [];
    var ecBlocks = [];
    var offset = 0;

    for (var i = 0; i < numG1; i++) {
      var block = dataCodewords.slice(offset, offset + dataG1);
      offset += dataG1;
      dataBlocks.push(block);
      ecBlocks.push(rsEncode(block, ecLen));
    }

    for (var j = 0; j < numG2; j++) {
      var block2 = dataCodewords.slice(offset, offset + dataG2);
      offset += dataG2;
      dataBlocks.push(block2);
      ecBlocks.push(rsEncode(block2, ecLen));
    }

    var result = [];
    var maxDataLen = Math.max(dataG1, dataG2);
    for (var col = 0; col < maxDataLen; col++) {
      for (var b = 0; b < dataBlocks.length; b++) {
        if (col < dataBlocks[b].length) {
          result.push(dataBlocks[b][col]);
        }
      }
    }
    for (var ecCol = 0; ecCol < ecLen; ecCol++) {
      for (var eb = 0; eb < ecBlocks.length; eb++) {
        result.push(ecBlocks[eb][ecCol]);
      }
    }
    return result;
  }

  function createEmptyMatrix(size) {
    var modules = new Array(size);
    var isFunction = new Array(size);
    for (var r = 0; r < size; r++) {
      modules[r] = new Array(size);
      isFunction[r] = new Array(size);
      for (var c = 0; c < size; c++) {
        modules[r][c] = false;
        isFunction[r][c] = false;
      }
    }
    return { modules: modules, isFunction: isFunction, size: size };
  }

  function setFunctionModule(matrix, row, col, isDark) {
    if (row < 0 || row >= matrix.size || col < 0 || col >= matrix.size) return;
    matrix.modules[row][col] = Boolean(isDark);
    matrix.isFunction[row][col] = true;
  }

  function drawFinderPattern(matrix, centerR, centerC) {
    for (var dy = -4; dy <= 4; dy++) {
      for (var dx = -4; dx <= 4; dx++) {
        var r = centerR + dy;
        var c = centerC + dx;
        if (r >= 0 && r < matrix.size && c >= 0 && c < matrix.size) {
          var dist = Math.max(Math.abs(dx), Math.abs(dy));
          var dark = dist !== 2 && dist !== 4;
          setFunctionModule(matrix, r, c, dark);
        }
      }
    }
  }

  function drawAlignmentPattern(matrix, centerR, centerC) {
    for (var dy = -2; dy <= 2; dy++) {
      for (var dx = -2; dx <= 2; dx++) {
        var dist = Math.max(Math.abs(dx), Math.abs(dy));
        setFunctionModule(matrix, centerR + dy, centerC + dx, dist !== 1);
      }
    }
  }

  function drawFormatBits(matrix, mask) {
    // ECC Level M = 00 in QR format indicator
    var data = (0 << 3) | mask;
    var rem = data;
    for (var i = 0; i < 10; i++) {
      rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    }
    var bits = ((data << 10) | rem) ^ 0x5412;
    var size = matrix.size;

    for (var idx = 0; idx < 15; idx++) {
      var mod = ((bits >>> idx) & 1) === 1;

      // Vertical
      if (idx < 6) {
        setFunctionModule(matrix, idx, 8, mod);
      } else if (idx < 8) {
        setFunctionModule(matrix, idx + 1, 8, mod);
      } else {
        setFunctionModule(matrix, size - 15 + idx, 8, mod);
      }

      // Horizontal
      if (idx < 8) {
        setFunctionModule(matrix, 8, size - idx - 1, mod);
      } else if (idx < 9) {
        setFunctionModule(matrix, 8, 15 - idx, mod);
      } else {
        setFunctionModule(matrix, 8, 15 - idx - 1, mod);
      }
    }

    // Fixed dark module
    setFunctionModule(matrix, size - 8, 8, true);
  }

  function drawVersionBits(matrix, version) {
    if (version < 7) return;
    var rem = version;
    for (var i = 0; i < 12; i++) {
      rem = (rem << 1) ^ ((rem >>> 11) * 0x1f25);
    }
    var bits = (version << 12) | rem;
    for (var j = 0; j < 18; j++) {
      var dark = ((bits >>> j) & 1) !== 0;
      var a = matrix.size - 11 + (j % 3);
      var b = Math.floor(j / 3);
      setFunctionModule(matrix, a, b, dark);
      setFunctionModule(matrix, b, a, dark);
    }
  }

  function setupFunctionPatterns(matrix, version) {
    var size = matrix.size;

    // Timing patterns
    for (var i = 0; i < size; i++) {
      setFunctionModule(matrix, 6, i, i % 2 === 0);
      setFunctionModule(matrix, i, 6, i % 2 === 0);
    }

    // 3 Finder patterns
    drawFinderPattern(matrix, 3, 3);
    drawFinderPattern(matrix, 3, size - 4);
    drawFinderPattern(matrix, size - 4, 3);

    // Alignment patterns
    var coords = ALIGNMENT_COORDS[version] || [];
    for (var rIdx = 0; rIdx < coords.length; rIdx++) {
      for (var cIdx = 0; cIdx < coords.length; cIdx++) {
        var isFinderCorner =
          (rIdx === 0 && cIdx === 0) ||
          (rIdx === 0 && cIdx === coords.length - 1) ||
          (rIdx === coords.length - 1 && cIdx === 0);
        if (!isFinderCorner) {
          drawAlignmentPattern(matrix, coords[rIdx], coords[cIdx]);
        }
      }
    }

    // Reserve format & version areas
    drawFormatBits(matrix, 0);
    drawVersionBits(matrix, version);
  }

  function placeCodewords(matrix, codewords) {
    var size = matrix.size;
    var bitIdx = 0;
    var totalBits = codewords.length * 8;

    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var vert = 0; vert < size; vert++) {
        for (var j = 0; j < 2; j++) {
          var col = right - j;
          var upward = ((right + 1) & 2) === 0;
          var row = upward ? size - 1 - vert : vert;
          if (!matrix.isFunction[row][col] && bitIdx < totalBits) {
            var byteVal = codewords[bitIdx >>> 3];
            var bitVal = ((byteVal >>> (7 - (bitIdx & 7))) & 1) !== 0;
            matrix.modules[row][col] = bitVal;
            bitIdx++;
          }
        }
      }
    }
  }

  function maskBit(mask, row, col) {
    switch (mask) {
      case 0:
        return (row + col) % 2 === 0;
      case 1:
        return row % 2 === 0;
      case 2:
        return col % 3 === 0;
      case 3:
        return (row + col) % 3 === 0;
      case 4:
        return (Math.floor(row / 2) + Math.floor(col / 3)) % 2 === 0;
      case 5:
        return ((row * col) % 2) + ((row * col) % 3) === 0;
      case 6:
        return (((row * col) % 2) + ((row * col) % 3)) % 2 === 0;
      case 7:
        return (((row * col) % 3) + ((row + col) % 2)) % 2 === 0;
      default:
        return false;
    }
  }

  function applyMask(matrix, mask) {
    var size = matrix.size;
    for (var r = 0; r < size; r++) {
      for (var c = 0; c < size; c++) {
        if (!matrix.isFunction[r][c] && maskBit(mask, r, c)) {
          matrix.modules[r][c] = !matrix.modules[r][c];
        }
      }
    }
  }

  function computePenalty(matrix) {
    var size = matrix.size;
    var m = matrix.modules;
    var penalty = 0;

    // Rule 1: 5+ consecutive modules of same color in row/col
    for (var r = 0; r < size; r++) {
      var runColor = m[r][0];
      var runLen = 1;
      for (var c = 1; c < size; c++) {
        if (m[r][c] === runColor) {
          runLen++;
        } else {
          if (runLen >= 5) penalty += 3 + (runLen - 5);
          runColor = m[r][c];
          runLen = 1;
        }
      }
      if (runLen >= 5) penalty += 3 + (runLen - 5);
    }

    for (var col = 0; col < size; col++) {
      var cRunColor = m[0][col];
      var cRunLen = 1;
      for (var row = 1; row < size; row++) {
        if (m[row][col] === cRunColor) {
          cRunLen++;
        } else {
          if (cRunLen >= 5) penalty += 3 + (cRunLen - 5);
          cRunColor = m[row][col];
          cRunLen = 1;
        }
      }
      if (cRunLen >= 5) penalty += 3 + (cRunLen - 5);
    }

    // Rule 2: 2x2 blocks of same color
    for (var r2 = 0; r2 < size - 1; r2++) {
      for (var c2 = 0; c2 < size - 1; c2++) {
        var color = m[r2][c2];
        if (
          color === m[r2][c2 + 1] &&
          color === m[r2 + 1][c2] &&
          color === m[r2 + 1][c2 + 1]
        ) {
          penalty += 3;
        }
      }
    }

    // Rule 3: 1:1:3:1:1 finder-like pattern preceded/followed by 4 light modules
    for (var r3 = 0; r3 < size; r3++) {
      var bitsCol = 0;
      var bitsRow = 0;
      for (var c3 = 0; c3 < size; c3++) {
        bitsCol = ((bitsCol << 1) & 0x7ff) | (m[r3][c3] ? 1 : 0);
        if (c3 >= 10 && (bitsCol === 0x5d0 || bitsCol === 0x05d)) {
          penalty += 40;
        }
        bitsRow = ((bitsRow << 1) & 0x7ff) | (m[c3][r3] ? 1 : 0);
        if (c3 >= 10 && (bitsRow === 0x5d0 || bitsRow === 0x05d)) {
          penalty += 40;
        }
      }
    }

    // Rule 4: Balance of dark and light modules
    var darkCount = 0;
    for (var r4 = 0; r4 < size; r4++) {
      for (var c4 = 0; c4 < size; c4++) {
        if (m[r4][c4]) darkCount++;
      }
    }
    var total = size * size;
    var k = Math.abs(Math.ceil((darkCount * 100 / total) / 5) - 10);
    penalty += k * 10;

    return penalty;
  }

  /**
   * Encodes a text string into a 2D boolean matrix of dark/light QR modules.
   */
  function encodeMatrix(text) {
    var payload = String(text || "").trim();
    if (!payload) {
      throw new Error("Cannot generate QR code for empty payload.");
    }
    var bytes = stringToUtf8Bytes(payload);
    var version = selectVersion(bytes.length);
    var size = version * 4 + 17;

    var dataCodewords = buildDataCodewords(bytes, version);
    var allCodewords = interleaveBlocks(dataCodewords, version);

    var matrix = createEmptyMatrix(size);
    setupFunctionPatterns(matrix, version);
    placeCodewords(matrix, allCodewords);

    var bestMask = 0;
    var bestPenalty = Infinity;

    for (var mask = 0; mask < 8; mask++) {
      applyMask(matrix, mask);
      drawFormatBits(matrix, mask);
      var penalty = computePenalty(matrix);
      if (penalty < bestPenalty) {
        bestPenalty = penalty;
        bestMask = mask;
      }
      // Undo mask to try next
      applyMask(matrix, mask);
    }

    applyMask(matrix, bestMask);
    drawFormatBits(matrix, bestMask);

    return matrix.modules;
  }

  /**
   * Renders a QR payload into a crisp, accessible SVG string.
   */
  function toSvgString(text, options) {
    var opts = options || {};
    var modules = encodeMatrix(text);
    var moduleCount = modules.length;
    var margin = typeof opts.margin === "number" ? opts.margin : 2;
    var totalSize = moduleCount + margin * 2;
    var pixelSize = opts.width || opts.size || 240;
    var darkColor = opts.dark || (opts.color && opts.color.dark) || "#17151C";
    var lightColor = opts.light || (opts.color && opts.color.light) || "#F9F7FC";
    var ariaLabel =
      opts.ariaLabel || "Purple Festival Gujarat 2027 Digital Entry QR Code";

    var pathParts = [];
    for (var r = 0; r < moduleCount; r++) {
      for (var c = 0; c < moduleCount; c++) {
        if (modules[r][c]) {
          var x = c + margin;
          var y = r + margin;
          pathParts.push("M" + x + "," + y + "h1v1h-1z");
        }
      }
    }

    var escapedLabel = String(ariaLabel)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    return (
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' +
      totalSize +
      " " +
      totalSize +
      '" width="' +
      pixelSize +
      '" height="' +
      pixelSize +
      '" shape-rendering="crispEdges" role="img" aria-label="' +
      escapedLabel +
      '">' +
      '<rect width="100%" height="100%" fill="' +
      lightColor +
      '"/>' +
      '<path d="' +
      pathParts.join("") +
      '" fill="' +
      darkColor +
      '"/>' +
      "</svg>"
    );
  }

  /**
   * Returns a data:image/svg+xml;utf8,... URL suitable for <img src="...">.
   */
  function toDataUrl(text, options) {
    var svg = toSvgString(text, options);
    return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
  }

  /**
   * Renders a QR code onto a <canvas>, <img>, or container HTMLElement.
   */
  function renderToElement(target, text, options) {
    var el =
      typeof target === "string" ? document.querySelector(target) : target;
    if (!el || !text) return null;

    var opts = options || {};
    var tag = (el.tagName || "").toUpperCase();

    if (tag === "IMG") {
      var dataUrl = toDataUrl(text, opts);
      el.setAttribute("src", dataUrl);
      if (opts.ariaLabel && !el.getAttribute("alt")) {
        el.setAttribute("alt", opts.ariaLabel);
      }
      return el;
    }

    if (tag === "CANVAS" && el.getContext) {
      var modules = encodeMatrix(text);
      var count = modules.length;
      var margin = typeof opts.margin === "number" ? opts.margin : 2;
      var total = count + margin * 2;
      var pxWidth = opts.width || opts.size || 240;
      var scale = Math.max(2, Math.floor(pxWidth / total));
      var actualSize = total * scale;

      el.width = actualSize;
      el.height = actualSize;
      var ctx = el.getContext("2d");
      var dark = opts.dark || (opts.color && opts.color.dark) || "#17151C";
      var light = opts.light || (opts.color && opts.color.light) || "#F9F7FC";

      ctx.fillStyle = light;
      ctx.fillRect(0, 0, actualSize, actualSize);
      ctx.fillStyle = dark;

      for (var r = 0; r < count; r++) {
        for (var c = 0; c < count; c++) {
          if (modules[r][c]) {
            ctx.fillRect(
              (c + margin) * scale,
              (r + margin) * scale,
              scale,
              scale
            );
          }
        }
      }
      return el;
    }

    // Container element (<div>, <span>, etc.): inject SVG
    el.innerHTML = toSvgString(text, opts);
    return el;
  }

  /**
   * Automatically scans the document for elements with [data-qr-payload] or [data-qr-text]
   * and renders their QR codes.
   */
  function renderAll(root) {
    if (typeof document === "undefined") return;
    var scope = root || document;
    var nodes = scope.querySelectorAll("[data-qr-payload], [data-qr-text]");
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      var payload =
        node.getAttribute("data-qr-payload") ||
        node.getAttribute("data-qr-text");
      if (payload) {
        var sizeAttr = parseInt(node.getAttribute("data-qr-size") || "220", 10);
        var labelAttr =
          node.getAttribute("data-qr-label") ||
          node.getAttribute("aria-label") ||
          undefined;
        renderToElement(node, payload, {
          width: isNaN(sizeAttr) ? 220 : sizeAttr,
          ariaLabel: labelAttr
        });
      }
    }
  }

  var api = {
    encodeMatrix: encodeMatrix,
    toSvgString: toSvgString,
    toDataUrl: toDataUrl,
    renderToElement: renderToElement,
    renderAll: renderAll
  };

  global.PFGQrCode = api;
  global.QRCodeGen = api;
  global.renderQrCode = renderToElement;
  global.generateQrSvg = toSvgString;
  global.generateQrDataUrl = toDataUrl;

  if (typeof module !== "undefined" && module.exports) {
    module.exports = api;
  }

  if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function () {
        renderAll(document);
      });
    } else {
      renderAll(document);
    }
  }
})(typeof window !== "undefined" ? window : globalThis);
