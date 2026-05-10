<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Anchor;
use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Video thumbnail with an overlaid play button that links to the video URL.
 *
 * Email clients cannot embed or play video. Best practice: show a thumbnail
 * image with a centered play button overlay. Clicking opens the video URL
 * in a browser.
 *
 * The play button is rendered as a centered table cell on top of the image
 * using a full-width anchor and a ▶ character in a styled circle. An optional
 * caption appears below.
 *
 * Structure:
 *   Container (containerWidth, devicewidth)
 *     col : Column
 *             link : Anchor (videoUrl)
 *                      img : Image (thumbnail)
 *             play : Text — centered play button circle
 *             caption : Text — optional caption
 *
 * Usage:
 *   $video = VideoBlock::make(
 *       videoUrl:      'https://www.youtube.com/watch?v=abc123',
 *       thumbnailSrc:  'https://img.youtube.com/vi/abc123/maxresdefault.jpg',
 *       thumbnailAlt:  'Watch our demo video',
 *       caption:       'See the framework in action — 2 min',
 *       sheet:         $sheet,
 *   );
 *   $email->body->demo = $video;
 */
class VideoBlock
{
    public static function make(
        string      $videoUrl      = '#',
        string      $thumbnailSrc  = '',
        string      $thumbnailAlt  = 'Watch video',
        string      $caption       = '',
        string      $playColor     = '#ffffff',
        string      $playBg        = 'rgba(0,0,0,0.6)',
        int         $containerWidth = 0,
        ?StyleSheet $sheet          = null,
    ): Container {
        $cw = $containerWidth ?: ($sheet?->containerWidth() ?? 600);

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
                'border'           => 'none',
                'border-collapse'  => 'collapse',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
                'position'         => 'relative',
            ],
        ]);
        $c->setClass('devicewidth');

        $col = new Column(['column' => ['padding' => '0', 'text-align' => 'center', 'position' => 'relative']]);
        $c->col = $col;

        // Thumbnail wrapped in a link
        $link = new Anchor($videoUrl, ['a' => ['display' => 'block', 'border' => '0', 'position' => 'relative']]);
        $link->img = new Image($thumbnailSrc, $thumbnailAlt, [
            'img' => [
                'width'     => '100%',
                'max-width' => "{$cw}px",
                'height'    => 'auto',
                'display'   => 'block',
                'border'    => '0',
            ],
        ]);
        $col->link = $link;

        // Play button overlay (centered using table alignment)
        // Rendered as a separate full-width link below the image in the same cell,
        // positioned absolutely via CSS (falls back to inline below on old clients)
        $col->play = new Text(
            '<a href="' . htmlspecialchars($videoUrl, ENT_QUOTES) . '" target="_blank" style="'
                . 'display:inline-block;'
                . 'width:64px;height:64px;line-height:64px;'
                . 'border-radius:50%;'
                . 'background-color:' . $playBg . ';'
                . 'color:' . $playColor . ';'
                . 'font-size:28px;text-align:center;text-decoration:none;'
                . 'border:3px solid ' . $playColor . ';'
                . 'mso-hide:all;'   // hide in Outlook (image+link is sufficient there)
            . '">&#9654;</a>',
            'div',
            [
                'div' => [
                    'text-align' => 'center',
                    'margin'     => '-52px 0 0 0',
                    'mso-hide'   => 'all',
                ],
            ]
        );

        if ($caption !== '') {
            $col->caption = new Text($caption, 'div', [
                'div' => [
                    'text-align'  => 'center',
                    'color'       => $sheet?->textColor()    ?? '#555555',
                    'font-family' => $sheet?->fontFamily()   ?? "Arial, sans-serif",
                    'font-size'   => '13px',
                    'padding'     => '10px 0 0 0',
                    'margin'      => '0',
                ],
            ]);
        }

        return $c;
    }
}
