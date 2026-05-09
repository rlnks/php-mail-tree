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
    private array $theme;
    private array $registry = [];

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
        'buttonRadius'   => '4px',
        'buttonHeight'   => 50,
        'buttonWidth'    => 200,
        'buttonFontSize' => '16px',
    ];

    public function __construct(array $theme = [])
    {
        $this->theme = array_replace(self::DEFAULTS, $theme);
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
    public function borderColor(): string   { return $this->theme['borderColor']; }
    public function fontFamily(): string    { return $this->theme['fontFamily']; }
    public function baseFontSize(): string  { return $this->theme['baseFontSize']; }
    public function containerWidth(): int   { return (int) $this->theme['containerWidth']; }
    public function marginWidth(): int      { return (int) $this->theme['marginWidth']; }

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
            'a' => [
                'color'           => $this->theme['primaryColor'],
                'text-decoration' => 'none',
            ],
        ], $overrides);
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
     * Includes:
     *   - Client resets (Outlook, Apple, Gmail, Samsung Mail)
     *   - Responsive media query (stack columns, full-width containers)
     *   - Utility classes (hidden-sm, show-sm)
     *   - Dark mode stub (commented out — opt-in)
     */
    public function responsiveCss(int $breakpoint = 620): string
    {
        $bp = $breakpoint;
        $cw = $this->containerWidth();

        return implode("\n", [
            '/* ── Client resets ──────────────────────────────────── */',
            'body, #MessageViewBody, #MessageWebViewDiv {',
            '  margin: 0; padding: 0;',
            '  -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;',
            '}',
            /* Outlook.com wrapping div */
            '.ExternalClass { width: 100%; }',
            '.ExternalClass, .ExternalClass p, .ExternalClass span,',
            '.ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }',
            /* Global table reset */
            'table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }',
            /* Image resets */
            'img { -ms-interpolation-mode: bicubic; border: 0; display: block; }',
            /* Kill auto-detected blue links (Apple Mail, iOS) */
            'a[x-apple-data-detectors] {',
            '  color: inherit !important; text-decoration: none !important;',
            '  font-size: inherit !important; font-family: inherit !important;',
            '  font-weight: inherit !important; line-height: inherit !important;',
            '}',
            /* Kill Gmail blue links (u+ targets Gmail's generated wrapper) */
            'u + .body a { color: inherit; text-decoration: none; font-size: inherit;',
            '  font-family: inherit; font-weight: inherit; line-height: inherit; }',
            /* Samsung Mail */
            '#MessageViewBody a { color: inherit; text-decoration: none; }',
            '',
            '/* ── Responsive ─────────────────────────────────────── */',
            "@media only screen and (max-width: {$bp}px) {",
            "  /* Full-width containers */",
            '  table[class~="devicewidth"] { width: 100% !important; max-width: 100% !important; }',
            '  td[class~="section-body"]   { width: 100% !important; max-width: 100% !important; }',
            '',
            "  /* 2-column → stack */",
            '  td[class~="col-2"] {',
            '    display: block !important;',
            '    width: 100% !important; max-width: 100% !important;',
            '    box-sizing: border-box !important;',
            '  }',
            "  /* 3-column → stack */",
            '  td[class~="col-3"] {',
            '    display: block !important;',
            '    width: 100% !important; max-width: 100% !important;',
            '    box-sizing: border-box !important;',
            '  }',
            '',
            "  /* Fluid images */",
            '  img { max-width: 100% !important; height: auto !important; }',
            '',
            "  /* Increase base font sizes for readability */",
            '  td[class~="section-body"] { font-size: 16px !important; line-height: 160% !important; }',
            '',
            "  /* Visibility utilities */",
            '  table[class~="hidden-sm"] {',
            '    display: none !important; max-height: 0 !important;',
            '    overflow: hidden !important; mso-hide: all !important;',
            '  }',
            '  table[class~="show-sm"] {',
            '    display: block !important; max-height: none !important;',
            '    overflow: visible !important; width: 100% !important;',
            '  }',
            '}',
            '',
            '/* ── Dark mode (opt-in — uncomment to use) ──────────── */',
            '/*',
            '@media (prefers-color-scheme: dark) {',
            "  body { background-color: #1a1a1a !important; }",
            "  table[class~=\"devicewidth\"] { background-color: #2d2d2d !important; }",
            "  td[class~=\"section-body\"]   { color: #dddddd !important; }",
            '}',
            '*/',
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

    /** Merge additional keys into a named style entry. */
    public function extend(string $name, array $extra): static
    {
        $this->registry[$name] = array_replace_recursive($this->registry[$name] ?? [], $extra);
        return $this;
    }
}
