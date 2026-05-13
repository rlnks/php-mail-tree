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
 *
 * Tree structure is always: col → link (Anchor) → img (Image).
 * When href is empty the Anchor renders transparently (no <a> wrapper).
 * This means src, alt, and href can all be set after make():
 *
 * Usage:
 *   // Inline:
 *   $banner = FullWidthImage::make($src, $alt, href: $url);
 *
 *   // Skeleton-first (declare structure, fill content later):
 *   $email->body->hero = FullWidthImage::make();
 *   // … later …
 *   $email->body->hero->col->link->setLink($heroUrl);
 *   $email->body->hero->col->link->img->setSrc($heroSrc, $heroAlt);
 */
class FullWidthImage
{
    public static function make(
        string      $src   = '',
        string      $alt        = '',
        string      $href       = '',
        ?StyleSheet $sheet      = null,
        string      $responsive = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
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

        // Always create the link→img subtree so the structure is consistent
        // whether or not a link is used. Anchor with empty href renders transparently.
        $col->link      = new Anchor($href, ['a' => ['border' => '0', 'display' => 'block']]);
        $col->link->img = new Image($src, $alt, $imgStyle);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
