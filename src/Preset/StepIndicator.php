<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;

/**
 * Horizontal step-progress indicator for order tracking, onboarding, etc.
 *
 * Renders as an inline-table so each step stays on one line on desktop. On mobile
 * the steps stack because each step cell is set to display:block.
 *
 * Each step shows:
 *   — a circle with the step number (filled for completed/current, hollow for future)
 *   — a label below the circle
 *   — a connector line between circles (hidden on last step)
 *
 * $current is zero-based: 0 = first step active, 1 = second, etc.
 * Steps before $current are "completed", $current is "active", steps after are "upcoming".
 *
 * Usage:
 *   $steps = StepIndicator::make(
 *       steps:   ['Order placed', 'Processing', 'Shipped', 'Delivered'],
 *       current: 1,
 *       sheet:   $sheet,
 *   );
 *   $email->body->progress = $steps;
 */
class StepIndicator implements Renderable
{
    private function __construct(
        private readonly array   $steps,
        private readonly int     $current,
        private readonly string  $activeColor,
        private readonly string  $completedColor,
        private readonly string  $upcomingColor,
        private readonly string  $fontFamily,
        private readonly string  $fontSize,
    ) {}

    public static function make(
        array       $steps          = [],
        int         $current        = 0,
        string      $activeColor    = '',
        string      $completedColor = '',
        string      $upcomingColor  = '#cccccc',
        string      $fontFamily     = '',
        string      $fontSize       = '12px',
        ?StyleSheet $sheet          = null,
    ): static {
        return new static(
            steps:          $steps,
            current:        $current,
            activeColor:    $activeColor    ?: ($sheet?->primaryColor()  ?? '#003366'),
            completedColor: $completedColor ?: ($sheet?->primaryColor()  ?? '#003366'),
            upcomingColor:  $upcomingColor,
            fontFamily:     $fontFamily     ?: ($sheet?->fontFamily()    ?? "Arial, 'Helvetica Neue', Helvetica, sans-serif"),
            fontSize:       $fontSize,
        );
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $t  = str_repeat("\t", $indent);
        $t1 = str_repeat("\t", $indent + 1);
        $t2 = str_repeat("\t", $indent + 2);
        $t3 = str_repeat("\t", $indent + 3);

        $count = count($this->steps);
        if ($count === 0) {
            return '';
        }

        $html = "\n{$t}<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" align=\"center\" style=\"border-collapse:collapse;\">";
        $html .= "\n{$t1}<tbody><tr>";

        foreach ($this->steps as $i => $label) {
            $isCompleted = $i < $this->current;
            $isActive    = $i === $this->current;
            $isLast      = $i === $count - 1;

            $circleColor = match(true) {
                $isCompleted => $this->completedColor,
                $isActive    => $this->activeColor,
                default      => $this->upcomingColor,
            };
            $textColor = ($isCompleted || $isActive) ? $circleColor : '#999999';
            $circleBg  = ($isCompleted || $isActive) ? $circleColor : '#ffffff';
            $circleTxt = ($isCompleted || $isActive) ? '#ffffff' : '#999999';
            $number    = $isCompleted ? '&#x2713;' : (string) ($i + 1);

            $cellStyle = 'text-align:center;vertical-align:top;padding:0 0 0 0;';

            // Step cell
            $html .= "\n{$t2}<td style=\"{$cellStyle}\">";
            $html .= "\n{$t3}<div style=\"display:inline-block;\">";

            // Circle
            $circleStyle = implode(';', [
                'display:inline-block',
                'width:32px',
                'height:32px',
                'line-height:32px',
                'border-radius:50%',
                'background-color:' . $circleBg,
                'border:2px solid ' . $circleColor,
                'color:' . $circleTxt,
                'font-family:' . $this->fontFamily,
                'font-size:14px',
                'font-weight:bold',
                'text-align:center',
                'mso-line-height-rule:exactly',
            ]);
            $html .= "\n{$t3}<div style=\"{$circleStyle}\">{$number}</div>";

            // Label
            $labelStyle = implode(';', [
                'display:block',
                'margin-top:6px',
                'color:' . $textColor,
                'font-family:' . $this->fontFamily,
                'font-size:' . $this->fontSize,
                'white-space:nowrap',
            ]);
            $html .= "\n{$t3}<div style=\"{$labelStyle}\">{$label}</div>";
            $html .= "\n{$t3}</div>";
            $html .= "\n{$t2}</td>";

            // Connector line between steps
            if (!$isLast) {
                $lineColor    = $isCompleted ? $this->completedColor : $this->upcomingColor;
                $connStyle    = 'vertical-align:top;padding:16px 4px 0 4px;';
                $lineStyle    = "display:block;width:32px;height:2px;background-color:{$lineColor};";
                $html .= "\n{$t2}<td style=\"{$connStyle}\">";
                $html .= "\n{$t3}<div style=\"{$lineStyle}\"></div>";
                $html .= "\n{$t2}</td>";
            }
        }

        $html .= "\n{$t1}</tr></tbody>";
        $html .= "\n{$t}</table>";

        return $html;
    }
}
