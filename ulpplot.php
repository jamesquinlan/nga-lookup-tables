<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ULP Distribution</title>
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
.lswatch { width:28px; height:3px; display:inline-block; border-radius:2px; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; flex-wrap:wrap; }
.op-row label { font-size:0.85rem; color:var(--muted); }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>ULP Distribution</h1>
  <p class="subtitle">
    A <em>unit in the last place</em> (ULP) is the gap between a representable value and
    the next larger one. The plot shows <strong>relative ULP</strong> $= \text{ULP}(x)/x$
    — the fractional precision at each value — across the full positive range of each 8-bit format.
    A flat horizontal line means <em>uniform relative precision</em> (same percentage error at
    every magnitude). A U-shaped curve means <em>tapered precision</em>: more precise near the
    centre, coarser at extremes. LNS is the only format with exactly uniform relative precision;
    Float is nearly uniform within each exponent band; Posit and Takum taper.
  </p>

  <div class="card">
    <div class="op-row">
      <label>Y-axis:</label>
      <button class="preset active" id="btn-rel" onclick="setMode('rel')">Relative ULP &nbsp;(ULP / value)</button>
      <button class="preset" id="btn-abs" onclick="setMode('abs')">Absolute ULP</button>
    </div>
    <div class="plot-wrap">
      <canvas id="cv-ulp" width="760" height="420" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row">
      <span><span class="lswatch" style="background:rgba(88,148,210,1)"></span>Float E4M3</span>
      <span><span class="lswatch" style="background:rgba(95,175,100,1)"></span>Posit⟨8,2⟩</span>
      <span><span class="lswatch" style="background:rgba(215,145,55,1)"></span>LNS I4F3</span>
      <span><span class="lswatch" style="background:rgba(175,90,165,1)"></span>Takum8</span>
    </div>
  </div>
</div>

<script>
'use strict';

// Collect positive representable values per format
function getPositiveVals(fmt) {
  var vals = [];
  if (fmt === 'float') {
    for (var i = 0; i < 256; i++) {
      var d = decodeFloat(i, 4, 3);
      if ((d.type === 'normal' || d.type === 'subnormal') && d.value > 0)
        vals.push(d.value);
    }
  } else if (fmt === 'posit') {
    for (var i = 1; i < 128; i++) {
      var v = decodePosit(i, 8, 2);
      if (v > 0 && isFinite(v)) vals.push(v);
    }
  } else if (fmt === 'lns') {
    for (var i = 0; i < 256; i++) {
      var d = decodeLNS(i, 4, 3);
      if (!d.isZero && d.signBit === 0 && d.value > 0 && isFinite(d.value))
        vals.push(d.value);
    }
  } else { // takum
    for (var i = 1; i < 128; i++) {
      var d = decodeTakum(i, 8);
      if (d.type !== 'nar' && d.type !== 'zero' && isFinite(d.linVal) && d.linVal > 0)
        vals.push(d.linVal);
    }
  }
  vals.sort(function(a, b) { return a - b; });
  return vals;
}

// Compute ULP points: for each consecutive pair, record {x, ulp, relUlp}
function computeULP(fmt) {
  var vals = getPositiveVals(fmt);
  var pts = [];
  for (var i = 0; i < vals.length - 1; i++) {
    var lo = vals[i], hi = vals[i + 1];
    var ulp = hi - lo;
    pts.push({ x: lo, ulp: ulp, relUlp: ulp / lo });
  }
  return pts;
}


// Proper unicode superscript rendering for axis tick labels
var _SD = ['\u2070','\u00B9','\u00B2','\u00B3','\u2074','\u2075','\u2076','\u2077','\u2078','\u2079'];
function fmtLog(e) {
  var sign = e < 0 ? '\u207B' : '';
  return '10' + sign + String(Math.abs(e)).split('').map(function(c){ return _SD[+c]; }).join('');
}

var CONFIGS = [
  { fmt: 'float', color: 'rgba(88,148,210,1)',  lw: 2,   label: 'Float E4M3'  },
  { fmt: 'posit', color: 'rgba(95,175,100,1)',  lw: 2,   label: 'Posit⟨8,2⟩' },
  { fmt: 'lns',   color: 'rgba(215,145,55,1)',  lw: 4,   label: 'LNS I4F3'   },
  { fmt: 'takum', color: 'rgba(175,90,165,1)',  lw: 2,   label: 'Takum8'     },
];

var PAD = { l:72, r:20, t:20, b:56 };
var currentMode = 'rel';  // 'rel' or 'abs'

// Precompute all ULP data
var ULP_DATA = {};
CONFIGS.forEach(function(cfg) { ULP_DATA[cfg.fmt] = computeULP(cfg.fmt); });

