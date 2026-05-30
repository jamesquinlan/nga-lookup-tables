<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Decimal to Posit</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/decimallookupv2.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Decimal → Posit Conversion <small style="font-size:0.6em;color:var(--muted)">(Beta)</small></h1>
  <p class="subtitle">
    Convert a decimal to its nearest posit representation across common configurations.
    Rounding: geometric mean for exponent bits, arithmetic mean for fraction bits;
    no overflow/underflow; banker's rounding.
    <br><small style="color:var(--muted)">[v2017-09-07] Sign shown as ±. Negatives in two's complement.</small>
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="decimal">Decimal value</label>
        <input id="decimal" type="number" step="any" class="wide" placeholder="e.g. 3.14159">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="convertDToP()">Convert</button>
      </div>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">±</span> Sign</span>
    <span class="legend-item"><span class="lr">r…</span> Regime</span>
    <span class="legend-item"><span class="lrt">t</span> Regime terminator</span>
    <span class="legend-item"><span class="le">e…</span> Exponent</span>
    <span class="legend-item"><span class="lm">f…</span> Fraction</span>
  </div>

  <pre id="output"></pre>
</div>
</body>
</html>
