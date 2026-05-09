<?php

namespace Rlnks\MailTree;

class Column implements Renderable
{
    use HasChildren;

    private array $style;
    private ?string $class = null;

    /**
     * @param array $style  Nested style array, e.g. ['column' => ['width' => '540px']]
     */
    public function __construct(array $style = [])
    {
        $this->style = $style;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $colStyle    = $mergedStyle['column'] ?? [];

        $outputStyle = '';
        foreach ($colStyle as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $attrs  = $this->dimensionAttr($colStyle, 'width');
        $attrs .= $this->dimensionAttr($colStyle, 'height');
        $attrs .= ($this->class !== null) ? ' class="' . htmlspecialchars($this->class, ENT_QUOTES) . '"' : '';

        $html  = "\n" . str_repeat("\t", $indent) . '<td' . $attrs . ' style="' . $outputStyle . '">';
        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= "\n" . str_repeat("\t", $indent) . '</td>';

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

    private function dimensionAttr(array $style, string $prop): string
    {
        if (!isset($style[$prop])) {
            return '';
        }
        $value = preg_replace('/ ?!important/', '', str_replace('px', '', (string) $style[$prop]));
        return ' ' . $prop . '="' . $value . '"';
    }
}
