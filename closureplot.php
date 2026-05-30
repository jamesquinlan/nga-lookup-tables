<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Closure Plots</title>
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
.plot-panel h3 { font-size:0.88rem; font-weight:700; margin-bottom:0.4rem; text-align:center; }
.plot-panel canvas {
  width:240px; height:240px;
  image-rendering:pixelated; image-rendering:crisp-edges;
  border:1px solid var(--border); border-radius:4px;
}
.cl-legend { display:flex; gap:1.2rem; flex-wrap:wrap; font-size:0.8rem;
             color:var(--muted); margin-top:0.85rem; align-items:center; }
.cl-legend span { display:flex; align-items:center; gap:0.35rem; }
.sw { width:13px; height:13px; border-radius:2px; flex-shrink:0; display:inline-block; }
.op-row { display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center; }
.op-row span { font-size:0.8rem; color:var(--muted); }
button.active { background:var(--accent) !important; color:#fff !important;
                border-color:var(--accent) !important; }
.hm-xlabel { font-size:0.7rem; color:var(--muted); text-align:center; margin-top:3px; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Closure under Arithmetic Operations</h1>
  <p class="subtitle">
    Each cell $(a, b)$ is coloured by the result category when computing $a \oplus b$
    across all 256 bit patterns.
    <strong>Black</strong>: result is exactly representable.
    <strong>Purple</strong>: result rounded to nearest representable value.
    <strong>Blue</strong>: non-zero result underflows to zero.
    <strong>Forest green</strong>: result saturates to maxpos or minpos (Posit and Takum treat
    overflow as clamping to the largest finite value rather than producing NaR).
    <strong>Red</strong>: result overflows to Inf or NaN (Float only).
    <strong>Yellow</strong>: at least one input is a special value (Inf, NaN, or NaR).
    LNS has no special values and always clamps to its representable range.
  </p>

  <div class="card">
    <div class="op-row">
      <span>Operation:</span>
      <button class="preset active" id="btn-add" onclick="setOp('add')">Addition &nbsp; a + b</button>
      <button class="preset"        id="btn-mul" onclick="setOp('mul')">Multiplication &nbsp; a × b</button>
    </div>

    <div class="plot-row">
      <div class="plot-panel">
        <h3>Float E4M3<br><span style="font-weight:400;color:var(--muted)">8-bit &nbsp;|&nbsp; max = 240</span></h3>
        <canvas id="cv-float" width="256" height="256"></canvas>
        <div class="hm-xlabel">a (bit pattern 0 → 255)</div>
      </div>
      <div class="plot-panel">
        <h3>Posit⟨8,2⟩<br><span style="font-weight:400;color:var(--muted)">8-bit &nbsp;|&nbsp; max ≈ 16.8 M</span></h3>
        <canvas id="cv-posit" width="256" height="256"></canvas>
        <div class="hm-xlabel">a (bit pattern 0 → 255)</div>
      </div>
      <div class="plot-panel">
        <h3>LNS I4F3<br><span style="font-weight:400;color:var(--muted)">8-bit &nbsp;|&nbsp; max ≈ 236 &nbsp;|&nbsp; saturates</span></h3>
        <canvas id="cv-lns" width="256" height="256"></canvas>
        <div class="hm-xlabel">a (bit pattern 0 → 255)</div>
      </div>
      <div class="plot-panel">
        <h3>Takum8<br><span style="font-weight:400;color:var(--muted)">8-bit &nbsp;|&nbsp; max ≈ 10³⁸</span></h3>
        <canvas id="cv-takum" width="256" height="256"></canvas>
        <div class="hm-xlabel">a (bit pattern 0 → 255)</div>
      </div>
    </div>

    <div class="cl-legend">
      <span><span class="sw" style="background:#000000"></span>Exact result</span>
      <span><span class="sw" style="background:#800080"></span>Rounded (approximate)</span>
      <span><span class="sw" style="background:#4169e1"></span>Underflow to zero</span>
      <span><span class="sw" style="background:#228b22"></span>Saturate to maxpos/minpos (Posit/Takum)</span>
      <span><span class="sw" style="background:#d23232"></span>Overflow to Inf/NaN (Float)</span>
      <span><span class="sw" style="background:#ffc800"></span>Special input (Inf/NaN/NaR)</span>
    </div>
    <p class="format-note" style="margin-top:0.5rem">
      Each axis (a horizontal, b vertical) enumerates all 256 bit patterns (0–255).
      Positive values occupy patterns 0–127; negative values occupy 128–255 (two's complement
      or sign-bit encoding depending on format).
      Float: patterns 120–127 = +Inf/+NaN, 248–255 = −Inf/−NaN (shown yellow).
      Posit/Takum: pattern 128 = NaR (shown yellow).
      LNS has no NaR; patterns 64 and 192 (for I4F3) are the ±zero sentinels.
    </p>
  </div>
</div>

<script>
'use strict';

// Heatmap
var COL = {
  EXACT:     [  0,   0,   0],  // black
  APPROX:    [128,   0, 128],  // purple
  OVERFLOW:  [210,  50,  50],  // red    (float Inf/NaN)
  UNDERFLOW: [ 65, 105, 225],  // royal blue
  SATURATE:  [ 34, 139,  34],  // forest green (posit/takum clamp to maxpos)
  SPECIAL:   [255, 200,   0],  // yellow (special input)
};

function drawHeatmap(canvas, getType) {
  var N   = 256;
  var ctx = canvas.getContext('2d');
  var img = ctx.createImageData(N, N);
  var d   = img.data;
  for (var row = 0; row < N; row++) {
    for (var col = 0; col < N; col++) {
      var c   = COL[getType(col, row)];
      var idx = (row * N + col) * 4;
      d[idx] = c[0]; d[idx+1] = c[1]; d[idx+2] = c[2]; d[idx+3] = 255;
    }
  }
  ctx.putImageData(img, 0, 0);
}

// Result-type classifiers
function applyOp(a, b, op) { return (op === 'mul') ? a * b : a + b; }

function floatType(a, b, op) {
  var da = decodeFloat(a, 4, 3);
  var db = decodeFloat(b, 4, 3);
  if (da.type === 'inf' || da.type === 'nan' || db.type === 'inf' || db.type === 'nan') return 'SPECIAL';
  var va = (da.type === 'zero') ? 0 : da.value;
  var vb = (db.type === 'zero') ? 0 : db.value;
  var res = applyOp(va, vb, op);
  var r = encodeFloat(res, 4, 3);
  if (r.type === 'inf' || r.type === 'nan') return 'OVERFLOW';
  var rv = (r.type === 'zero') ? 0 : r.value;
  if (rv === 0 && res !== 0) return 'UNDERFLOW';
  if (rv === res) return 'EXACT';
  return 'APPROX';
}

function positType(a, b, op, lut) {
  var NAR = 128;
  if (a === NAR || b === NAR) return 'SPECIAL';
  var va = decodePosit(a, 8, 2);
  var vb = decodePosit(b, 8, 2);
  var res = applyOp(va, vb, op);
  if (!isFinite(res) || res !== res || Math.abs(res) > lut[lut.length - 1]) return 'SATURATE';
  var rp = encodePosit(res, 8, lut);
  if (rp === NAR) return 'SATURATE';
  if (rp === 0 && res !== 0) return 'UNDERFLOW';
  var rv = decodePosit(rp, 8, 2);
  if (rv === res) return 'EXACT';
  return 'APPROX';
}

function lnsType(a, b, op) {
  var da = decodeLNS(a, 4, 3);
  var db = decodeLNS(b, 4, 3);
  var va = da.isZero ? 0 : da.value;
  var vb = db.isZero ? 0 : db.value;
  var res = applyOp(va, vb, op);
  var re = encodeLNS(res, 4, 3);
  var rv = re.isZero ? 0 : re.value;
  if (rv === 0 && res !== 0) return 'UNDERFLOW';
  if (rv === res) return 'EXACT';
  return 'APPROX';
}

function takumType(a, b, op, lut) {
  var da = decodeTakum(a, 8);
  var db = decodeTakum(b, 8);
  if (da.type === 'nar' || db.type === 'nar') return 'SPECIAL';
  var va = (da.type === 'zero') ? 0 : da.linVal;
  var vb = (db.type === 'zero') ? 0 : db.linVal;
  var res = applyOp(va, vb, op);
  if (!isFinite(res) || res !== res || Math.abs(res) > lut[126]) return 'SATURATE';
  var rp = encodeTakum8(res, lut);
  if (rp === 128) return 'SATURATE';
  if (rp === 0 && res !== 0) return 'UNDERFLOW';
  var rv = decodeTakum(rp, 8);
  var rvl = (rv.type === 'zero') ? 0 : rv.linVal;
  if (rvl === res) return 'EXACT';
  return 'APPROX';
}

// Main
var currentOp = 'add';

function drawAll() {
  var op = currentOp;
  drawHeatmap(document.getElementById('cv-float'),
    function(a,b) { return floatType(a, b, op); });
  drawHeatmap(document.getElementById('cv-posit'),
    function(a,b) { return positType(a, b, op, POSITLUT); });
  drawHeatmap(document.getElementById('cv-lns'),
    function(a,b) { return lnsType(a, b, op); });
  drawHeatmap(document.getElementById('cv-takum'),
    function(a,b) { return takumType(a, b, op, TAKUMLUT); });
}

function setOp(op) {
  currentOp = op;
  document.getElementById('btn-add').classList.toggle('active', op === 'add');
  document.getElementById('btn-mul').classList.toggle('active', op === 'mul');
  drawAll();
}

window.addEventListener('load', drawAll);
</script>
</body>
</html>
