<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * E-commerce product card: image + title + description + price + CTA button.
 *
 * Designed to be placed inside a TwoColumn, ThreeColumn, or NColumn cell so
 * multiple products can appear side by side on desktop and stack on mobile.
 * Can also stand alone inside a Section for a full-width featured product.
 *
 * Structure:
 *   Container (full-width within its parent cell)
 *     col : Column
 *             img    : Image
 *             title  : Text (h3)
 *             desc   : Text (div)
 *             price  : Text (div)
 *             cta    : Button (optional, only when ctaUrl is provided)
 *
 * Usage — inside a two-column layout:
 *   $row = TwoColumn::make(sheet: $sheet);
 *   $row->left  = ProductCard::make('…img…', 'Widget Pro',  '$9.99',  ctaUrl: '…', sheet: $sheet)->col;
 *   $row->right = ProductCard::make('…img…', 'Gadget Deluxe','$49.99', ctaUrl: '…', sheet: $sheet)->col;
 *
 * Usage — standalone featured product:
 *   $email->body->featured = ProductCard::make($src, $title, $price, ctaUrl: $url, sheet: $sheet);
 */
class ProductCard
{
    public static function make(
        string      $imageSrc    = '',
        string      $title       = '',
        string      $description = '',
        string      $price       = '',
        string      $ctaLabel    = 'Shop now',
        string      $ctaUrl      = '',
        string      $ctaBgColor  = '',
        string      $imageAlt       = '',
        int         $containerWidth = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw      = $containerWidth ?: 0;   // 0 = inherit from parent cell (width:100%)
        $primary = $ctaBgColor ?: ($sheet?->primaryColor() ?? '#333333');

        $c = new Container([
            'container' => [
                'border-collapse'  => 'collapse',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
                'width'            => $cw > 0 ? "{$cw}px" : '100%',
            ],
        ]);

        $col = new Column([
            'column' => [
                'padding'    => '0',
                'text-align' => 'left',
            ],
        ]);
        $c->col = $col;

        if ($imageSrc !== '') {
            $col->img = new Image($imageSrc, $imageAlt ?: $title, [
                'img' => [
                    'width'   => '100%',
                    'height'  => 'auto',
                    'display' => 'block',
                    'border'  => '0',
                    'margin'  => '0 0 12px 0',
                ],
            ]);
        }

        if ($title !== '') {
            $col->title = new Text($title, 'h3', [
                'h3' => [
                    'color'       => $sheet?->primaryColor() ?? '#333333',
                    'font-size'   => '16px',
                    'font-weight' => 'bold',
                    'margin'      => '0 0 8px 0',
                    'line-height' => '130%',
                ],
            ]);
        }

        if ($description !== '') {
            $col->desc = new Text($description, 'p', [
                'p' => [
                    'color'       => $sheet?->textColor() ?? '#555555',
                    'font-size'   => '13px',
                    'line-height' => '150%',
                    'margin'      => '0 0 10px 0',
                ],
            ]);
        }

        if ($price !== '') {
            $col->price = new Text($price, 'p', [
                'p' => [
                    'color'       => $sheet?->primaryColor() ?? '#333333',
                    'font-size'   => '20px',
                    'font-weight' => 'bold',
                    'margin'      => '0 0 12px 0',
                ],
            ]);
        }

        if ($ctaUrl !== '') {
            $col->cta = Button::make($ctaLabel, $ctaUrl, bgColor: $primary, sheet: $sheet);
        }

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
