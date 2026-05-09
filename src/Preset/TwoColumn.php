<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * A responsive two-column layout that stacks on mobile.
 *
 * Structure (desktop — 600px):
 *   Container (600px, devicewidth)
 *     lmargin : Column (30px)  — left gutter
 *     left    : Column (270px, col-2) — left content
 *     right   : Column (270px, col-2) — right content
 *     rmargin : Column (30px)  — right gutter
 *
 * On mobile (≤ breakpoint):
 *   StyleSheet::responsiveCss() adds:
 *     td[class~="col-2"] { display:block; width:100%; }
 *   → left stacks above right, each full width.
 *
 * Best practices:
 *   - Equal column widths: (containerWidth − margin×2) / 2.
 *   - No gap column between left and right — add horizontal padding to the
 *     columns themselves via setStyle() if gutters are needed.
 *   - Columns use box-sizing: border-box in responsive CSS so padding stays
 *     within the 100% width when stacked.
 *   - table-layout: fixed on the container prevents column width negotiation.
 *
 * Usage:
 *   $row = TwoColumn::make();
 *   $row->left->img   = new Image($src1, $alt1);
 *   $row->right->title = new Text('Feature name', 'h2');
 *   $row->right->desc  = new Text('Description…', 'div');
 *
 *   // With a StyleSheet:
 *   $row = TwoColumn::make(sheet: $sheet);
 */
class TwoColumn
{
    public static function make(
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
    ): Container {
        $cw = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $cw_inner = $cw - $mw * 2;
        $colW     = (int) floor($cw_inner / 2);

        $containerBg = $sheet?->containerBg() ?? '#ffffff';

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
                'background-color' => $containerBg,
                'border-collapse'  => 'collapse',
                'table-layout'     => 'fixed',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ]);
        $c->setClass('devicewidth');

        $margin = new Column(['column' => ['width' => "{$mw}px"]]);
        $margin->space = new Text('&nbsp;');

        $c->lmargin = $margin;
        $c->left    = new Column(['column' => ['width' => "{$colW}px", 'max-width' => "{$colW}px"]]);
        $c->left->setClass('col-2');
        $c->right   = new Column(['column' => ['width' => "{$colW}px", 'max-width' => "{$colW}px"]]);
        $c->right->setClass('col-2');
        $c->rmargin = deepclone($margin);

        return $c;
    }
}
