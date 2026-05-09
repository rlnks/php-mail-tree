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

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $imgStyle    = $mergedStyle['img'] ?? [];

        $width  = $this->numericDimensionAttr($imgStyle, 'width');
        $height = $this->numericDimensionAttr($imgStyle, 'height');

        $html  = "\n" . str_repeat("\t", $indent);
        $html .= '<img' . $width . $height . ' style="' . $this->cssString($imgStyle) . '"';
        $html .= ' src="' . $this->src . '"';
        $html .= ' alt="' . htmlspecialchars($this->alt, ENT_QUOTES) . '"';
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
