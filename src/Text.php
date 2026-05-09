<?php

namespace Rlnks\MailTree;

class Text implements Renderable
{
    use HasChildren;

    private string $text;
    private array $style;
    private ?string $tag;

    /**
     * @param string      $text   Raw HTML content (may contain inline HTML)
     * @param string|null $tag    HTML tag to wrap content in (div, h1, span, p, …). Null = no wrapper.
     * @param array       $style  Nested style overrides
     */
    public function __construct(string $text, ?string $tag = null, array $style = [])
    {
        $this->text  = $text;
        $this->tag   = $tag;
        $this->style = $style;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        // Apply tag-specific style on top of base text style
        $textStyle = $mergedStyle['text'] ?? [];
        if ($this->tag !== null && isset($mergedStyle[$this->tag]) && is_array($mergedStyle[$this->tag])) {
            $textStyle = array_replace_recursive($textStyle, $mergedStyle[$this->tag]);
        }

        $outputStyle = '';
        foreach ($textStyle as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $html = '';

        if ($this->tag !== null) {
            $prefix = ($this->tag !== 'span') ? "\n" . str_repeat("\t", $indent) : '';
            $html  .= $prefix . '<' . $this->tag . ' style="' . $outputStyle . '">';
        }

        $html .= $this->text;
        $html .= $this->renderChildren($mergedStyle, $indent + 1);

        if ($this->tag !== null) {
            $html .= '</' . $this->tag . '>';
        }

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
