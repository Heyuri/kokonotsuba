<?php

namespace Puchiko\normalize;

/**
 * Normalize $text for use in filter/spam comparisons.
 *
 * Pipeline: strip invisibles → NFKC → map homoglyphs.
 * The returned string is intentionally lossy and must NOT be displayed to users.
 */
function toFilterString(string $text): string
{
    $text = stripInvisible($text);
    $text = applyNFKC($text);
    $text = mapHomoglyphs($text);
    return $text;
}

/**
 * Like toFilterString() but also lowercases the result (mb_strtolower).
 * Use for case-insensitive filter matching.
 */
function toLowerFilterString(string $text): string
{
    return mb_strtolower(toFilterString($text), 'UTF-8');
}

/**
 * Remove zero-width, format-control, and invisible Unicode characters.
 *
 * Strips Unicode category Cf (soft hyphen, BOM, directional/format marks, etc.),
 * variation selectors (FE00–FE0F, E0100–E01EF), and Mongolian free variation
 * selectors (180B–180D). Preserves normal whitespace (\t, \n, \r, space).
 */
function stripInvisible(string $text): string
{
    // Unicode category Cf covers the vast majority of invisible format chars.
    $text = preg_replace('/\p{Cf}/u', '', $text) ?? $text;

    // Variation selectors are category Mn, not Cf — strip them explicitly.
    $text = preg_replace('/[\x{180B}-\x{180D}]/u', '', $text) ?? $text;  // Mongolian VS
    $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text) ?? $text;  // VS1–VS16
    $text = preg_replace('/[\x{E0100}-\x{E01EF}]/u', '', $text) ?? $text; // VS17–VS256

    return $text;
}

/**
 * Apply Unicode NFKC normalization.
 *
 * Handles fullwidth/halfwidth forms (Ａ→A), ligatures (ﬁ→fi),
 * enclosed letters (Ⓐ→A), and many mathematical/styled variants.
 * Requires the PHP intl extension; silently passes through if unavailable.
 */
function applyNFKC(string $text): string
{
    if (!class_exists('Normalizer', false)) {
        return $text;
    }
    $result = \Normalizer::normalize($text, \Normalizer::NFKC);
    return ($result !== false) ? $result : $text;
}

/**
 * Strip diacritical marks (accents, etc.) from text, reducing e.g. é→e, ñ→n.
 *
 * This step is NOT included in toFilterString() by default because it can
 * produce false positives for non-ASCII content. Call it explicitly when you
 * want maximum aggressiveness.
 */
function stripDiacritics(string $text): string
{
    if (class_exists('Normalizer', false)) {
        $nfd = \Normalizer::normalize($text, \Normalizer::NFD);
        if ($nfd !== false) {
            $text = $nfd;
        }
    }
    return preg_replace('/\p{Mn}/u', '', $text) ?? $text;
}

/**
 * Replace visually similar Unicode characters with their ASCII equivalents.
 *
 * Covers characters that NFKC does NOT normalize:
 *   - Cyrillic/Greek lookalikes (а→a, ο→o, …)
 *   - Letterlike symbols (ℬ→B, ℓ→l, …)
 *   - IPA / small-capital phonetic extensions (ᴀ→a, ɡ→g, …)
 *   - Mathematical alphanumeric symbols (𝐀→A, 𝑎→a, …) as a NFKC fallback
 *   - Superscript and subscript digits (²→2, ₃→3, …)
 *   - Enclosed alphanumerics (Ⓐ→A) as a NFKC fallback
 */
function mapHomoglyphs(string $text): string
{
    return strtr($text, _buildHomoglyphMap());
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Build (and cache) the homoglyph → ASCII lookup table.
 *
 * Loads from homoglyphMap.generated.php in the global backend directory.
 * If the file doesn't exist, it is automatically generated from the Unicode
 * Consortium's official confusables.txt dataset and cached for future use.
 * Falls back to the built-in manual map if generation fails.
 *
 * @return array<string, string>
 */
function _buildHomoglyphMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $generatedPath = getBackendGlobalDir() . 'homoglyphMap.generated.php';

    if (file_exists($generatedPath)) {
        $map = require $generatedPath;
        if (is_array($map)) {
            return $map;
        }
    }

    // Try to generate from Unicode confusables.txt
    $map = _generateHomoglyphMapFromConfusables($generatedPath);
    if ($map !== null) {
        return $map;
    }

    // Fallback: built-in manual map
    $map = _buildManualHomoglyphMap();
    return $map;
}

