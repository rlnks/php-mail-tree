<?php

namespace Rlnks\MailTree;

/**
 * Centralized style configuration for an email.
 *
 * Acts as two things at once:
 *   1. A theme — primary color, fonts, spacing, widths — that generates
 *      properly-structured style arrays for every framework class.
 *   2. A named-style registry — define('button', [...]) / get('button') —
 *      replacing the raw $style array pattern from manual builds.
 *
 * Usage:
 *   $sheet = new StyleSheet(['primaryColor' => '#c00', 'fontFamily' => 'Poppins, Arial, sans-serif']);
 *   $email = new EmailDocument($sheet->emailStyle());
 *   $email->body->setCSS($sheet->responsiveCss());
 */
class StyleSheet
{
    private static ?self $default = null;

    private array $theme;
    private array $registry        = [];
    private array $baseStyles      = [];
    private array $responsiveRules = [];
    private array $webFonts        = [];

    private const DEFAULTS = [
        'primaryColor'   => '#333333',
        'textColor'      => '#444444',
        'bgColor'        => '#f0f0f0',
        'containerBg'    => '#ffffff',
        'borderColor'    => '#dddddd',
        'fontFamily'     => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
        'baseFontSize'   => '14px',
        'lineHeight'     => '150%',
        'containerWidth' => 600,
        'marginWidth'    => 30,
        'spacerHeight'   => '20px',
        'spacerBg'       => '',             // empty = inherit containerBg
        'buttonRadius'   => '4px',
        'buttonHeight'   => 50,
        'buttonWidth'    => 200,
        'buttonFontSize' => '16px',
        // Dark mode tokens (used by darkModeCss() / responsiveCss(darkMode: true))
        'darkBgColor'       => '#1a1a1a',
        'darkContainerBg'   => '#2d2d2d',
        'darkTextColor'     => '#dddddd',
        'darkPrimaryColor'  => '',          // defaults to primaryColor if empty
        'darkBorderColor'   => '#444444',
    ];

    public function __construct(array $theme = [])
    {
        $tokens     = [];
        $baseStyles = [];
        $registry   = [];

        foreach ($theme as $key => $value) {
            if (!is_array($value)) {
                $tokens[$key] = $value;
            } elseif ($this->isNestedStyleArray($value)) {
                $registry[$key] = $value;   // 'header' => ['container' => [...]]
            } else {
                $baseStyles[$key] = $value; // 'h1' => ['font-size' => '22px']
            }
        }

        $this->theme      = array_replace(self::DEFAULTS, $tokens);
        $this->baseStyles = $baseStyles;
        $this->registry   = $registry;
    }

    /** Returns true when at least one value in the array is itself an array (named-style shape). */
    private function isNestedStyleArray(array $arr): bool
    {
        foreach ($arr as $v) {
            if (is_array($v)) {
                return true;
            }
        }
        return false;
    }

    // ── Theme variable access ─────────────────────────────────────────────────

    public function theme(string $key, mixed $default = null): mixed
    {
        return $this->theme[$key] ?? $default;
    }

    public function primaryColor(): string  { return $this->theme['primaryColor']; }
    public function textColor(): string     { return $this->theme['textColor']; }
    public function bgColor(): string       { return $this->theme['bgColor']; }
    public function containerBg(): string   { return $this->theme['containerBg']; }
    public function spacerBg(): string      { return $this->theme['spacerBg'] ?: $this->theme['containerBg']; }
    public function borderColor(): string   { return $this->theme['borderColor']; }
    public function fontFamily(): string    { return $this->theme['fontFamily']; }
    public function baseFontSize(): string  { return $this->theme['baseFontSize']; }
    public function containerWidth(): int   { return (int) $this->theme['containerWidth']; }
    public function marginWidth(): int      { return (int) $this->theme['marginWidth']; }

    /**
     * Returns the highlight style as a ready-to-use inline CSS string.
     * Pass to Translator::setHighlightStyle() so **...** in translation values
     * is rendered with the same style as in Text::build().
     */
    public function highlightInlineStyle(): string
    {
        $styles = $this->emailStyle()['highlight'] ?? [];
        return implode(';', array_map(
            fn(string $prop, mixed $val): string => $prop . ':' . $val,
            array_keys($styles),
            $styles,
        ));
    }

