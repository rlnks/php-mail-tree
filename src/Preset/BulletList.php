<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;

/**
 * Cross-client ordered or unordered list.
 *
 * Email clients (especially Outlook) strip bullet symbols, ignore list-style-type,
 * and mangle padding on <ul>/<ol>. Safe approach: table with a narrow bullet cell
 * beside a text cell per item.
 *
 * Structure per item:
 *   <table role="presentation">
 *     <tr>
 *       <td style="…bullet cell…">• / 1.</td>
 *       <td style="…text cell…">Item text</td>
 *     </tr>
 *   </table>
 *
 * Items may contain raw HTML (bold, links, etc.).
 *
 * Usage:
 *   $list = BulletList::make(['First item', 'Second item', '<strong>Third</strong>']);
 *   $list = BulletList::make($items, ordered: true, bulletColor: '#003366');
 *   $list = BulletList::make($items, sheet: $sheet);
 *   $section->body->features = BulletList::make($items, sheet: $sheet);
 */
class BulletList implements Renderable
{
    private function __construct(
        private readonly array   $items,
        private readonly bool    $ordered,
        private readonly string  $bulletColor,
        private readonly string  $textColor,
        private readonly string  $fontFamily,
        private readonly string  $fontSize,
        private readonly string  $lineHeight,
        private readonly string  $itemSpacing,
        private readonly string  $bulletWidth,
    ) {}

    public static function make(
        array       $items        = [],
        bool        $ordered      = false,
        string      $bulletColor  = '',
        string      $textColor    = '',
        string      $fontFamily   = '',
        string      $fontSize     = '',
        string      $lineHeight   = '',
        string      $itemSpacing  = '8px',
        string      $bulletWidth  = '20px',
        ?StyleSheet $sheet        = null,
    ): static {
        return new static(
            items:       $items,
            ordered:     $ordered,
            bulletColor: $bulletColor ?: ($sheet?->primaryColor()  ?? '#333333'),
            textColor:   $textColor   ?: ($sheet?->textColor()     ?? '#444444'),
            fontFamily:  $fontFamily  ?: ($sheet?->fontFamily()    ?? "Arial, 'Helvetica Neue', Helvetica, sans-serif"),
            fontSize:    $fontSize    ?: ($sheet?->baseFontSize()  ?? '14px'),
            lineHeight:  $lineHeight  ?: ($sheet?->theme('lineHeight') ?? '150%'),
            itemSpacing: $itemSpacing,
            bulletWidth: $bulletWidth,
        );
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $t   = str_repeat("\t", $indent);
        $t1  = str_repeat("\t", $indent + 1);
        $t2  = str_repeat("\t", $indent + 2);
        $t3  = str_repeat("\t", $indent + 3);
        $t4  = str_repeat("\t", $indent + 4);

        $bulletCellStyle = implode(';', [
            'vertical-align:top',
            'width:' . $this->bulletWidth,
            'padding-right:6px',
            'color:' . $this->bulletColor,
            'font-family:' . $this->fontFamily,
            'font-size:' . $this->fontSize,
            'line-height:' . $this->lineHeight,
        ]);

        $textCellStyle = implode(';', [
            'vertical-align:top',
            'color:' . $this->textColor,
            'font-family:' . $this->fontFamily,
            'font-size:' . $this->fontSize,
            'line-height:' . $this->lineHeight,
        ]);

        $html = '';
        foreach ($this->items as $i => $item) {
            $bullet = $this->ordered ? ($i + 1) . '.' : '&bull;';

            $bottomPadding = ($i < count($this->items) - 1) ? $this->itemSpacing : '0';
            $rowBulletStyle = $bulletCellStyle . ';padding-bottom:' . $bottomPadding;
            $rowTextStyle   = $textCellStyle   . ';padding-bottom:' . $bottomPadding;

            $html .= "\n{$t}<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" style=\"border-collapse:collapse;\">";
            $html .= "\n{$t1}<tbody><tr>";
            $html .= "\n{$t2}<td style=\"{$rowBulletStyle}\">{$bullet}</td>";
            $html .= "\n{$t2}<td style=\"{$rowTextStyle}\">{$item}</td>";
            $html .= "\n{$t1}</tr></tbody>";
            $html .= "\n{$t}</table>";
        }

        return $html;
    }
}
