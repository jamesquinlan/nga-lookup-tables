# Numeric Lookup Tables

Interactive, browser-based lookup tables and analysis tools for alternative numeric
representations: Posit, IEEE 754-like floating point, Logarithmic Number System (LNS),
and Takum. All computation runs client-side in JavaScript. There is no build step and
no server-side dependency beyond PHP includes for shared navigation.

The Posit lookup and decimal converter tools originate from work by Siew Hoon Leong
(Cerlane), 2017. Live tools (Cerlane): https://posithub.org/widget/lookup and https://posithub.org/widget/plookup (Posit lookup and decimal converter, hosted by PositHub.) 

This project extends those tools and adds support for Float, LNS, and Takum formats along with the analysis pages.

---

## Getting Started

```
git clone https://github.com/jamesquinlan/nga-lookup-tables
cd numeric-tables
php -S localhost:8080
```

Then open `http://localhost:8080` in a browser. A PHP-capable server is required only
for the shared navigation include (`nav.php`). If you prefer Python:

```
python3 -m http.server 8080
```

Note: with the Python server, navigation includes will not render. Each page is otherwise
self-contained and will display correctly.

---

## Formats

### Floating Point (IEEE 754-like)

Standard sign-exponent-mantissa layout: `s | eee... | mmm...` with bias = 2^(E-1)-1,
subnormal numbers, +/-0, +/-Inf, and NaN. The exponent width E and mantissa width M are
configurable.

Preset configurations: E1M2, E2M1, E2M3, E3M2, E3M4, E4M3, E5M2, E2M5,
FP16 (E5M10), BF16 (E8M7).

JavaScript: `js/floatlookup.js`

### Posit

Variable-length regime encoding with a single NaR (Not a Real) special value in place
of NaN and Inf. The regime field is a run-length-encoded integer k; the useed
2^(2^es) controls the scale. Posit arithmetic is closed: normal inputs never produce Inf
or NaN.

JavaScript: `js/positlookup.js`, `js/decimallookupv2.js`

Original implementation: Siew Hoon Leong (Cerlane), 2017.
Live: https://posithub.org/widget/lookup and https://posithub.org/widget/plookup
This work extends and improves upon Cerlane's original tools.

Note: the decimal-to-posit converter is only accurate when the input can be represented
exactly as a 64-bit IEEE 754 double.

### Takum

Signed two's-complement encoding with a 4-bit regime field (D R2 R1 R0) followed by a
variable-length characteristic and mantissa. This gives tapered precision: finer near
moderate values, coarser at extremes. Two interpretations share the same bit pattern:

- **Linear:** value = (-1)^s * (1+m) * 2^c
- **Logarithmic:** value = (-1)^s * exp(|l| / 2)

Zero = all-zeros; NaR = most-negative two's-complement value. Takum is closed under
addition and multiplication.

JavaScript: `js/takumlookup.js`

Reference: Hunhold, "Beating Posits at Their Own Game: Takum Arithmetic",
CoNGA 2024. arXiv:2404.18603

### Logarithmic Number System (LNS)

Value = (-1)^s * 2^(log), where the log field is a two's-complement fixed-point number
with I integer bits and F fraction bits. The most-negative log pattern is reserved as the
zero sentinel. LNS provides exactly uniform relative precision across all representable
magnitudes.

JavaScript: `js/lnslookup.js`

---

## Lookup Tables

Each format has two tools: a full bit-pattern table and a decimal converter.

