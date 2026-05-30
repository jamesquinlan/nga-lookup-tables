<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Associativity Tests</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
<script src="js/lnslookup.js"></script>
<script src="js/takumlookup.js"></script>
<script src="js/analysishelpers.js"></script>
<style>
.plot-row { display:flex; flex-wrap:wrap; gap:1.5rem; margin-top:1rem; }
.plot-panel { display:flex; flex-direction:column; align-items:center; }
.plot-panel h3 { font-size:0.88rem; font-weight:700; margin-bottom:0.3rem; text-align:center; }
.plot-panel canvas {
  border:1px solid var(--border); border-radius:4px;
  display:block;
}
.stats-row { font-size:0.77rem; color:var(--muted); margin-top:0.35rem; text-align:center; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Associativity of Addition</h1>
  <p class="subtitle">
    For a random sample of 4&thinsp;000 triples (a, b, c) of positive normal values, each scatter
    plot compares <em>(a + b) + c</em> (x-axis) against <em>a + (b + c)</em> (y-axis) in
    log₁₀ scale. Points on the diagonal are <strong>exactly associative</strong>; points off
    the diagonal show rounding-order dependence. All arithmetic is performed by decoding to
    float64, operating, then rounding back to the format — the same as software emulation.
    The histogram below shows the distribution of log₁₀(relative error) for all four formats.
  </p>
  <p class="format-note" style="margin-bottom:1rem">
    Note: the common claim that posits and takums are "associative" refers to a hardware
    <em>quire</em> accumulator that maintains an exact running sum before a final rounding
    step. In basic software arithmetic all four formats exhibit non-associativity; the plots
    show how their error distributions compare.
  </p>

  <div class="card">
    <div class="plot-row" id="scatter-row"></div>
  </div>

  <div class="card" style="margin-top:1rem">
    <h2>Relative-error distribution (all formats overlaid)</h2>
    <p class="subtitle" style="margin-bottom:0.75rem">
      Each bar shows the fraction of triples whose relative error
      |(left&minus;right)| / avg(|left|,|right|) falls in that log₁₀ bin.
      The leftmost bar (labelled "exact") counts triples where both orderings produce
      identical decoded values.
    </p>
    <canvas id="cv-hist" width="760" height="280"
      style="max-width:100%;display:block;border:1px solid var(--border);border-radius:4px"></canvas>
    <div style="display:flex;gap:1.2rem;flex-wrap:wrap;font-size:0.8rem;color:var(--muted);margin-top:0.6rem">
      <span><span style="display:inline-block;width:12px;height:12px;background:rgba(88,148,210,0.8);border-radius:2px;vertical-align:middle"></span>&nbsp;Float E4M3</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:rgba(95,175,100,0.8);border-radius:2px;vertical-align:middle"></span>&nbsp;Posit⟨8,2⟩</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:rgba(215,145,55,0.8);border-radius:2px;vertical-align:middle"></span>&nbsp;LNS I4F3</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:rgba(175,90,165,0.8);border-radius:2px;vertical-align:middle"></span>&nbsp;Takum8</span>
    </div>
  </div>
</div>

<script>
'use strict';

var CONFIGS = [
  { fmt: 'float', label: 'Float E4M3',  sub: '8-bit | max = 240',   color: 'rgba(88,148,210,0.65)'  },
  { fmt: 'posit', label: 'Posit⟨8,2⟩', sub: '8-bit | max ≈ 16.8 M', color: 'rgba(95,175,100,0.65)'  },
  { fmt: 'lns',   label: 'LNS I4F3',   sub: '8-bit | max ≈ 236',   color: 'rgba(215,145,55,0.65)'  },
  { fmt: 'takum', label: 'Takum8',      sub: '8-bit | max ≈ 10³⁸',  color: 'rgba(175,90,165,0.65)'  },
];

// Return list of positive normal bit patterns for a format
function getPositivePats(fmt) {
  var pats = [];
  var i, d;
  if (fmt === 'float') {
    for (i = 1; i < 256; i++) {
      d = decodeFloat(i, 4, 3);
      if ((d.type === 'normal' || d.type === 'subnormal') && d.value > 0) pats.push(i);
    }
  } else if (fmt === 'posit') {
    for (i = 1; i < 128; i++) {
      var v = decodePosit(i, 8, 2);
      if (isFinite(v) && v > 0) pats.push(i);
    }
  } else if (fmt === 'lns') {
    for (i = 0; i < 256; i++) {
      d = decodeLNS(i, 4, 3);
      if (!d.isZero && d.signBit === 0 && d.value > 0) pats.push(i);
    }
  } else { // takum
    for (i = 1; i < 128; i++) {
      d = decodeTakum(i, 8);
      if (d.type !== 'nar' && d.type !== 'zero' && isFinite(d.linVal) && d.linVal > 0)
        pats.push(i);
    }
  }
  return pats;
}

function decodeVal(pat, fmt) {
  if (fmt === 'float') return decodeFloat(pat, 4, 3).value;
  if (fmt === 'posit') return decodePosit(pat, 8, 2);
  if (fmt === 'lns')   { var d = decodeLNS(pat, 4, 3); return d.isZero ? 0 : d.value; }
  var d = decodeTakum(pat, 8); return (d.type === 'zero') ? 0 : d.linVal;
}


// Sample N random triples; return array of {left, right} real values
function sampleAssoc(fmt, N) {
  var pats = getPositivePats(fmt);
  if (pats.length === 0) return [];
  var results = [];
  for (var t = 0; t < N; t++) {
    var pa = pats[(Math.random() * pats.length) | 0];
    var pb = pats[(Math.random() * pats.length) | 0];
    var pc = pats[(Math.random() * pats.length) | 0];
    var va = decodeVal(pa, fmt);
    var vb = decodeVal(pb, fmt);
    var vc = decodeVal(pc, fmt);
    var left  = fmtAdd(fmtAdd(va, vb, fmt), vc, fmt);
    var right = fmtAdd(va, fmtAdd(vb, vc, fmt), fmt);
    if (isFinite(left) && isFinite(right) && left > 0 && right > 0) {
      results.push({ left: left, right: right });
    }
  }
  return results;
}

//Scatter plot
var PAD = { l:46, r:12, t:12, b:40 };

function drawScatter(canvas, samples, color) {
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  if (!samples.length) return null;

  var logVals = samples.map(function(s) {
    return [Math.log10(s.left), Math.log10(s.right)];
  });
  var allV = logVals.reduce(function(a, v) { return a.concat(v); }, []);
  var vmin = Math.floor(Math.min.apply(null, allV));
  var vmax = Math.ceil(Math.max.apply(null, allV));
  var span = vmax - vmin || 1;

  var pw = W - PAD.l - PAD.r;
  var ph = H - PAD.t - PAD.b;

  function xPx(lv) { return PAD.l + (lv - vmin) / span * pw; }
  function yPx(lv) { return PAD.t + (1 - (lv - vmin) / span) * ph; }

  // Grid
  var gridStep = Math.max(1, Math.ceil(span / 8));
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var v = vmin; v <= vmax; v += gridStep) {
    ctx.beginPath(); ctx.moveTo(xPx(v), PAD.t); ctx.lineTo(xPx(v), PAD.t + ph); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(PAD.l, yPx(v)); ctx.lineTo(PAD.l + pw, yPx(v)); ctx.stroke();
  }

  // Diagonal y = x
  ctx.strokeStyle = '#ccc'; ctx.lineWidth = 1.5; ctx.setLineDash([4,3]);
  ctx.beginPath();
  ctx.moveTo(xPx(vmin), yPx(vmin));
  ctx.lineTo(xPx(vmax), yPx(vmax));
  ctx.stroke();
  ctx.setLineDash([]);

  // Points
  ctx.fillStyle = color;
  for (var i = 0; i < logVals.length; i++) {
    ctx.beginPath();
    ctx.arc(xPx(logVals[i][0]), yPx(logVals[i][1]), 2.2, 0, 2 * Math.PI);
    ctx.fill();
  }

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, PAD.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t + ph); ctx.lineTo(PAD.l + pw, PAD.t + ph); ctx.stroke();

  // X tick labels
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  for (var v = vmin; v <= vmax; v += gridStep) {
    ctx.fillText('10' + (v >= 0 ? '\u207A' : '\u207B') + Math.abs(v), xPx(v), PAD.t + ph + 14);
  }
  // Y tick labels
  ctx.textAlign = 'right';
  for (var v = vmin; v <= vmax; v += gridStep) {
    ctx.fillText('10' + (v >= 0 ? '\u207A' : '\u207B') + Math.abs(v), PAD.l - 4, yPx(v) + 4);
  }

  // Axis labels
  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('(a + b) + c', PAD.l + pw / 2, H - 4);
  ctx.save();
  ctx.translate(13, PAD.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('a + (b + c)', 0, 0);
  ctx.restore();

  // Stats
  var exact = 0, near = 0;
  for (var i = 0; i < samples.length; i++) {
    if (samples[i].left === samples[i].right) exact++;
    else if (Math.abs(samples[i].left - samples[i].right) /
             ((Math.abs(samples[i].left) + Math.abs(samples[i].right)) / 2) < 0.01) near++;
  }
  return { exact: exact, near: near, total: samples.length };
}