function drawPlot(mode) {
  var canvas = document.getElementById('cv-ulp');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  // Gather all x and y values for range
  var allX = [], allY = [];
  CONFIGS.forEach(function(cfg) {
    ULP_DATA[cfg.fmt].forEach(function(p) {
      allX.push(Math.log10(p.x));
      var y = (mode === 'rel') ? p.relUlp : p.ulp;
      if (y > 0) allY.push(Math.log10(y));
    });
  });

  var xMin = Math.floor(Math.min.apply(null, allX));
  var xMax = Math.ceil( Math.max.apply(null, allX));
  var yMin = Math.floor(Math.min.apply(null, allY));
  var yMax = Math.ceil( Math.max.apply(null, allY));
  var xStride = Math.max(1, Math.ceil((xMax - xMin) / 8));
  var yStride = Math.max(1, Math.ceil((yMax - yMin) / 7));

  function xPx(lv) { return PAD.l + (lv - xMin) / (xMax - xMin) * pw; }
  function yPx(lv) { return PAD.t + (1 - (lv - yMin) / (yMax - yMin)) * ph; }

  // Grid
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var x = xMin; x <= xMax; x += xStride) {
    ctx.beginPath(); ctx.moveTo(xPx(x), PAD.t); ctx.lineTo(xPx(x), PAD.t + ph); ctx.stroke();
  }
  for (var y = yMin; y <= yMax; y += yStride) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(y)); ctx.lineTo(PAD.l + pw, yPx(y)); ctx.stroke();
  }

  // Reference line at y=0 (rel ULP = 1 = 100%) for abs mode, or machine eps for rel
  if (mode === 'rel') {
    // Draw a light reference at y = log10(0.125) = -0.9 (Float E4M3 nominal eps)
    ctx.strokeStyle = '#ddd'; ctx.lineWidth = 1; ctx.setLineDash([3, 4]);
    var refY = Math.log10(0.125);
    if (refY >= yMin && refY <= yMax) {
      ctx.beginPath(); ctx.moveTo(PAD.l, yPx(refY)); ctx.lineTo(PAD.l + pw, yPx(refY)); ctx.stroke();
    }
    ctx.setLineDash([]);
  }

  // Draw lines
  CONFIGS.forEach(function(cfg) {
    var pts = ULP_DATA[cfg.fmt];
    ctx.strokeStyle = cfg.color;
    ctx.lineWidth   = cfg.lw;
    ctx.beginPath();
    var started = false;
    for (var i = 0; i < pts.length; i++) {
      var p  = pts[i];
      var yv = (mode === 'rel') ? p.relUlp : p.ulp;
      if (yv <= 0) continue;
      var lx = Math.log10(p.x);
      var ly = Math.log10(yv);
      if (lx < xMin || lx > xMax || ly < yMin || ly > yMax) {
        started = false; continue;
      }
      var px = xPx(lx), py = yPx(ly);
      if (!started) { ctx.moveTo(px, py); started = true; } else { ctx.lineTo(px, py); }
    }
    ctx.stroke();
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X tick labels  (10^x)
  ctx.fillStyle = '#888'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  for (var x = xMin; x <= xMax; x += xStride) {
    ctx.fillText(fmtLog(x), xPx(x), PAD.t + ph + 18);
  }
  // Y tick labels
  ctx.textAlign = 'right';
  for (var y = yMin; y <= yMax; y += yStride) {
    ctx.fillText(fmtLog(y), PAD.l - 6, yPx(y) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('value  x  (log scale)', PAD.l + pw / 2, H - 6);
  ctx.save();
  ctx.translate(14, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  var yLabel = (mode === 'rel')
    ? 'relative ULP  =  ULP(x) / x  (log scale)'
    : 'ULP(x)  =  next value \u2212 x  (log scale)';
  ctx.fillText(yLabel, 0, 0);
  ctx.restore();

  // Annotate key features
  if (mode === 'rel') {
    ctx.font = 'italic 10px sans-serif';

    var lnsData = ULP_DATA['lns'];
    if (lnsData.length > 0) {
      var lnsY = Math.log10(lnsData[Math.floor(lnsData.length / 2)].relUlp);
      ctx.fillStyle = 'rgba(215,145,55,0.85)';
      ctx.textAlign = 'left';
      if (lnsY >= yMin && lnsY <= yMax)
        ctx.fillText('LNS: uniform', xPx(xMin + 0.2), yPx(lnsY) - 7);
    }

    var floatData = ULP_DATA['float'];
    if (floatData.length > 0) {
      var floatY = Math.log10(floatData[Math.floor(floatData.length * 0.6)].relUlp);
      ctx.fillStyle = 'rgba(88,148,210,0.85)';
      ctx.textAlign = 'right';
      if (floatY >= yMin && floatY <= yMax)
        ctx.fillText('Float: ≈ uniform per band', xPx(xMax - 0.1), yPx(floatY) - 7);
    }

    ctx.fillStyle = 'rgba(95,175,100,0.7)';
    ctx.textAlign = 'left';
    ctx.fillText('Posit: most precise near 1', xPx(-0.3), yPx(yMin + 0.3));
  }
}

function setMode(mode) {
  currentMode = mode;
  document.getElementById('btn-rel').classList.toggle('active', mode === 'rel');
  document.getElementById('btn-abs').classList.toggle('active', mode === 'abs');
  drawPlot(mode);
}

window.addEventListener('load', function() { drawPlot(currentMode); });
</script>
</body>
</html>