/**
 * Download Unicode confusables.txt, parse it, and write a cached PHP map file.
 *
 * Only keeps mappings where the target is pure printable ASCII (0x20–0x7E).
 * Includes manual overrides for characters the official dataset misses.
 *
 * @return array<string, string>|null  The map on success, null on failure.
 */
function _generateHomoglyphMapFromConfusables(string $outputPath): ?array
{
    $url = 'https://www.unicode.org/Public/security/latest/confusables.txt';

    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Kokonotsuba HomoglyphMapGenerator/1.0',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return null;
    }

    $map = [];
    $lines = explode("\n", $raw);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode(';', $line);
        if (count($parts) < 3) {
            continue;
        }

        $sourceCp = intval(trim($parts[0]), 16);

        // Skip ASCII source characters
        if ($sourceCp >= 0x20 && $sourceCp <= 0x7E) {
            continue;
        }

        // Convert target hex sequence to UTF-8
        $targetParts = preg_split('/\s+/', trim($parts[1]));
        $targetUtf8 = '';
        foreach ($targetParts as $h) {
            $cp = intval($h, 16);
            if ($cp === 0) {
                $targetUtf8 = '';
                break;
            }
            $targetUtf8 .= mb_chr($cp, 'UTF-8');
        }

        if ($targetUtf8 === '' || preg_match('/^[\x20-\x7E]+$/', $targetUtf8) !== 1) {
            continue;
        }

        // First mapping wins
        $sourceUtf8 = mb_chr($sourceCp, 'UTF-8');
        if (!isset($map[$sourceUtf8])) {
            $map[$sourceUtf8] = $targetUtf8;
        }
    }

    // Manual overrides for characters the official dataset misses or maps
    // to non-ASCII targets
    $overrides = [
        "\u{0412}" => 'B',  "\u{041D}" => 'H',  "\u{0423}" => 'Y',
        "\u{042F}" => 'R',  "\u{0442}" => 't',  "\u{043A}" => 'k',
        "\u{044F}" => 'r',  "\u{04AE}" => 'Y',
        "\u{04AF}" => 'y',  "\u{050C}" => 'G',  "\u{050D}" => 'g',
        "\u{051C}" => 'W',  "\u{051D}" => 'w',  "\u{0376}" => 'N',
        "\u{0377}" => 'n',  "\u{037F}" => 'J',  "\u{03B7}" => 'n',
        "\u{03BC}" => 'u',  "\u{03C5}" => 'y',  "\u{03C9}" => 'w',
        "\u{00A9}" => 'C',
        "\u{00AE}" => 'R',  "\u{0181}" => 'B',  "\u{018A}" => 'D',
        "\u{0191}" => 'F',  "\u{0193}" => 'G',  "\u{0198}" => 'K',
        "\u{0199}" => 'k',  "\u{019D}" => 'N',  "\u{01A4}" => 'P',
        "\u{01A5}" => 'p',  "\u{01AC}" => 'T',  "\u{01AD}" => 't',
        "\u{01B3}" => 'Y',  "\u{01B4}" => 'y',  "\u{01B5}" => 'Z',
        "\u{01B6}" => 'z',  "\u{01DD}" => 'e',  "\u{0250}" => 'a',
        "\u{0253}" => 'b',  "\u{0254}" => 'c',  "\u{0256}" => 'd',
        "\u{0257}" => 'd',  "\u{0260}" => 'g',  "\u{0266}" => 'h',
        "\u{0272}" => 'n',  "\u{A7FB}" => 'F',  "\u{A7FC}" => 'P',
    ];

    foreach ($overrides as $src => $ascii) {
        $map[$src] = $ascii;
    }

    // Write cache file
    $php = "<?php\n// AUTO-GENERATED from Unicode confusables.txt — do not edit.\n";
    $php .= "// Generated: " . date('Y-m-d H:i:s') . " | Entries: " . count($map) . "\n";
    $php .= "return [\n";

    foreach ($map as $src => $ascii) {
        $hex = sprintf('"\\u{%04X}"', mb_ord($src, 'UTF-8'));
        $target = '"' . addcslashes($ascii, '"\\') . '"';
        $php .= "    {$hex} => {$target},\n";
    }

    $php .= "];\n";

    if (@file_put_contents($outputPath, $php, LOCK_EX) === false) {
        return null;
    }

    return $map;
}

/**
 * Built-in manual homoglyph map — used as fallback when the generated map
 * does not exist. Covers the most common evasion characters.
 *
 * @return array<string, string>
 */
