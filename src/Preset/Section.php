<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * A single-column content section with side margins.
 *
 * Structure:
 *   Container (600px, devicewidth)
 *     lmargin : Column (30px) — invisible left gutter
 *     body    : Column (540px, section-body class) — your content goes here
 *     rmargin : Column (30px) — invisible right gutter
 *
 * The `section-body` class is targeted by StyleSheet::responsiveCss() to
 * ensure the content column goes full-width on mobile.
 *
 * Best practices:
 *   - table-layout: fixed prevents cells from stretching beyond set widths.
 *   - margin columns use &nbsp; to stop clients from collapsing them.
 *   - Symmetric deepclone for rmargin ensures independent style mutations.
 *
 * Usage:
 *   $hero = Section::make();
 *   $hero->body->title = new Text('Hello!', 'h1');
 *   $hero->body->desc  = new Text('Body copy here.', 'div');
 *   $hero->body->cta   = Button::make('Start now', $url);
 *
 *   // With a StyleSheet:
 *   $section = Section::make(sheet: $sheet);
 *   $section->setStyle(['container' => ['background-color' => '#f5f5f5']]);
 */
class Section
{
    public static function make(
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $bw = $cw - $mw * 2;

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
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
                'width'     => "{$bw}px",
                'max-width' => "{$bw}px",
            ],
        ]);
        $c->body->setClass('section-body');
        $c->rmargin = deepclone($margin);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
