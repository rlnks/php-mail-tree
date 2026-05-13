<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;

/**
 * CTA button with full Outlook / VML support.
 *
 * Strategy (best practice for 2025):
 *   - Outlook 2007-2019 / 365 on Windows: uses VML <v:roundrect> with the
 *     href attribute so the entire colored shape is clickable. The arcsize
 *     attribute approximates border-radius by expressing the corner curve as
 *     a percentage of the smaller dimension.
 *   - All other clients: inline-block <a> with background-color, border-radius,
 *     and explicit width/line-height for reliable height in all renderers.
 *   - mso-hide:all on the <a> tag hides it from Outlook (which uses VML).
 *
 * Usage:
 *   $btn = Button::make('Subscribe', 'https://example.com');
 *   $btn = Button::make('Buy now', $url, bgColor: '#c00', width: 240, height: 52);
 *   $btn = Button::make('Go', $url, sheet: $sheet);          // pulls theme defaults
 *
 * Placement: add it as a child of any Column or Text node.
 *   $section->body->cta = Button::make('Click me', $url);
 */
class Button implements Renderable
{
    private function __construct(
        private readonly string $label,
        private readonly string $href,
        private readonly int    $width,
        private readonly int    $height,
        private readonly string $bgColor,
        private readonly string $textColor,
        private readonly string $fontFamily,
        private readonly string $fontSize,
        private readonly string $borderRadius,
    ) {}

    public static function make(
        string      $label,
        string      $href,
        string      $bgColor      = '',
        string      $textColor    = '#ffffff',
        int         $width        = 0,
        int         $height       = 0,
        string      $fontSize     = '',
        string      $borderRadius = '',
        string      $fontFamily   = '',
        ?StyleSheet $sheet        = null,
    ): static {
        $sheet ??= StyleSheet::getDefault();
        return new static(
            label:        $label,
            href:         $href,
            width:        $width        ?: (int) ($sheet?->theme('buttonWidth')    ?? 200),
            height:       $height       ?: (int) ($sheet?->theme('buttonHeight')   ?? 50),
            bgColor:      $bgColor      ?: ($sheet?->primaryColor()                ?? '#333333'),
            textColor:    $textColor,
            fontFamily:   $fontFamily   ?: ($sheet?->fontFamily()                  ?? "Arial, 'Helvetica Neue', Helvetica, sans-serif"),
            fontSize:     $fontSize     ?: ($sheet?->theme('buttonFontSize')       ?? '16px'),
            borderRadius: $borderRadius ?: ($sheet?->theme('buttonRadius')         ?? '4px'),
        );
    }

    public function toArray(): array
    {
        return [
            'type'         => 'Button',
            'label'        => $this->label,
            'href'         => $this->href,
            'width'        => $this->width,
            'height'       => $this->height,
            'bgColor'      => $this->bgColor,
            'textColor'    => $this->textColor,
            'fontFamily'   => $this->fontFamily,
            'fontSize'     => $this->fontSize,
            'borderRadius' => $this->borderRadius,
        ];
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $i        = str_repeat("\t", $indent);
        $arcsize  = $this->computeArcsize();
        $inlineA  = $this->buildAnchorStyle();
        $hrefEsc  = htmlspecialchars($this->href, ENT_QUOTES);

        $html  = "\n{$i}<!--[if mso]>";
        $html .= "\n{$i}<v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\"";
        $html .= " xmlns:w=\"urn:schemas-microsoft-com:office:word\"";
        $html .= " href=\"{$hrefEsc}\"";
        $html .= " style=\"height:{$this->height}px;v-text-anchor:middle;width:{$this->width}px;\"";
        $html .= " arcsize=\"{$arcsize}%\" stroke=\"f\" fillcolor=\"{$this->bgColor}\">";
        $html .= "\n{$i}\t<w:anchorlock/>";
        $html .= "\n{$i}\t<center style=\"color:{$this->textColor};font-family:{$this->fontFamily};font-size:{$this->fontSize};font-weight:bold;\">{$this->label}</center>";
        $html .= "\n{$i}</v:roundrect>";
        $html .= "\n{$i}<![endif]--><!--[if !mso]><!-->"; // no newline: flush with anchor

        $html .= "\n{$i}<a href=\"{$hrefEsc}\" target=\"_blank\" style=\"{$inlineA}\">{$this->label}</a>";
        $html .= "\n{$i}<!--<![endif]-->";

        return $html;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * VML arcsize = border-radius-px / shorter-dimension * 100.
     * Capped at 50 so it never exceeds a pill shape.
     */
    private function computeArcsize(): int
    {
        $radiusPx    = (int) preg_replace('/[^0-9]/', '', $this->borderRadius);
        $smallerSide = min($this->width, $this->height);

        if ($smallerSide === 0 || $radiusPx === 0) {
            return 0;
        }

        return (int) min(50, round($radiusPx / $smallerSide * 100));
    }

    /** Inline CSS string for the non-Outlook <a> tag. */
    private function buildAnchorStyle(): string
    {
        $props = [
            'background-color'       => $this->bgColor,
            'border-radius'          => $this->borderRadius,
            'color'                  => $this->textColor,
            'display'                => 'inline-block',
            'font-family'            => $this->fontFamily,
            'font-size'              => $this->fontSize,
            'font-weight'            => 'bold',
            'line-height'            => $this->height . 'px',
            'mso-hide'               => 'all',
            'text-align'             => 'center',
            'text-decoration'        => 'none',
            'width'                  => $this->width . 'px',
            '-webkit-text-size-adjust' => 'none',
            'letter-spacing'         => '0.5px',
        ];

        $css = '';
        foreach ($props as $prop => $value) {
            $css .= $prop . ':' . $value . ';';
        }
        return $css;
    }
}
