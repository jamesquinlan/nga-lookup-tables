<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Decimal to Takum</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/takumlookup.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Decimal → Takum Conversion</h1>
  <p class="subtitle">
    Convert a decimal number to its nearest Takum8 and Takum16 representations.
    Rounding: nearest-value search over all representable patterns (round-to-nearest).
    Both the linear interpretation (real number) and logarithmic interpretation
    (e^(|l|/2)) are shown for the matched bit pattern.
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="dt-decimal">Decimal value</label>
        <input id="dt-decimal" type="number" step="any" class="wide" placeholder="e.g. 3.14159">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="decimalToTakum()">Convert</button>
      </div>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign</span>
    <span class="legend-item"><span class="lr">DRRR</span> Regime</span>
    <span class="legend-item"><span class="le">c…</span> Characteristic</span>
    <span class="legend-item"><span class="lm">m…</span> Mantissa</span>
  </div>

  <div id="dt-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>Format</th>
            <th>Field decomp (|t|)</th>
            <th>Type</th>
            <th>Linear value</th>
            <th>Log value (e^(|l|/2))</th>
            <th>Relative error</th>
            <th>Decimal accuracy</th>
          </tr>
        </thead>
        <tbody id="dt-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
