<?php

namespace Rlnks\MailTree;

class Body implements Renderable
{
    use HasChildren, HasStyle;

    private string $css        = '';
    private string $preheader  = '';

    public function __construct(
        private array $style = [],
    ) {}

    public function setCSS(string $css): void
    {
        $this->css = $css;
    }

    public function setPreheader(string $text): void
    {
        $this->preheader = $text;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        $html  = "\n" . str_repeat("\t", $indent) . '<body style="' . $this->cssString($mergedStyle['body'] ?? []) . '">';

        if ($this->css !== '') {
            $html .= "\n" . str_repeat("\t", $indent + 1) . '<style type="text/css">' . $this->css . '</style>';
        }

        if ($this->preheader !== '') {
            $t      = Translator::getDefault();
            $text   = $t !== null ? $t->resolve($this->preheader) : $this->preheader;
            $filler = str_repeat('&nbsp;&zwnj;', 100);
            $html  .= "\n" . str_repeat("\t", $indent + 1)
                . '<div style="display:none;font-size:1px;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">'
                . htmlspecialchars($text, ENT_QUOTES) . $filler
                . '</div>';
        }

        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= "\n" . str_repeat("\t", $indent) . '</body>';

        return $html;
    }
}
