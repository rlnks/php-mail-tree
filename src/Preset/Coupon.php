<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\RawHtml;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Promotional coupon / promo code block.
 *
 * Structure:
 *   Container (containerWidth, devicewidth)
 *     lmargin : Column (marginWidth)
 *     body    : Column
 *                 description : Text — "Use this code for 20% off"
 *                 code        : Text — the actual code "SAVE20"
 *                 expiry      : Text — optional "Valid until Dec 31, 2025"
 *     rmargin : Column (marginWidth)
 *
 * The code cell uses a dashed border and monospace font, mimicking a paper coupon.
 * A subtle scissor character (✂) is prepended as a visual cut-line cue.
 *
 * Usage:
 *   $coupon = Coupon::make(
 *       code:        'SAVE20',
 *       description: 'Use this code at checkout for 20% off your order.',
 *       expiry:      'Valid until December 31, 2025.',
 *       sheet:       $sheet,
 *   );
 *   $email->body->promo = $coupon;
 */
class Coupon
{
    public static function make(
        string      $code           = '',
        string      $description    = '',
        string      $expiry         = '',
        string      $bgColor        = '#f7f9fc',
        string      $borderColor    = '',
        string      $codeColor      = '',
        string      $textColor      = '',
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw      = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw      = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $border  = $borderColor ?: ($sheet?->borderColor()   ?? '#bbbbbb');
        $primary = $codeColor   ?: ($sheet?->primaryColor()  ?? '#003366');
        $text    = $textColor   ?: ($sheet?->textColor()     ?? '#444444');

        // A 2px dashed border adds 4px (2px each side) to the rendered width.
        // Subtract it from the declared container width to stay within $cw.
        $outerW = $cw - 4;
        $bw     = $outerW - $mw * 2;

        $c = new Container([
            'container' => [
                'width'            => "{$outerW}px",
                'max-width'        => "{$outerW}px",
                'background-color' => $bgColor,
                'border'           => "2px dashed {$border}",
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
        $c->body    = new Column([
            'column' => [
                'width'      => "{$bw}px",
                'max-width'  => "{$bw}px",
                'padding'    => '20px 0',
                'text-align' => 'center',
            ],
        ]);
        $c->body->setClass('section-body');

        if ($description !== '') {
            $c->body->description = new Text($description, 'p', [
                'p' => [
                    'color'       => $text,
                    'font-size'   => '14px',
                    'margin'      => '0 0 14px 0',
                    'text-align'  => 'center',
                ],
            ]);
        }

        // Use a centered table instead of display:inline-block on a div —
        // inline-block on div is unreliable in Outlook.
        $codeStyle = implode(';', [
            'color:' . $primary,
            'font-size:28px',
            'font-weight:bold',
            'letter-spacing:4px',
            "font-family:'Courier New', Courier, monospace",
            'background-color:#ffffff',
            'border:1px solid ' . $border,
            'padding:10px 20px',
            'text-align:center',
        ]);
        $c->body->code = RawHtml::make(
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="border-collapse:collapse;margin:0 auto 0;">'
            . '<tbody><tr><td style="' . $codeStyle . '">' . htmlspecialchars($code, ENT_QUOTES) . '</td></tr></tbody>'
            . '</table>'
        );

        if ($expiry !== '') {
            $c->body->expiry = new Text($expiry, 'p', [
                'p' => [
                    'color'       => '#888888',
                    'font-size'   => '12px',
                    'margin'      => '12px 0 0 0',
                    'text-align'  => 'center',
                ],
            ]);
        }

        $c->rmargin = deepclone($margin);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
