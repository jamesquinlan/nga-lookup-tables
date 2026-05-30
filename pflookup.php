<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Decimal to Float</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/floatlookup.js"></script>
<style>
  .dim { color: var(--muted); font-size: 0.75em; }
  .custom-row td { outline: 1px solid #b8cff5; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Decimal → Float Conversion</h1>
  <p class="subtitle">
    Convert a decimal number to its nearest representation in a range of
    IEEE 754-like minifloat formats.  Rounding: round-to-nearest-even (banker's).
    Overflow → ±Inf; gradual underflow via subnormals.
    Optionally add a custom E/M format (highlighted in the table).
  </p>
  <p class="format-note">Note: BF16 subnormals are defined by the format (it inherits float32's 8-bit exponent field), but most hardware implementations flush them to zero (FTZ) for simplicity.</p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="df-decimal">Decimal value</label>
        <input id="df-decimal" type="number" step="any" class="wide" placeholder="e.g. 3.14159">
      </div>
      <div class="form-group">
        <label for="df-exp">Custom E bits</label>
        <input id="df-exp" type="number" min="1" max="8" placeholder="e.g. 4">
      </div>
      <div class="form-group">
        <label for="df-mant">Custom M bits</label>
        <input id="df-mant" type="number" min="0" max="15" placeholder="e.g. 3">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="decimalToFloat()">Convert</button>
      </div>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign</span>
    <span class="legend-item"><span class="le">e…</span> Exponent</span>
    <span class="legend-item"><span class="lm">m…</span> Mantissa</span>
  </div>

  <div id="df-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>Format</th>
            <th>Binary</th>
            <th>Type</th>
            <th>Decoded value</th>
            <th>Relative error</th>
            <th>Decimal accuracy</th>
          </tr>
        </thead>
        <tbody id="df-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