| Page | URL | Description |
|------|-----|-------------|
| Float Lookup | `flookup.php` | All 2^(1+E+M) bit patterns for a configurable ExMy format. Shows field decomposition, type (normal/subnormal/special), and decoded value. |
| Decimal to Float | `pflookup.php` | Convert a decimal to its nearest float representation across common configurations. Reports relative error and decimal accuracy. |
| Posit Lookup | `lookup.php` | All 2^n bit patterns for Posit(n, es). Shows sign, regime, exponent, fraction, and decoded value. |
| Decimal to Posit | `plookup.php` | Convert a decimal to its nearest posit across common Posit(n, es) configurations. |
| Takum Lookup | `takumlookup.php` | All 2^n patterns for Takum8 or Takum16. Shows both linear and logarithmic interpretations side by side. |
| Decimal to Takum | `dtakumlookup.php` | Convert a decimal to its nearest Takum8 and Takum16 representations. |
| LNS Lookup | `lnslookup.php` | All bit patterns for a configurable LNS(I, F). Shows sign, log field, and decoded value. |
| Decimal to LNS | `dlnslookup.php` | Convert a decimal to its nearest LNS representation across several I/F configurations. |

Lookup tables are limited to formats with at most 16 total bits (up to 65,536 rows).

---

## Analysis Tools

All analysis pages compute results directly from each format's bit-pattern definitions.
The 8-bit formats used throughout are Float E4M3, Posit(8,2), LNS I4F3, and Takum8.
Most pages include a toggle to switch to their 16-bit counterparts.

### Closure

**`closureplot.php`** - Heatmaps showing when a op b produces a non-finite or special
result for all pairs (a, b) in each format. Illustrates how Posit and Takum saturate
(closed arithmetic) while Float and LNS can overflow to Inf.

### Associativity

**`assocplot.php`** - Scatter plots and error histograms comparing (a+b)+c against
a+(b+c) across all four formats. Quantifies rounding-order dependence for random
triple combinations.

### Error Accumulation

**`accumplot.php`** - Repeated addition of the geometric series sum(1/2^k). Shows
the step at which each format's precision is exhausted and the accumulated error
becomes constant.

### Dot Product Accuracy

**`dotprodplot.php`** - Mean relative error of a dot b versus the exact float64 result,
for random vectors of increasing length L = 1 to 64. Three error metrics are available:

- Standard relative error: |est - exact| / exact
- Log ratio: |ln(est / exact)|
- Decimal accuracy: -log10|ln(est / exact)|

### Dynamic Range vs. Precision

**`rangeplot.php`** - Scatter plot placing each 8-bit format variant at the coordinates
(dynamic range in decades, -log10(machine epsilon)). Visualises the fundamental
range-precision tradeoff within a fixed bit budget.

### Format Comparison Table

**`formatcompare.php`** - Side-by-side table covering Float, Posit (8-bit and 16-bit),
Takum (linear and logarithmic, 8-bit and 16-bit), and LNS configurations. Columns:
bit width, minimum positive value, maximum value, dynamic range (decades), machine
epsilon near 1, total finite representable values, special values present, and whether
the format is closed under addition and multiplication.

### Harmonic Series Stagnation

**`harmonicplot.php`** - Accumulates H_N = 1 + 1/2 + 1/3 + ... + 1/N in each format
by in-format left-to-right addition. Because terms shrink while the running sum grows,
addition eventually has no effect. The stagnation point N* is the last term that changes
the sum; beyond it the format is permanently frozen. The table reports N*, the frozen
value, the float64 reference, and the relative error. Includes 8-bit and 16-bit modes.

### Taylor Series for e

**`taylorplot.php`** - Accumulates e = sum(1/k!) in each format. Unlike the harmonic
series this series converges, so stagnation marks the format's permanent best
approximation to e. The plot shows the partial sums converging toward the true value
e = 2.71828..., with a red reference line and triangle markers at each format's
stagnation point. Includes 8-bit and 16-bit modes with adjustable term counts.

### Alternating Harmonic Series

**`altharmonicplot.php`** - Accumulates ln(2) = 1 - 1/2 + 1/3 - 1/4 + ... in each
format. The running sum is always in (0, 1) so there is no overflow, and the series
oscillates around ln(2) as it converges. This tests cancellation behaviour: each step
adds or subtracts a small quantity near a moderate value. Stagnation indicates the
format can no longer distinguish consecutive partial sums. Includes 8-bit and 16-bit
modes.

