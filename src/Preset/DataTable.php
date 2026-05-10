<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;

/**
 * Cross-client data table for order summaries, invoices, and comparisons.
 *
 * Structure:
 *   <table role="presentation" width="100%">
 *     <thead> — optional header row with column labels
 *     <tbody> — alternating or uniform data rows
 *     <tfoot> — optional footer row (totals, grand total, etc.)
 *   </table>
 *
 * All cell styling is inline for maximum client compatibility.
 * Column count is derived from the $headers array; rows with fewer cells are
 * padded with empty cells so the table never breaks.
 *
 * Usage:
 *   $table = DataTable::make(
 *       headers: ['Product', 'Qty', 'Unit price', 'Total'],
 *       rows: [
 *           ['Widget Pro',   '2', '$9.99',  '$19.98'],
 *           ['Gadget Deluxe','1', '$49.99', '$49.99'],
 *       ],
 *       footer: ['', '', 'Order total', '<strong>$69.97</strong>'],
 *       sheet: $sheet,
 *   );
 *   $section->body->order = $table;
 */
class DataTable implements Renderable
{
    private function __construct(
        private readonly array   $headers,
        private readonly array   $rows,
        private readonly array   $footer,
        private readonly string  $headerBg,
        private readonly string  $headerColor,
        private readonly string  $rowBg,
        private readonly string  $altRowBg,
        private readonly string  $footerBg,
        private readonly string  $footerColor,
        private readonly string  $borderColor,
        private readonly string  $fontFamily,
        private readonly string  $fontSize,
        private readonly string  $cellPadding,
    ) {}

    public static function make(
        array       $headers     = [],
        array       $rows        = [],
        array       $footer      = [],
        string      $headerBg    = '',
        string      $headerColor = '#ffffff',
        string      $rowBg       = '#ffffff',
        string      $altRowBg    = '#f7f9fc',
        string      $footerBg    = '',
        string      $footerColor = '',
        string      $borderColor = '',
        string      $fontFamily  = '',
        string      $fontSize    = '',
        string      $cellPadding = '10px 12px',
        ?StyleSheet $sheet       = null,
    ): static {
        return new static(
            headers:     $headers,
            rows:        $rows,
            footer:      $footer,
            headerBg:    $headerBg    ?: ($sheet?->primaryColor()  ?? '#333333'),
            headerColor: $headerColor,
            rowBg:       $rowBg,
            altRowBg:    $altRowBg,
            footerBg:    $footerBg    ?: ($sheet?->theme('bgColor') ?? '#f0f0f0'),
            footerColor: $footerColor ?: ($sheet?->textColor()      ?? '#444444'),
            borderColor: $borderColor ?: ($sheet?->borderColor()    ?? '#dddddd'),
            fontFamily:  $fontFamily  ?: ($sheet?->fontFamily()     ?? "Arial, 'Helvetica Neue', Helvetica, sans-serif"),
            fontSize:    $fontSize    ?: ($sheet?->baseFontSize()   ?? '14px'),
            cellPadding: $cellPadding,
        );
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $t  = str_repeat("\t", $indent);
        $t1 = str_repeat("\t", $indent + 1);
        $t2 = str_repeat("\t", $indent + 2);
        $t3 = str_repeat("\t", $indent + 3);

        $colCount = max(1, count($this->headers) ?: (count($this->rows[0] ?? [1])));
        $border   = "1px solid {$this->borderColor}";

        $tableStyle = implode(';', [
            'width:100%',
            'border-collapse:collapse',
            'font-family:' . $this->fontFamily,
            'font-size:' . $this->fontSize,
        ]);

        $html = "\n{$t}<table role=\"presentation\" width=\"100%\" style=\"{$tableStyle}\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";

        // Header
        if ($this->headers) {
            $thStyle = implode(';', [
                'background-color:' . $this->headerBg,
                'color:' . $this->headerColor,
                'font-weight:bold',
                'padding:' . $this->cellPadding,
                'border-bottom:' . $border,
                'text-align:left',
            ]);
            $html .= "\n{$t1}<thead>\n{$t2}<tr>";
            foreach ($this->pad($this->headers, $colCount) as $h) {
                $html .= "\n{$t3}<th style=\"{$thStyle}\">" . $h . '</th>';
            }
            $html .= "\n{$t2}</tr>\n{$t1}</thead>";
        }

        // Body rows
        if ($this->rows) {
            $html .= "\n{$t1}<tbody>";
            foreach ($this->rows as $i => $row) {
                $bg     = ($i % 2 === 1) ? $this->altRowBg : $this->rowBg;
                $tdBase = implode(';', [
                    'background-color:' . $bg,
                    'padding:' . $this->cellPadding,
                    'border-bottom:' . $border,
                    'color:' . $this->footerColor,
                    'vertical-align:top',
                ]);
                $html .= "\n{$t2}<tr>";
                foreach ($this->pad($row, $colCount) as $cell) {
                    $html .= "\n{$t3}<td style=\"{$tdBase}\">" . $cell . '</td>';
                }
                $html .= "\n{$t2}</tr>";
            }
            $html .= "\n{$t1}</tbody>";
        }

        // Footer
        if ($this->footer) {
            $tfStyle = implode(';', [
                'background-color:' . $this->footerBg,
                'color:' . $this->footerColor,
                'font-weight:bold',
                'padding:' . $this->cellPadding,
                'text-align:left',
                'vertical-align:top',
            ]);
            $html .= "\n{$t1}<tfoot>\n{$t2}<tr>";
            foreach ($this->pad($this->footer, $colCount) as $cell) {
                $html .= "\n{$t3}<td style=\"{$tfStyle}\">" . $cell . '</td>';
            }
            $html .= "\n{$t2}</tr>\n{$t1}</tfoot>";
        }

        $html .= "\n{$t}</table>";
        return $html;
    }

    /** Pad/trim an array to exactly $length cells. */
    private function pad(array $arr, int $length): array
    {
        $arr = array_values($arr);
        while (count($arr) < $length) {
            $arr[] = '&nbsp;';
        }
        return array_slice($arr, 0, $length);
    }
}
