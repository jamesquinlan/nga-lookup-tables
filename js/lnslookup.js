/**
 * Log Number System (LNS) lookup table generator.
 *
 * Format: 1 sign bit | (I + F) log bits
 *   I = integer bits of log (including sign of log, i.e., two's complement)
 *   F = fraction bits of log
 *
 * Value = (-1)^sign × 2^(log_field)
 *   where log_field is a two's complement fixed-point with F fraction bits.
 *
 * Zero representation: the "negative infinity log" is approximated by the
 * most-negative two's complement value for the log field.  That pattern is
 * flagged as zero in the table (by common convention).
 *
 * Reference: Swartzlander & Alexopoulos, "The Sign/Logarithm Number System",
 *            IEEE TC 1975; Coleman et al. "The European Logarithmic
 *            Microprocesor", IEEE TC 2008.
 *
 * BibTeX: 
 * @article{swartzlander1975sign,
 * title={The sign/logarithm number system},
 * author={Swartzlander, Earl E and Alexopoulos, Aristides G},
 * journal={IEEE Transactions on Computers},
 * volume={100},
 * number={12},
 * pages={1238--1242},
 * year={1975},
 * publisher={IEEE}
 *}
 */

'use strict';
 

/**
 * Decode bit pattern i (N = 1+I+F bits) to LNS components.
 */
function decodeLNS(i, I, F) {
  var N    = 1 + I + F;
  var bits = i.toString(2);
  while (bits.length < N) bits = '0' + bits;

  var signBit   = parseInt(bits[0]);
  var logBits   = bits.substring(1);          // I+F bits, two's complement

  // Interpret log_field as a two's complement signed integer with F frac bits
  var logInt    = parseInt(logBits, 2);
  var logLen    = I + F;
  var halfRange = 1 << (logLen - 1);          // 2^(I+F-1)

  // Two's complement: values >= halfRange are negative
  var logSigned = (logInt >= halfRange) ? logInt - (1 << logLen) : logInt;

  // Fixed-point value: divide by 2^F
  var logValue  = logSigned / Math.pow(2, F);

  // Zero convention: most-negative log value (0b1000...0) → 0
  var isZero    = (logInt === halfRange);

  var value;
  if (isZero) {
    value = 0;
  } else {
    value = Math.pow(-1, signBit) * Math.pow(2, logValue);
  }

  return {
    bits:      bits,
    signBit:   signBit,
    intBits:   logBits.substring(0, I),
    fracBits:  logBits.substring(I),
    logBits:   logBits,
    logInt:    logInt,
    logSigned: logSigned,
    logValue:  logValue,
    value:     value,
    isZero:    isZero
  };
}


function encodeLNS(x, I, F) {
  var logLen    = I + F;
  var halfRange = 1 << (logLen - 1);
  var mantDenom = Math.pow(2, F);

  var signBit = (x < 0 || (x === 0 && (1/x) < 0)) ? 1 : 0;
  var ax      = Math.abs(x);

  if (ax === 0) {
    // Return the zero pattern
    var zeroPat = (signBit << logLen) | halfRange;
    var r = decodeLNS(zeroPat, I, F);
    r.error    = 0;
    r.accuracy = Infinity;
    return r;
  }

  var logExact  = Math.log2(ax);                      // exact log2
  var logScaled = logExact * mantDenom;               // scaled by 2^F
  var logSigned = Math.round(logScaled);              // nearest integer

  // Clamp to representable two's complement range (excluding zero sentinel)
  var minLog = -halfRange + 1;   // most-negative non-zero value
  var maxLog =  halfRange - 1;

  if (logSigned < minLog) logSigned = minLog;
  if (logSigned > maxLog) logSigned = maxLog;

  // Back to unsigned two's complement
  var logUnsigned = (logSigned >= 0) ? logSigned : logSigned + (1 << logLen);

  var pat = (signBit << logLen) | logUnsigned;
  var d   = decodeLNS(pat, I, F);

  var err=(ax !== 0 && !d.isZero) ? Math.abs((ax - Math.abs(d.value)) / ax) : 0;
  d.error    = err;
  d.accuracy = (err > 0) ? -Math.log10(err) : Infinity;
  return d;
}


function colorBitsLNS(d) {
  var intPart  = (d.intBits.length  > 0) ? '<span class="bit-integer">'  + d.intBits  + '</span>' : '';
  var fracPart = (d.fracBits.length > 0) ? '<span class="bit-fraction">' + d.fracBits + '</span>' : '';
  return '<span class="bit-sign">' + d.bits[0] + '</span>' + intPart + fracPart;
}

function fmtValLNS(d) {
  if (d.isZero) return '0';
  var v = d.value;
  if (!isFinite(v)) return v > 0 ? '+Inf' : '−Inf';
  var a = Math.abs(v);
  if (a >= 1e-4 && a < 1e7) return parseFloat(v.toPrecision(8)).toString();
  return v.toExponential(5);
}

function fmtLogFixed(d) {
  // Show as integer.fraction₂
  if (d.intBits.length === 0)  return '.' + d.fracBits;
  if (d.fracBits.length === 0) return d.intBits;
  return d.intBits + '.' + d.fracBits;
}



