<?php

namespace Rlnks\MailTree;

class Image implements Renderable
{
    private string $src;
    private string $alt;
    private array $style;

    /**
     * @param string $src   Image URL
     * @param string $alt   Alt text
     * @param array  $style Nested style overrides, e.g. ['img' => ['width' => '150px']]
     */
    public function __construct(string $src = '', string $alt = '', array $style = [])
    {
        $this->src   = $src;
        $this->alt   = $alt;
        $this->style = $style;
    }

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

        $outputStyle = '';
        foreach ($imgStyle as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $width  = $this->numericDimensionAttr($imgStyle, 'width');
        $height = $this->numericDimensionAttr($imgStyle, 'height');

        $html  = "\n" . str_repeat("\t", $indent);
        $html .= '<img' . $width . $height . ' style="' . $outputStyle . '"';
        $html .= ' src="' . $this->src . '"';
        $html .= ' alt="' . htmlspecialchars($this->alt, ENT_QUOTES) . '"';
        $html .= ' moz-do-not-send="true">';

        return $html;
    }

    public function setStyle(array $style): void
    {
        $this->style = array_replace_recursive($this->style, $style);
    }

    /** Only emits the HTML attribute when the CSS value is a plain pixel number (not %, auto, etc.) */
    private function numericDimensionAttr(array $style, string $prop): string
    {
        if (!isset($style[$prop])) {
            return '';
        }
        $value = preg_replace('/ ?!important/', '', str_replace('px', '', (string) $style[$prop]));
        return is_numeric($value) ? ' ' . $prop . '="' . $value . '"' : '';
    }
}
