<?php

namespace Rlnks\MailTree;

class Anchor implements Renderable
{
    use HasChildren, HasStyle;

    public function __construct(
        private string $href  = '#',
        private array $style  = [],
    ) {}

    public function setLink(string $href): void
    {
        $this->href = $href;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        // Empty href → transparent wrapper: render children as if the Anchor weren't there.
        if ($this->href === '') {
            return $this->renderChildren($mergedStyle, $indent);
        }

        $aStyle = $mergedStyle['a'] ?? [];
        $href   = Translator::getDefault()?->resolve($this->href) ?? $this->href;

        $html  = "\n" . str_repeat("\t", $indent) . '<a style="' . $this->cssString($aStyle) . '" href="' . $href . '">';
        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= '</a>';

        return $html;
    }
}
