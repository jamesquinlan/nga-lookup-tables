/**
 * IEEE 754-like minifloat lookup table generator
 * Supports configurable exponent (E) and mantissa (M) bit widths.
 * Format: 1 sign bit | E exponent bits | M mantissa bits
 * Follows IEEE 754 conventions: subnormals, \pm 0, \pm Inf, NaN.
 */

'use strict';


var FLOAT_PRESETS = [
  { label: 'E1M2 (4-bit)', E: 1, M: 2 },
  { label: 'E2M1 (4-bit)', E: 2, M: 1 },
  { label: 'E2M3 (6-bit)', E: 2, M: 3 },
  { label: 'E3M2 (6-bit)', E: 3, M: 2 },
  { label: 'E3M4 (8-bit)', E: 3, M: 4 },
  { label: 'E4M3 (8-bit)', E: 4, M: 3 },
  { label: 'E5M2 (8-bit)', E: 5, M: 2 },
  { label: 'E2M5 (8-bit)', E: 2, M: 5 },
  { label: 'E5M10 / FP16', E: 5, M: 10 },
  { label: 'E8M7 / BF16',  E: 8, M: 7  },
];


/**
 * Decode a bit pattern (integer i, N=1+E+M bits) to its float components.
 * @returns {{ sign, expStored, expActual, mantBits, mantValue, value, type }}
 */
function decodeFloat(i, E, M) {
  var N = 1 + E + M;
  var bits = i.toString(2);
  while (bits.length < N) bits = '0' + bits;

  var sign     = parseInt(bits[0]);
  var expBits  = bits.substring(1, 1 + E);
  var mantBits = bits.substring(1 + E);

  var bias       = (1 << (E - 1)) - 1;          // 2^(E-1) - 1
  var maxExpRaw  = (1 << E) - 1;                 // all-ones exponent
  var mantDenom  = 1 << M;                       // 2^M

  var expStored  = parseInt(expBits,  2) || 0;
  var mantStored = parseInt(mantBits, 2) || 0;

  var type, value, expActual, mantValue;

  if (expStored === 0 && mantStored === 0) {
    type      = 'zero';
    expActual = 1 - bias;
    mantValue = 0;
    value     = (sign === 0) ? 0 : -0;
  } else if (expStored === 0) {
    type      = 'subnormal';
    expActual = 1 - bias;
    mantValue = mantStored / mantDenom;
    value     = Math.pow(-1, sign) * Math.pow(2, expActual) * mantValue;
  } else if (expStored === maxExpRaw && mantStored === 0) {
    type      = 'inf';
    expActual = null;
    mantValue = null;
    value     = (sign === 0) ? Infinity : -Infinity;
  } else if (expStored === maxExpRaw) {
    type      = 'nan';
    expActual = null;
    mantValue = null;
    value     = NaN;
  } else {
    type      = 'normal';
    expActual = expStored - bias;
    mantValue = 1 + mantStored / mantDenom;
    value     = Math.pow(-1, sign) * Math.pow(2, expActual) * mantValue;
  }

  return {
    bits: bits, sign: sign,
    expBits: expBits, expStored: expStored, expActual: expActual,
    mantBits: mantBits, mantStored: mantStored, mantValue: mantValue,
    value: value, type: type, bias: bias
  };
}


/**
 * Convert a decimal value to its nearest E/M float representation.
 * Returns the same shape as decodeFloat plus { error, accuracy }.
 */
