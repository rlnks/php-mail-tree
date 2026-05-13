<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * A responsive N-column layout that stacks on mobile.
 *
 * Structure (desktop):
 *   Container (containerWidth, devicewidth)
 *     lmargin : Column (marginWidth)
 *     col1    : Column (colWidth, col-N) — 1-indexed
 *     col2    : Column (colWidth, col-N)
 *     ...
 *     colN    : Column (colWidth, col-N)
 *     rmargin : Column (marginWidth)
 *
 * Columns are named col1, col2, … colN. On mobile they stack full-width via
 * StyleSheet::responsiveCss() which generates td[class~="col-N"] rules.
 *
 * $widths is an optional array of integers that express each column's share
 * of the inner width as a percentage (e.g. [40, 60] → 40%/60%). If omitted,
 * columns are divided equally. Values do not need to sum to exactly 100 —
 * the remainder (rounding dust) is added to the first column.
 *
 * Usage:
 *   $row = NColumn::make(3, sheet: $sheet);
 *   $row->col1->title = new Text('Feature A', 'h3');
 *   $row->col2->title = new Text('Feature B', 'h3');
 *   $row->col3->title = new Text('Feature C', 'h3');
 *
 *   // Asymmetric 40/60 split:
 *   $row = NColumn::make(2, widths: [40, 60], sheet: $sheet);
 */
class NColumn
{
    public static function make(
        int         $columns        = 2,
        array       $widths         = [],
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $columns = max(1, $columns);
        $cw      = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw      = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $inner   = $cw - $mw * 2;

        $pixelWidths = static::resolveWidths($columns, $widths, $inner);

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

        $cssClass = "col-{$columns}";
        for ($i = 0; $i < $columns; $i++) {
            $w    = $pixelWidths[$i];
            $name = 'col' . ($i + 1);
            $col  = new Column(['column' => ['width' => "{$w}px", 'max-width' => "{$w}px"]]);
            $col->setClass($cssClass);
            $c->$name = $col;
        }

        $c->rmargin = deepclone($margin);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }

    private static function resolveWidths(int $columns, array $widths, int $inner): array
    {
        if (empty($widths)) {
            $base      = (int) floor($inner / $columns);
            $remainder = $inner - $base * $columns;
            $result    = array_fill(0, $columns, $base);
            $result[0] += $remainder;
            return $result;
        }

        // Treat provided values as percentage shares — normalise to sum=100 first
        $sum    = array_sum($widths);
        $result = [];
        $used   = 0;
        $count  = count($widths);
        for ($i = 0; $i < $count; $i++) {
            if ($i === $count - 1) {
                $result[] = $inner - $used;
            } else {
                $px      = (int) round($inner * $widths[$i] / $sum);
                $result[] = $px;
                $used    += $px;
            }
        }

        // Pad with equal-width columns if fewer widths than columns were given
        while (count($result) < $columns) {
            $result[] = (int) floor($inner / $columns);
        }

        return $result;
    }
}
