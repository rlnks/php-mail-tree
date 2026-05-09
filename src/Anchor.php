<?php

namespace Rlnks\MailTree;

class Anchor implements Renderable
{
    use HasChildren;

    private string $href;
    private array $style;

    /**
     * @param string $href   Target URL
     * @param array  $style  Nested style overrides, e.g. ['a' => ['color' => '#fff']]
     */
    public function __construct(string $href = '#', array $style = [])
    {
        $this->href  = $href;
        $this->style = $style;
    }

    public function setLink(string $href): void
    {
        $this->href = $href;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $aStyle      = $mergedStyle['a'] ?? [];

        $outputStyle = '';
        foreach ($aStyle as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $html  = "\n" . str_repeat("\t", $indent) . '<a style="' . $outputStyle . '" href="' . $this->href . '">';
        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= '</a>';

        return $html;
    }

    public function setStyle(array $style): void
    {
        $this->style = array_replace_recursive($this->style, $style);
    }
}