    /** Width available inside the margin columns */
    public function bodyWidth(): int
    {
        return $this->containerWidth() - $this->marginWidth() * 2;
    }

    // ── Style array generators ────────────────────────────────────────────────

    /**
     * Base style for new EmailDocument($sheet->emailStyle()).
     * Sets body background, global font, and common text-element defaults.
     */
    public function emailStyle(array $overrides = []): array
    {
        return array_replace_recursive([
            'body' => [
                'background-color'          => $this->theme['bgColor'],
                'margin'                    => 0,
                'border'                    => 'none',
                '-webkit-text-size-adjust'  => '100%',
                '-ms-text-size-adjust'      => '100%',
            ],
            'text' => [
                'font-family'  => $this->theme['fontFamily'],
                'font-size'    => $this->theme['baseFontSize'],
                'line-height'  => $this->theme['lineHeight'],
                'color'        => $this->theme['textColor'],
            ],
            'h1' => [
                'font-size'   => '28px',
                'line-height' => '120%',
                'color'       => $this->theme['primaryColor'],
                'margin'      => 0,
            ],
            'h2' => [
                'font-size'   => '22px',
                'line-height' => '120%',
                'color'       => $this->theme['primaryColor'],
                'margin'      => 0,
            ],
            'h3' => [
                'font-size'   => '18px',
                'line-height' => '120%',
                'margin'      => 0,
            ],
            'div' => [
                'margin' => 0,
            ],
            'p' => [
                'margin' => 0,
            ],
            'a' => [
                'color'           => $this->theme['primaryColor'],
                'text-decoration' => 'none',
            ],
            'container' => [
                'background-color' => $this->theme['containerBg'],
            ],
            'highlight' => [
                'color'       => $this->theme['primaryColor'],
                'font-weight' => 'bold',
            ],
        ], $this->baseStyles, $overrides);
    }

