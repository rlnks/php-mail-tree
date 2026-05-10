<?php

namespace Rlnks\MailTree\Preset;

use Rlnks\MailTree\Renderable;
use Rlnks\MailTree\StyleSheet;

/**
 * Row of social media icon links for email footers.
 *
 * Each icon is an <a>-wrapped <img>. Icons are sourced from either a user-provided
 * URL or a built-in placehold.co placeholder (swap with your CDN-hosted icons in
 * production).
 *
 * $links is an array of associative entries:
 *   ['platform' => 'facebook',  'url' => 'https://…']   — uses built-in icon
 *   ['platform' => 'custom',    'url' => '…', 'icon' => 'https://cdn/icon.png',  'alt' => 'Custom']
 *
 * Built-in platform keys (case-insensitive):
 *   facebook, twitter, instagram, linkedin, youtube, pinterest, tiktok, github
 *
 * Usage:
 *   $bar = SocialBar::make([
 *       ['platform' => 'facebook',  'url' => 'https://facebook.com/rlnks'],
 *       ['platform' => 'twitter',   'url' => 'https://twitter.com/rlnks'],
 *       ['platform' => 'instagram', 'url' => 'https://instagram.com/rlnks'],
 *   ], sheet: $sheet);
 *   $email->body->footer->body->social = $bar;
 */
class SocialBar implements Renderable
{
    // Built-in placeholder icons (replace with your own CDN URLs in production)
    private const BUILTIN_ICONS = [
        'facebook'  => ['color' => '1877F2', 'alt' => 'Facebook'],
        'twitter'   => ['color' => '1DA1F2', 'alt' => 'Twitter / X'],
        'instagram' => ['color' => 'E1306C', 'alt' => 'Instagram'],
        'linkedin'  => ['color' => '0A66C2', 'alt' => 'LinkedIn'],
        'youtube'   => ['color' => 'FF0000', 'alt' => 'YouTube'],
        'pinterest' => ['color' => 'E60023', 'alt' => 'Pinterest'],
        'tiktok'    => ['color' => '000000', 'alt' => 'TikTok'],
        'github'    => ['color' => '181717', 'alt' => 'GitHub'],
    ];

    private function __construct(
        private readonly array  $links,
        private readonly int    $iconSize,
        private readonly string $iconSpacing,
        private readonly string $align,
    ) {}

    public static function make(
        array       $links       = [],
        int         $iconSize    = 32,
        string      $iconSpacing = '8px',
        string      $align       = 'center',
        ?StyleSheet $sheet       = null,
    ): static {
        return new static(
            links:       $links,
            iconSize:    $iconSize,
            iconSpacing: $iconSpacing,
            align:       $align,
        );
    }

    public function build(array $style = [], int $indent = 0): string
    {
        if (empty($this->links)) {
            return '';
        }

        $t  = str_repeat("\t", $indent);
        $t1 = str_repeat("\t", $indent + 1);
        $t2 = str_repeat("\t", $indent + 2);
        $t3 = str_repeat("\t", $indent + 3);

        $tableStyle = "border-collapse:collapse;text-align:{$this->align};";
        $html = "\n{$t}<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" align=\"{$this->align}\" style=\"{$tableStyle}\">";
        $html .= "\n{$t1}<tbody><tr>";

        $size  = $this->iconSize;
        $count = count($this->links);

        foreach ($this->links as $i => $link) {
            $platform = strtolower($link['platform'] ?? 'custom');
            $url      = $link['url'] ?? '#';
            $isLast   = ($i === $count - 1);

            [$iconSrc, $alt] = $this->resolveIcon($platform, $link, $size);

            $paddingRight = $isLast ? '0' : $this->iconSpacing;
            $tdStyle      = "padding:0 {$paddingRight} 0 0;vertical-align:middle;";
            $aStyle       = "display:block;border:0;text-decoration:none;";
            $imgStyle     = "display:block;border:0;width:{$size}px;height:{$size}px;";

            $html .= "\n{$t2}<td style=\"{$tdStyle}\">";
            $html .= "\n{$t3}<a href=\"" . htmlspecialchars($url, ENT_QUOTES) . "\" target=\"_blank\" style=\"{$aStyle}\">";
            $html .= "\n{$t3}<img src=\"{$iconSrc}\" alt=\"" . htmlspecialchars($alt, ENT_QUOTES) . "\" width=\"{$size}\" height=\"{$size}\" style=\"{$imgStyle}\">";
            $html .= "\n{$t3}</a>";
            $html .= "\n{$t2}</td>";
        }

        $html .= "\n{$t1}</tr></tbody>";
        $html .= "\n{$t}</table>";

        return $html;
    }

    private function resolveIcon(string $platform, array $link, int $size): array
    {
        if (isset($link['icon'])) {
            return [$link['icon'], $link['alt'] ?? $platform];
        }

        if (isset(self::BUILTIN_ICONS[$platform])) {
            $meta  = self::BUILTIN_ICONS[$platform];
            $label = ucfirst($platform);
            // Simple monogram placeholder — replace with real CDN icons in production
            $src = "https://placehold.co/{$size}x{$size}/{$meta['color']}/FFFFFF?text=" . urlencode($label[0]);
            return [$src, $meta['alt']];
        }

        $label = ucfirst($platform);
        $src   = "https://placehold.co/{$size}x{$size}/888888/FFFFFF?text=" . urlencode($label[0]);
        return [$src, $label];
    }
}
