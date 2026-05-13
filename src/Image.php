<?php

namespace Rlnks\MailTree;

class Image implements Renderable
{
    use HasStyle;

    public function __construct(
        private string $src = '',
        private string $alt = '',
        private array $style = [],
    ) {}

    public function setSrc(string $src, ?string $alt = null): void
    {
        $this->src = $src;
        if ($alt !== null) {
            $this->alt = $alt;
        }
    }

    public function toArray(): array
    {
        return [
            'type'   => 'Image',
            'src'    => $this->src,
            'alt'    => $this->alt,
            'style'  => $this->style,
            'hidden' => $this->hidden,
        ];
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $imgStyle    = $mergedStyle['img'] ?? [];

        $width  = $this->numericDimensionAttr($imgStyle, 'width');
        $height = $this->numericDimensionAttr($imgStyle, 'height');

        // Emit border="0" HTML attribute when the style declares no border — prevents
        // blue link-borders in Outlook and older email clients that ignore inline CSS.
        $borderVal = isset($imgStyle['border']) ? trim((string) $imgStyle['border']) : null;
        $border    = ($borderVal !== null && ($borderVal === '0' || str_starts_with($borderVal, '0 ') || $borderVal === 'none'))
            ? ' border="0"'
            : '';

        $t   = Translator::getDefault();
        $src = $t !== null ? $t->resolve($this->src) : $this->src;
        $alt = $t !== null ? $t->resolve($this->alt) : $this->alt;

        $html  = "\n" . str_repeat("\t", $indent);
        $html .= '<img' . $width . $height . $border . ' style="' . $this->cssString($imgStyle) . '"';
        $html .= ' src="' . $src . '"';
        $html .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"';
        $html .= ' moz-do-not-send="true">';

        return $html;
    }

    /** Only emits the HTML attribute when the CSS value is a plain pixel number (not %, auto, etc.) */
    private function numericDimensionAttr(array $style, string $prop): string
    {
        if (!isset($style[$prop])) {
            return '';
        }
        $value = str_replace([' !important', '!important', 'px'], '', (string) $style[$prop]);
        return is_numeric($value) ? ' ' . $prop . '="' . $value . '"' : '';
    }
}
