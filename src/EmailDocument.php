<?php

namespace Rlnks\MailTree;

use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Translator;

class EmailDocument implements Renderable
{
    use HasChildren, HasStyle;

    private array       $style;
    private string      $subject    = '';
    private string      $preheader  = '';
    private string      $lang       = 'en';
    private array       $links      = [];
    private array       $headLinks  = [];
    private ?StyleSheet $sheet      = null;
    private ?Translator $translator = null;

    public function __construct(?StyleSheet $sheet = null, ?Translator $t = null)
    {
        $this->sheet      = $sheet;
        $sheet?->useAsDefault();
        $this->style      = array_replace_recursive($this->defaultStyle(), $sheet?->emailStyle() ?? []);
        $this->translator = $t;
    }

    public function setSubject(string $title): void
    {
        $this->subject = $title;
    }

    public function setPreheader(string $text): void
    {
        $this->preheader = $text;
    }

    /**
     * Set the BCP 47 language tag for the <html lang="…"> attribute.
     * Defaults to 'en'. Pass the same value you use for translation locales
     * ('fr', 'fr-CA', 'de', …). Locale underscores are normalised to hyphens
     * automatically ('fr_CA' → 'fr-CA').
     * When build() is called with a $locale argument that locale takes precedence.
     */
    public function setLang(string $lang): static
    {
        $this->lang = str_replace('_', '-', $lang);
        return $this;
    }

    public function addLink(string $href, string $rel): void
    {
        $this->links[] = ['href' => $href, 'rel' => $rel];
    }

    /**
     * Inject a raw HTML string into <head> (rendered after <link> tags).
     *
     * Use for elements that require attributes beyond href/rel — for example
     * crossorigin on preconnect links, integrity hashes, or Open Graph <meta>
     * tags. Web fonts registered via StyleSheet::addWebFont() are injected
     * automatically; only use this for anything beyond that.
     */
    public function addHeadLink(string $html): static
    {
        $this->headLinks[] = $html;
        return $this;
    }

    /**
     * Render the complete HTML document.
     *
     * Pass $t and $locale to resolve translation placeholders and process
     * **...** highlighting in the same pass — the cascade CSS context is
     * available simultaneously, so per-section highlight overrides work correctly.
     *
     * Without $t the output contains raw {{placeholders}} as before, which can
     * then be resolved separately via $t->resolve($base, $locale).
     */
    public function build(array $style = [], int $indent = 0, ?Translator $t = null, ?string $locale = null): string
    {
        $translator = $t ?? $this->translator;
        if ($translator !== null) {
            if ($locale !== null) {
                $translator->setLocale($locale);
            }
            $translator->useAsDefault();
        }

        // Push preheader down to the Body child so it renders inside <body>
        if ($this->preheader !== '') {
            foreach ($this->children as $child) {
                if ($child instanceof Body) {
                    $child->setPreheader($this->preheader);
                    break;
                }
            }
        }

        $mergedStyle = array_replace_recursive($style, $this->style);

        $i = $indent;

        $lang  = $locale !== null ? str_replace('_', '-', $locale) : $this->lang;

        $html  = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="' . htmlspecialchars($lang, ENT_QUOTES) . '">' . "\n";
        $html .= str_repeat("\t", $i + 1) . '<head>' . "\n";
        $html .= str_repeat("\t", $i + 2) . '<meta charset="UTF-8">' . "\n";
        $html .= str_repeat("\t", $i + 2) . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $subject = Translator::getDefault()?->resolve($this->subject) ?? $this->subject;
        $html .= str_repeat("\t", $i + 2) . '<title>' . htmlspecialchars($subject, ENT_QUOTES) . '</title>';

        foreach ($this->links as $link) {
            $html .= "\n" . str_repeat("\t", $i + 2) . '<link href="' . $link['href'] . '" rel="' . $link['rel'] . '">';
        }

        // Web font <link> tags (preconnect + stylesheet) from StyleSheet::addWebFont()
        if ($this->sheet !== null) {
            $fontLinks = $this->sheet->webFontLinks();
            if ($fontLinks !== '') {
                foreach (explode("\n", $fontLinks) as $link) {
                    if ($link !== '') {
                        $html .= "\n" . str_repeat("\t", $i + 2) . $link;
                    }
                }
            }
        }

        foreach ($this->headLinks as $link) {
            $html .= "\n" . str_repeat("\t", $i + 2) . $link;
        }

        $html .= "\n" . str_repeat("\t", $i + 1) . '</head>';
        $html .= $this->renderChildren($mergedStyle, $i + 1);
        $html .= "\n" . '</html>';

        return $html;
    }

