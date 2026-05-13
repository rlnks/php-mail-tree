<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Anchor;
use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\RawHtml;
use Rlnks\MailTree\StyleSheet;

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
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw = $containerWidth ?: ($sheet?->containerWidth() ?? 600);

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
                'border'           => 'none',
                'border-collapse'  => 'collapse',
                'mso-table-lspace' => '0pt',
                'mso-table-rspace' => '0pt',
            ],
        ]);
        $c->setClass('devicewidth');

        $col = new Column(['column' => ['padding' => '0', 'text-align' => 'center']]);
        $c->col = $col;

        // Thumbnail wrapped in a link
        $link = new Anchor($videoUrl, ['a' => ['display' => 'block', 'border' => '0']]);
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

        // Play button overlay as a table with negative margin — mso-hide:all hides it from Outlook
        // Using a table instead of a div so no layout-div is introduced
        $videoUrlEsc     = htmlspecialchars($videoUrl, ENT_QUOTES);
        $playAnchorStyle = implode(';', [
            'display:inline-block',
            'width:64px',
            'height:64px',
            'line-height:64px',
            'border-radius:50%',
            'background-color:' . $playBg,
            'color:' . $playColor,
            'font-size:28px',
            'text-align:center',
            'text-decoration:none',
            'border:3px solid ' . $playColor,
            'mso-hide:all',
        ]);
        $col->play = RawHtml::make(
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"'
            . ' style="border-collapse:collapse;margin:-32px auto 32px;mso-hide:all;">'
            . '<tbody><tr><td style="text-align:center;">'
            . '<a href="' . $videoUrlEsc . '" target="_blank" style="' . $playAnchorStyle . '">&#9654;</a>'
            . '</td></tr></tbody></table>'
        );

        if ($caption !== '') {
            $captionStyle = implode(';', [
                'text-align:center',
                'color:' . ($sheet?->textColor()  ?? '#555555'),
                'font-family:' . ($sheet?->fontFamily() ?? 'Arial, sans-serif'),
                'font-size:13px',
                'padding:10px 0 0 0',
            ]);
            $col->caption = RawHtml::make(
                '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;width:100%;">'
                . '<tbody><tr><td style="' . $captionStyle . '">' . $caption . '</td></tr></tbody>'
                . '</table>'
            );
        }

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        $c->setPreset('VideoBlock');
        return $c;
    }
}
