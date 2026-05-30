<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Numeric Lookup Tables and Tests</title>
<link rel="stylesheet" href="styles.css">
<script>MathJax = { tex: { inlineMath: [['$','$']] } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Numeric Format Tables</h1>
  <p class="subtitle">
    Interactive lookup tables for alternative number representations.
    Choose a format below to explore all bit patterns or convert a decimal value.
  </p>

  <h2>Floating Point (IEEE 754-like)</h2>
  <ul class="tool-list">
    <li><a href="flookup.php">Float Lookup Table</a>: Browse all bit patterns for a configurable E<sub>e</sub>M<sub>m</sub> float (E4M3, E5M2, FP16, BF16…). Shows type, exponent, significand, and decoded value.</li>
    <li><a href="pflookup.php">Decimal → Float</a>: Convert a decimal to its nearest representation across common minifloat and reduced-precision formats, with relative error and decimal accuracy.</li>
  </ul>

  <h2 style="margin-top:1.5rem">Posit</h2>
  <ul class="tool-list">
    <li><a href="lookup.php">Posit Lookup Table</a>: Browse all 2<sup>n</sup> bit patterns for a given Posit⟨n, es⟩ configuration. Shows sign, regime, exponent, fraction, and decoded value.</li>
    <li><a href="plookup.php">Decimal → Posit</a>: Convert a decimal to its nearest posit representation across common Posit⟨n, es⟩ configurations, with relative error and accuracy.</li>
  </ul>

  <h2 style="margin-top:1.5rem">Takum</h2>
  <ul class="tool-list">
    <li><a href="takumlookup.php">Takum Lookup Table</a>: Browse all 2<sup>n</sup> bit patterns for Takum8 or Takum16. Signed two's-complement encoding with a 4-bit regime field. Shows both linear and logarithmic interpretations.</li>
    <li><a href="dtakumlookup.php">Decimal → Takum</a>: Convert a decimal to its nearest Takum8 and Takum16 representations with relative error and decimal accuracy.</li>
  </ul>

  <h2 style="margin-top:1.5rem">Logarithmic Number System (LNS)</h2>
  <ul class="tool-list">
    <li><a href="lnslookup.php">LNS Lookup Table</a>: Browse all bit patterns for a configurable LNS with integer and fraction log bits. Value = (−1)<sup>s</sup> × 2<sup>log</sup>.</li>
    <li><a href="dlnslookup.php">Decimal → LNS</a>: Convert a decimal to its nearest LNS representation across several integer/fraction bit configurations.</li>
  </ul>

  <h2 style="margin-top:1.5rem">Analysis</h2>
  <ul class="tool-list">
    <li><a href="closureplot.php">Closure Plots</a>: Heatmaps showing when a ⊕ b overflows or produces a special value for Float E4M3, Posit⟨8,2⟩, LNS I4F3, and Takum8. Compares closure behaviour and the impact of dynamic range.</li>
    <li><a href="assocplot.php">Associativity Plots</a>: Scatter plots and error histograms comparing (a+b)+c vs a+(b+c) across all four formats. Illustrates rounding-order dependence and non-associativity.</li>
    <li><a href="accumplot.php">Error Accumulation</a>: Line plot showing how rounding error grows over repeated addition of a geometric series $\sum 1/2^k$. Reveals the step at which each format's precision is exhausted.</li>
    <li><a href="dotprodplot.php">Dot Product Accuracy</a>: Mean relative error of $\mathbf{a} \cdot \mathbf{b}$ vs. exact (float64) for random vectors of increasing length. Shows how accumulation errors scale with vector dimension.</li>
    <li><a href="rangeplot.php">Range vs. Precision</a>: Scatter plot comparing all 8-bit format variants by dynamic range (decades) vs. machine epsilon near 1. Visualises the fundamental range–precision tradeoff within a fixed bit budget.</li>
    <li><a href="formatcompare.php">Format Comparison</a>: Side-by-side table of min/max representable values, dynamic range, machine epsilon, total representable values, special values, and closure for all formats.</li>
    <li><a href="harmonicplot.php">Harmonic Series</a>: Accumulates $1 + \tfrac{1}{2} + \tfrac{1}{3} + \cdots$ in each format. Shows the stagnation point $N^*$ where the sum freezes, the final value, and relative error vs float64.</li>
    <li><a href="taylorplot.php">Taylor Series for $e$</a>: Accumulates $e = \sum 1/k!$ in each format. Unlike the harmonic series this converges, so stagnation marks the format's best-achievable approximation to $e$.</li>
    <li><a href="altharmonicplot.php">Alternating Harmonic Series</a>: Accumulates $\ln 2 = 1 - \tfrac{1}{2} + \tfrac{1}{3} - \cdots$ in each format. Tests cancellation handling; the sum stays in $(0,1)$ and oscillates toward $\ln 2$.</li>
    <li><a href="chebyplot.php">Chebyshev Node Accuracy</a>: Measures how accurately each format can represent Chebyshev nodes $x_k = \cos\!\left(\tfrac{(2k-1)\pi}{2N}\right)$, which cluster near $\pm 1$. Formats with tapered precision near $\pm 1$ (Posit, Takum) have a natural advantage.</li>
    <li><a href="ulpplot.php">ULP Distribution</a>: Log-log plot of the unit in the last place (ULP) vs value magnitude for each format. Shows absolute and relative ULP; reveals uniform LNS precision, near-flat Float bands, and the U-shaped tapered precision of Posit and Takum.</li>
  </ul>
</div>
</body>
</html>