    /**
     * Generate a plain-text alternative from the rendered HTML.
     *
     * Suitable for the text/plain part of a multipart email. Links are preserved
     * as "label ( url )", images as "[alt]". Block elements become line breaks.
     * Accepts the same optional $t / $locale as build() so both versions are
     * produced in the same translator pass.
     */
    /**
     * Generate a plain-text alternative from the rendered HTML.
     *
     * Suitable for the text/plain part of a multipart email. Links are preserved
     * as "label ( url )", images as "[alt]". Block elements become line breaks.
     * Accepts the same optional $t / $locale as build() so both versions are
     * produced in the same translator pass.
     */
    public function buildText(array $style = [], ?Translator $t = null, ?string $locale = null): string
    {
        $html = $this->build($style, 0, $t, $locale);

        // Remove non-visible sections entirely (content would pollute plain text)
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);

        // Remove display:none elements (preheader div, tracking wrappers, etc.)
        $html = preg_replace('/<\w+[^>]+display\s*:\s*none[^>]*>.*?<\/\w+>/si', '', $html);

        // Remove HTML comments including Outlook conditionals
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Block boundaries → newlines (opening and closing tags)
        $html = preg_replace('/<(?:p|div|br\s*\/?|h[1-6]|tr|td|li)[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/(?:p|div|h[1-6]|td|li)[^>]*>/i', "\n", $html);

        // Links → "label ( url )", skip anchors with no real destination (#, empty)
        $html = preg_replace_callback(
            '/<a[^>]+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si',
            function (array $m): string {
                $href  = trim($m[1]);
                $label = trim(strip_tags($m[2]));
                if ($href === '' || $href === '#') {
                    return $label; // keep visible text, drop the useless URL
                }
                return $label !== '' ? $label . ' ( ' . $href . ' )' : $href;
            },
            $html,
        );

        // Images → "[alt]" or nothing
        $html = preg_replace_callback(
            '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i',
            fn(array $m): string => $m[1] !== '' ? '[' . $m[1] . ']' : '',
            $html,
        );

        $text = strip_tags($html);

        // Decode HTML entities (&amp; → &, &nbsp; → NBSP, etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove zero-width characters (decoded &zwnj;, &zwsp;, BOM, word-joiner…)
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text);

        // Normalize whitespace (treat decoded &nbsp; U+00A0 as regular space)
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\n[ \t]*/m', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Prepend subject — translator is already active from the build() call above
        $subject = Translator::getDefault()?->resolve($this->subject) ?? $this->subject;
        if ($subject !== '') {
            $text = $subject . "\n\n" . trim($text);
        }

        return trim($text);
    }

    private function defaultStyle(): array
    {
        return [
            'body' => [
                'background-color' => '#ffffff',
                'border'           => 'none',
                'margin'           => 0,
            ],
            'text' => [
                'font-family'              => "'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Helvetica, Arial, sans-serif",
                'font-size'                => '12px',
                'line-height'              => '100%',
                'color'                    => '#666666',
                '-webkit-text-size-adjust' => 'none',
                'font-style'               => 'normal',
                'font-weight'              => 'normal',
                'font-variant'             => 'normal',
                'text-indent'              => '0px',
                'text-decoration'          => 'none',
                'text-transform'           => 'none',
                'text-align'               => 'left',
                'letter-spacing'           => 'normal',
                'vertical-align'           => 'baseline',
                '-webkit-margin-before'    => 0,
                '-webkit-margin-after'     => 0,
            ],
            'container' => [
                'background-color' => '#ffffff',
                'border-collapse'  => 'collapse',
                'table-layout'     => 'fixed',
                'padding'          => 0,
                'border'           => 'none',
                'margin'           => 'auto',
                'width'            => '100%',
                'border-spacing'   => 0,
                'mso-cellspacing'  => 0,
            ],
            'column' => [
                'padding' => 0,
            ],
            'ul' => [
                'list-style-type' => 'disc',
            ],
            'img' => [
                'width'        => '100%',
                'height'       => 'auto',
                'display'      => 'block',
                'border-style' => 'none',
            ],
            'a' => [
                'text-decoration' => 'underline',
            ],
        ];
    }
}
