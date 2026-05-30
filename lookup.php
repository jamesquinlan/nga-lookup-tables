<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Posit Lookup Table</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/positlookup.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Posit Lookup Table</h1>
  <p class="subtitle">
    All 2<sup>n</sup> bit patterns for a Posit&lt;n, es&gt; configuration.
    Format: <span class="bit-sign">s</span><span class="bit-regime">rrr…</span><span class="bit-regime-term">t</span><span class="bit-exponent">e…</span><span class="bit-mantissa">f…</span>
    &nbsp;|&nbsp; Negative values shown in two's complement form.
    <br><small style="color:var(--muted)">[v2017-09-08] Added bits column.</small>
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="posit">Posit bit size (n)</label>
        <input id="posit" type="number" min="2" max="16" value="8">
      </div>
      <div class="form-group">
        <label for="expo">Exponent bits (es)</label>
        <input id="expo" type="number" min="0" max="4" value="0">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="checkPosit(document.getElementById('posit').value, document.getElementById('expo').value)">Generate</button>
      </div>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign</span>
    <span class="legend-item"><span class="lr">r…</span> Regime</span>
    <span class="legend-item"><span class="lrt">t</span> Regime terminator</span>
    <span class="legend-item"><span class="le">e…</span> Exponent</span>
    <span class="legend-item"><span class="lm">f…</span> Fraction</span>
  </div>

  <pre id="output"></pre>
</div>
</body>
</html>
