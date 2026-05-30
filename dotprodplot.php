<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dot Product Accuracy</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
<script src="js/lnslookup.js"></script>
<script src="js/takumlookup.js"></script>
<script src="js/analysishelpers.js"></script>
<style>
.plot-wrap canvas { display:block; max-width:100%;
  border:1px solid var(--border); border-radius:4px; }
.legend-row { display:flex; gap:1.2rem; flex-wrap:wrap; font-size:0.8rem;
              color:var(--muted); margin-top:0.6rem; align-items:center; }
.legend-row span { display:flex; align-items:center; gap:0.4rem; }
.lswatch { width:28px; height:12px; display:inline-block; border-radius:2px; }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; flex-wrap:wrap; }
.op-row label { font-size:0.85rem; color:var(--muted); }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Dot Product Accuracy</h1>
  <p class="subtitle">
    For random positive vectors $\mathbf{a}, \mathbf{b}$ of length $L$,
    each format computes $\hat{d} = \sum_{i=1}^{L} \hat{a}_i \times \hat{b}_i$
    (multiply then accumulate, each step rounded to the format).
    The exact value $d^*$ is the float64 dot product of the same encoded values.
    Relative error $= |\hat{d} - d^*| / d^*$ is averaged over 200 random trials per length.
    Elements are drawn uniformly from the format's positive representable values.
  </p>

  <div class="card">
    <div class="op-row">
      <label>Error metric:</label>
      <button class="preset active" id="btn-rel"    onclick="setMode('rel')">|est&minus;exact|/exact</button>
      <button class="preset"        id="btn-logr"   onclick="setMode('logr')">|ln(est/exact)|</button>
      <button class="preset"        id="btn-decacc" onclick="setMode('decacc')">&minus;log&#x2081;&#x2080;|ln(est/exact)|</button>
    </div>
    <div class="op-row">
      <label>Resample:</label>
      <button class="preset" onclick="redraw()">New random sample</button>
    </div>
    <div class="plot-wrap">
      <canvas id="cv-dot" width="760" height="360" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row">
      <span><span class="lswatch" style="background:rgba(88,148,210,0.85)"></span>Float E4M3</span>
      <span><span class="lswatch" style="background:rgba(95,175,100,0.85)"></span>Posit⟨8,2⟩</span>
      <span><span class="lswatch" style="background:rgba(215,145,55,0.85)"></span>LNS I4F3</span>
      <span><span class="lswatch" style="background:rgba(175,90,165,0.85)"></span>Takum8</span>
    </div>
  </div>
</div>

<script>
'use strict';

// Collect positive representable values for each format
var POS_PATS = {
  float: (function() {
    var v = [];
    for (var i = 1; i < 256; i++) {
      var d = decodeFloat(i, 4, 3);
      if ((d.type === 'normal' || d.type === 'subnormal') && d.value > 0) v.push(d.value);
    }
    return v;
  })(),
  posit: (function() {
    var v = [];
    for (var i = 1; i < 128; i++) { var x = decodePosit(i, 8, 2); if (x > 0 && isFinite(x)) v.push(x); }
    return v;
  })(),
  lns: (function() {
    var v = [];
    for (var i = 0; i < 256; i++) {
      var d = decodeLNS(i, 4, 3);
      if (!d.isZero && d.signBit === 0 && d.value > 0) v.push(d.value);
    }
    return v;
  })(),
  takum: (function() {
    var v = [];
    for (var i = 1; i < 128; i++) {
      var d = decodeTakum(i, 8);
      if (d.type !== 'nar' && d.type !== 'zero' && isFinite(d.linVal) && d.linVal > 0) v.push(d.linVal);
    }
    return v;
  })(),
};

function fmtRound(x, fmt) {
  if (fmt === 'float') {
    var r = encodeFloat(x, 4, 3);
    return (r.type === 'normal' || r.type === 'subnormal') ? r.value : (r.type === 'inf' ? Infinity : 0);
  }
  if (fmt === 'posit') { var p = encodePosit(x, 8, POSITLUT); return decodePosit(p, 8, 2); }
  if (fmt === 'lns')   { var r = encodeLNS(x, 4, 3); return r.isZero ? 0 : r.value; }
  var p = encodeTakum8(x, TAKUMLUT);
  var d = decodeTakum(p, 8);
  return (d.type === 'zero') ? 0 : d.linVal;
}

function dotProduct(a, b, fmt) {
  // multiply-accumulate, each product and accumulation rounded
  var sum = 0;
  for (var i = 0; i < a.length; i++) {
    var prod = fmtRound(a[i] * b[i], fmt);
    sum = fmtRound(sum + prod, fmt);
  }
  return sum;
}

// Superscript helper

var _SD = ['⁰','¹','²','³','⁴','⁵','⁶','⁷','⁸','⁹'];
function fmtLog(e) {
  var sign = e < 0 ? '⁻' : '';
  return '10' + sign + String(Math.abs(e)).split('').map(function(c){ return _SD[+c]; }).join('');
}

// Error metrics

var errMode = 'rel';

function computeMetric(approx, exact, mode) {
  if (!isFinite(approx) || !isFinite(exact) || exact <= 0 || approx <= 0) return null;
  if (mode === 'rel') {
    var e = Math.abs(approx - exact) / exact;
    return e > 0 ? Math.log10(e) : null;
  }
  var lr = Math.abs(Math.log(approx / exact));
  if (lr === 0) return null;
  if (mode === 'logr') return Math.log10(lr);
  return -Math.log10(lr);
}

