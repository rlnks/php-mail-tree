<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * A full-width horizontal rule.
 *
 * Best practices applied:
 *   - border-bottom on the <td> rather than <hr> — max client compatibility.
 *   - font-size: 0; line-height: 0; height: 0 collapse the cell height to 1px.
 *   - mso-line-height-rule: exactly prevents Outlook from inflating the cell.
 *   - Optional top/bottom padding creates breathing room without a second spacer.
 *
 * Usage:
 *   $divider = Divider::make();
 *   $divider = Divider::make(color: '#dddddd', paddingY: '20px');
 *   $divider = Divider::make(sheet: $sheet);
 */
class Divider
{
    public static function make(
        string      $color    = '',
        string      $width    = '1px',
        string      $paddingY = '0',
        ?StyleSheet $sheet    = null,
    ): Container {
        $color = $color ?: ($sheet?->borderColor() ?? '#dddddd');

        $colStyle = [
            'border-bottom'        => "{$width} solid {$color}",
            'height'               => '0',
            'font-size'            => '0',
            'line-height'          => '0',
            'mso-line-height-rule' => 'exactly',
        ];
        if ($paddingY !== '0') {
            $colStyle['padding-top']    = $paddingY;
            $colStyle['padding-bottom'] = $paddingY;
        }

        $c = new Container([
            'container' => [
                'border'           => 'none',
                'background-color' => 'transparent',
                'border-collapse'  => 'collapse',
            ],
        ]);
        $c->col = new Column(['column' => $colStyle]);
        $c->col->space = new Text('&nbsp;');

        return $c;
    }
}
