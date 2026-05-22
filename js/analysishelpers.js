'use strict';

//Posit helpers

function decodePosit(i, n, es) {
  var NAR = 1 << (n - 1);
  if (i === 0)   return 0;
  if (i === NAR) return NaN;
  var sign   = (i > NAR) ? -1 : 1;
  var absInt = (sign < 0) ? ((~i + 1) & ((1 << n) - 1)) : i;
  var bits   = absInt.toString(2).padStart(n, '0');
  var rb = parseInt(bits[1]), run = 1, j = 2;
  while (j < n && parseInt(bits[j]) === rb) { run++; j++; }
  j++;
  var k = (rb === 1) ? run - 1 : -run;
  var expVal = 0;
  for (var b = 0; b < es && j < n; b++, j++) expVal = (expVal << 1) | parseInt(bits[j]);
  var frac = 1.0, w = 0.5;
  while (j < n) { frac += parseInt(bits[j++]) * w; w /= 2; }
  return sign * Math.pow(Math.pow(2, Math.pow(2, es)), k) * Math.pow(2, expVal) * frac;
}

function buildPositLUT(n, es) {
  var NAR = 1 << (n - 1);
  var lut = new Float64Array(NAR - 1);
  for (var i = 1; i < NAR; i++) lut[i - 1] = decodePosit(i, n, es);
  return lut;
}

function encodePosit(x, n, lut) {
  var NAR = 1 << (n - 1);
  if (x === 0 || x !== x) return 0;
  if (!isFinite(x)) return NAR;
  var sign = (x < 0) ? -1 : 1;
  var ax   = Math.abs(x);
  if (ax > lut[lut.length - 1]) return NAR;
  var lo = 0, hi = lut.length - 1;
  while (lo < hi) { var mid = (lo + hi) >> 1; if (lut[mid] < ax) lo = mid + 1; else hi = mid; }
  if (lo > 0 && Math.abs(lut[lo - 1] - ax) < Math.abs(lut[lo] - ax)) lo--;
  return (sign < 0) ? ((1 << n) - (lo + 1)) : (lo + 1);
}

//Takum helpers 
function buildTakumLUT() {
  var lut = new Float64Array(127);
  for (var i = 1; i < 128; i++) lut[i - 1] = decodeTakum(i, 8).linVal;
  return lut;
}

function encodeTakum8(x, lut) {
  if (x === 0) return 0;
  if (!isFinite(x) || x !== x) return 128;
  var sign = (x < 0) ? -1 : 1;
  var ax   = Math.abs(x);
  if (ax > lut[126]) return 128;
  var lo = 0, hi = 126;
  while (lo < hi) { var mid = (lo + hi) >> 1; if (lut[mid] < ax) lo = mid + 1; else hi = mid; }
  if (lo > 0 && Math.abs(lut[lo - 1] - ax) < Math.abs(lut[lo] - ax)) lo--;
  return (sign < 0) ? (256 - (lo + 1)) : (lo + 1);
}

function buildTakumLUT16() {
  var lut = new Float64Array(32767);
  for (var i = 1; i <= 32767; i++) lut[i - 1] = decodeTakum(i, 16).linVal;
  return lut;
}

function encodeTakum16(x, lut) {
  if (x === 0) return 0;
  if (!isFinite(x) || x !== x) return 32768;
  var sign = (x < 0) ? -1 : 1;
  var ax   = Math.abs(x);
  if (ax > lut[32766]) return 32768;
  var lo = 0, hi = 32766;
  while (lo < hi) { var mid = (lo + hi) >> 1; if (lut[mid] < ax) lo = mid + 1; else hi = mid; }
  if (lo > 0 && Math.abs(lut[lo - 1] - ax) < Math.abs(lut[lo] - ax)) lo--;
  return (sign < 0) ? (65536 - (lo + 1)) : (lo + 1);
}

// LUTs (8-bit eager, 16-bit lazy)
var POSITLUT   = buildPositLUT(8, 2);
var TAKUMLUT   = buildTakumLUT();
var POSITLUT16 = null;
var TAKUMLUT16 = null;

function ensure16bitLUTs() {
  if (!POSITLUT16) POSITLUT16 = buildPositLUT(16, 2);
  if (!TAKUMLUT16) TAKUMLUT16 = buildTakumLUT16();
}

//Format round-trip add
function fmtAdd(a, b, fmt) {
  var s = a + b;
  if (fmt === 'float') {
    var r = encodeFloat(s, 4, 3);
    return (r.type === 'normal' || r.type === 'subnormal') ? r.value : (r.type === 'zero' ? 0 : a);
  }
  if (fmt === 'posit') {
    var p = encodePosit(s, 8, POSITLUT);
    return decodePosit(p, 8, 2);
  }
  if (fmt === 'lns') {
    var r = encodeLNS(s, 4, 3);
    return r.isZero ? 0 : r.value;
  }
  if (fmt === 'takum') {
    var p = encodeTakum8(s, TAKUMLUT);
    var d = decodeTakum(p, 8);
    return (d.type === 'zero') ? 0 : d.linVal;
  }
  if (fmt === 'float16') {
    var r = encodeFloat(s, 5, 10);
    return (r.type === 'normal' || r.type === 'subnormal') ? r.value : (r.type === 'zero' ? 0 : a);
  }
  if (fmt === 'posit16') {
    var p = encodePosit(s, 16, POSITLUT16);
    return decodePosit(p, 16, 2);
  }
  if (fmt === 'lns16') {
    var r = encodeLNS(s, 8, 7);
    return r.isZero ? 0 : r.value;
  }
  // takum16
  var p = encodeTakum16(s, TAKUMLUT16);
  var d = decodeTakum(p, 16);
  return (d.type === 'zero') ? 0 : d.linVal;
}
