<?php

namespace Rlnks\MailTree;

class EmailDocument implements Renderable
{
    use HasChildren;

    private array $style;
    private string $subject = '';
    private array $links = [];

    public function __construct(array $style = [])
    {
        $this->style = array_replace_recursive($this->defaultStyle(), $style);
    }

    public function setSubject(string $title): void
    {
        $this->subject = $title;
    }

    public function addLink(string $href, string $rel): void
    {
        $this->links[] = ['href' => $href, 'rel' => $rel];
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $mergedStyle = array_replace_recursive($style, $this->style);

        $i = $indent;
        $html  = '<!DOCTYPE html>' . "\n";
        $html .= '<html>' . "\n";
        $html .= str_repeat("\t", $i + 1) . '<head>' . "\n";
        $html .= str_repeat("\t", $i + 2) . '<meta charset="UTF-8">' . "\n";
        $html .= str_repeat("\t", $i + 2) . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= str_repeat("\t", $i + 2) . '<title>' . htmlspecialchars($this->subject, ENT_QUOTES) . '</title>';

        foreach ($this->links as $link) {
            $html .= "\n" . str_repeat("\t", $i + 2) . '<link href="' . $link['href'] . '" rel="' . $link['rel'] . '">';
        }

        $html .= "\n" . str_repeat("\t", $i + 1) . '</head>';
        $html .= $this->renderChildren($mergedStyle, $i + 1);
        $html .= "\n" . '</html>';

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

    private function defaultStyle(): array
    {
        return [
            'body' => [
                'background-color' => '#ffffff',
                'border'           => 'none',
                'margin'           => 0,
            ],
            'text' => [
                'font-family'              => "'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Helvetica, Arial, sans-serif",
                'font-size'                => '12px',
                'line-height'              => '100%',
                'color'                    => '#666666',
                '-webkit-text-size-adjust' => 'none',
                'font-style'               => 'normal',
                'font-weight'              => 'normal',
                'font-variant'             => 'normal',
                'text-indent'              => '0px',
                'text-decoration'          => 'none',
                'text-transform'           => 'none',
                'text-align'               => 'left',
                'letter-spacing'           => 'normal',
                'vertical-align'           => 'baseline',
                '-webkit-margin-before'    => 0,
                '-webkit-margin-after'     => 0,
            ],
            'container' => [
                'background-color' => '#ffffff',
                'border-collapse'  => 'collapse',
                'table-layout'     => 'fixed',
                'padding'          => 0,
                'border'           => 'none',
                'margin'           => 'auto',
                'width'            => '100%',
                'border-spacing'   => 0,
                'mso-cellspacing'  => 0,
            ],
            'column' => [
                'padding' => 0,
            ],
            'ul' => [
                'list-style-type' => 'disc',
            ],
            'img' => [
                'width'        => '100%',
                'height'       => 'auto',
                'display'      => 'block',
                'border-style' => 'none',
            ],
            'a' => [
                'text-decoration' => 'underline',
            ],
        ];
    }
}