function encodeFloat(x, E, M) {
  var bias       = (1 << (E - 1)) - 1;
  var maxExpRaw  = (1 << E) - 1;
  var mantDenom  = 1 << M;
  var minNormExp = 1 - bias;
  var maxNormal  = (2 - 1 / mantDenom) * Math.pow(2, bias);
  var minSubnorm = Math.pow(2, minNormExp) / mantDenom;

  var sign = (x < 0 || (x === 0 && (1/x) === -Infinity)) ? 1 : 0;
  var ax   = Math.abs(x);

 
  if (isNaN(x)) {  // if NaN
    return decodeFloat((1 << (1 + E + M - 1)) | ((maxExpRaw << M) | 1), E, M);
  }
  if (!isFinite(x)) { // if Inf
    var infPat = (sign << (E + M)) | (maxExpRaw << M);
    return decodeFloat(infPat, E, M);
  }

  
  if (ax === 0) { // if 0
    return decodeFloat(sign << (E + M), E, M);
  }

  // Overflow maps to ±Inf
  if (ax > maxNormal) {
    var infPat2 = (sign << (E + M)) | (maxExpRaw << M);
    var r = decodeFloat(infPat2, E, M);
    r.error = Infinity; r.accuracy = -Infinity;
    return r;
  }

  // Find biased exponent (Math.log2 can be off by ±1 ULP near powers of 2)
  var eActual   = Math.floor(Math.log2(ax));
  if (ax / Math.pow(2, eActual) >= 2) eActual++;   // log2 too low
  if (ax / Math.pow(2, eActual) < 1)  eActual--;   // log2 too high
  var isSubnorm = eActual < minNormExp;

  var expStored, mantissa_f;
  if (isSubnorm) {
    expStored  = 0;
    mantissa_f = ax / Math.pow(2, minNormExp);
  } else {
    expStored  = eActual + bias;
    mantissa_f = ax / Math.pow(2, eActual) - 1;
  }

  // Round mantissa: round-to-nearest-even
  var mantRaw   = mantissa_f * mantDenom;
  var mantFloor = Math.floor(mantRaw);
  var frac      = mantRaw - mantFloor;
  var mantStored;
  if (frac > 0.5)      mantStored = mantFloor + 1;
  else if (frac < 0.5) mantStored = mantFloor;
  else                 mantStored = (mantFloor % 2 === 0) ? mantFloor : mantFloor + 1;

  // Mantissa overflow to carry into exponent
  if (mantStored >= mantDenom) {
    mantStored = 0;
    expStored++;
    if (expStored >= maxExpRaw) {
      // Overflow to ±Inf
      var infPat3 = (sign << (E + M)) | (maxExpRaw << M);
      var r2 = decodeFloat(infPat3, E, M);
      r2.error = Infinity; r2.accuracy = -Infinity;
      return r2;
    }
  }

  // Underflow to \pm 0
  if (isSubnorm && mantStored === 0) {
    var r3 = decodeFloat(sign << (E + M), E, M);
    r3.error    = (ax === 0) ? 0 : 1;
    r3.accuracy = (ax === 0) ? Infinity : 0;
    return r3;
  }

  var pat = (sign << (E + M)) | (expStored << M) | mantStored;
  var r4  = decodeFloat(pat, E, M);

  var err      = (x !== 0) ? Math.abs((x - r4.value) / x) : 0;
  var accuracy = (err > 0) ? -Math.log10(err) : Infinity;

  r4.error    = err;
  r4.accuracy = accuracy;
  return r4;
}


function colorBitsFloat(d) {
  return '<span class="bit-sign">'     + d.bits[0]      + '</span>' +
         '<span class="bit-exponent">' + d.expBits       + '</span>' +
         '<span class="bit-mantissa">' + d.mantBits      + '</span>';
}

function fmtVal(v) {
  if (isNaN(v))         return 'NaN';
  if (v === Infinity)   return '+Inf';
  if (v === -Infinity)  return '−Inf';
  if (Object.is(v, -0)) return '−0';
  if (v === 0)          return '+0';
  var a = Math.abs(v);
  var s = (a >= 1e-4 && a < 1e7) ? parseFloat(v.toPrecision(8)).toString()
                                   : v.toExponential(5);
  return s;
}

function fmtExp(d) {
  if (d.expActual === null) return '—';
  return d.expActual.toString();
}

function fmtMant(d) {
  if (d.mantValue === null) return '—';
  if (d.type === 'zero')    return '0';
  var prefix = (d.type === 'subnormal') ? '0.' : '1.';
  return prefix + d.mantBits + ' = ' + d.mantValue;
}

function rowClass(type) {
  return { zero: 'r-zero', inf: 'r-inf', nan: 'r-nan', subnormal: 'r-sub', normal: '' }[type] || '';
}

function typeLabel(type) {
  return { zero: 'Zero', subnormal: 'Subnorm', normal: 'Normal', inf: 'Inf', nan: 'NaN' }[type] || type;
}