### Chebyshev Node Accuracy

**`chebyplot.php`** - Measures the representational error when each format encodes the
Chebyshev nodes of the first kind, x_k = cos((2k-1)*pi / (2N)), on [-1, 1]. These nodes
cluster near +/-1, which is where Posit and Takum concentrate their precision (tapered
distribution). The y-axis shows log10 of the absolute error for each node; a lower point
means that node is more accurately representable. The summary table gives mean, max, and
minimum error per format and counts exactly-represented nodes. Node counts N = 8, 16, 32
are available; 8-bit and 16-bit toggle included.

### ULP Distribution

**`ulpplot.php`** - Log-log plot of the unit in the last place (ULP(x) = next
representable value minus x) across the full positive range of each 8-bit format.
Two modes: relative ULP (ULP(x)/x, where a flat line indicates uniform relative
precision) and absolute ULP. LNS is the only format with exactly flat relative ULP;
Float is approximately flat within each exponent band; Posit and Takum show a
U-shaped curve with finest precision near 1.

---

## Bit Color Coding

All lookup tables use a consistent color scheme to distinguish bit fields:

| Color | Field |
|-------|-------|
| Red | Sign bit |
| Gold | Regime bits (Posit) / Integer log bits (LNS) |
| Dark gold | Regime terminator (Posit) |
| Blue | Exponent bits (Posit, Float) |
| Green | Mantissa / fraction bits (Float) |
| Orange | Integer part of log (LNS) |
| Purple | Fraction part of log (LNS) |

---

## Project Structure

```
index.php                 Hub page
nav.php                   Shared navigation include (PHP)
styles.css                Site-wide styles
Department-of-Computer-Science.png  USM logo used in the header

js/
  floatlookup.js          Float encode / decode
  lnslookup.js            LNS encode / decode
  takumlookup.js          Takum encode / decode
  positlookup.js          Posit decode
  decimallookupv2.js      Decimal-to-posit converter
  analysishelpers.js      Shared Posit/Takum encode helpers and fmtAdd (analysis pages)

Lookup tables:
  flookup.php             Float bit-pattern table
  pflookup.php            Decimal to float converter
  lookup.php              Posit bit-pattern table
  plookup.php             Decimal to posit converter
  takumlookup.php         Takum bit-pattern table
  dtakumlookup.php        Decimal to takum converter
  lnslookup.php           LNS bit-pattern table
  dlnslookup.php          Decimal to LNS converter

Analysis pages:
  closureplot.php         Closure heatmaps
  assocplot.php           Associativity plots
  accumplot.php           Error accumulation
  dotprodplot.php         Dot product accuracy
  rangeplot.php           Dynamic range vs. precision
  formatcompare.php       Format comparison table
  harmonicplot.php        Harmonic series stagnation
  taylorplot.php          Taylor series for e
  altharmonicplot.php     Alternating harmonic series
  chebyplot.php           Chebyshev node accuracy
  ulpplot.php             ULP distribution
```

---

## Deployment

The site is static PHP. Any server that executes PHP will work:

```
php -S localhost:8080
```

For Apache or Nginx, point the document root at the repository directory. No database,
no framework, no build step.

---

## Citations

To cite this project:

```bibtex
@misc{quinlan2026ngalookup,
  author       = {Quinlan, James},
  title        = {Numeric Lookup Tables},
  year         = {2026},
  howpublished = {\url{https://github.com/jamesquinlan/nga-lookup-tables}},
  note         = {GitHub repository}
}
```


Takum format reference:

```bibtex
@misc{hunhold2024takum,
  author = {Hunhold, Laslo},
  title  = {Beating Posits at Their Own Game: Takum Arithmetic},
  year   = {2024},
  note   = {CoNGA 2024, arXiv:2404.18603}
}
```
