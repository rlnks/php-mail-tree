<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Anchor;
use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\StyleSheet;

/**
 * A full-width image banner with optional link.
 *
 * Best practices applied:
 *   - No margin columns — image runs edge-to-edge inside the container.
 *   - width="600" attribute alongside CSS width:100% for Outlook compatibility
 *     (Outlook respects the HTML attribute, not max-width CSS).
 *   - max-width:100% ensures the image scales down on mobile without CSS.
 *   - display:block removes the 4px gap beneath inline images (baseline quirk).
 *   - border:0 neutralizes link-border in old email clients.
 *   - moz-do-not-send="true" (already set in Image::build) prevents Thunderbird
 *     from attaching the image as a file.
 *   - Wrap with MSO conditional so Outlook centres correctly at fixed width.
 *
 * Usage:
 *   $banner = FullWidthImage::make('https://…/hero.jpg', 'Hero banner');
 *   $banner = FullWidthImage::make($src, $alt, href: 'https://…', sheet: $sheet);
 */
class FullWidthImage
{
    public static function make(
        string      $src,
        string      $alt   = '',
        string      $href  = '',
        ?StyleSheet $sheet = null,
    ): Container {
        $containerWidth = $sheet?->containerWidth() ?? 600;

        $c = new Container([
            'container' => [
                'width'            => "{$containerWidth}px",
                'max-width'        => "{$containerWidth}px",
                'border'           => 'none',
                'border-collapse'  => 'collapse',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ]);
        $c->setClass('devicewidth');

        $imgStyle = [
            'img' => [
                'width'     => '100%',
                'max-width' => "{$containerWidth}px",
                'height'    => 'auto',
                'display'   => 'block',
                'border'    => '0',
            ],
        ];

        $col = new Column();
        $c->col = $col;

        if ($href !== '') {
            $col->link = new Anchor($href, ['a' => ['border' => '0', 'display' => 'block']]);
            $col->link->img = new Image($src, $alt, $imgStyle);
        } else {
            $col->img = new Image($src, $alt, $imgStyle);
        }

        return $c;
    }
}
