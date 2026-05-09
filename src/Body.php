<?php

namespace Rlnks\MailTree;

class Body implements Renderable
{
    use HasChildren, HasStyle;

    private string $css = '';

    public function __construct(
        private array $style = [],
    ) {}

    public function setCSS(string $css): void
    {
        $this->css = $css;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        $html  = "\n" . str_repeat("\t", $indent) . '<body style="' . $this->cssString($mergedStyle['body'] ?? []) . '">';

        if ($this->css !== '') {
            $html .= "\n" . str_repeat("\t", $indent + 1) . '<style type="text/css">' . $this->css . '</style>';
        }

        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= "\n" . str_repeat("\t", $indent) . '</body>';

        return $html;
    }
}
