<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Takum Lookup Table</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<script src="js/takumlookup.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Takum Lookup Table</h1>
  <p class="subtitle">
    All 2<sup>n</sup> bit patterns for an <em>n</em>-bit Takum (n = 8 or 16).
    Takums use a signed two's-complement encoding; the sign bit is the MSB.
    The remaining bits encode a 4-bit regime (D R2 R1 R0), a variable-length
    characteristic, and mantissa bits — producing tapered precision like posit.<br>
    Format: <span class="bit-sign">s</span><span class="bit-regime">DRRR</span><span class="bit-exponent">c…</span><span class="bit-mantissa">m…</span>
    &nbsp;|&nbsp; Bit pattern 0 = Zero &nbsp;|&nbsp; Bit pattern 2<sup>n−1</sup> = NaR.
    <br><small style="color:var(--muted)">Hunhold, "Beating Posits at Their Own Game: Takum Arithmetic", CoNGA 2024. arXiv:2404.18603</small>
  </p>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="tl-bits">Bit width</label>
        <input id="tl-bits" type="number" min="8" max="16" value="8">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button onclick="takumLookup()">Generate</button>
      </div>
    </div>
    <div class="preset-row">
      <span>Presets:</span>
      <button class="preset" onclick="document.getElementById('tl-bits').value=8">Takum8</button>
      <button class="preset" onclick="document.getElementById('tl-bits').value=16">Takum16</button>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><span class="ls">s</span> Sign (two's complement MSB)</span>
    <span class="legend-item"><span class="lr">DRRR</span> Regime (D + R2R1R0 = DR index)</span>
    <span class="legend-item"><span class="le">c…</span> Characteristic bits</span>
    <span class="legend-item"><span class="lm">m…</span> Mantissa bits</span>
    <span class="legend-item" style="color:#9aa0a6">■ Zero (bit pattern 0)</span>
    <span class="legend-item" style="color:#7b22b8">■ NaR</span>
    <span class="legend-item" style="color:#457aaa">■ Negative</span>
    <span class="legend-item" style="color:#333">■ Positive</span>
  </div>

  <div id="tl-info" class="info-bar" style="display:none"></div>

  <div id="tl-container" style="display:none">
    <div class="table-scroll">
      <table class="out">
        <thead>
          <tr>
            <th>#</th>
            <th>Raw bits</th>
            <th>Field decomp (|t|)</th>
            <th>Type</th>
            <th>Sign</th>
            <th>DR</th>
            <th>c</th>
            <th>m</th>
            <th>l = (1−2s)(c+m)</th>
            <th>Linear value</th>
            <th>Log value (e^(|l|/2))</th>
          </tr>
        </thead>
        <tbody id="tl-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
