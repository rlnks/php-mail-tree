<?php

namespace Rlnks\MailTree;

class Column implements Renderable
{
    use HasChildren, HasStyle;

    private ?string $class = null;

    public function __construct(
        private array $style = [],
    ) {}

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $colStyle    = $mergedStyle['column'] ?? [];

        $attrs  = $this->dimensionAttr($colStyle, 'width');
        $attrs .= $this->dimensionAttr($colStyle, 'height');
        $attrs .= $this->class !== null ? ' class="' . htmlspecialchars($this->class, ENT_QUOTES) . '"' : '';

        $html  = "\n" . str_repeat("\t", $indent) . '<td' . $attrs . ' style="' . $this->cssString($colStyle) . '">';
        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= "\n" . str_repeat("\t", $indent) . '</td>';

        return $html;
    }

    private function dimensionAttr(array $style, string $prop): string
    {
        if (!isset($style[$prop])) {
            return '';
        }
        $value = str_replace([' !important', '!important', 'px'], '', (string) $style[$prop]);
        return ' ' . $prop . '="' . $value . '"';
    }
}
