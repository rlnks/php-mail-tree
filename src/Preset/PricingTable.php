<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\RawHtml;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;

/**
 * Pricing tiers table — N side-by-side pricing columns that stack on mobile.
 *
 * Each tier is an associative array:
 *   [
 *     'name'       => 'Pro',            // tier name
 *     'price'      => '$29',            // price string (may include HTML)
 *     'period'     => '/month',         // billing period label
 *     'features'   => ['Feature A', 'Feature B'], // list of features
 *     'cta_label'  => 'Get started',    // CTA button label
 *     'cta_url'    => 'https://…',      // CTA URL
 *     'highlight'  => true,             // optional: render with primaryColor bg
 *   ]
 *
 * Columns stack to full-width on mobile via col-N CSS class.
 *
 * Usage:
 *   $pricing = PricingTable::make([
 *       ['name' => 'Free',  'price' => '$0',  'period' => '/mo', 'features' => ['1 user', '5 projects'],  'cta_label' => 'Start free',  'cta_url' => '…'],
 *       ['name' => 'Pro',   'price' => '$29', 'period' => '/mo', 'features' => ['10 users', 'Unlimited'], 'cta_label' => 'Get Pro',     'cta_url' => '…', 'highlight' => true],
 *       ['name' => 'Enterprise', 'price' => 'Custom', 'period' => '', 'features' => ['Unlimited', 'SSO'], 'cta_label' => 'Contact us',  'cta_url' => '…'],
 *   ], sheet: $sheet);
 */
class PricingTable
{
    public static function make(
        array       $tiers          = [],
        int         $containerWidth = 0,
        int         $marginWidth    = 0,
        ?StyleSheet $sheet          = null,
        string      $responsive     = '',
    ): Container {
        $sheet ??= StyleSheet::getDefault();
        $cw    = $containerWidth ?: ($sheet?->containerWidth() ?? 600);
        $mw    = $marginWidth    ?: ($sheet?->marginWidth()    ?? 30);
        $inner = $cw - $mw * 2;
        $n     = max(1, count($tiers));
        $colW  = (int) floor($inner / $n);
        $extra = $inner - $colW * $n;

        $primary     = $sheet?->primaryColor() ?? '#003366';
        $containerBg = $sheet?->containerBg()  ?? '#ffffff';
        $border      = $sheet?->borderColor()  ?? '#dddddd';
        $textColor   = $sheet?->textColor()    ?? '#444444';
        $fontFamily  = $sheet?->fontFamily()   ?? "Arial, 'Helvetica Neue', Helvetica, sans-serif";

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

        $marginCol = new Column(['column' => ['width' => "{$mw}px"]]);
        $marginCol->space = new Text('&nbsp;');
        $c->lmargin = $marginCol;

        $cssClass = "col-{$n}";
        $hPad     = 32; // 2 × 16px horizontal padding applied to each column

        foreach ($tiers as $i => $tier) {
            $highlighted = !empty($tier['highlight']);
            // Subtract horizontal padding and border from the column CSS width so the
            // total rendered cell (content + padding + border) stays within the container.
            $borderPx    = $highlighted ? 0 : 2; // 1px border each side when not highlighted
            $w           = $colW + ($i === 0 ? $extra : 0) - $hPad - $borderPx;
            $colBg       = $highlighted ? $primary : $containerBg;
            $nameColor   = $highlighted ? '#ffffff' : $primary;
            $priceColor  = $highlighted ? '#ffffff' : $primary;
            $featColor   = $highlighted ? 'rgba(255,255,255,0.85)' : $textColor;
            $borderStyle = $highlighted ? 'none' : "1px solid {$border}";

            $col = new Column([
                'column' => [
                    'width'            => "{$w}px",
                    'max-width'        => "{$w}px",
                    'background-color' => $colBg,
                    'border'           => $borderStyle,
                    'padding'          => '24px 16px',
                    'vertical-align'   => 'top',
                    'text-align'       => 'center',
                ],
            ]);
            $col->setClass($cssClass);

            // Tier name
            $col->name = new Text($tier['name'] ?? '', 'p', [
                'p' => [
                    'color'         => $nameColor,
                    'font-family'   => $fontFamily,
                    'font-size'     => '13px',
                    'font-weight'   => 'bold',
                    'letter-spacing'=> '1px',
                    'text-transform'=> 'uppercase',
                    'margin'        => '0 0 12px 0',
                    'text-align'    => 'center',
                ],
            ]);

            // Price
            $priceText = ($tier['price'] ?? '') . (isset($tier['period']) && $tier['period'] !== '' ? '<span style="font-size:13px;font-weight:normal;">' . $tier['period'] . '</span>' : '');
            $col->price = new Text($priceText, 'p', [
                'p' => [
                    'color'       => $priceColor,
                    'font-family' => $fontFamily,
                    'font-size'   => '32px',
                    'font-weight' => 'bold',
                    'margin'      => '0 0 16px 0',
                    'line-height' => '120%',
                    'text-align'  => 'center',
                ],
            ]);

            // Feature list — rendered as a table to avoid div layout elements
            if (!empty($tier['features'])) {
                $borderC  = $highlighted ? 'rgba(255,255,255,0.2)' : $border;
                $featRows = '';
                foreach ($tier['features'] as $feat) {
                    $tdStyle = implode(';', [
                        'padding:5px 0',
                        'border-bottom:1px solid ' . $borderC,
                        'color:' . $featColor,
                        'font-family:' . $fontFamily,
                        'font-size:13px',
                        'text-align:center',
                    ]);
                    $featRows .= '<tr><td style="' . $tdStyle . '">' . htmlspecialchars($feat, ENT_QUOTES) . '</td></tr>';
                }
                $col->features = RawHtml::make(
                    '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;width:100%;margin:0 0 20px 0;">'
                    . '<tbody>' . $featRows . '</tbody>'
                    . '</table>'
                );
            }

            // CTA button — width matches the content area (column width after padding)
            if (!empty($tier['cta_url'])) {
                $btnBg    = $highlighted ? '#ffffff'  : $primary;
                $btnColor = $highlighted ? $primary   : '#ffffff';
                $col->cta = Button::make(
                    $tier['cta_label'] ?? 'Get started',
                    $tier['cta_url'],
                    bgColor:   $btnBg,
                    textColor: $btnColor,
                    width:     $w,
                    sheet:     $sheet,
                );
            }

            $name     = 'col' . ($i + 1);
            $c->$name = $col;
        }

        $c->rmargin = deepclone($marginCol);

        if ($responsive !== '') {
            $c->setResponsive($responsive);
        }

        $c->setPreset('PricingTable');
        return $c;
    }
}
