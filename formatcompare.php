<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Format Comparison Table</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
<script src="js/lnslookup.js"></script>
<script src="js/takumlookup.js"></script>
<script src="js/analysishelpers.js"></script>
<style>
table.cmp { width:100%; border-collapse:collapse; font-size:0.82rem; }
table.cmp th { background:var(--accent); color:#fff; padding:6px 8px; text-align:left; white-space:nowrap; }
table.cmp td { padding:5px 8px; border-bottom:1px solid var(--border); vertical-align:top; }
table.cmp tr:nth-child(even) td { background:#f7f8fa; }
table.cmp tr:hover td { background:#eef3fb; }
.fmt-group td:first-child { font-weight:700; color:var(--accent); padding-top:10px; }
.tag { display:inline-block; font-size:0.7rem; padding:1px 5px; border-radius:3px;
       margin:1px; font-weight:600; }
.tag-yes  { background:#d4edda; color:#155724; }
.tag-no   { background:#f8d7da; color:#721c24; }
.tag-inf  { background:#fff3cd; color:#856404; }
.tag-nar  { background:#e2d9f3; color:#4a1d96; }
.tag-nan  { background:#fde8d8; color:#7c3400; }
.best { color:#155724; font-weight:700; }
.worst { color:#721c24; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Format Comparison</h1>
  <p class="subtitle">
    Side-by-side comparison of dynamic range, precision, and special-value handling
    across Float, Posit, Takum, and LNS formats. All properties are computed directly
    from each format's bit-pattern definitions. Machine epsilon $\varepsilon$ is the
    gap between $1.0$ and the next larger representable value. Dynamic range is
    $\log_{10}(\text{max} / \text{min}^+)$. "Closed" means normal inputs never
    produce a non-finite result (posit and takum saturate; float and LNS may overflow).
  </p>
  <div class="table-scroll">
    <table class="cmp" id="cmp-table">
      <thead>
        <tr>
          <th>Format</th>
          <th>Bits</th>
          <th>Min positive</th>
          <th>Max value</th>
          <th>Dyn. range (decades)</th>
          <th>$\varepsilon$ near 1</th>
          <th>Finite representable</th>
          <th>Special values</th>
          <th>Closed +/×</th>
        </tr>
      </thead>
      <tbody id="cmp-tbody"></tbody>
    </table>
  </div>
</div>

<script>
'use strict';

// Format analysis

function fmtNum(x) {
  if (x === null || x === undefined) return '—';
  if (!isFinite(x)) return x > 0 ? '+∞' : '−∞';
  if (Math.abs(x) >= 1e6 || (Math.abs(x) > 0 && Math.abs(x) < 1e-4))
    return x.toExponential(3);
  return +x.toPrecision(4) + '';
}

function analyze(fmt) {
  var N = 1 << fmt.bits;
  var posVals = [];
  var hasInf = false, hasNaN = false, hasNaR = false;
  var machEps = null;

  if (fmt.type === 'float') {
    var oneIdx = -1;
    for (var i = 0; i < N; i++) {
      var d = decodeFloat(i, fmt.E, fmt.M);
      if (d.type === 'inf') { hasInf = true; }
      else if (d.type === 'nan') { hasNaN = true; }
      else if ((d.type === 'normal' || d.type === 'subnormal') && d.value > 0) {
        posVals.push(d.value);
        if (d.value === 1.0) oneIdx = i;
      }
    }
    if (oneIdx >= 0) {
      var dn = decodeFloat(oneIdx + 1, fmt.E, fmt.M);
      if (dn.type === 'normal') machEps = dn.value - 1.0;
    }
  } else if (fmt.type === 'posit') {
    var NAR = N >> 1;
    hasNaR = true;
    var oneIdx = -1;
    for (var i = 1; i < NAR; i++) {
      var v = decodePosit(i, fmt.bits, fmt.es);
      if (isFinite(v) && v > 0) {
        posVals.push(v);
        if (v === 1.0) oneIdx = i;
      }
    }
    if (oneIdx >= 0 && oneIdx + 1 < NAR) {
      machEps = decodePosit(oneIdx + 1, fmt.bits, fmt.es) - 1.0;
    }
  } else if (fmt.type === 'takum') {
    var NAR = N >> 1;
    hasNaR = true;
    var oneIdx = -1;
    for (var i = 1; i < NAR; i++) {
      var d = decodeTakum(i, fmt.bits);
      if (d.type !== 'nar' && isFinite(d.linVal) && d.linVal > 0) {
        posVals.push(d.linVal);
        if (Math.abs(d.linVal - 1.0) < 1e-12) oneIdx = i;
      }
    }
    if (oneIdx >= 0 && oneIdx + 1 < NAR) {
      var d2 = decodeTakum(oneIdx + 1, fmt.bits);
      machEps = d2.linVal - decodeTakum(oneIdx, fmt.bits).linVal;
    }
  } else if (fmt.type === 'logtakum') {
    var NAR = N >> 1;
    hasNaR = true;
    var oneIdx = -1, bestDist = Infinity;
    for (var i = 1; i < NAR; i++) {
      var d = decodeTakum(i, fmt.bits);
      if (d.type !== 'nar' && isFinite(d.logVal) && d.logVal > 0) {
        posVals.push(d.logVal);
        var dist = Math.abs(d.logVal - 1.0);
        if (dist < bestDist) { bestDist = dist; oneIdx = i; }
      }
    }
    if (oneIdx >= 0 && oneIdx + 1 < NAR) {
      var da = decodeTakum(oneIdx, fmt.bits), db = decodeTakum(oneIdx + 1, fmt.bits);
      if (isFinite(db.logVal) && db.logVal > da.logVal) machEps = db.logVal - da.logVal;
    }
  } else if (fmt.type === 'lns') {
    var oneIdx = -1, bestDist = Infinity;
    for (var i = 0; i < N; i++) {
      var d = decodeLNS(i, fmt.I, fmt.F);
      if (!d.isZero && d.signBit === 0 && isFinite(d.value) && d.value > 0) {
        posVals.push(d.value);
        var dist = Math.abs(d.value - 1.0);
        if (dist < bestDist) { bestDist = dist; oneIdx = i; }
      }
    }
    if (oneIdx >= 0) {
      var d1 = decodeLNS(oneIdx, fmt.I, fmt.F);
      var d2 = decodeLNS(oneIdx + 1, fmt.I, fmt.F);
      if (!d2.isZero && d2.value > d1.value) machEps = d2.value - d1.value;
    }
  }

  posVals.sort(function(a, b) { return a - b; });
  var minPos = posVals[0];
  var maxPos = posVals[posVals.length - 1];
  var dynDec = Math.log10(maxPos / minPos);
  // finite representable: positive + negative + zero (exclude specials)
  var totalFinite = posVals.length * 2 + 1;

  return {
    fmt: fmt, minPos: minPos, maxPos: maxPos,
    dynDec: dynDec, machEps: machEps, totalFinite: totalFinite,
    hasInf: hasInf, hasNaN: hasNaN, hasNaR: hasNaR,
    closed: (fmt.type === 'posit' || fmt.type === 'takum' || fmt.type === 'logtakum'),
  };
}

//Render

var FORMATS = [
  // Float
  { name: 'Float8 E4M3', bits: 8,  type: 'float', E: 4, M: 3,  group: 'Float' },
  { name: 'Float8 E5M2', bits: 8,  type: 'float', E: 5, M: 2,  group: 'Float' },
  { name: 'Float16',     bits: 16, type: 'float', E: 5, M: 10, group: 'Float' },
  { name: 'BF16',        bits: 16, type: 'float', E: 8, M: 7,  group: 'Float' },
  // Posit
  { name: 'Posit⟨8,0⟩',  bits: 8,  type: 'posit', es: 0, group: 'Posit' },
  { name: 'Posit⟨8,1⟩',  bits: 8,  type: 'posit', es: 1, group: 'Posit' },
  { name: 'Posit⟨8,2⟩',  bits: 8,  type: 'posit', es: 2, group: 'Posit' },
  { name: 'Posit⟨16,0⟩', bits: 16, type: 'posit', es: 0, group: 'Posit' },
  { name: 'Posit⟨16,1⟩', bits: 16, type: 'posit', es: 1, group: 'Posit' },
  { name: 'Posit⟨16,2⟩', bits: 16, type: 'posit', es: 2, group: 'Posit' },
  // Takum
  { name: 'Takum8',        bits: 8,  type: 'takum',    group: 'Takum' },
  { name: 'Log Takum8',    bits: 8,  type: 'logtakum', group: 'Takum' },
  { name: 'Takum16',       bits: 16, type: 'takum',    group: 'Takum' },
  { name: 'Log Takum16',   bits: 16, type: 'logtakum', group: 'Takum' },
  // LNS
  { name: 'LNS I3F4',   bits: 8,  type: 'lns', I: 3, F: 4, group: 'LNS' },
  { name: 'LNS I4F3',   bits: 8,  type: 'lns', I: 4, F: 3, group: 'LNS' },
  { name: 'LNS I5F2',   bits: 8,  type: 'lns', I: 5, F: 2, group: 'LNS' },
];

window.addEventListener('load', function() {
  var rows = FORMATS.map(analyze);

  // Find best/worst for highlighting
  var allDyn  = rows.map(function(r) { return r.dynDec; });
  var allEps  = rows.map(function(r) { return r.machEps; });
  var allFin  = rows.map(function(r) { return r.totalFinite; });
  var maxDyn  = Math.max.apply(null, allDyn);
  var minEps  = Math.min.apply(null, allEps.filter(function(e){ return e !== null; }));

  var tbody = document.getElementById('cmp-tbody');
  var lastGroup = '';
  rows.forEach(function(r, idx) {
    var fmt = r.fmt;
    var tr = document.createElement('tr');

    function td(html, cls) {
      var el = document.createElement('td');
      el.innerHTML = html;
      if (cls) el.className = cls;
      return el;
    }

    // Group separator row
    if (fmt.group !== lastGroup) {
      lastGroup = fmt.group;
      var sep = document.createElement('tr');
      sep.className = 'fmt-group';
      var sc = document.createElement('td');
      sc.colSpan = 9;
      sc.textContent = fmt.group;
      sep.appendChild(sc);
      tbody.appendChild(sep);
    }

    // Special-value tags
    var specHTML = '<span class="tag tag-yes">Zero</span>';
    if (r.hasInf) specHTML += ' <span class="tag tag-inf">±Inf</span>';
    if (r.hasNaN) specHTML += ' <span class="tag tag-nan">NaN</span>';
    if (r.hasNaR) specHTML += ' <span class="tag tag-nar">NaR</span>';

    var dynCls = (r.dynDec === maxDyn) ? 'best' : '';
    var epsCls = (r.machEps !== null && r.machEps === minEps) ? 'best' : '';

    tr.appendChild(td('<strong>' + fmt.name + '</strong>'));
    tr.appendChild(td(fmt.bits));
    tr.appendChild(td(fmtNum(r.minPos)));
    tr.appendChild(td(fmtNum(r.maxPos)));
    tr.appendChild(td('<span class="' + dynCls + '">' + r.dynDec.toFixed(1) + '</span>'));
    tr.appendChild(td(r.machEps !== null
      ? '<span class="' + epsCls + '">' + fmtNum(r.machEps) + '</span>'
      : '—'));
    tr.appendChild(td(r.totalFinite.toLocaleString()));
    tr.appendChild(td(specHTML));
    tr.appendChild(td(r.closed
      ? '<span class="tag tag-yes">Yes</span>'
      : '<span class="tag tag-no">No</span>'));

    tbody.appendChild(tr);
  });
});
</script>
</body>
</html>