    /**
     * Style for the outer Container wrapper (the full-width bounding box).
     * Pass to new Container($sheet->outerContainerStyle(), mso: true).
     */
    public function outerContainerStyle(array $overrides = []): array
    {
        return array_replace_recursive([
            'container' => [
                'width'            => $this->containerWidth() . 'px',
                'max-width'        => $this->containerWidth() . 'px',
                'border-collapse'  => 'collapse',
                'table-layout'     => 'fixed',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ], $overrides);
    }

    /**
     * Style for a standard content Container (with visible background/border).
     */
    public function containerStyle(array $overrides = []): array
    {
        return array_replace_recursive([
            'container' => [
                'width'            => $this->containerWidth() . 'px',
                'max-width'        => $this->containerWidth() . 'px',
                'background-color' => $this->theme['containerBg'],
                'border-collapse'  => 'collapse',
                'table-layout'     => 'fixed',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ], $overrides);
    }

    /** Style for a left/right margin Column. */
    public function marginColumnStyle(array $overrides = []): array
    {
        return array_replace_recursive([
            'column' => ['width' => $this->marginWidth() . 'px'],
        ], $overrides);
    }

    /** Style for the main body Column inside a 1-column Section. */
    public function bodyColumnStyle(array $overrides = []): array
    {
        $w = $this->bodyWidth();
        return array_replace_recursive([
            'column' => ['width' => "{$w}px", 'max-width' => "{$w}px"],
        ], $overrides);
    }

    /** Style for a 2-column layout Column (equal halves). */
    public function twoColStyle(array $overrides = []): array
    {
        $w = (int) floor($this->bodyWidth() / 2);
        return array_replace_recursive([
            'column' => ['width' => "{$w}px", 'max-width' => "{$w}px"],
        ], $overrides);
    }

    /** Style for a 3-column layout Column (equal thirds). */
    public function threeColStyle(array $overrides = []): array
    {
        $w = (int) floor($this->bodyWidth() / 3);
        return array_replace_recursive([
            'column' => ['width' => "{$w}px", 'max-width' => "{$w}px"],
        ], $overrides);
    }

    /**
     * Style for an N-column layout Column.
     *
     * $widths is an optional array of percentage shares (must match column count).
     * If omitted, columns are divided equally.
     *
     * Example — 4 equal columns:
     *   $sheet->nColStyle(4)
     *
     * Example — asymmetric 40/60 two-column:
     *   $sheet->nColStyle(2, [40, 60])   // returns style for first column
     *   Use $index to get subsequent columns.
     */
    public function nColStyle(int $columns, array $widths = [], int $index = 0, array $overrides = []): array
    {
        $inner = $this->bodyWidth();
        if ($widths) {
            $sum  = array_sum($widths);
            $used = 0;
            $pxWidths = [];
            foreach ($widths as $i => $pct) {
                if ($i === count($widths) - 1) {
                    $pxWidths[] = $inner - $used;
                } else {
                    $px        = (int) round($inner * $pct / $sum);
                    $pxWidths[] = $px;
                    $used      += $px;
                }
            }
            $w = $pxWidths[$index] ?? (int) floor($inner / $columns);
        } else {
            $base = (int) floor($inner / $columns);
            $rem  = $inner - $base * $columns;
            $w    = $base + ($index === 0 ? $rem : 0);
        }
        return array_replace_recursive([
            'column' => ['width' => "{$w}px", 'max-width' => "{$w}px"],
        ], $overrides);
    }

    /** Style for a Spacer Container. */
    public function spacerStyle(string $height = '', array $overrides = []): array
    {
        $h = $height ?: $this->theme['spacerHeight'];
        return array_replace_recursive([
            'div' => [
                'font-size'            => $h,
                'line-height'          => $h,
                'height'               => $h,
                'mso-line-height-rule' => 'exactly',
            ],
        ], $overrides);
    }

    /** Style array for a CTA button Container. */
    public function buttonContainerStyle(
        string $bgColor = '',
        string $radius  = '',
        array  $overrides = []
    ): array {
        $bg = $bgColor ?: $this->theme['primaryColor'];
        $r  = $radius  ?: $this->theme['buttonRadius'];

        return array_replace_recursive([
            'container' => [
                'background-color'      => $bg,
                'border-radius'         => $r,
                '-webkit-border-radius' => $r,
                '-moz-border-radius'    => $r,
                'text-align'            => 'center',
                'border-collapse'       => 'separate',
            ],
            'column' => [
                'text-align'     => 'center',
                'padding-top'    => '14px',
                'padding-bottom' => '14px',
                'padding-left'   => '24px',
                'padding-right'  => '24px',
            ],
            'a' => [
                'color'           => '#ffffff',
                'text-decoration' => 'none',
                'font-weight'     => 'bold',
                'font-size'       => $this->theme['buttonFontSize'],
                'letter-spacing'  => '0.5px',
                'text-transform'  => 'uppercase',
            ],
            'span' => [
                'color'          => '#ffffff',
                'font-size'      => $this->theme['buttonFontSize'],
                'font-weight'    => 'bold',
                'letter-spacing' => '0.5px',
                'text-transform' => 'uppercase',
            ],
        ], $overrides);
    }

    // ── CSS generator ─────────────────────────────────────────────────────────

    /**
     * Returns the full CSS string to inject via Body::setCSS().
     *
     * Pass darkMode: true to activate the prefers-color-scheme block using the
     * dark-mode tokens (darkBgColor, darkContainerBg, darkTextColor, etc.) set
     * in the theme. You can override each token when constructing the StyleSheet:
     *
     *   $sheet = new StyleSheet([
     *       'primaryColor'  => '#003366',
     *       'darkBgColor'   => '#0d1b2a',
     *       'darkTextColor' => '#e0e6ef',
     *   ]);
     *   $email->body->setCSS($sheet->responsiveCss(darkMode: true));
     */
    public function responsiveCss(int $breakpoint = 620, bool $darkMode = false): string
    {
        $bp  = $breakpoint;
        $css = <<<CSS
/* ── Client resets ──────────────────────────────────── */
body, #MessageViewBody, #MessageWebViewDiv {
  margin: 0;
  padding: 0;
  -webkit-text-size-adjust: 100%;
  -ms-text-size-adjust: 100%;
}
.ExternalClass { width: 100%; }
.ExternalClass,
.ExternalClass p,
.ExternalClass span,
.ExternalClass font,
.ExternalClass td,
.ExternalClass div { line-height: 100%; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; display: block; }
a[x-apple-data-detectors] {
  color: inherit !important;
  text-decoration: none !important;
  font-size: inherit !important;
  font-family: inherit !important;
  font-weight: inherit !important;
  line-height: inherit !important;
}
u + .body a {
  color: inherit;
  text-decoration: none;
  font-size: inherit;
  font-family: inherit;
  font-weight: inherit;
  line-height: inherit;
}
#MessageViewBody a { color: inherit; text-decoration: none; }

/* ── Responsive ─────────────────────────────────────── */
@media only screen and (max-width: {$bp}px) {

  /* Full-width containers */
  table[class~="devicewidth"] {
    width: 100% !important;
    max-width: 100% !important;
  }
  td[class~="section-body"] {
    width: 100% !important;
    max-width: 100% !important;
  }

  /* Reset all fixed-width cells to auto — prevents horizontal overflow */
  table[class~="devicewidth"] > tbody > tr > td {
    width: auto !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }
  /* Keep margin spacers narrow on mobile */
  table[class~="devicewidth"] > tbody > tr > td[width="30"] {
    width: 16px !important;
    max-width: 16px !important;
  }

  /* N-column → stack (col-2 through col-5) */
  td[class~="col-2"],
  td[class~="col-3"],
  td[class~="col-4"],
  td[class~="col-5"] {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  /* DataTable — reduce padding and font-size on narrow viewports */
  table[class~="datatable"] th,
  table[class~="datatable"] td {
    padding: 8px 6px !important;
    font-size: 13px !important;
  }
  table[class~="datatable"] th { font-size: 12px !important; }

  /* Fluid images */
  img {
    max-width: 100% !important;
    height: auto !important;
  }

  /* Increase base font size for readability */
  td[class~="section-body"] {
    font-size: 16px !important;
    line-height: 160% !important;
  }

  /* Visibility utilities */
  table[class~="hidden-sm"] {
    display: none !important;
    max-height: 0 !important;
    overflow: hidden !important;
    mso-hide: all !important;
  }
  table[class~="show-sm"] {
    display: block !important;
    max-height: none !important;
    overflow: visible !important;
    width: 100% !important;
  }
CSS;

        if ($this->responsiveRules !== []) {
            $css .= "\n\n  /* ── Custom responsive rules ───────────────────── */";
            foreach ($this->responsiveRules as $selector => $props) {
                $css .= "\n  {$selector} {";
                foreach ($props as $prop => $value) {
                    $css .= "\n    {$prop}: {$value};";
                }
                $css .= "\n  }";
            }
        }

        $css .= "\n\n}";

        return $css . ($darkMode ? "\n\n" . $this->darkModeCss() : '');
    }

    /**
     * Register a custom rule to be emitted inside the responsive @media block.
     *
     * Example — zero out right padding on mobile:
     *   $sheet->addResponsiveRule('td[class~="no-pad-right"]', ['padding-right' => '0 !important']);
     *
     * Add the matching inline style on the element and assign the class:
     *   $col->setStyle(['column' => ['padding-right' => '20px']]);
     *   $col->setClass('no-pad-right');
     */
    public function addResponsiveRule(string $selector, array $properties): static
    {
        $this->responsiveRules[$selector] = array_merge($this->responsiveRules[$selector] ?? [], $properties);
        return $this;
    }

    /**
     * Register a web font URL to be loaded in <head> and via @import in <style>.
     *
     * For Google Fonts URLs, preconnect tags are generated automatically by
     * webFontLinks(). If $fontName is provided it is prepended to fontFamily in
     * the theme so every Text / Section element inherits it immediately — no
     * further style changes needed.
     *
     * Loading strategy (automatically applied via EmailDocument):
     *   • <link rel="preconnect"> tags in <head> — speed up Google Fonts lookup
     *   • <link rel="stylesheet"> tag in <head> — the actual font load
     * This covers Apple Mail, iOS Mail, Samsung Mail, and Outlook.com webmail.
     * Outlook on Windows ignores web fonts; it uses the font-family fallback stack.
     * Gmail ignores web fonts regardless of loading method.
     *
     * Note: @import inside <style> is intentionally omitted — it is silently
     * ignored by Gmail and Outlook, so <link> alone provides identical coverage.
     *
     * Usage:
     *   $sheet->addWebFont(
     *       'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap',
     *       'Open Sans',
     *   );
     *   $email = new EmailDocument($sheet);   // <link> tags auto-injected in <head>
     */
    public function addWebFont(string $url, string $fontName = ''): static
    {
        $this->webFonts[] = ['url' => $url, 'name' => $fontName];

        if ($fontName !== '' && !str_contains($this->theme['fontFamily'], $fontName)) {
            $this->theme['fontFamily'] = $fontName . ', ' . $this->theme['fontFamily'];
        }

        return $this;
    }

    /**
     * Returns HTML <link> tags for all registered web fonts.
     *
     * For Google Fonts URLs, preconnect tags for fonts.googleapis.com and
     * fonts.gstatic.com (with crossorigin) are prepended automatically.
     *
     * Inject the output into <head> via EmailDocument — this is done
     * automatically when you pass the StyleSheet to new EmailDocument($sheet).
     */
    public function webFontLinks(): string
    {
        if (empty($this->webFonts)) { return ''; }

        $lines          = [];
        $preconnectDone = false;

        foreach ($this->webFonts as $font) {
            $url = $font['url'];
            if (!$preconnectDone && str_contains($url, 'fonts.googleapis.com')) {
                $lines[]        = '<link rel="preconnect" href="https://fonts.googleapis.com">';
                $lines[]        = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
                $preconnectDone = true;
            }
            $lines[] = '<link href="' . htmlspecialchars($url, ENT_QUOTES) . '" rel="stylesheet">';
        }

        return implode("\n", $lines);
    }

    /**
     * CSS block for dark mode support (prefers-color-scheme + Outlook.com).
     * Called automatically by responsiveCss(darkMode: true).
     * Can also be called standalone and appended to a custom <style> block.
     */
    public function darkModeCss(): string
    {
        $darkBg        = $this->theme['darkBgColor'];
        $darkContainer = $this->theme['darkContainerBg'];
        $darkText      = $this->theme['darkTextColor'];
        $darkPrimary   = $this->theme['darkPrimaryColor'] ?: $this->theme['primaryColor'];
        $darkBorder    = $this->theme['darkBorderColor'];

        return implode("\n", [
            '/* ── Dark mode ──────────────────────────────────────── */',
            '@media (prefers-color-scheme: dark) {',
            "  body, #MessageViewBody { background-color: {$darkBg} !important; }",
            "  table[class~=\"devicewidth\"] { background-color: {$darkContainer} !important; }",
            "  td[class~=\"section-body\"]   { color: {$darkText} !important; }",
            "  h1, h2, h3 { color: {$darkPrimary} !important; }",
            "  a { color: {$darkPrimary} !important; }",
            '}',
            '/* Outlook.com dark mode overrides */',
            "[data-ogsb] body { background-color: {$darkBg} !important; }",
            "[data-ogsc] td[class~=\"section-body\"] { color: {$darkText} !important; }",
        ]);
    }

    // ── Named-style registry ──────────────────────────────────────────────────

    /**
     * Register a named style entry (replaces the $style['key'] pattern).
     *
     *   $sheet->define('hero', ['container' => ['background-color' => '#003'], ...]);
     *   $section->setStyle($sheet->get('hero'));
     */
    public function define(string $name, array $style): static
    {
        $this->registry[$name] = $style;
        return $this;
    }

    /** Retrieve a named style, with optional inline overrides. */
    public function get(string $name, array $overrides = []): array
    {
        $base = $this->registry[$name] ?? [];
        return $overrides ? array_replace_recursive($base, $overrides) : $base;
    }

    public function has(string $name): bool
    {
        return isset($this->registry[$name]);
    }

    // ── Default sheet registry ────────────────────────────────────────────────

    /**
     * Register this instance as the process-wide default.
     * All presets resolve to it when no explicit $sheet is passed.
     * Call once at bootstrap — typically right after constructing your StyleSheet.
     */
    public function useAsDefault(): static
    {
        self::$default = $this;
        return $this;
    }

    /** Return the default sheet, or null if none has been set. */
    public static function getDefault(): ?static
    {
        return self::$default;
    }

    /** Clear the default (useful in tests). */
    public static function clearDefault(): void
    {
        self::$default = null;
    }

    /** Merge additional keys into a named style entry. */
    public function extend(string $name, array $extra): static
    {
        $this->registry[$name] = array_replace_recursive($this->registry[$name] ?? [], $extra);
        return $this;
    }
}
