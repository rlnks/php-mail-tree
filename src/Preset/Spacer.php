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
    public static function make(string $height = '', ?StyleSheet $sheet = null): Container
    {
        $h = $height ?: ($sheet?->theme('spacerHeight') ?? '20px');

        $c = new Container([
            'container' => [
                'border'           => 'none',
                'background-color' => 'transparent',
                'border-collapse'  => 'collapse',
            ],
        ]);

        $c->col = new Column([
            'column' => [
                'height'               => $h,
                'font-size'            => $h,
                'line-height'          => $h,
                'mso-line-height-rule' => 'exactly',
            ],
        ]);
        $c->col->space = new Text('&nbsp;');

        return $c;
    }
}
