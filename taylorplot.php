<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Taylor Series for e</title>
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
.lswatch-ref  { width:28px; height:0; display:inline-block;
                border-top:2px dashed #c8303a; position:relative; top:-1px; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; flex-wrap:wrap; }
.op-row label { font-size:0.85rem; color:var(--muted); }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
table.ts { border-collapse:collapse; font-size:0.82rem; width:100%; margin-top:1rem; }
table.ts th { background:var(--accent); color:#fff; padding:6px 10px; text-align:left; }
table.ts td { padding:5px 10px; border-bottom:1px solid var(--border); }
table.ts tr:nth-child(even) td { background:#f7f8fa; }
.stag-mark { color:var(--accent); font-weight:700; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Taylor Series for $e$</h1>
  <p class="subtitle">
    Each format accumulates $e = e^1 = \sum_{k=0}^{\infty} \frac{1}{k!} = 1 + 1 + \frac{1}{2!} + \frac{1}{3!} + \cdots$
    by computing in-format additions left to right.
    Unlike the harmonic series, this series <em>converges</em> — but each format still stagnates
    at the term where $1/k!$ falls below the representable resolution at the running sum.
    After stagnation the format has found its best approximation to $e$; adding more terms
    changes nothing. The table compares the frozen value with the true $e \approx 2.71828$.
    The stagnation point $k^*$ tells you how many terms the format can usefully absorb.
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
      <canvas id="cv-taylor" width="760" height="380" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row" id="ts-legend">
      <!-- populated by JS -->
    </div>
  </div>

  <div class="card" style="margin-top:1rem">
    <h2>Stagnation summary</h2>
    <table class="ts">
      <thead>
        <tr>
          <th>Format</th>
          <th>Stagnation $k^*$</th>
          <th>Frozen sum</th>
          <th>True $e$</th>
          <th>Abs. error</th>
          <th>Relative error</th>
        </tr>
      </thead>
      <tbody id="ts-tbody"></tbody>
    </table>
    <p class="format-note" style="margin-top:0.5rem">
      $k^*$ is the number of terms usefully accumulated before the format's resolution is exhausted.
      "Frozen sum" is the format's permanent best approximation to $e$.
      Stagnation is not an error in the algorithm — it reflects the format's finite precision.
    </p>
  </div>
</div>

<script>
'use strict';

// Taylor series computation
var TRUE_E = Math.E;

function taylorSeries(fmt, N) {
  var sums = new Float64Array(N);
  var stagnAt = null;
  var sum = 0, prev = 0;
  var term = 1.0;  // starts at 1/0! = 1
  for (var k = 1; k <= N; k++) {
    sum = fmtAdd(sum, term, fmt);
    sums[k - 1] = sum;
    if (stagnAt === null && k > 1 && sum === prev) stagnAt = k - 1;
    prev = sum;
    term = term / k;  // next iteration will add 1/k! (current: 1/(k-1)!)
  }
  if (stagnAt === null) stagnAt = N;
  return { sums: sums, stagnAt: stagnAt, frozenVal: sum };
}

// Config
var CONFIGS_8 = [
  { fmt: 'float',   label: 'Float E4M3',           color: 'rgba(88,148,210,1)'  },
  { fmt: 'posit',   label: 'Posit⟨8,2⟩', color: 'rgba(95,175,100,1)'  },
  { fmt: 'lns',     label: 'LNS I4F3',             color: 'rgba(215,145,55,1)'  },
  { fmt: 'takum',   label: 'Takum8',               color: 'rgba(175,90,165,1)'  },
];

var CONFIGS_16 = [
  { fmt: 'float16', label: 'Float16 (E5M10)',        color: 'rgba(88,148,210,1)'  },
  { fmt: 'posit16', label: 'Posit⟨16,2⟩',  color: 'rgba(95,175,100,1)'  },
  { fmt: 'lns16',   label: 'LNS I8F7',              color: 'rgba(215,145,55,1)'  },
  { fmt: 'takum16', label: 'Takum16',               color: 'rgba(175,90,165,1)'  },
];

var TERM_OPTIONS_8  = [5, 8, 12];
var TERM_OPTIONS_16 = [8, 12, 20];

var currentBits     = 8;
var currentConfig   = CONFIGS_8;
var currentTermOpts = TERM_OPTIONS_8;
var currentN        = 8;

var PAD = { l:54, r:20, t:20, b:46 };

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
  var leg = document.getElementById('ts-legend');
  var html = '<span><span class="lswatch-ref"></span>true <em>e</em></span>';
  html += '<span><span class="lswatch-dash"></span>float64 partial sum</span>';
  currentConfig.forEach(function(cfg) {
    html += '<span><span class="lswatch-line" style="background:' + cfg.color + '"></span>' + cfg.label + '</span>';
  });
  html += '<span style="color:var(--muted)">▸ marks stagnation point</span>';
  leg.innerHTML = html;
}

// Draw
function draw(N) {
  var series = currentConfig.map(function(cfg) { return taylorSeries(cfg.fmt, N); });

  // float64 reference (exact accumulation)
  var ref64 = new Float64Array(N);
  var rsum = 0, rterm = 1.0;
  for (var k = 1; k <= N; k++) {
    rsum += rterm;
    ref64[k - 1] = rsum;
    rterm = rterm / k;
  }

  var yMin = 0;
  var yMax = Math.max(3.2, Math.ceil(TRUE_E + 0.5));

  var canvas = document.getElementById('cv-taylor');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  function xPx(k)  { return PAD.l + (k - 1) / Math.max(N - 1, 1) * pw; }
  function yPx(v)  { return PAD.t + (1 - (v - yMin) / (yMax - yMin)) * ph; }

  // Grid
  var yStep = 0.5;
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var yv = 0; yv <= yMax + yStep; yv += yStep) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(yv)); ctx.lineTo(PAD.l + pw, yPx(yv)); ctx.stroke();
  }
  var xStep = Math.max(1, Math.ceil(N / 8));
  for (var k = 1; k <= N; k += xStep) {
    ctx.beginPath(); ctx.moveTo(xPx(k), PAD.t); ctx.lineTo(xPx(k), PAD.t + ph); ctx.stroke();
  }

  // True e reference line (red dashed)
  ctx.strokeStyle = '#c8303a'; ctx.lineWidth = 1.5; ctx.setLineDash([6, 4]);
  ctx.beginPath();
  ctx.moveTo(PAD.l, yPx(TRUE_E));
  ctx.lineTo(PAD.l + pw, yPx(TRUE_E));
  ctx.stroke();
  ctx.setLineDash([]);
  ctx.fillStyle = '#c8303a'; ctx.font = 'italic 10px sans-serif'; ctx.textAlign = 'left';
  ctx.fillText('e ≈ 2.71828', PAD.l + 4, yPx(TRUE_E) - 4);

  // float64 reference (grey dashed)
  ctx.strokeStyle = '#aaa'; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
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

  // X tick labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  for (var k = 1; k <= N; k += xStep) ctx.fillText(k, xPx(k), PAD.t + ph + 14);
  // Y tick labels
  ctx.textAlign = 'right';
  for (var yv = 0; yv <= yMax + yStep; yv += yStep) {
    ctx.fillText(yv.toFixed(1), PAD.l - 5, yPx(yv) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('terms accumulated  (k)', PAD.l + pw / 2, H - 5);
  ctx.save();
  ctx.translate(13, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('partial sum  Σ₁ᵏ 1/k!', 0, 0);
  ctx.restore();

  // Summary table
  var tbody = document.getElementById('ts-tbody');
  tbody.innerHTML = '';

  currentConfig.forEach(function(cfg, ci) {
    var data   = series[ci];
    var sk     = data.stagnAt;
    var frozen = data.frozenVal;
    var absErr = Math.abs(frozen - TRUE_E);
    var relErr = absErr / TRUE_E;

    var tr = document.createElement('tr');
    function td(html) { var el = document.createElement('td'); el.innerHTML = html; return el; }

    var stagnText = (sk >= N)
      ? '<em>did not stagnate in ' + N + ' terms</em>'
      : '<span class="stag-mark">k* = ' + sk + '</span>';

    tr.appendChild(td('<span style="color:' + cfg.color + ';font-weight:700">' + cfg.label + '</span>'));
    tr.appendChild(td(stagnText));
    tr.appendChild(td(frozen.toPrecision(6)));
    tr.appendChild(td(TRUE_E.toPrecision(6)));
    tr.appendChild(td(absErr < 1e-15 ? '0' : absErr.toExponential(2)));
    tr.appendChild(td(relErr < 1e-15 ? '0' : (relErr * 100).toPrecision(3) + ' %'));
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
  currentN = currentTermOpts[1];

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