function _buildManualHomoglyphMap(): array
{
    $map = [];

    // --- Mathematical Alphanumeric Symbols (U+1D400–U+1D7FF) ---------------
    // NFKC normally handles these; kept as a fallback for environments where
    // the intl extension is absent or an older Unicode dataset is in use.
    $alphaRanges = [
        [0x1D400, 0x1D41A], // Mathematical Bold
        [0x1D434, 0x1D44E], // Mathematical Italic
        [0x1D468, 0x1D482], // Mathematical Bold Italic
        [0x1D49C, 0x1D4B6], // Mathematical Script
        [0x1D4D0, 0x1D4EA], // Mathematical Bold Script
        [0x1D504, 0x1D51E], // Mathematical Fraktur
        [0x1D538, 0x1D552], // Mathematical Double-struck
        [0x1D56C, 0x1D586], // Mathematical Bold Fraktur
        [0x1D5A0, 0x1D5BA], // Mathematical Sans-serif
        [0x1D5D4, 0x1D5EE], // Mathematical Sans-serif Bold
        [0x1D608, 0x1D622], // Mathematical Sans-serif Italic
        [0x1D63C, 0x1D656], // Mathematical Sans-serif Bold Italic
        [0x1D670, 0x1D68A], // Mathematical Monospace
    ];
    for ($i = 0; $i < 26; $i++) {
        foreach ($alphaRanges as [$upperBase, $lowerBase]) {
            $map[mb_chr($upperBase + $i, 'UTF-8')] = chr(0x41 + $i); // A–Z
            $map[mb_chr($lowerBase + $i, 'UTF-8')] = chr(0x61 + $i); // a–z
        }
    }

    // Mathematical digit variants (Bold, Double-struck, Sans, Sans-Bold, Mono)
    foreach ([0x1D7CE, 0x1D7D8, 0x1D7E2, 0x1D7EC, 0x1D7F6] as $base) {
        for ($i = 0; $i < 10; $i++) {
            $map[mb_chr($base + $i, 'UTF-8')] = (string)$i;
        }
    }

    // --- Enclosed Alphanumerics (Ⓐ–Ⓩ, ⓐ–ⓩ) — NFKC fallback --------------
    for ($i = 0; $i < 26; $i++) {
        $map[mb_chr(0x24B6 + $i, 'UTF-8')] = chr(0x41 + $i); // Ⓐ–Ⓩ → A–Z
        $map[mb_chr(0x24D0 + $i, 'UTF-8')] = chr(0x61 + $i); // ⓐ–ⓩ → a–z
    }

    // --- Superscript digits (U+00B9, U+00B2–U+00B3, U+2070, U+2074–U+2079) -
    $map += [
        "\u{2070}" => '0', "\u{00B9}" => '1', "\u{00B2}" => '2',
        "\u{00B3}" => '3', "\u{2074}" => '4', "\u{2075}" => '5',
        "\u{2076}" => '6', "\u{2077}" => '7', "\u{2078}" => '8',
        "\u{2079}" => '9',
    ];

    // Subscript digits (U+2080–U+2089)
    for ($i = 0; $i < 10; $i++) {
        $map[mb_chr(0x2080 + $i, 'UTF-8')] = (string)$i;
    }

    // --- Cyrillic → Latin confusables ----------------------------------------
    $map += [
        // Uppercase
        "\u{0405}" => 'S',  // Ѕ  (Macedonian)
        "\u{0406}" => 'I',  // І  (Ukrainian/Belarusian)
        "\u{0408}" => 'J',  // Ј
        "\u{0410}" => 'A',  // А
        "\u{0412}" => 'B',  // В
        "\u{0415}" => 'E',  // Е
        "\u{041A}" => 'K',  // К
        "\u{041C}" => 'M',  // М
        "\u{041D}" => 'H',  // Н
        "\u{041E}" => 'O',  // О
        "\u{0420}" => 'P',  // Р
        "\u{0421}" => 'C',  // С
        "\u{0422}" => 'T',  // Т
        "\u{0423}" => 'Y',  // У  (looks like Y)
        "\u{0425}" => 'X',  // Х
        "\u{042F}" => 'R',  // Я  (reversed R — common evasion char)
        // Lowercase
        "\u{0430}" => 'a',  // а
        "\u{0435}" => 'e',  // е
        "\u{043A}" => 'k',  // к
        "\u{043E}" => 'o',  // о
        "\u{0440}" => 'p',  // р
        "\u{0441}" => 'c',  // с
        "\u{0442}" => 't',  // т
        "\u{0443}" => 'y',  // у
        "\u{0445}" => 'x',  // х
        "\u{044F}" => 'r',  // я  (reversed r)
        "\u{0455}" => 's',  // ѕ  (Macedonian)
        "\u{0456}" => 'i',  // і  (Ukrainian/Belarusian)
        "\u{0458}" => 'j',  // ј
        "\u{04AE}" => 'Y',  // Ү  (Kazakh/Turkic U, resembles Y)
        "\u{04AF}" => 'y',  // ү
        "\u{04CF}" => 'l',  // ӏ  (Chechen palochka — l/1 lookalike)
        "\u{0501}" => 'd',  // ԁ  (Komi de)
        "\u{050C}" => 'G',  // Ԍ  (Komi Sje — looks like G)
        "\u{050D}" => 'g',  // ԍ
        "\u{051C}" => 'W',  // Ԝ  (Cyrillic We)
        "\u{051D}" => 'w',  // ԝ
    ];

    // --- Greek → Latin confusables -------------------------------------------
    $map += [
        // Uppercase Greek with Latin-identical appearance
        "\u{0391}" => 'A',  // Α  Alpha
        "\u{0392}" => 'B',  // Β  Beta
        "\u{0395}" => 'E',  // Ε  Epsilon
        "\u{0396}" => 'Z',  // Ζ  Zeta
        "\u{0397}" => 'H',  // Η  Eta
        "\u{0399}" => 'I',  // Ι  Iota
        "\u{039A}" => 'K',  // Κ  Kappa
        "\u{039C}" => 'M',  // Μ  Mu
        "\u{039D}" => 'N',  // Ν  Nu
        "\u{039F}" => 'O',  // Ο  Omicron
        "\u{03A1}" => 'P',  // Ρ  Rho
        "\u{03A4}" => 'T',  // Τ  Tau
        "\u{03A5}" => 'Y',  // Υ  Upsilon
        "\u{03A7}" => 'X',  // Χ  Chi
        // Lowercase Greek with Latin-similar appearance
        "\u{03B1}" => 'a',  // α  alpha
        "\u{03B5}" => 'e',  // ε  epsilon
        "\u{03B7}" => 'n',  // η  eta      (resembles n in many fonts)
        "\u{03B9}" => 'i',  // ι  iota
        "\u{03BA}" => 'k',  // κ  kappa
        "\u{03BC}" => 'u',  // μ  mu       (resembles u)
        "\u{03BD}" => 'v',  // ν  nu
        "\u{03BF}" => 'o',  // ο  omicron
        "\u{03C1}" => 'p',  // ρ  rho
        "\u{03C5}" => 'y',  // υ  upsilon  (lowercase of Υ→Y; used as Y evasion)
        "\u{03C7}" => 'x',  // χ  chi
        "\u{03C9}" => 'w',  // ω  omega    (resembles w)
        "\u{03F2}" => 'c',  // ϲ  lunate sigma
        "\u{03F3}" => 'j',  // ϳ  yot (lowercase)
        // Archaic / extended Greek confusables
        "\u{0376}" => 'N',  // Ͷ  Pamphylian Digamma (looks like N)
        "\u{0377}" => 'n',  // ͷ  Pamphylian Digamma small
        "\u{037F}" => 'J',  // Ϳ  Capital Yot (looks like J)
        "\u{03DC}" => 'F',  // Ϝ  Digamma (looks like F)
        "\u{03DD}" => 'f',  // ϝ  digamma small
        "\u{03F9}" => 'C',  // Ϲ  Capital Lunate Sigma
    ];

    // --- Letterlike Symbols (U+2100–U+214F) ----------------------------------
    $map += [
        "\u{2102}" => 'C',  // ℂ  double-struck C
        "\u{210A}" => 'g',  // ℊ  script small g
        "\u{210B}" => 'H',  // ℋ  script capital H
        "\u{210C}" => 'H',  // ℌ  black-letter capital H
        "\u{210D}" => 'H',  // ℍ  double-struck H
        "\u{210E}" => 'h',  // ℎ  italic small h
        "\u{210F}" => 'h',  // ℏ  h-bar (Planck)
        "\u{2110}" => 'I',  // ℐ  script capital I
        "\u{2111}" => 'I',  // ℑ  black-letter capital I
        "\u{2112}" => 'L',  // ℒ  script capital L
        "\u{2113}" => 'l',  // ℓ  script small l
        "\u{2115}" => 'N',  // ℕ  double-struck N
        "\u{2119}" => 'P',  // ℙ  double-struck P
        "\u{211A}" => 'Q',  // ℚ  double-struck Q
        "\u{211B}" => 'R',  // ℛ  script capital R
        "\u{211C}" => 'R',  // ℜ  black-letter capital R
        "\u{211D}" => 'R',  // ℝ  double-struck R
        "\u{2124}" => 'Z',  // ℤ  double-struck Z
        "\u{2128}" => 'Z',  // ℨ  black-letter Z
        "\u{212A}" => 'K',  // K  Kelvin sign
        "\u{212C}" => 'B',  // ℬ  script capital B
        "\u{212D}" => 'C',  // ℭ  black-letter capital C
        "\u{212F}" => 'e',  // ℯ  script small e
        "\u{2130}" => 'E',  // ℰ  script capital E
        "\u{2131}" => 'F',  // ℱ  script capital F
        "\u{2133}" => 'M',  // ℳ  script capital M
        "\u{2134}" => 'o',  // ℴ  script small o
        "\u{2139}" => 'i',  // ℹ  information source
        // Symbol lookalikes
        "\u{00A9}" => 'C',  // ©  copyright sign
        "\u{00AE}" => 'R',  // ®  registered sign
    ];

    // --- IPA, Phonetic Extensions, Dotless Letters ---------------------------
    $map += [
        "\u{0131}" => 'i',  // ı  dotless i
        "\u{00B5}" => 'u',  // µ  micro sign  (NFKC maps this to μ; kept for robustness)
        "\u{0251}" => 'a',  // ɑ  Latin alpha
        "\u{0261}" => 'g',  // ɡ  script g
        "\u{0269}" => 'i',  // ɩ  Latin iota
        "\u{026A}" => 'I',  // ɪ  small capital I
        "\u{028C}" => 'v',  // ʌ  turned v
        "\u{01C0}" => 'l',  // ǀ  dental click  (vertical bar lookalike)
        // Turned / reversed Latin letters
        "\u{01DD}" => 'e',  // ǝ  turned e
        "\u{0250}" => 'a',  // ɐ  turned a
        "\u{0254}" => 'c',  // ɔ  open o  (resembles reversed c)
        "\u{0279}" => 'r',  // ɹ  turned r
        "\u{027E}" => 'r',  // ɾ  r with fishhook
        "\u{0283}" => 's',  // ʃ  esh     (resembles long s)
        "\u{0253}" => 'b',  // ɓ  b with hook
        "\u{0256}" => 'd',  // ɖ  d with tail
        "\u{0257}" => 'd',  // ɗ  d with hook
        "\u{0260}" => 'g',  // ɠ  g with hook
        "\u{0266}" => 'h',  // ɦ  h with hook
        "\u{0272}" => 'n',  // ɲ  n with left hook
        // Latin Extended-B hooked capitals
        "\u{0181}" => 'B',  // Ɓ  B with hook
        "\u{018A}" => 'D',  // Ɗ  D with hook
        "\u{0191}" => 'F',  // Ƒ  F with hook
        "\u{0192}" => 'f',  // ƒ  f with hook
        "\u{0193}" => 'G',  // Ɠ  G with hook
        "\u{0198}" => 'K',  // Ƙ  K with hook
        "\u{0199}" => 'k',  // ƙ  k with hook
        "\u{019D}" => 'N',  // Ɲ  N with left hook
        "\u{01A4}" => 'P',  // Ƥ  P with hook
        "\u{01A5}" => 'p',  // ƥ  p with hook
        "\u{01AC}" => 'T',  // Ƭ  T with hook
        "\u{01AD}" => 't',  // ƭ  t with hook
        "\u{01B3}" => 'Y',  // Ƴ  Y with hook
        "\u{01B4}" => 'y',  // ƴ  y with hook
        "\u{01B5}" => 'Z',  // Ƶ  Z with stroke
        "\u{01B6}" => 'z',  // ƶ  z with stroke
        // Latin Extended-D reversed letters
        "\u{A7FB}" => 'F',  // ꟻ  reversed F
        "\u{A7FC}" => 'P',  // ꟼ  reversed P (Claudian)
        // Small capitals (Phonetic Extensions block, U+1D00–U+1D2F)
        "\u{1D00}" => 'a',  // ᴀ
        "\u{1D04}" => 'c',  // ᴄ
        "\u{1D05}" => 'd',  // ᴅ
        "\u{1D07}" => 'e',  // ᴇ
        "\u{1D0A}" => 'j',  // ᴊ
        "\u{1D0B}" => 'k',  // ᴋ
        "\u{1D0D}" => 'm',  // ᴍ
        "\u{1D0F}" => 'o',  // ᴏ
        "\u{1D18}" => 'p',  // ᴘ
        "\u{1D1B}" => 't',  // ᴛ
        "\u{1D1C}" => 'u',  // ᴜ
        "\u{1D20}" => 'v',  // ᴠ
        "\u{1D21}" => 'w',  // ᴡ
        "\u{1D22}" => 'z',  // ᴢ
    ];

    return $map;
}
