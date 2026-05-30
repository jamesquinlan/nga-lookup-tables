<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LNS Lookup Table</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/lnslookup.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>LNS Lookup Table</h1>
  <p class="subtitle">
    All 2<sup>1+I+F</sup> bit patterns for a Log Number System with
    <em>I</em> integer log bits and <em>F</em> fraction log bits.
    Format: <span class="bit-sign">s</span><span class="bit-integer">iii…</span><span class="bit-fraction">fff…</span>
    &nbsp;|&nbsp; Value = (−1)<sup>s</sup> × 2<sup>log<sub>2</sub></sup>
    where the log field is a two's-complement fixed-point number.
    The most-negative log pattern represents zero (log = −∞ convention).
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="lns-int">Integer log bits (I)</label>
        <input id="lns-int" type="number" min="1" max="12" value="3">
      </div>
      <div class="form-group">
        <label for="lns-frac">Fraction log bits (F)</label>
        <input id="lns-frac" type="number" min="0" max="12" value="2">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="lnsLookup()">Generate</button>
      </div>
    </div>
    <div class="preset-row">
      <span>Presets:</span>
      <button class="preset" onclick="applyLNSPreset(2,1,'lns-int','lns-frac')">I2F1 (4-bit)</button>
      <button class="preset" onclick="applyLNSPreset(3,0,'lns-int','lns-frac')">I3F0 (4-bit)</button>
      <button class="preset" onclick="applyLNSPreset(3,2,'lns-int','lns-frac')">I3F2 (6-bit)</button>
      <button class="preset" onclick="applyLNSPreset(2,4,'lns-int','lns-frac')">I2F4 (7-bit)</button>
      <button class="preset" onclick="applyLNSPreset(4,3,'lns-int','lns-frac')">I4F3 (8-bit)</button>
      <button class="preset" onclick="applyLNSPreset(3,4,'lns-int','lns-frac')">I3F4 (8-bit)</button>
      <button class="preset" onclick="applyLNSPreset(5,2,'lns-int','lns-frac')">I5F2 (8-bit)</button>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign of number</span>
    <span class="legend-item"><span class="li">i…</span> Integer part of log</span>
    <span class="legend-item"><span class="lf">f…</span> Fraction part of log</span>
    <span class="legend-item" style="color:#9aa0a6">■ Zero (sentinel)</span>
    <span class="legend-item" style="color:#333">■ Normal</span>
  </div>

  <div id="lns-info" class="info-bar" style="display:none"></div>

  <div id="lns-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>#</th>
            <th>Bits</th>
            <th>Type</th>
            <th>Sign</th>
            <th>Log field (binary.fixed = decimal)</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody id="lns-tbody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var orig = lnsLookup;
  lnsLookup = function() {
    document.getElementById('lns-info').style.display = '';
    orig();
  };
})();
</script>
</body>
</html>
