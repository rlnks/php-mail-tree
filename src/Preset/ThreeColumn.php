<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * A responsive three-column layout that stacks on mobile.
 *
 * Structure (desktop — 600px):
 *   Container (600px, devicewidth)
 *     lmargin : Column (30px)  — left gutter
 *     col1    : Column (180px, col-3) — first column
 *     col2    : Column (180px, col-3) — second column
 *     col3    : Column (180px, col-3) — third column
 *     rmargin : Column (30px)  — right gutter
 *
 * 30 + 180 + 180 + 180 + 30 = 600px ✓
 *
 * On mobile (≤ breakpoint):
 *   td[class~="col-3"] { display:block; width:100%; }
 *   → all three columns stack vertically, each full width.
 *
 * Best practices:
 *   - Integer pixel widths prevent sub-pixel rendering discrepancies.
 *   - If container is not divisible by 3 after margins, extra pixels go to col1
 *     (floor + remainder distribution avoids Outlook table overflow).
 *   - Gutters between columns: use column-level padding via setStyle(), e.g.
 *     $row->col1->setStyle(['column' => ['padding-right' => '10px']]).
 *
 * Usage:
 *   $row = ThreeColumn::make();
 *   $row->col1->img  = new Image($src1, '');
 *   $row->col2->img  = new Image($src2, '');
 *   $row->col3->img  = new Image($src3, '');
 *
 *   // With a StyleSheet:
 *   $row = ThreeColumn::make(sheet: $sheet);
 */
class ThreeColumn
{
    public static function make(
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw       = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw       = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $inner    = $cw - $mw * 2;
        $baseColW = (int) floor($inner / 3);
        $remainder = $inner - $baseColW * 3;         // 0, 1, or 2 px remainder
        $col1W    = $baseColW + $remainder;           // col1 absorbs remainder
        $col23W   = $baseColW;

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
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
        $c->col1    = new Column(['column' => ['width' => "{$col1W}px",  'max-width' => "{$col1W}px"]]);
        $c->col1->setClass('col-3');
        $c->col2    = new Column(['column' => ['width' => "{$col23W}px", 'max-width' => "{$col23W}px"]]);
        $c->col2->setClass('col-3');
        $c->col3    = new Column(['column' => ['width' => "{$col23W}px", 'max-width' => "{$col23W}px"]]);
        $c->col3->setClass('col-3');
        $c->rmargin = deepclone($margin);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
