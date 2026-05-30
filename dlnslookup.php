<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Decimal to LNS</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/lnslookup.js"></script>
<style>
  .dim { color: var(--muted); font-size: 0.75em; }
  .custom-row td { outline: 1px solid #b8cff5; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Decimal → LNS Conversion</h1>
  <p class="subtitle">
    Convert a decimal number to its nearest Log Number System representation
    across several I/F configurations.  The log field is rounded to the nearest
    representable fixed-point value.  The most-negative log pattern is reserved
    for zero.  Optionally add a custom I/F format (highlighted).
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="dlns-decimal">Decimal value</label>
        <input id="dlns-decimal" type="number" step="any" class="wide" placeholder="e.g. 3.14159">
      </div>
      <div class="form-group">
        <label for="dlns-int">Custom I bits</label>
        <input id="dlns-int" type="number" min="1" max="12" placeholder="e.g. 4">
      </div>
      <div class="form-group">
        <label for="dlns-frac">Custom F bits</label>
        <input id="dlns-frac" type="number" min="0" max="12" placeholder="e.g. 3">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="decimalToLNS()">Convert</button>
      </div>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign</span>
    <span class="legend-item"><span class="li">i…</span> Integer log bits</span>
    <span class="legend-item"><span class="lf">f…</span> Fraction log bits</span>
  </div>

  <div id="dlns-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>Format</th>
            <th>Binary</th>
            <th>Decoded value</th>
            <th>Relative error</th>
            <th>Decimal accuracy</th>
          </tr>
        </thead>
        <tbody id="dlns-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
