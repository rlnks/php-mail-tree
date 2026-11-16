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

    public function toArray(): array
    {
        return [
            'type'     => 'Anchor',
            'href'     => $this->href,
            'style'    => $this->style,
            'hidden'   => $this->hidden,
            'children' => (object) $this->childrenToArray(),
        ];
    }

    public function build(array $style = [], int $indent = 0, ?string $mbKey = null): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        // Empty href → transparent wrapper: render children as if the Anchor weren't there.
        if ($this->href === '') {
            return $this->renderChildren($mergedStyle, $indent);
        }

        $aStyle = $mergedStyle['a'] ?? [];
        $flat   = array_filter($this->style, fn($v) => !is_array($v));
        if ($flat) {
            $aStyle = array_replace($aStyle, $flat);
        }
        $href   = Translator::getDefault()?->resolve($this->href) ?? $this->href;
        $mbAttr = $mbKey !== null ? ' data-mb-key="' . htmlspecialchars($mbKey, ENT_QUOTES) . '"' : '';

        $html  = "\n" . str_repeat("\t", $indent) . '<a' . $mbAttr . ' style="' . $this->cssString($aStyle) . '" href="' . $href . '">';
        $html .= $this->renderChildren($mergedStyle, $indent + 1);
        $html .= '</a>';

        return $html;
    }
}
