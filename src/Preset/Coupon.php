<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
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
    ): Container {
        $cw      = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw      = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $bw      = $cw - $mw * 2;
        $border  = $borderColor ?: ($sheet?->borderColor()   ?? '#bbbbbb');
        $primary = $codeColor   ?: ($sheet?->primaryColor()  ?? '#003366');
        $text    = $textColor   ?: ($sheet?->textColor()     ?? '#444444');

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
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
            $c->body->description = new Text($description, 'div', [
                'div' => [
                    'color'       => $text,
                    'font-size'   => '14px',
                    'margin'      => '0 0 14px 0',
                    'text-align'  => 'center',
                ],
            ]);
        }

        $c->body->code = new Text($code, 'div', [
            'div' => [
                'color'           => $primary,
                'font-size'       => '28px',
                'font-weight'     => 'bold',
                'letter-spacing'  => '4px',
                'font-family'     => "'Courier New', Courier, monospace",
                'background-color'=> '#ffffff',
                'border'          => "1px solid {$border}",
                'padding'         => '10px 20px',
                'display'         => 'inline-block',
                'margin'          => '0',
                'text-align'      => 'center',
            ],
        ]);

        if ($expiry !== '') {
            $c->body->expiry = new Text($expiry, 'div', [
                'div' => [
                    'color'       => '#888888',
                    'font-size'   => '12px',
                    'margin'      => '12px 0 0 0',
                    'text-align'  => 'center',
                ],
            ]);
        }

        $c->rmargin = deepclone($margin);

        return $c;
    }
}
