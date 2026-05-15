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

    public function toArray(): array
    {
        $data = [
            'type'     => 'Column',
            'class'    => $this->class,
            'style'    => $this->style,
            'hidden'   => $this->hidden,
            'children' => (object) $this->childrenToArray(),
        ];
        if ($this->preset !== '') {
            $data['_tag'] = $this->preset;
        }
        return $data;
    }

    public function build(array $style = [], int $indent = 0, ?string $mbKey = null): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);
        $colStyle    = $mergedStyle['column'] ?? [];

        $attrs  = $this->dimensionAttr($colStyle, 'width');
        $attrs .= $this->dimensionAttr($colStyle, 'height');
        $attrs .= $this->class !== null ? ' class="' . htmlspecialchars($this->class, ENT_QUOTES) . '"' : '';

        $mbAttr = $mbKey !== null ? ' data-mb-key="' . htmlspecialchars($mbKey, ENT_QUOTES) . '"' : '';

        $html  = "\n" . str_repeat("\t", $indent) . '<td' . $mbAttr . $attrs . ' style="' . $this->cssString($colStyle) . '">';
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
