<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chebyshev Node Accuracy</title>
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
.ldot { width:10px; height:10px; border-radius:50%; display:inline-block; flex-shrink:0; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; flex-wrap:wrap; }
.op-row label { font-size:0.85rem; color:var(--muted); }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
table.cb { border-collapse:collapse; font-size:0.82rem; width:100%; margin-top:1rem; }
table.cb th { background:var(--accent); color:#fff; padding:6px 10px; text-align:left; }
table.cb td { padding:5px 10px; border-bottom:1px solid var(--border); }
table.cb tr:nth-child(even) td { background:#f7f8fa; }
.best  { color:#155724; font-weight:700; }
.worst { color:#721c24; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Chebyshev Node Accuracy</h1>
  <p class="subtitle">
    Chebyshev nodes of the first kind on $[-1, 1]$ are $x_k = \cos\!\left(\frac{(2k-1)\pi}{2N}\right)$
    for $k = 1, \ldots, N$.
    They cluster near $\pm 1$ — the classical choice for polynomial interpolation because they
    minimise the Runge phenomenon.
    This plot measures how accurately each 8-bit format can <em>represent</em> these nodes:
    the y-axis shows $\log_{10}|\hat{x}_k - x_k|$, the base-10 log of the absolute
    representational error, where $\hat{x}_k$ is the nearest value the format can express.
    Formats with more precision near $\pm 1$ (Posit, Takum — their precision tapers toward 1)
    should perform better for these nodes than formats with uniform relative precision (LNS)
    or per-band uniform precision (Float).
  </p>

  <div class="card">
    <div class="op-row">
      <label>Bit width:</label>
      <button class="preset active" id="btn-8bit"  onclick="setBits(8)">8-bit</button>
      <button class="preset"        id="btn-16bit" onclick="setBits(16)">16-bit</button>
    </div>
    <div class="op-row">
      <label>Nodes $N$:</label>
      <button class="preset active" id="btn-n-8"  onclick="setN(8)">8</button>
      <button class="preset"        id="btn-n-16" onclick="setN(16)">16</button>
      <button class="preset"        id="btn-n-32" onclick="setN(32)">32</button>
    </div>
    <div class="plot-wrap">
      <canvas id="cv-cheby" width="760" height="400" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row" id="cb-legend"></div>
  </div>

  <div class="card" style="margin-top:1rem">
    <h2>Error summary per format</h2>
    <table class="cb">
      <thead>
        <tr>
          <th>Format</th>
          <th>Mean abs. error</th>
          <th>Max abs. error</th>
          <th>Min abs. error</th>
          <th>Exact nodes</th>
        </tr>
      </thead>
      <tbody id="cb-tbody"></tbody>
    </table>
    <p class="format-note" style="margin-top:0.5rem">
      "Exact nodes" counts how many Chebyshev nodes the format can represent exactly
      (error = 0 in float64 arithmetic). The mean and max errors characterise the
      format's suitability for Chebyshev-based polynomial interpolation at this precision.
    </p>
  </div>
</div>

<script>
'use strict';

// Representational error per format
function encodeAndDecode(x, fmt) {
  if (fmt === 'float') {
    var r = encodeFloat(x, 4, 3);
    return (r.type === 'zero') ? 0 : r.value;
  }
  if (fmt === 'posit') {
    var p = encodePosit(x, 8, POSITLUT);
    return decodePosit(p, 8, 2);
  }
  if (fmt === 'lns') {
    var r = encodeLNS(x, 4, 3);
    return r.isZero ? 0 : r.value;
  }
  if (fmt === 'takum') {
    var p = encodeTakum8(x, TAKUMLUT);
    var d = decodeTakum(p, 8);
    return (d.type === 'zero') ? 0 : d.linVal;
  }
  if (fmt === 'float16') {
    var r = encodeFloat(x, 5, 10);
    return (r.type === 'zero') ? 0 : r.value;
  }
  if (fmt === 'posit16') {
    var p = encodePosit(x, 16, POSITLUT16);
    return decodePosit(p, 16, 2);
  }
  if (fmt === 'lns16') {
    var r = encodeLNS(x, 8, 7);
    return r.isZero ? 0 : r.value;
  }
  // takum16
  var p = encodeTakum16(x, TAKUMLUT16);
  var d = decodeTakum(p, 16);
  return (d.type === 'zero') ? 0 : d.linVal;
}

//Chebyshev nodes
function chebyNodes(N) {
  var nodes = [];
  for (var k = 1; k <= N; k++) {
    nodes.push(Math.cos((2 * k - 1) * Math.PI / (2 * N)));
  }
  nodes.sort(function(a, b) { return a - b; });
  return nodes;
}

// Config

var CONFIGS_8 = [
  { fmt: 'float',  label: 'Float E4M3',           color: 'rgba(88,148,210,1)',  r: 6 },
  { fmt: 'posit',  label: 'Posit⟨8,2⟩', color: 'rgba(95,175,100,1)',  r: 5 },
  { fmt: 'lns',    label: 'LNS I4F3',             color: 'rgba(215,145,55,1)',  r: 4 },
  { fmt: 'takum',  label: 'Takum8',               color: 'rgba(175,90,165,1)',  r: 3 },
];

var CONFIGS_16 = [
  { fmt: 'float16', label: 'Float16 (E5M10)',       color: 'rgba(88,148,210,1)',  r: 6 },
  { fmt: 'posit16', label: 'Posit⟨16,2⟩',  color: 'rgba(95,175,100,1)',  r: 5 },
  { fmt: 'lns16',   label: 'LNS I8F7',             color: 'rgba(215,145,55,1)',  r: 4 },
  { fmt: 'takum16', label: 'Takum16',              color: 'rgba(175,90,165,1)',  r: 3 },
];

var currentBits   = 8;
var currentConfig = CONFIGS_8;
var currentN      = 8;

var PAD = { l:68, r:20, t:24, b:52 };

var _SD = ['⁰','¹','²','³','⁴','⁵','⁶','⁷','⁸','⁹'];
function fmtLog(e) {
  var sign = e < 0 ? '⁻' : '';
  return '10' + sign + String(Math.abs(e)).split('').map(function(c){ return _SD[+c]; }).join('');
}

// Render legend
function renderLegend() {
  var leg = document.getElementById('cb-legend');
  var html = '';
  currentConfig.forEach(function(cfg) {
    html += '<span><span class="ldot" style="background:' + cfg.color + '"></span>' + cfg.label + '</span>';
  });
  leg.innerHTML = html;
}

// Draw
function draw(N) {
  var nodes = chebyNodes(N);

  // Compute errors per format
  var errSets = currentConfig.map(function(cfg) {
    return nodes.map(function(x) {
      var decoded = encodeAndDecode(x, cfg.fmt);
      return Math.abs(decoded - x);
    });
  });

  var canvas = document.getElementById('cv-cheby');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  // Find y range (log scale)
  var allLogs = [];
  errSets.forEach(function(es) {
    es.forEach(function(e) { if (e > 0) allLogs.push(Math.log10(e)); });
  });
  if (allLogs.length === 0) {
    ctx.fillStyle = '#888'; ctx.font = '14px sans-serif'; ctx.textAlign = 'center';
    ctx.fillText('All nodes represented exactly', W / 2, H / 2);
    return;
  }

  var yMin = Math.floor(Math.min.apply(null, allLogs)) - 0.5;
  var yMax = Math.ceil( Math.max.apply(null, allLogs)) + 0.5;
  var yStride = Math.max(1, Math.ceil((yMax - yMin) / 7));

  function xPx(v) { return PAD.l + (v - (-1)) / 2 * pw; }
  function yPx(v) { return PAD.t + (1 - (v - yMin) / (yMax - yMin)) * ph; }

  // Grid
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var y = Math.ceil(yMin); y <= yMax; y += yStride) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(y)); ctx.lineTo(PAD.l + pw, yPx(y)); ctx.stroke();
  }
  // Vertical guide at x=0
  ctx.strokeStyle = '#ddd';
  ctx.beginPath(); ctx.moveTo(xPx(0), PAD.t); ctx.lineTo(xPx(0), PAD.t + ph); ctx.stroke();
  // Vertical guides at x=±1
  ctx.strokeStyle = '#e8e8e8';
  ctx.beginPath(); ctx.moveTo(xPx(-1), PAD.t); ctx.lineTo(xPx(-1), PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(xPx( 1), PAD.t); ctx.lineTo(xPx( 1), PAD.t + ph); ctx.stroke();

  // Draw lines + dots per format
  currentConfig.forEach(function(cfg, ci) {
    var errs = errSets[ci];
    var col  = cfg.color;

    // Connect dots with a thin line
    ctx.strokeStyle = col; ctx.lineWidth = 1; ctx.globalAlpha = 0.45;
    ctx.beginPath();
    var started = false;
    nodes.forEach(function(x, ni) {
      var e = errs[ni];
      if (e <= 0) { started = false; return; }
      var px = xPx(x), py = yPx(Math.log10(e));
      if (!started) { ctx.moveTo(px, py); started = true; } else { ctx.lineTo(px, py); }
    });
    ctx.stroke();
    ctx.globalAlpha = 1;

    // Dots
    ctx.fillStyle = col;
    nodes.forEach(function(x, ni) {
      var e = errs[ni];
      if (e <= 0) return;
      var px = xPx(x), py = yPx(Math.log10(e));
      ctx.beginPath(); ctx.arc(px, py, cfg.r, 0, 2 * Math.PI); ctx.fill();
    });
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X tick labels (node values)
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  [-1, -0.5, 0, 0.5, 1].forEach(function(v) {
    ctx.fillText(v.toFixed(1), xPx(v), PAD.t + ph + 14);
  });
  // Y tick labels (log10 error)
  ctx.textAlign = 'right';
  for (var y = Math.ceil(yMin); y <= yMax; y += yStride) {
    ctx.fillText(fmtLog(y), PAD.l - 6, yPx(y) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('node value  xₖ  (in [−1, 1])', PAD.l + pw / 2, H - 6);
  ctx.save();
  ctx.translate(14, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('log₁₀ |error|  (lower = more accurate)', 0, 0);
  ctx.restore();

  // Annotation: label clustering near ±1
  ctx.fillStyle = '#aaa'; ctx.font = 'italic 9px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('nodes cluster near ±1', PAD.l + pw / 2, PAD.t + 14);

  // Summary table 
  var tbody = document.getElementById('cb-tbody');
  tbody.innerHTML = '';

  var allMeans = errSets.map(function(es) {
    var s = 0, n = 0;
    es.forEach(function(e) { s += e; n++; });
    return n > 0 ? s / n : 0;
  });
  var minMean = Math.min.apply(null, allMeans);

  currentConfig.forEach(function(cfg, ci) {
    var errs = errSets[ci];
    var sum = 0, maxE = 0, minE = Infinity, exact = 0;
    errs.forEach(function(e) {
      sum += e;
      if (e > maxE) maxE = e;
      if (e < minE) minE = e;
      if (e === 0) exact++;
    });
    var mean = sum / errs.length;
    if (minE === Infinity) minE = 0;

    var tr = document.createElement('tr');
    function td(html, cls) {
      var el = document.createElement('td');
      el.innerHTML = html;
      if (cls) el.className = cls;
      return el;
    }

    var meanCls = (mean === minMean) ? 'best' : '';

    tr.appendChild(td('<span style="color:' + cfg.color + ';font-weight:700">' + cfg.label + '</span>'));
    tr.appendChild(td('<span class="' + meanCls + '">' + mean.toExponential(2) + '</span>'));
    tr.appendChild(td(maxE.toExponential(2)));
    tr.appendChild(td(minE === 0 ? '0 (exact)' : minE.toExponential(2)));
    tr.appendChild(td(exact + ' / ' + errs.length));
    tbody.appendChild(tr);
  });
}

// Controls
function setN(n) {
  currentN = n;
  [8, 16, 32].forEach(function(v) {
    var el = document.getElementById('btn-n-' + v);
    if (el) el.classList.toggle('active', v === n);
  });
  draw(n);
}

function setBits(b) {
  currentBits = b;
  if (b === 16) ensure16bitLUTs();
  currentConfig = (b === 8) ? CONFIGS_8 : CONFIGS_16;

  document.getElementById('btn-8bit').classList.toggle('active',  b === 8);
  document.getElementById('btn-16bit').classList.toggle('active', b === 16);

  renderLegend();
  draw(currentN);
}

window.addEventListener('load', function() {
  renderLegend();
  draw(currentN);
});
</script>
</body>
</html>
