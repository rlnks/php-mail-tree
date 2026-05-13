<?php

namespace Rlnks\MailTree;

class Text implements Renderable
{
    use HasChildren, HasStyle;

    public function __construct(
        private string $text,
        private readonly ?string $tag = null,
        private array $style          = [],
    ) {}

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        $textStyle = $mergedStyle['text'] ?? [];
        if ($this->tag !== null && isset($mergedStyle[$this->tag]) && is_array($mergedStyle[$this->tag])) {
            $textStyle = array_replace_recursive($textStyle, $mergedStyle[$this->tag]);
        }

        $html = '';

        if ($this->tag !== null) {
            $prefix = $this->tag !== 'span' ? "\n" . str_repeat("\t", $indent) : '';
            $html  .= $prefix . '<' . $this->tag . ' style="' . $this->cssString($textStyle) . '">';
        }

        $text = ($t = Translator::getDefault()) !== null
            ? $t->resolve($this->text)
            : $this->text;

        $html .= $this->applyHighlight($text, $mergedStyle);
        $html .= $this->renderChildren($mergedStyle, $indent + 1);

        if ($this->tag !== null) {
            $html .= '</' . $this->tag . '>';
        }

        return $html;
    }

    private function applyHighlight(string $text, array $style): string
    {
        if (!str_contains($text, '**')) {
            return $text;
        }
        $hlStyle = $style['highlight'] ?? [];
        if (empty($hlStyle)) {
            return $text;
        }
        $css = $this->cssString($hlStyle);
        return preg_replace(
            '/\*\*(.+?)\*\*/s',
            '<span style="' . $css . '">$1</span>',
            $text,
        );
    }
}
