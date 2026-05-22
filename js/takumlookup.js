/**
 * Takum arithmetic lookup table generator.
 *
 * Takum numbers were introduced by Laslo Hunhold,
 * "Beating Posits at Their Own Game: Takum Arithmetic", CoNGA 2024.
 * arXiv:2404.18603.  Reference implementation: github.com/takum-arithmetic/libtakum
 *
 * Format (n-bit, two's complement signed integer):
 *   0          → exact zero
 *   -2^(n-1)   → NaR (Not a Real - analogous to NaN)
 *   all others → real numbers encoded via a 4-bit regime (D R2 R1 R0),
 *                a variable-length characteristic field, and mantissa bits.
 *
 * The bit-field structure is applied to the ABSOLUTE VALUE of the signed
 * integer. The sign of the number is the two's-complement sign bit.
 *
 * Bit layout of |t| (n-1 bits, MSB first):
 *   D | R2 R1 R0 | characteristic bits | mantissa bits
 *
 * The DR index (0–15) into the LUTs is:
 *   DR = bits [n-2 .. n-5]   (D=bit n-2, R2=bit n-3, R1=bit n-4, R0=bit n-5)
 *
 * For 8-bit: the top 16-bit window used for LUT lookup is abs_bits << 8.
 * For 16-bit: the top 16-bit window is abs_bits itself.
 *
 * Intermediate log value: l = (1-2s) * (c + M/2^n)
 *   where c = C_BIAS_LUT[DR] + char_field,  M = abs_bits << (16-p)  (n-bit truncated)
 *
 * Linear Takum value:  (-1)^s * (1 + M/2^n) * 2^c
 * Log Takum value:     (-1)^s * exp(|l| / 2)   [= sqrt(e)^|l|]
 *
 * Source: libtakum/src/codec.c - get_c_and_return_shift(), codec_takum_log*_to_l()
 */

'use strict';

var TAKUM_C_BIAS = [-255,-127,-63,-31,-15,-7,-3,-1, 0, 1, 3, 7, 15, 31, 63,127];
var TAKUM_P      = [   4,   5,  6,  7,  8, 9,10,11,11,10, 9, 8,  7,  6,  5,  4];

// Core decode

/**
 * Decode bit pattern i (0 … 2^n−1) for an n-bit takum (n = 8 or 16).
 */
function decodeTakum(i, n) {
  var NAR  = 1 << (n - 1);           // 128 or 32768
  var mask = NAR - 1;                 // 0x7F or 0x7FFF
  var bits = i.toString(2).padStart(n, '0');

  if (i === 0)   return { bits: bits, type: 'zero', s: 0 };
  if (i === NAR) return { bits: bits, type: 'nar',  s: 1 };

  var signed  = (i > mask) ? i - (1 << n) : i;
  var s       = (signed < 0) ? 1 : 0;
  var abs     = Math.abs(signed);              // 1 … NAR-1
  var top16   = (n === 8) ? (abs << 8) : abs;
  var DR      = (top16 & 0x7800) >> 11;
  var p       = TAKUM_P[DR];
  var shift   = 16 - p;
  var lower11 = top16 & 0x07FF;
  var charVal = lower11 >> p;
  var c       = TAKUM_C_BIAS[DR] + charVal;

  
  var nMask   = (n === 8) ? 0xFF : 0xFFFF;
  var M       = (abs << shift) & nMask;// Mantissa bits (n-bit truncation of abs << shift)
  var m       = M / Math.pow(2, n); // fractional mantissa \in [0,1)

  var l_abs   = c + m;
  var l       = (1 - 2 * s) * l_abs;


  var linVal  = (1 - 2 * s) * (1 + m) * Math.pow(2, c);  // Linear takum: (-1)^s * (1+m) * 2^c

 
  var logVal  = (1 - 2 * s) * Math.exp(l_abs / 2); // Log takum: (-1)^s * exp(l_abs/2)   
    //[value = sqrt(e)^l_abs]

  var absBits = abs.toString(2).padStart(n - 1, '0');// Abs-value bit string for field coloring (n-1 bits)

  // Number of char/mant bits in the 3 data-bits of the 8-bit "rest" region,
  // or in the 11-bit lower region for 16-bit.  See paper for details
  var charCount, mantCount;
  if (n === 16) {
    charCount = 11 - p;   // 0–7
    mantCount = p;         // 4–11
  } else {
    // For 8-bit: 3 actual data bits occupy positions 10,9,8 in top16's lower11.
    // Positions >= p  → characteristic;  positions < p => mantissa.
    if (p <= 8) {
      charCount = 3; mantCount = 0;
    } else if (p <= 10) {
      charCount = 1; mantCount = 2;   // only position 10 is char when p=9 or 10
    } else {                           // p == 11
      charCount = 0; mantCount = 3;
    }
  }

  return {
    bits: bits, absBits: absBits,
    type: s === 0 ? 'pos' : 'neg',
    s: s, DR: DR, p: p,
    c: c, m: m, M: M, charVal: charVal,
    l: l, l_abs: l_abs,
    linVal: linVal, logVal: logVal,
    charCount: charCount, mantCount: mantCount
  };
}


