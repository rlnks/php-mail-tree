<?php

namespace Rlnks\MailTree;

class Container implements Renderable
{
    use HasChildren;

    private array $style;
    private bool $mso;
    private ?string $class = null;
    private ?string $id = null;

    /**
     * @param array $style  Nested style array, e.g. ['container' => ['width' => '600px']]
     * @param bool  $mso    Wrap with MSO/Outlook conditional comments
     */
    public function __construct(array $style = [], bool $mso = false)
    {
        $this->style = $style;
        $this->mso   = $mso;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function setID(string $id): void
    {
        $this->id = $id;
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle    = array_replace_recursive($style, $this->style);
        $containerStyle = $mergedStyle['container'] ?? [];

        $outputStyle = '';
        foreach ($containerStyle as $prop => $value) {
            $outputStyle .= $prop . ':' . $value . ';';
        }

        $attrs  = 'cellspacing="0" cellpadding="0" align="center"';
        $attrs .= ($this->id !== null)    ? ' id="'    . htmlspecialchars($this->id,    ENT_QUOTES) . '"' : '';
        $attrs .= ($this->class !== null) ? ' class="' . htmlspecialchars($this->class, ENT_QUOTES) . '"' : '';
        $attrs .= (!isset($containerStyle['border']) || $containerStyle['border'] === 'none') ? ' border="0"' : '';
        $attrs .= $this->dimensionAttr($containerStyle, 'width');
        $attrs .= $this->dimensionAttr($containerStyle, 'height');

        $t    = $indent;
        $html = '';

        if ($this->mso) {
            $html .= '<!--[if (gte mso 9)|(IE)]>';
        }
        $html .= "\n" . str_repeat("\t", $t)     . '<table ' . $attrs . ' style="' . $outputStyle . '">';
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
