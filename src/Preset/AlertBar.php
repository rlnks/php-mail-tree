<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Full-width alert bar in four semantic variants.
 *
 * Structure:
 *   Container (full-width, devicewidth)
 *     lmargin : Column (marginWidth)
 *     body    : Column — icon + message inline
 *     rmargin : Column (marginWidth)
 *
 * Variants and their default colors:
 *   info    — blue    bg:#e3f2fd  border:#1565c0  text:#0d47a1
 *   success — green   bg:#e8f5e9  border:#2e7d32  text:#1b5e20
 *   warning — amber   bg:#fff8e1  border:#f57f17  text:#e65100
 *   error   — red     bg:#ffebee  border:#c62828  text:#b71c1c
 *
 * Usage:
 *   $alert = AlertBar::make('Your order has been confirmed!', 'success');
 *   $alert = AlertBar::make('{{alert_message}}', 'warning', icon: '⚠️', sheet: $sheet);
 *   $email->body->alert = AlertBar::make('Payment failed.', 'error');
 */
class AlertBar
{
    private const VARIANTS = [
        'info'    => ['bg' => '#e3f2fd', 'border' => '#1565c0', 'text' => '#0d47a1', 'icon' => 'ℹ️'],
        'success' => ['bg' => '#e8f5e9', 'border' => '#2e7d32', 'text' => '#1b5e20', 'icon' => '✅'],
        'warning' => ['bg' => '#fff8e1', 'border' => '#f57f17', 'text' => '#e65100', 'icon' => '⚠️'],
        'error'   => ['bg' => '#ffebee', 'border' => '#c62828', 'text' => '#b71c1c', 'icon' => '❌'],
    ];

    public static function make(
        string      $message        = '',
        string      $type           = 'info',
        string      $icon           = '',
        bool        $showIcon       = true,
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $bw = $cw - $mw * 2;

        $variant   = self::VARIANTS[$type] ?? self::VARIANTS['info'];
        $iconText  = $icon ?: ($showIcon ? $variant['icon'] : '');
        $content   = $iconText !== '' ? "{$iconText}&nbsp;&nbsp;{$message}" : $message;

        $c = new Container([
            'container' => [
                'width'            => "{$cw}px",
                'max-width'        => "{$cw}px",
                'background-color' => $variant['bg'],
                'border-top'       => "3px solid {$variant['border']}",
                'border-bottom'    => "3px solid {$variant['border']}",
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
                'padding'    => '14px 0',
                'color'      => $variant['text'],
                'font-weight'=> 'bold',
            ],
        ]);
        $c->body->setClass('section-body');
        $c->body->message = new Text($content, 'p', [
            'p' => [
                'color'       => $variant['text'],
                'font-weight' => 'bold',
                'margin'      => '0',
            ],
        ]);
        $c->rmargin = deepclone($margin);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        return $c;
    }
}
