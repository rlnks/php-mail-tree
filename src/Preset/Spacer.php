<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Vertical whitespace between sections.
 *
 * Best practices applied:
 *   - font-size, line-height AND height all set to the same value so every
 *     email client (especially Outlook) renders the exact height requested.
 *   - mso-line-height-rule: exactly  prevents Outlook from adding extra space.
 *   - &nbsp; prevents some clients from collapsing a truly empty cell.
 *
 * Usage:
 *   $spacer = Spacer::make('20px');
 *   $frame->gap1 = Spacer::make('40px');
 *   $frame->gap2 = Spacer::make(sheet: $sheet);   // uses sheet's default height
 */
class Spacer
{
    public static function make(string $height = '', ?StyleSheet $sheet = null, string $bg = '', string $responsive = ''): Container
    {
        $sheet ??= StyleSheet::getDefault();
        $h  = $height ?: ($sheet?->theme('spacerHeight') ?? '20px');
        $cw = $sheet?->containerWidth() ?? 600;
        $bg = $bg ?: ($sheet?->spacerBg() ?? 'transparent');

        $c = new Container([
            'container' => [
                'width'            => '100%',
                'max-width'        => "{$cw}px",
                'border'           => 'none',
                'background-color' => $bg,
                'border-collapse'  => 'collapse',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ]);

        $c->col = new Column([
            'column' => [
                // width:100% overrides any cascaded parent column width so the
                // spacer adapts correctly when nested inside a narrow column.
                // Padding zeroed out so a parent column's cascade (e.g. Section
                // body padding:28px 0) doesn't inflate the spacer height.
                'width'                => '100%',
                'padding'              => '0',
                'height'               => $h,
                'font-size'            => $h,
                'line-height'          => $h,
                'mso-line-height-rule' => 'exactly',
            ],
        ]);
        $c->col->space = new Text('&nbsp;');

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        $c->setPreset('Spacer');
        return $c;
    }
}