function floatLookup() {
  var E = parseInt(document.getElementById('fl-exp').value,  10);
  var M = parseInt(document.getElementById('fl-mant').value, 10);

  var err = validateEM(E, M);
  if (err) { document.getElementById('fl-info').textContent = err; return; }

  var N     = 1 + E + M;
  var count = 1 << N;
  var bias  = (1 << (E - 1)) - 1;
  var maxNormal = (2 - Math.pow(2, -M)) * Math.pow(2, bias);
  var minSubnorm = N <= 30 ? Math.pow(2, 1 - bias - M) : '< 1e-300';

  document.getElementById('fl-info').innerHTML =
    '<strong>E' + E + 'M' + M + '</strong> &nbsp;(' + N + '-bit) &nbsp;|&nbsp; ' +
    'Bias = ' + bias + ' &nbsp;|&nbsp; ' +
    'eActual ∈ [' + (1-bias) + ', ' + (Math.pow(2,E)-2-bias) + '] (normal) &nbsp;|&nbsp; ' +
    'Max = ' + fmtVal(maxNormal) + ' &nbsp;|&nbsp; ' +
    'Min subnormal ≈ ' + (typeof minSubnorm === 'number' ? fmtVal(minSubnorm) : minSubnorm);

  var html = '';
  for (var i = 0; i < count; i++) {
    var d = decodeFloat(i, E, M);
    html +=
      '<tr class="' + rowClass(d.type) + '">' +
      '<td>' + i + '</td>' +
      '<td>' + colorBitsFloat(d) + '</td>' +
      '<td>' + typeLabel(d.type) + '</td>' +
      '<td>' + (d.sign === 0 ? '+1' : '−1') + '</td>' +
      '<td>' + (d.type === 'normal' ? d.expStored : '—') + '</td>' +
      '<td>' + fmtExp(d) + '</td>' +
      '<td>' + fmtMant(d) + '</td>' +
      '<td>' + fmtVal(d.value) + '</td>' +
      '</tr>';
  }

  document.getElementById('fl-tbody').innerHTML = html;
  document.getElementById('fl-container').style.display = '';
}


/** Formats to show in decimal→float table; extended by custom E/M if given. */
function DFLOAT_FORMATS() {
  return [
    { E: 1, M: 2 }, { E: 2, M: 1 },
    { E: 2, M: 3 }, { E: 3, M: 2 },
    { E: 3, M: 4 }, { E: 4, M: 3 },
    { E: 5, M: 2 }, { E: 2, M: 5 },
    { E: 5, M: 10 },{ E: 8, M: 7  },
  ];
}

function decimalToFloat() {
  var x  = parseFloat(document.getElementById('df-decimal').value);
  var cE = parseInt(document.getElementById('df-exp').value,  10);
  var cM = parseInt(document.getElementById('df-mant').value, 10);

  if (isNaN(x)) { alert('Enter a valid decimal number.'); return; }

  var formats = DFLOAT_FORMATS();

  // Add custom format if provided and not already in list
  if (!isNaN(cE) && !isNaN(cM) && cE >= 1 && cM >= 0) {
    var already = formats.some(function(f) { return f.E === cE && f.M === cM; });
    if (!already) formats.push({ E: cE, M: cM, custom: true });
  }

  var html = '';
  for (var fi = 0; fi < formats.length; fi++) {
    var f  = formats[fi];
    var ev = validateEM(f.E, f.M);
    if (ev) continue;
    var d  = encodeFloat(x, f.E, f.M);
    var err      = d.error;
    var accuracy = d.accuracy;

    var errStr = isNaN(err)       ? '—' :
                 !isFinite(err)   ? '∞' :
                 err === 0        ? '0' :
                 err.toExponential(4);
    var accStr = !isFinite(accuracy) ? '∞' :
                 isNaN(accuracy)     ? '—' :
                 accuracy.toFixed(4);

    html +=
      '<tr class="' + rowClass(d.type) + (f.custom ? ' custom-row' : '') + '">' +
      '<td><strong>E' + f.E + 'M' + f.M + '</strong>' +
        (f.E + f.M + 1 <= 16 ? ' <span class="dim">(' + (f.E+f.M+1) + '-bit)</span>' : '') +
        '</td>' +
      '<td>' + colorBitsFloat(d) + '</td>' +
      '<td>' + typeLabel(d.type) + '</td>' +
      '<td>' + fmtVal(d.value) + '</td>' +
      '<td>' + errStr + '</td>' +
      '<td>' + accStr + '</td>' +
      '</tr>';
  }

  document.getElementById('df-tbody').innerHTML = html;
  document.getElementById('df-container').style.display = '';
}


function validateEM(E, M) {
  if (isNaN(E) || E < 1 || E > 8) return 'Exponent bits must be 1–8.';
  if (isNaN(M) || M < 0 || M > 15) return 'Mantissa bits must be 0–15.';
  if (1 + E + M > 16) return 'Total bits (1+E+M) must be ≤ 16 for the lookup table.';
  return null;
}


function applyFloatPreset(E, M, expId, mantId) {
  document.getElementById(expId).value  = E;
  document.getElementById(mantId).value = M;
}