function colorBitsTakum(d, n) {
  var sBit = '<span class="bit-sign">' + d.bits[0] + '</span>';
  if (d.type === 'zero' || d.type === 'nar') {
    return sBit + '<span style="color:#9aa0a6">' + d.bits.substring(1) + '</span>';
  }
  // First 4 bits of absBits = D R2 R1 R0 (regime-like field, see reference for details)
  var drrr  = '<span class="bit-regime">'   + d.absBits.substring(0, 4) + '</span>';
  var rest  = d.absBits.substring(4);       // remaining bits
  var cpart = '<span class="bit-exponent">' + rest.substring(0, d.charCount) + '</span>';
  var mpart = '<span class="bit-mantissa">' + rest.substring(d.charCount, d.charCount + d.mantCount) + '</span>';
  // leftover bits (shouldn't happen but in case)
  var tail  = rest.substring(d.charCount + d.mantCount);
  return sBit + drrr + cpart + mpart + tail;
}


function fmtTakumVal(v) {
  if (isNaN(v))        return 'NaR';
  if (!isFinite(v))    return v > 0 ? '+Inf' : '−Inf';
  if (v === 0)         return '0';
  var a = Math.abs(v);
  if (a >= 1e-4 && a < 1e13) return parseFloat(v.toPrecision(7)).toString();
  return v.toExponential(5);
}

function rowClass(type) {
  return { zero:'r-zero', nar:'r-nan', pos:'', neg:'r-sub' }[type] || '';
}