// Histogram
function relErr(s) {
  if (s.left === s.right) return 0;
  return Math.abs(s.left - s.right) / ((Math.abs(s.left) + Math.abs(s.right)) / 2);
}

function drawHistogram(canvas, allSamples, colors) {
  var W = canvas.width, H = canvas.height;
  var ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, W, H);

  var PAD2 = { l:52, r:20, t:20, b:50 };
  var pw = W - PAD2.l - PAD2.r;
  var ph = H - PAD2.t - PAD2.b;

  // Determine error range dynamically from actual data
  var errLogs = [];
  allSamples.forEach(function(sArr) {
    sArr.forEach(function(s) {
      var e = relErr(s);
      if (e > 0 && isFinite(e)) errLogs.push(Math.log10(e));
    });
  });
  var LMAX = 0;
  var LMIN, BSTEP, BINS;
  if (errLogs.length === 0) {
    LMIN = -4; BSTEP = 1; BINS = 7;
  } else {
    var eMin = Math.floor(Math.min.apply(null, errLogs));
    var range = LMAX - eMin;
    BSTEP = range <= 3 ? 0.5 : 1;
    LMIN = eMin;
    BINS = Math.round((LMAX - LMIN) / BSTEP) + 2;
  }

  function binOf(err) {
    if (err === 0) return 0;
    var l = Math.log10(err);
    if (l < LMIN) return 1;
    if (l >= LMAX) return BINS - 1;
    return 1 + Math.floor((l - LMIN) / BSTEP);
  }

  var counts = allSamples.map(function(samples) {
    var c = new Array(BINS).fill(0);
    for (var i = 0; i < samples.length; i++)
      if (samples[i].left > 0 && samples[i].right > 0) c[binOf(relErr(samples[i]))]++;
    return c;
  });

  var totals = allSamples.map(function(s) { return s.length || 1; });
  var maxFrac = 0;
  counts.forEach(function(c, fi) {
    c.forEach(function(v) { maxFrac = Math.max(maxFrac, v / totals[fi]); });
  });
  maxFrac = Math.min(maxFrac * 1.15, 1.0);

  function xBin(b) { return PAD2.l + (b / (BINS - 1)) * pw; }
  function yFrac(f) { return PAD2.t + (1 - f / maxFrac) * ph; }

  // Grid
  ctx.strokeStyle = '#ebebeb'; ctx.lineWidth = 1;
  for (var step = 0; step <= 4; step++) {
    var f = maxFrac * step / 4;
    ctx.beginPath(); ctx.moveTo(PAD2.l, yFrac(f)); ctx.lineTo(PAD2.l + pw, yFrac(f)); ctx.stroke();
  }

  // Bars
  var bw = pw / BINS;
  var gw = bw * 0.88 / allSamples.length;
  for (var fi = 0; fi < counts.length; fi++) {
    ctx.fillStyle = colors[fi];
    for (var b = 0; b < BINS; b++) {
      var frac = counts[fi][b] / totals[fi];
      if (frac === 0) continue;
      var bx = PAD2.l + b * bw + fi * gw + bw * 0.06;
      var by = yFrac(frac);
      ctx.fillRect(bx, by, gw - 1, PAD2.t + ph - by);
    }
  }

  // Axes
  ctx.strokeStyle = '#b0b4b8'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(PAD2.l, PAD2.t); ctx.lineTo(PAD2.l, PAD2.t + ph); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD2.l, PAD2.t + ph); ctx.lineTo(PAD2.l + pw, PAD2.t + ph); ctx.stroke();

  // X ticks
  ctx.fillStyle = '#888'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('exact', xBin(0), PAD2.t + ph + 14);
  for (var b = 1; b < BINS; b++) {
    var logVal = LMIN + (b - 1) * BSTEP;
    var lbl = (logVal === Math.round(logVal))
      ? ('10' + (logVal >= 0 ? '\u207A' : '\u207B') + Math.abs(logVal))
      : ('10^' + logVal.toFixed(1));
    ctx.fillText(lbl, xBin(b) + gw * allSamples.length / 2, PAD2.t + ph + 14);
  }
  // Y ticks
  ctx.textAlign = 'right';
  for (var step = 0; step <= 4; step++) {
    var f = maxFrac * step / 4;
    ctx.fillText((f * 100).toFixed(0) + '%', PAD2.l - 5, yFrac(f) + 4);
  }

  ctx.fillStyle = '#666'; ctx.font = '11px sans-serif'; ctx.textAlign = 'center';
  ctx.fillText('log₁₀(relative error)  or  exact match', PAD2.l + pw / 2, H - 6);
  ctx.save();
  ctx.translate(14, PAD2.t + ph / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText('fraction of triples', 0, 0);
  ctx.restore();
}

