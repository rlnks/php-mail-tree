<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Testimonial / pull-quote block.
 *
 * Structure (with avatar):
 *   Container (containerWidth, devicewidth)
 *     lmargin : Column (marginWidth)
 *     body    : Column
 *                 quote  : Text — the quotation text
 *                 avatar : Image — optional round avatar (hidden if src='')
 *                 author : Text — name + role attribution
 *     rmargin : Column (marginWidth)
 *
 * A left-side accent bar is rendered via border-left on the body column.
 * The opening quotation mark is prepended inline to the quote text.
 *
 * Usage:
 *   $q = Quote::make(
 *       text:   '"This framework saved us hours of cross-client testing."',
 *       author: 'Jane Smith',
 *       role:   'Lead Developer, Acme Corp',
 *       sheet:  $sheet,
 *   );
 *
 *   // With avatar:
 *   $q = Quote::make(text: $text, author: $name, avatarSrc: $imgUrl, sheet: $sheet);
 */
class Quote
{
    public static function make(
        string      $text           = '',
        string      $author         = '',
        string      $role           = '',
        string      $avatarSrc      = '',
        string      $avatarAlt      = '',
        string      $accentColor    = '',
        string      $quoteColor     = '',
        string      $attributeColor = '',
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
    ): Container {
        $cw    = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw    = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $bw    = $cw - $mw * 2;
        $accent    = $accentColor    ?: ($sheet?->primaryColor() ?? '#003366');
        $qColor    = $quoteColor     ?: ($sheet?->textColor()    ?? '#333333');
        $attrColor = $attributeColor ?: ($sheet?->theme('textColor') ?? '#555555');

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
                'background-color' => $sheet?->containerBg() ?? '#ffffff',
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
                'width'         => "{$bw}px",
                'max-width'     => "{$bw}px",
                'padding'       => '20px 0 20px 20px',
                'border-left'   => "4px solid {$accent}",
            ],
        ]);
        $c->body->setClass('section-body');

        $c->body->quote = new Text(
            $text,
            'div',
            [
                'div' => [
                    'color'       => $qColor,
                    'font-style'  => 'italic',
                    'font-size'   => '17px',
                    'line-height' => '160%',
                    'margin'      => '0 0 16px 0',
                ],
            ]
        );

        if ($avatarSrc !== '') {
            $c->body->avatar = new Image($avatarSrc, $avatarAlt ?: $author, [
                'img' => [
                    'width'         => '48px',
                    'height'        => '48px',
                    'border-radius' => '50%',
                    'display'       => 'inline-block',
                    'margin'        => '0 10px 0 0',
                    'vertical-align'=> 'middle',
                ],
            ]);
        }

        $attribution = $author;
        if ($role !== '') {
            $attribution .= ', <em>' . $role . '</em>';
        }

        $c->body->attribution = new Text(
            $attribution,
            'div',
            [
                'div' => [
                    'color'       => $attrColor,
                    'font-size'   => '13px',
                    'font-weight' => 'bold',
                    'margin'      => '0',
                ],
            ]
        );

        $c->rmargin = deepclone($margin);

        return $c;
    }
}