function lnsLookup() {
  var I = parseInt(document.getElementById('lns-int').value,  10);
  var F = parseInt(document.getElementById('lns-frac').value, 10);

  var err = validateLNS(I, F);
  if (err) { document.getElementById('lns-info').textContent = err; return; }

  var N     = 1 + I + F;
  var count = 1 << N;

  var logLen    = I + F;
  var halfRange = 1 << (logLen - 1);
  var maxLog    = (halfRange - 1) / Math.pow(2, F);
  var minLog    = -(halfRange - 1) / Math.pow(2, F);

  document.getElementById('lns-info').innerHTML =
    '<strong>' + N + '-bit LNS</strong> &nbsp;|&nbsp; ' +
    'Log integer bits = ' + I + ' &nbsp;|&nbsp; ' +
    'Log fraction bits = ' + F + ' &nbsp;|&nbsp; ' +
    'Log range ≈ [' + minLog.toFixed(F > 0 ? F : 0) + ', ' + maxLog.toFixed(F > 0 ? F : 0) + '] &nbsp;|&nbsp; ' +
    'Value range ≈ [2<sup>' + minLog + '</sup>, 2<sup>' + maxLog + '</sup>]';

  var html = '';
  for (var i = 0; i < count; i++) {
    var d       = decodeLNS(i, I, F);
    var signStr = d.signBit === 0 ? '+1' : '−1';
    var logDisp = d.isZero ? '−∞ (zero)' : fmtLogFixed(d) + ' = ' + d.logValue.toFixed(Math.max(F, 2));
    var valStr  = fmtValLNS(d);
    var rclass  = d.isZero ? 'r-zero' : '';

    html +=
      '<tr class="' + rclass + '">' +
      '<td>' + i + '</td>' +
      '<td>' + colorBitsLNS(d) + '</td>' +
      '<td>' + (d.isZero ? 'Zero' : 'Normal') + '</td>' +
      '<td>' + signStr + '</td>' +
      '<td>' + logDisp + '</td>' +
      '<td>' + valStr + '</td>' +
      '</tr>';
  }

  document.getElementById('lns-tbody').innerHTML = html;
  document.getElementById('lns-container').style.display = '';
}



var LNS_FORMATS = [
  { I: 3, F: 0 }, { I: 4, F: 0 },
  { I: 2, F: 1 }, { I: 3, F: 1 },
  { I: 2, F: 2 }, { I: 3, F: 2 },
  { I: 2, F: 4 }, { I: 3, F: 4 },
  { I: 4, F: 3 }, { I: 5, F: 2 },
  { I: 4, F: 7 }, { I: 5, F: 10 },
]; // Decimal 2 LNS conversion table

function decimalToLNS() {
  var x  = parseFloat(document.getElementById('dlns-decimal').value);
  var cI = parseInt(document.getElementById('dlns-int').value,  10);
  var cF = parseInt(document.getElementById('dlns-frac').value, 10);

  if (isNaN(x)) { alert('Enter a valid decimal number.'); return; }

  var formats = LNS_FORMATS.slice();

  if (!isNaN(cI) && !isNaN(cF) && cI >= 1 && cF >= 0) {
    var already = formats.some(function(f) {return f.I === cI && f.F === cF; });
    if (!already) formats.push({ I: cI, F: cF, custom: true });
  }

  var html = '';
  for (var fi = 0; fi < formats.length; fi++) {
    var f  = formats[fi];
    var ev = validateLNS(f.I, f.F);
    if (ev) continue;
    var d  = encodeLNS(x, f.I, f.F);

    var errStr = (!isFinite(d.error) || isNaN(d.error)) ? '—' :
                 d.error === 0 ? '0' :
                 d.error.toExponential(4);
    var accStr = !isFinite(d.accuracy) ? '∞' :
                 isNaN(d.accuracy)     ? '—' :
                 d.accuracy.toFixed(4);

    html +=
      '<tr class="' + (d.isZero ? 'r-zero' : '') + (f.custom ? ' custom-row' : '') + '">' +
      '<td><strong>I' + f.I + 'F' + f.F + '</strong>' +
        ' <span class="dim">(' + (f.I+f.F+1) + '-bit)</span></td>' +
      '<td>' + colorBitsLNS(d) + '</td>' +
      '<td>' + fmtValLNS(d) + '</td>' +
      '<td>' + errStr + '</td>' +
      '<td>' + accStr + '</td>' +
      '</tr>';
  }

  document.getElementById('dlns-tbody').innerHTML = html;
  document.getElementById('dlns-container').style.display = '';
}


function validateLNS(I, F) {
  if (isNaN(I) || I < 1 || I > 12) return 'Integer log bits must be 1–12.';
  if (isNaN(F) || F < 0 || F > 12) return 'Fraction log bits must be 0–12.';
  if (1 + I + F > 16) return 'Total bits (1+I+F) must be ≤ 16 for the lookup table.';
  return null;
}

function applyLNSPreset(I, F, intId, fracId) {
  document.getElementById(intId).value  = I;
  document.getElementById(fracId).value = F;
}

