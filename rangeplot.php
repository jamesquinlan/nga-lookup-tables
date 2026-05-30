<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Range &amp; Precision</title>
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
.legend-row { display:flex; gap:1.5rem; flex-wrap:wrap; font-size:0.8rem;
              color:var(--muted); margin-top:0.6rem; align-items:center; }
.legend-row span { display:flex; align-items:center; gap:0.4rem; }
.ldot { width:12px; height:12px; border-radius:50%; display:inline-block; flex-shrink:0; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Dynamic Range vs. Precision</h1>
  <p class="subtitle">
    Each point represents one format configuration. The <strong>x-axis</strong> shows
    $\log_{10}(\text{max} / \text{min}^+)$, the dynamic range in decades.
    The <strong>y-axis</strong> shows $-\log_{10}(\varepsilon)$ where
    $\varepsilon$ is the machine epsilon near 1 (larger = more precise near 1).
    All formats share 8 bits. Points further right have wider range; points
    higher have finer precision near 1. The ideal format is upper-right — but
    with a fixed bit budget the tradeoff is fundamental.
  </p>

  <div class="card">
    <div class="plot-wrap">
      <canvas id="cv-range" width="700" height="460" style="max-width:100%"></canvas>
    </div>
    <div class="legend-row" id="legend-row"></div>
  </div>
</div>

<script>
'use strict';


// Format analysis
function analyzeFloat(E, M) {
  var posVals = []; var oneIdx = -1;
  for (var i = 1; i < 256; i++) {
    var d = decodeFloat(i, E, M);
    if ((d.type === 'normal' || d.type === 'subnormal') && d.value > 0) {
      posVals.push(d.value);
      if (d.value === 1.0) oneIdx = i;
    }
  }
  posVals.sort(function(a,b){return a-b;});
  var machEps = null;
  if (oneIdx >= 0) {
    var dn = decodeFloat(oneIdx + 1, E, M);
    if (dn.type === 'normal') machEps = dn.value - 1.0;
  }
  return { minPos: posVals[0], maxPos: posVals[posVals.length-1], machEps: machEps };
}

function analyzePosit(es) {
  var posVals = []; var oneIdx = -1;
  for (var i = 1; i < 128; i++) {
    var v = decodePosit(i, 8, es);
    if (isFinite(v) && v > 0) {
      posVals.push(v);
      if (v === 1.0) oneIdx = i;
    }
  }
  posVals.sort(function(a,b){return a-b;});
  var machEps = (oneIdx >= 0 && oneIdx < 127)
    ? decodePosit(oneIdx + 1, 8, es) - 1.0 : null;
  return { minPos: posVals[0], maxPos: posVals[posVals.length-1], machEps: machEps };
}

function analyzeTakum() {
  var posVals = []; var oneIdx = -1;
  for (var i = 1; i < 128; i++) {
    var d = decodeTakum(i, 8);
    if (d.type !== 'nar' && isFinite(d.linVal) && d.linVal > 0) {
      posVals.push(d.linVal);
      if (Math.abs(d.linVal - 1.0) < 1e-12) oneIdx = i;
    }
  }
  posVals.sort(function(a,b){return a-b;});
  var machEps = null;
  if (oneIdx >= 0 && oneIdx < 127) {
    machEps = decodeTakum(oneIdx + 1, 8).linVal - decodeTakum(oneIdx, 8).linVal;
  }
  return { minPos: posVals[0], maxPos: posVals[posVals.length-1], machEps: machEps };
}

function analyzeLNS(I, F) {
  var posVals = []; var oneIdx = -1, bestDist = Infinity;
  for (var i = 0; i < 256; i++) {
    var d = decodeLNS(i, I, F);
    if (!d.isZero && d.signBit === 0 && isFinite(d.value) && d.value > 0) {
      posVals.push(d.value);
      var dist = Math.abs(d.value - 1.0);
      if (dist < bestDist) { bestDist = dist; oneIdx = i; }
    }
  }
  posVals.sort(function(a,b){return a-b;});
  var machEps = null;
  if (oneIdx >= 0) {
    var d1 = decodeLNS(oneIdx, I, F), d2 = decodeLNS(oneIdx + 1, I, F);
    if (!d2.isZero && d2.value > d1.value) machEps = d2.value - d1.value;
  }
  return { minPos: posVals[0], maxPos: posVals[posVals.length-1], machEps: machEps };
}

// Format list

var GROUP_COLORS = {
  Float:  '#5894d2',
  Posit:  '#5faf64',
  Takum:  '#af5aa5',
  LNS:    '#d79137',
};

var FORMATS = [
  { name: 'E4M3',      group: 'Float',  fn: function(){ return analyzeFloat(4, 3); } },
  { name: 'E5M2',      group: 'Float',  fn: function(){ return analyzeFloat(5, 2); } },
  { name: 'E3M4',      group: 'Float',  fn: function(){ return analyzeFloat(3, 4); } },
  { name: 'P⟨8,0⟩',   group: 'Posit',  fn: function(){ return analyzePosit(0); } },
  { name: 'P⟨8,1⟩',   group: 'Posit',  fn: function(){ return analyzePosit(1); } },
  { name: 'P⟨8,2⟩',   group: 'Posit',  fn: function(){ return analyzePosit(2); } },
  { name: 'P⟨8,3⟩',   group: 'Posit',  fn: function(){ return analyzePosit(3); } },
  { name: 'Takum8',    group: 'Takum',  fn: function(){ return analyzeTakum(); } },
  { name: 'I2F5',      group: 'LNS',    fn: function(){ return analyzeLNS(2, 5); } },
  { name: 'I3F4',      group: 'LNS',    fn: function(){ return analyzeLNS(3, 4); } },
  { name: 'I4F3',      group: 'LNS',    fn: function(){ return analyzeLNS(4, 3); } },
  { name: 'I5F2',      group: 'LNS',    fn: function(){ return analyzeLNS(5, 2); } },
  { name: 'I6F1',      group: 'LNS',    fn: function(){ return analyzeLNS(6, 1); } },
];

// Draw

var PAD = { l:62, r:20, t:20, b:52 };

window.addEventListener('load', function() {
  var canvas = document.getElementById('cv-range');
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  var points = FORMATS.map(function(f) {
    var r = f.fn();
    var dynRange = Math.log10(r.maxPos / r.minPos);
    var precBits = (r.machEps !== null && r.machEps > 0) ? -Math.log10(r.machEps) : null;
    return { name: f.name, group: f.group, x: dynRange, y: precBits };
  }).filter(function(p) { return p.y !== null; });

  var xMin = Math.floor(Math.min.apply(null, points.map(function(p){return p.x;})));
  var xMax = Math.ceil( Math.max.apply(null, points.map(function(p){return p.x;})));
  var yMin = Math.floor(Math.min.apply(null, points.map(function(p){return p.y;})));
  var yMax = Math.ceil( Math.max.apply(null, points.map(function(p){return p.y;})));
  // Add a bit of padding
  xMin -= 0.5; xMax += 0.5; yMin -= 0.3; yMax += 0.3;

  function xPx(v) { return PAD.l + (v - xMin) / (xMax - xMin) * pw; }
  function yPx(v) { return PAD.t + (1 - (v - yMin) / (yMax - yMin)) * ph; }

  var xStride = Math.max(1, Math.ceil((xMax - xMin) / 4));
  var yStride = Math.max(1, Math.ceil((yMax - yMin) / 6));

  // Grid
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var x = Math.ceil(xMin); x <= xMax; x += xStride) {
    ctx.beginPath(); ctx.moveTo(xPx(x), PAD.t); ctx.lineTo(xPx(x), PAD.t + ph); ctx.stroke();
  }
  for (var y = Math.ceil(yMin); y <= yMax; y += yStride) {
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(y)); ctx.lineTo(PAD.l + pw, yPx(y)); ctx.stroke();
  }

  // Points + labels
  points.forEach(function(p) {
    var col = GROUP_COLORS[p.group];
    var px = xPx(p.x), py = yPx(p.y);
    ctx.fillStyle = col;
    ctx.beginPath(); ctx.arc(px, py, 7, 0, 2 * Math.PI); ctx.fill();
    ctx.fillStyle = '#333';
    ctx.font = 'bold 10px sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(p.name, px + 10, py + 4);
  });

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // Tick labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  for (var x = Math.ceil(xMin); x <= xMax; x += xStride) {
    ctx.fillText(x.toFixed(0), xPx(x), PAD.t + ph + 14);
  }
  ctx.textAlign = 'right';
  for (var y = Math.ceil(yMin); y <= yMax; y += yStride) {
    ctx.fillText(y.toFixed(1), PAD.l - 5, yPx(y) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('dynamic range  (log\u2081\u2080(max / min\u207A)  decades)', PAD.l + pw / 2, H - 8);
  ctx.save();
  ctx.translate(14, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('\u2212log\u2081\u2080(\u03b5)  \u2014  precision near 1  (higher = better)', 0, 0);
  ctx.restore();

  // Legend
  var legRow = document.getElementById('legend-row');
  Object.keys(GROUP_COLORS).forEach(function(g) {
    var sp = document.createElement('span');
    sp.innerHTML = '<span class="ldot" style="background:' + GROUP_COLORS[g] + '"></span>' + g;
    legRow.appendChild(sp);
  });
});
</script>
</body>
</html>
