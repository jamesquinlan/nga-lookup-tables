<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Error Accumulation</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
<script src="js/lnslookup.js"></script>
<script src="js/takumlookup.js"></script>
<script src="js/analysishelpers.js"></script>
<style>
.plot-wrap { position:relative; }
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
  <h1>Error Accumulation</h1>
  <p class="subtitle">
    How does rounding error grow with repeated addition?
    For each step $k = 1, 2, \ldots, N$, we add the term $1/2^k$ to a running sum
    (a geometric series converging to 1). The plot shows $|\hat{S}_k - S_k^*| / S_k^*$
    where $S_k^* = 1 - 2^{-k}$ is the exact partial sum and $\hat{S}_k$ is the
    format-rounded result. Once a term $1/2^k$ falls below the format's minimum
    representable positive value, it rounds to zero and the sum stagnates.
  </p>

  <div class="card">
    <div class="op-row">
      <label>Max terms:</label>
      <button class="preset active" id="btn-30"  onclick="setN(30)">30</button>
      <button class="preset"        id="btn-60"  onclick="setN(60)">60</button>
      <button class="preset"        id="btn-100" onclick="setN(100)">100</button>
    </div>
    <div class="plot-wrap">
      <canvas id="cv-accum" width="760" height="360" style="max-width:100%"></canvas>
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

//Plot

var CONFIGS = [
  { fmt: 'float', color: 'rgba(88,148,210,1)',  label: 'Float E4M3'  },
  { fmt: 'posit', color: 'rgba(95,175,100,1)',  label: 'Posit⟨8,2⟩' },
  { fmt: 'lns',   color: 'rgba(215,145,55,1)',  label: 'LNS I4F3'   },
  { fmt: 'takum', color: 'rgba(175,90,165,1)',  label: 'Takum8'     },
];

var PAD = { l:58, r:16, t:16, b:46 };
var currentN = 30;

function computeSeries(fmt, N) {
  // Geometric series: S_k = sum_{j=1}^{k} 1/2^j, exact = 1 - 1/2^k
  // Compute in format; track relative error at each k
  var errors = [];
  var sum = 0;
  for (var k = 1; k <= N; k++) {
    var term = Math.pow(2, -k);           // exact term
    sum = fmtAdd(sum, term, fmt);         // add in format
    var exact = 1 - Math.pow(2, -k);
    var err = Math.abs(sum - exact) / exact;
    errors.push(err);
  }
  return errors;
}

function drawPlot(N) {
  var canvas = document.getElementById('cv-accum');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  // Compute all series
  var allSeries = CONFIGS.map(function(cfg) {
    return computeSeries(cfg.fmt, N);
  });

  // Y range: log10(err), ignoring 0 errors
  var allLogs = [];
  allSeries.forEach(function(s) {
    s.forEach(function(e) { if (e > 0) allLogs.push(Math.log10(e)); });
  });
  var yMin = allLogs.length ? Math.floor(Math.min.apply(null, allLogs)) - 1 : -10;
  var yMax = Math.min(1, allLogs.length ? Math.ceil(Math.max.apply(null, allLogs)) + 1 : 1);

  function xPx(k)  { return PAD.l + (k - 1) / (N - 1) * pw; }
  function yPx(lv) { return PAD.t + (1 - (lv - yMin) / (yMax - yMin)) * ph; }

  // Grid (horizontal log lines)
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var lv = yMin; lv <= yMax; lv++) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(lv)); ctx.lineTo(PAD.l + pw, yPx(lv)); ctx.stroke();
  }
  // Vertical grid every 5 steps
  var xStep = Math.max(1, Math.ceil(N / 10));
  for (var k = 1; k <= N; k += xStep) {
    ctx.beginPath(); ctx.moveTo(xPx(k), PAD.t); ctx.lineTo(xPx(k), PAD.t + ph); ctx.stroke();
  }

  // Lines
  CONFIGS.forEach(function(cfg, ci) {
    var series = allSeries[ci];
    ctx.strokeStyle = cfg.color;
    ctx.lineWidth = 2;
    ctx.beginPath();
    var started = false;
    for (var k = 1; k <= N; k++) {
      var e = series[k - 1];
      var lv = (e > 0) ? Math.log10(e) : yMin;
      var px = xPx(k), py = yPx(Math.max(yMin, Math.min(yMax, lv)));
      if (!started) { ctx.moveTo(px, py); started = true; } else { ctx.lineTo(px, py); }
    }
    ctx.stroke();
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X tick labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  for (var k = 1; k <= N; k += xStep) {
    ctx.fillText(k, xPx(k), PAD.t + ph + 14);
  }
  // Y tick labels
  ctx.textAlign = 'right';
  for (var lv = yMin; lv <= yMax; lv++) {
    var sup = lv >= 0 ? '\u207A' + lv : '\u207B' + Math.abs(lv);
    ctx.fillText('10' + sup, PAD.l - 5, yPx(lv) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('k  (number of terms added)', PAD.l + pw / 2, H - 5);
  ctx.save();
  ctx.translate(13, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('relative error', 0, 0);
  ctx.restore();
}

function setN(n) {
  currentN = n;
  ['30', '60', '100'].forEach(function(v) {
    document.getElementById('btn-' + v).classList.toggle('active', +v === n);
  });
  drawPlot(n);
}

window.addEventListener('load', function() { drawPlot(currentN); });
</script>
</body>
</html>