// Main
var N_TRIPLES = 4000;

window.addEventListener('load', function() {
  var row        = document.getElementById('scatter-row');
  var allSamples = [];

  CONFIGS.forEach(function(cfg) {
    var samples = sampleAssoc(cfg.fmt, N_TRIPLES);
    allSamples.push(samples);

    var panel = document.createElement('div');
    panel.className = 'plot-panel';

    var title = document.createElement('h3');
    title.innerHTML = cfg.label +
      '<br><span style="font-weight:400;color:var(--muted)">' + cfg.sub + '</span>';
    panel.appendChild(title);

    var cv = document.createElement('canvas');
    cv.width  = 300;
    cv.height = 300;
    panel.appendChild(cv);

    var stats = drawScatter(cv, samples, cfg.color);

    var info = document.createElement('div');
    info.className = 'stats-row';
    if (stats) {
      info.textContent =
        'n = ' + stats.total + ' triples  |  exact: ' +
        (stats.exact / stats.total * 100).toFixed(1) + '%  |  error < 1%: ' +
        ((stats.exact + stats.near) / stats.total * 100).toFixed(1) + '%';
    }
    panel.appendChild(info);
    row.appendChild(panel);
  });

  drawHistogram(
    document.getElementById('cv-hist'),
    allSamples,
    CONFIGS.map(function(c) { return c.color; })
  );
});
</script>
</body>
</html>
