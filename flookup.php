<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Float Lookup Table</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Float Lookup Table</h1>
  <p class="subtitle">
    All 2<sup>1+E+M</sup> bit patterns for an IEEE 754-like float with
    <em>E</em> exponent bits and <em>M</em> mantissa (fraction) bits.
    Format: <span class="bit-sign">s</span><span class="bit-exponent">eee...</span><span class="bit-mantissa">mmm…</span>
    &nbsp;|&nbsp; Bias = 2<sup>E−1</sup>−1 &nbsp;|&nbsp;
    Exponent all-zeros → subnormal/zero; all-ones → Inf/NaN.
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="fl-exp">Exponent bits (E)</label>
        <input id="fl-exp" type="number" min="1" max="8" value="4">
      </div>
      <div class="form-group">
        <label for="fl-mant">Mantissa bits (M)</label>
        <input id="fl-mant" type="number" min="0" max="15" value="3">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="floatLookup()">Generate</button>
      </div>
    </div>
    <div class="preset-row">
      <span>Presets:</span>
      <button class="preset" onclick="applyFloatPreset(1,2,'fl-exp','fl-mant')">E1M2</button>
      <button class="preset" onclick="applyFloatPreset(2,1,'fl-exp','fl-mant')">E2M1</button>
      <button class="preset" onclick="applyFloatPreset(2,3,'fl-exp','fl-mant')">E2M3</button>
      <button class="preset" onclick="applyFloatPreset(3,2,'fl-exp','fl-mant')">E3M2</button>
      <button class="preset" onclick="applyFloatPreset(3,4,'fl-exp','fl-mant')">E3M4</button>
      <button class="preset" onclick="applyFloatPreset(4,3,'fl-exp','fl-mant')">E4M3</button>
      <button class="preset" onclick="applyFloatPreset(5,2,'fl-exp','fl-mant')">E5M2</button>
      <button class="preset" onclick="applyFloatPreset(2,5,'fl-exp','fl-mant')">E2M5</button>
      <button class="preset" onclick="applyFloatPreset(5,10,'fl-exp','fl-mant')">FP16</button>
      <button class="preset" onclick="applyFloatPreset(8,7,'fl-exp','fl-mant')">BF16</button>
    </div>
    <p class="format-note">Note: BF16 subnormals are defined by the format (it inherits float32's 8-bit exponent field), but most hardware implementations flush them to zero (FTZ) for simplicity.</p>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign</span>
    <span class="legend-item"><span class="le">e…</span> Exponent</span>
    <span class="legend-item"><span class="lm">m…</span> Mantissa</span>
    <span class="legend-item" style="color:#9aa0a6">■ Zero</span>
    <span class="legend-item" style="color:#457aaa">■ Subnormal</span>
    <span class="legend-item" style="color:#333">■ Normal</span>
    <span class="legend-item" style="color:#b83232">■ Infinity</span>
    <span class="legend-item" style="color:#7b22b8">■ NaN</span>
  </div>

  <div id="fl-info" class="info-bar" style="display:none"></div>

  <div id="fl-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>#</th>
            <th>Bits</th>
            <th>Type</th>
            <th>Sign</th>
            <th>e stored</th>
            <th>e actual</th>
            <th>Significand</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody id="fl-tbody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Show info bar once table is generated
(function() {
  var origFL = floatLookup;
  floatLookup = function() {
    document.getElementById('fl-info').style.display = '';
    origFL();
  };
})();
</script>
</body>
</html>
