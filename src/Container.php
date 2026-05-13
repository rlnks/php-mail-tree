<?php

namespace Rlnks\MailTree;

class Container implements Renderable
{
    use HasChildren, HasStyle;

    private ?string $class     = null;
    private ?string $id        = null;
    private ?string $responsive = null;

    public function __construct(
        private array $style        = [],
        private readonly bool $mso  = false,
    ) {}

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function setID(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Apply a responsive visibility mode to this container.
     *
     * 'mobile-only'  → hidden on desktop (inline display:none), shown on mobile via show-sm.
     * 'desktop-only' → visible on desktop, hidden on mobile via hidden-sm.
     */
    public function setResponsive(string $mode): void
    {
        $this->responsive = $mode;
        $suffix = match ($mode) {
            'mobile-only'  => 'show-sm',
            'desktop-only' => 'hidden-sm',
            default        => '',
        };
        if ($suffix !== '') {
            $this->class = $this->class !== null ? $this->class . ' ' . $suffix : $suffix;
        }
    }

    public function toArray(): array
    {
        $data = [
            'type'       => 'Container',
            'mso'        => $this->mso,
            'class'      => $this->class,
            'id'         => $this->id,
            'responsive' => $this->responsive,
            'style'      => $this->style,
            'hidden'     => $this->hidden,
            'children'   => $this->childrenToArray(),
        ];
        if ($this->preset !== '') {
            $data['_tag'] = $this->preset;
        }
        return $data;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle    = array_replace_recursive($style, $this->style);
        $containerStyle = $mergedStyle['container'] ?? [];

        if ($this->responsive === 'mobile-only') {
            $containerStyle['display']    = 'none';
            $containerStyle['max-height'] = '0';
            $containerStyle['overflow']   = 'hidden';
        }

        $attrs  = 'role="presentation" cellspacing="0" cellpadding="0" align="center"';
        $attrs .= $this->id    !== null ? ' id="'    . htmlspecialchars($this->id,    ENT_QUOTES) . '"' : '';
        $attrs .= $this->class !== null ? ' class="' . htmlspecialchars($this->class, ENT_QUOTES) . '"' : '';
        $attrs .= (!isset($containerStyle['border']) || $containerStyle['border'] === 'none') ? ' border="0"' : '';
        $attrs .= $this->dimensionAttr($containerStyle, 'width');
        $attrs .= $this->dimensionAttr($containerStyle, 'height');

        $t    = $indent;
        $html = '';

        if ($this->mso) {
            $html .= '<!--[if (gte mso 9)|(IE)]>';
        }
        $html .= "\n" . str_repeat("\t", $t)     . '<table ' . $attrs . ' style="' . $this->cssString($containerStyle) . '">';
        $html .= "\n" . str_repeat("\t", $t + 1) . '<tbody>';
        $html .= "\n" . str_repeat("\t", $t + 2) . '<tr>';
        if ($this->mso) {
            $html .= '<td style="padding:0;"><![endif]-->';
        }

        $html .= $this->renderChildren($mergedStyle, $t + 3);

        if ($this->mso) {
            $html .= '<!--[if (gte mso 9)|(IE)]></td>';
        }
        $html .= "\n" . str_repeat("\t", $t + 2) . '</tr>';
        $html .= "\n" . str_repeat("\t", $t + 1) . '</tbody>';
        $html .= "\n" . str_repeat("\t", $t)     . '</table>';
        if ($this->mso) {
            $html .= '<![endif]-->';
        }

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