//Experiment

var LENGTHS = [2, 4, 8, 16, 32, 64];
var N_TRIALS = 200;

function runExperiment(fmt, mode) {
  // Restrict to values ≤ 2 so products stay within all formats' dynamic ranges
  var pool = POS_PATS[fmt].filter(function(v) { return v <= 2; });
  if (pool.length < 5) pool = POS_PATS[fmt];
  return LENGTHS.map(function(L) {
    var total = 0, count = 0;
    for (var t = 0; t < N_TRIALS; t++) {
      var a = [], b = [];
      for (var i = 0; i < L; i++) {
        a.push(pool[(Math.random() * pool.length) | 0]);
        b.push(pool[(Math.random() * pool.length) | 0]);
      }
      var exact = 0;
      for (var i = 0; i < L; i++) exact += a[i] * b[i];
      var approx = dotProduct(a, b, fmt);
      var m = computeMetric(approx, exact, mode);
      if (m !== null && isFinite(m)) { total += m; count++; }
    }
    return count > 0 ? total / count : null;
  });
}

// Draw

var CONFIGS = [
  { fmt: 'float', color: 'rgba(88,148,210,0.85)',  label: 'Float E4M3'  },
  { fmt: 'posit', color: 'rgba(95,175,100,0.85)',  label: 'Posit⟨8,2⟩' },
  { fmt: 'lns',   color: 'rgba(215,145,55,0.85)',  label: 'LNS I4F3'   },
  { fmt: 'takum', color: 'rgba(175,90,165,0.85)',  label: 'Takum8'     },
];

var PAD = { l:58, r:16, t:16, b:46 };

function drawPlot() {
  var canvas = document.getElementById('cv-dot');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  var allData = CONFIGS.map(function(cfg) { return runExperiment(cfg.fmt, errMode); });

  var allVals = [];
  allData.forEach(function(series) {
    series.forEach(function(v) { if (v !== null && isFinite(v)) allVals.push(v); });
  });
  if (!allVals.length) return;
  var yMin = Math.floor(Math.min.apply(null, allVals)) - 1;
  var yMax = Math.ceil( Math.max.apply(null, allVals)) + 1;
  var yStride = Math.max(1, Math.ceil((yMax - yMin) / 7));

  var nL = LENGTHS.length;
  function xPx(li) { return PAD.l + li / (nL - 1) * pw; }
  function yPx(v)  { return PAD.t + (1 - (v - yMin) / (yMax - yMin)) * ph; }

  // Grid
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var lv = yMin; lv <= yMax; lv += yStride) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(lv)); ctx.lineTo(PAD.l + pw, yPx(lv)); ctx.stroke();
  }
  for (var li = 0; li < nL; li++) {
    ctx.beginPath(); ctx.moveTo(xPx(li), PAD.t); ctx.lineTo(xPx(li), PAD.t + ph); ctx.stroke();
  }

  // Lines + dots
  CONFIGS.forEach(function(cfg, ci) {
    var series = allData[ci];
    ctx.strokeStyle = cfg.color; ctx.lineWidth = 2.5;
    ctx.beginPath();
    var started = false;
    for (var li = 0; li < nL; li++) {
      var v = series[li];
      if (v === null || !isFinite(v)) { started = false; continue; }
      var py = yPx(Math.max(yMin, Math.min(yMax, v)));
      if (!started) { ctx.moveTo(xPx(li), py); started = true; } else { ctx.lineTo(xPx(li), py); }
    }
    ctx.stroke();
    ctx.fillStyle = cfg.color;
    for (var li = 0; li < nL; li++) {
      var v = series[li];
      if (v === null || !isFinite(v)) continue;
      ctx.beginPath();
      ctx.arc(xPx(li), yPx(Math.max(yMin, Math.min(yMax, v))), 4, 0, 2 * Math.PI);
      ctx.fill();
    }
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  LENGTHS.forEach(function(L, li) { ctx.fillText(L, xPx(li), PAD.t + ph + 14); });
  // Y labels
  ctx.textAlign = 'right';
  for (var lv = yMin; lv <= yMax; lv += yStride) {
    if (errMode === 'decacc') {
      ctx.fillText(lv.toFixed(0), PAD.l - 5, yPx(lv) + 4);
    } else {
      ctx.fillText(fmtLog(lv), PAD.l - 5, yPx(lv) + 4);
    }
  }

  // Axis labels
  var yLabel;
  if      (errMode === 'rel')    yLabel = 'mean relative error  (log scale)';
  else if (errMode === 'logr')   yLabel = 'mean |ln(est/exact)|  (log scale)';
  else                           yLabel = 'decimal accuracy  \u2212log\u2081\u2080|ln(est/exact)|  (higher\u2009=\u2009better)';

  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('vector length L', PAD.l + pw / 2, H - 5);
  ctx.save();
  ctx.translate(13, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText(yLabel, 0, 0);
  ctx.restore();
}

function setMode(m) {
  errMode = m;
  ['rel', 'logr', 'decacc'].forEach(function(v) {
    document.getElementById('btn-' + v).classList.toggle('active', v === m);
  });
  drawPlot();
}

function redraw() { drawPlot(); }
window.addEventListener('load', drawPlot);
</script>
</body>
</html>
