<?php

namespace Rlnks\MailTree;

class Body implements Renderable
{
    use HasChildren;

    private array $style;
    private string $css = '';

    public function __construct(array $style = [])
    {
        $this->style = $style;
    }

    public function setCSS(string $css): void
    {
        $this->css = $css;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        $outputStyle = '';
        foreach ($mergedStyle['body'] ?? [] as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $html  = "\n" . str_repeat("\t", $indent) . '<body style="' . $outputStyle . '">';

        if ($this->css !== '') {
            $html .= "\n" . str_repeat("\t", $indent + 1) . '<style type="text/css">' . $this->css . '</style>';
        }

        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= "\n" . str_repeat("\t", $indent) . '</body>';

        return $html;
    }

    public function getStyle(): array
    {
        return $this->style;
    }

    public function setStyle(array $style): void
    {
        $this->style = array_replace_recursive($this->style, $style);
    }
}
