<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Harmonic Series Test</title>
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
.lswatch-line { width:28px; height:3px; display:inline-block; border-radius:2px; }
.lswatch-dash { width:28px; height:0; display:inline-block;
                border-top:2px dashed #888; position:relative; top:-1px; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; flex-wrap:wrap; }
.op-row label { font-size:0.85rem; color:var(--muted); }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
table.hs { border-collapse:collapse; font-size:0.82rem; width:100%; margin-top:1rem; }
table.hs th { background:var(--accent); color:#fff; padding:6px 10px; text-align:left; }
table.hs td { padding:5px 10px; border-bottom:1px solid var(--border); }
table.hs tr:nth-child(even) td { background:#f7f8fa; }
.stag-mark { color:var(--accent); font-weight:700; }
</style>
<!-- 
 I saw this test in: 
 
[1] Higham, N. J., & Pranesh, S. (2019). Simulating low precision floating-point arithmetic. SIAM Journal on Scientific Computing, 41(5), C585-C602.  

@article{higham2019simulating,
  title={Simulating low precision floating-point arithmetic},
  author={Higham, Nicholas J and Pranesh, Srikara},
  journal={SIAM Journal on Scientific Computing},
  volume={41},
  number={5},
  pages={C585--C602},
  year={2019},
  publisher={SIAM}
}
 -->
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Harmonic Series</h1>
  <p class="subtitle">
    Each format accumulates the harmonic series $H_N = 1 + \tfrac{1}{2} + \tfrac{1}{3} + \cdots + \tfrac{1}{N}$
    by computing in-format additions left to right.
    Because the terms $1/k$ shrink while the running sum grows,
    at some stagnation point $N^*$ the next term is smaller than half the format's
    ULP at the current sum — and the sum stops changing forever.
    The table shows the stagnation point, the final frozen value, and the
    relative error compared to the float64 partial sum at the same $N$.
  </p>

  <div class="card">
    <div class="op-row">
      <label>Bit width:</label>
      <button class="preset active" id="btn-8bit"  onclick="setBits(8)">8-bit</button>
      <button class="preset"        id="btn-16bit" onclick="setBits(16)">16-bit</button>
    </div>
    <div class="op-row" id="terms-row">
      <!-- populated by JS -->
    </div>
    <div class="plot-wrap">
      <canvas id="cv-harm" width="760" height="380" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row" id="hs-legend">
      <!-- populated by JS -->
    </div>
  </div>

  <div class="card" style="margin-top:1rem">
    <h2>Stagnation summary</h2>
    <table class="hs" id="hs-table">
      <thead>
        <tr>
          <th>Format</th>
          <th>Stagnation point $N^*$</th>
          <th>Frozen sum</th>
          <th>float64 $H_{N^*}$</th>
          <th>Relative error</th>
          <th>Missed contribution</th>
        </tr>
      </thead>
      <tbody id="hs-tbody"></tbody>
    </table>
    <p class="format-note" style="margin-top:0.5rem">
      "Missed contribution" is $H_\infty^\text{fmt} - H_{N^*}^\text{float64}$
      expressed as a fraction of $H_{N^*}^\text{float64}$:
      how much of the tail $\sum_{k > N^*} 1/k$ the format can never accumulate.
      Since the harmonic series diverges, this grows without bound as more terms
      are considered — but the format is permanently frozen at $N^*$.
    </p>
  </div>
</div>

<script>
'use strict';

// Harmonic series computation

function harmonicSeries(fmt, N) {
  var sums = new Float64Array(N);
  var stagnAt = null;
  var prev = 0, sum = 0;
  for (var k = 1; k <= N; k++) {
    sum = fmtAdd(sum, 1 / k, fmt);
    sums[k - 1] = sum;
    if (stagnAt === null && k > 1 && sum === prev) stagnAt = k - 1;
    prev = sum;
  }
  if (stagnAt === null) stagnAt = N;
  return { sums: sums, stagnAt: stagnAt, frozenVal: sum };
}

function harmonicFloat64(N) {
  var s = 0;
  for (var k = 1; k <= N; k++) s += 1 / k;
  return s;
}

// Config 

var CONFIGS_8 = [
  { fmt: 'float',   label: 'Float E4M3',      color: 'rgba(88,148,210,1)'  },
  { fmt: 'posit',   label: 'Posit\u27E88,2\u27E9', color: 'rgba(95,175,100,1)'  },
  { fmt: 'lns',     label: 'LNS I4F3',        color: 'rgba(215,145,55,1)'  },
  { fmt: 'takum',   label: 'Takum8',          color: 'rgba(175,90,165,1)'  },
];

var CONFIGS_16 = [
  { fmt: 'float16', label: 'Float16 (E5M10)',  color: 'rgba(88,148,210,1)'  },
  { fmt: 'posit16', label: 'Posit\u27E816,2\u27E9', color: 'rgba(95,175,100,1)'  },
  { fmt: 'lns16',   label: 'LNS I8F7',        color: 'rgba(215,145,55,1)'  },
  { fmt: 'takum16', label: 'Takum16',         color: 'rgba(175,90,165,1)'  },
];

var TERM_OPTIONS_8  = [10, 20, 50];
var TERM_OPTIONS_16 = [100, 500, 2000];

var currentBits       = 8;
var currentConfig     = CONFIGS_8;
var currentTermOpts   = TERM_OPTIONS_8;
var currentN          = 20;

var PAD = { l:54, r:16, t:16, b:46 };

// UI helpers

function renderTermButtons() {
  var row = document.getElementById('terms-row');
  row.innerHTML = '<label>Terms:</label>';
  currentTermOpts.forEach(function(v) {
    var btn = document.createElement('button');
    btn.className = 'preset' + (v === currentN ? ' active' : '');
    btn.id = 'btn-n-' + v;
    btn.textContent = v;
    btn.onclick = (function(n) { return function() { setN(n); }; })(v);
    row.appendChild(btn);
  });
}

function renderLegend() {
  var leg = document.getElementById('hs-legend');
  var html = '<span><span class="lswatch-dash"></span>float64 reference</span>';
  currentConfig.forEach(function(cfg) {
    html += '<span><span class="lswatch-line" style="background:' + cfg.color + '"></span>' + cfg.label + '</span>';
  });
  html += '<span style="color:var(--muted)">\u25b8 marks stagnation point</span>';
  leg.innerHTML = html;
}

// Draw 

function draw(N) {
  var series = currentConfig.map(function(cfg) { return harmonicSeries(cfg.fmt, N); });

  var ref64 = new Float64Array(N);
  var s = 0;
  for (var k = 1; k <= N; k++) { s += 1 / k; ref64[k - 1] = s; }

  var yMax = Math.ceil(ref64[N - 1]) + 1;
  var yMin = 0;

  var canvas = document.getElementById('cv-harm');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  function xPx(k)  { return PAD.l + (k - 1) / (N - 1) * pw; }
  function yPx(v)  { return PAD.t + (1 - (v - yMin) / (yMax - yMin)) * ph; }

  var yStep = Math.max(0.5, Math.ceil(yMax) / 6);
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var yv = 0; yv <= yMax + yStep; yv += yStep) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(yv)); ctx.lineTo(PAD.l + pw, yPx(yv)); ctx.stroke();
  }
  var xStep = Math.max(1, Math.ceil(N / 8));
  for (var k = 1; k <= N; k += xStep) {
    ctx.beginPath(); ctx.moveTo(xPx(k), PAD.t); ctx.lineTo(xPx(k), PAD.t + ph); ctx.stroke();
  }

  // float64 reference (dashed)
  ctx.strokeStyle = '#aaa'; ctx.lineWidth = 1.5; ctx.setLineDash([5, 4]);
  ctx.beginPath();
  for (var k = 1; k <= N; k++) {
    var px = xPx(k), py = yPx(ref64[k - 1]);
    if (k === 1) ctx.moveTo(px, py); else ctx.lineTo(px, py);
  }
  ctx.stroke();
  ctx.setLineDash([]);

  // Format lines + stagnation markers
  currentConfig.forEach(function(cfg, ci) {
    var data = series[ci];
    ctx.strokeStyle = cfg.color; ctx.lineWidth = 2.2;
    ctx.beginPath();
    for (var k = 1; k <= N; k++) {
      var px = xPx(k), py = yPx(data.sums[k - 1]);
      if (k === 1) ctx.moveTo(px, py); else ctx.lineTo(px, py);
    }
    ctx.stroke();

    var sk = Math.min(data.stagnAt, N);
    var sx = xPx(sk), sy = yPx(data.sums[sk - 1]);
    ctx.fillStyle = cfg.color;
    ctx.beginPath();
    ctx.moveTo(sx, sy - 8);
    ctx.lineTo(sx - 5, sy - 1);
    ctx.lineTo(sx + 5, sy - 1);
    ctx.closePath();
    ctx.fill();
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  for (var k = 1; k <= N; k += xStep) ctx.fillText(k, xPx(k), PAD.t + ph + 14);
  // Y labels
  ctx.textAlign = 'right';
  for (var yv = 0; yv <= yMax + yStep; yv += yStep) {
    ctx.fillText(yv.toFixed(1), PAD.l - 5, yPx(yv) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('k  (number of terms)', PAD.l + pw / 2, H - 5);
  ctx.save();
  ctx.translate(13, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('partial sum  H\u2096', 0, 0);
  ctx.restore();

  // Summary table 

  var tbody = document.getElementById('hs-tbody');
  tbody.innerHTML = '';

  currentConfig.forEach(function(cfg, ci) {
    var data   = series[ci];
    var sk     = data.stagnAt;
    var frozen = data.frozenVal;
    var ref    = harmonicFloat64(sk);
    var relErr = Math.abs(frozen - ref) / ref;
    var tailFrac = (ref64[N - 1] - ref) / ref;

    var tr = document.createElement('tr');
    function td(html) { var el = document.createElement('td'); el.innerHTML = html; return el; }

    var stagnText = (sk >= N)
      ? '<em>did not stagnate in ' + N + ' terms</em>'
      : '<span class="stag-mark">N* = ' + sk + '</span>';

    tr.appendChild(td('<span style="color:' + cfg.color + ';font-weight:700">' + cfg.label + '</span>'));
    tr.appendChild(td(stagnText));
    tr.appendChild(td(frozen.toPrecision(5)));
    tr.appendChild(td(ref.toPrecision(5)));
    tr.appendChild(td(relErr < 1e-10 ? '0' : (relErr * 100).toPrecision(3) + ' %'));
    tr.appendChild(td(tailFrac > 0 ? '+' + (tailFrac * 100).toFixed(1) + ' %' : '0 %'));
    tbody.appendChild(tr);
  });
}

function setN(n) {
  currentN = n;
  currentTermOpts.forEach(function(v) {
    var el = document.getElementById('btn-n-' + v);
    if (el) el.classList.toggle('active', v === n);
  });
  draw(n);
}

function setBits(b) {
  currentBits = b;
  if (b === 16) ensure16bitLUTs();
  currentConfig   = (b === 8) ? CONFIGS_8  : CONFIGS_16;
  currentTermOpts = (b === 8) ? TERM_OPTIONS_8 : TERM_OPTIONS_16;
  currentN = currentTermOpts[1];   // middle option as default

  document.getElementById('btn-8bit').classList.toggle('active',  b === 8);
  document.getElementById('btn-16bit').classList.toggle('active', b === 16);

  renderTermButtons();
  renderLegend();
  draw(currentN);
}

window.addEventListener('load', function() {
  renderTermButtons();
  renderLegend();
  draw(currentN);
});
</script>
</body>
</html>