function takumLookup() {
  var n = parseInt(document.getElementById('tl-bits').value, 10);
  if (n !== 8 && n !== 16) {
    document.getElementById('tl-info').textContent = 'Bit width must be 8 or 16.';
    return;
  }

  var count  = 1 << n;
  var NAR    = 1 << (n - 1);

  document.getElementById('tl-info').innerHTML =
    '<strong>Takum ' + n + '-bit</strong> &nbsp;|&nbsp; ' +
    'Zero = bit pattern 0 &nbsp;|&nbsp; ' +
    'NaR = bit pattern ' + NAR + ' (0x' + NAR.toString(16).toUpperCase() + ') &nbsp;|&nbsp; ' +
    'Signed two\'s complement encoding &nbsp;|&nbsp; ' +
    'Regime LUT: 4-bit DR field → c_bias, mantissa bits p';

  var html = '';
  for (var i = 0; i < count; i++) {
    var d = decodeTakum(i, n);

    var signStr  = (d.type === 'pos') ? '+1' : (d.type === 'neg') ? '−1' : '-';
    var drStr    = (d.DR  != null) ? d.DR  : '-';
    var cStr     = (d.c   != null) ? d.c   : '-';
    var mStr     = (d.m   != null) ? d.m.toFixed(6) : '-';
    var lStr     = (d.l   != null) ? fmtTakumVal(d.l) : '-';
    var linStr   = fmtTakumVal(d.linVal);
    var logStr   = fmtTakumVal(d.logVal);

    if (d.type === 'zero') {
      signStr = '0'; drStr = '-'; cStr = '-'; mStr = '-'; lStr = '-∞';
      linStr = '0'; logStr = '0';
    } else if (d.type === 'nar') {
      signStr = 'NaR'; drStr = '-'; cStr = '-'; mStr = '-'; lStr = 'NaR';
      linStr = 'NaR'; logStr = 'NaR';
    }

    html +=
      '<tr class="' + rowClass(d.type) + '">' +
      '<td>' + i + '</td>' +
      '<td>' + d.bits + '</td>' +
      '<td>' + colorBitsTakum(d, n) + '</td>' +
      '<td>' + d.type.charAt(0).toUpperCase() + d.type.slice(1) + '</td>' +
      '<td>' + signStr + '</td>' +
      '<td>' + drStr + '</td>' +
      '<td>' + cStr + '</td>' +
      '<td>' + mStr + '</td>' +
      '<td>' + lStr + '</td>' +
      '<td>' + linStr + '</td>' +
      '<td>' + logStr + '</td>' +
      '</tr>';
  }

  document.getElementById('tl-tbody').innerHTML = html;
  document.getElementById('tl-container').style.display = '';
  document.getElementById('tl-info').style.display = '';
}

// Brute-force encoder (nearest linear-takum value)
/**
 * Find the bit pattern (1 \dots NAR-1) whose decoded linear value is nearest to ax.
 * Returns the positive-side bit pattern; negate externally for negative numbers.
 */
function findNearestPositiveTakum(ax, n) {
  var NAR = 1 << (n - 1);
  var best = 1, bestErr = Infinity;
  for (var i = 1; i < NAR; i++) {
    var d = decodeTakum(i, n);
    var err = Math.abs(d.linVal - ax);
    if (err < bestErr) { bestErr = err; best = i; }
    if (err === 0) break;
  }
  return best;
}


function decimalToTakum() {
  var x = parseFloat(document.getElementById('dt-decimal').value);
  if (isNaN(x)) { alert('Enter a valid decimal number.'); return; }

  var html = '';
  var sizes = [8, 16];

  for (var si = 0; si < sizes.length; si++) {
    var n   = sizes[si];
    var NAR = 1 << (n - 1);

    var pat;
    if (x === 0) {
      pat = 0;
    } else {
      var s  = (x < 0) ? 1 : 0;
      var ax = Math.abs(x);
      var pos = findNearestPositiveTakum(ax, n);
      pat = s === 0 ? pos : (1 << n) - pos;
    }

    var d   = decodeTakum(pat, n);
    var err = (x !== 0 && d.type !== 'zero' && d.type !== 'nar')
              ? Math.abs((x - d.linVal) / x) : 0;
    var acc = (err > 0) ? -Math.log10(err) : Infinity;

    var errStr = err === 0   ? '0'               : err.toExponential(4);
    var accStr = !isFinite(acc) ? '∞'            : acc.toFixed(4);

    html +=
      '<tr>' +
      '<td><strong>Takum' + n + '</strong></td>' +
      '<td>' + colorBitsTakum(d, n) + '</td>' +
      '<td>' + d.type.charAt(0).toUpperCase() + d.type.slice(1) + '</td>' +
      '<td>' + fmtTakumVal(d.linVal) + '</td>' +
      '<td>' + fmtTakumVal(d.logVal) + '</td>' +
      '<td>' + errStr + '</td>' +
      '<td>' + accStr + '</td>' +
      '</tr>';
  }

  document.getElementById('dt-tbody').innerHTML = html;
  document.getElementById('dt-container').style.display = '';
}

