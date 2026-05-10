<?php

namespace Rlnks\MailTree;

/**
 * HTML → MailTree PHP code converter.
 *
 * Parses an existing HTML email and generates a ready-to-run PHP file that
 * recreates the same structure using the MailTree framework. The output is
 * a "90% done" starting point — complex CSS or non-standard HTML may need
 * manual adjustment.
 *
 * What the importer detects:
 *   — Document-level: DOCTYPE, charset, lang, title, preheader div
 *   — Colours: background, container bg, primary colour (from headings/links)
 *   — Typography: font-family, base font-size
 *   — Container width: largest table width found in the document
 *   — Sections: groups of consecutive top-level table rows
 *   — Per-section layout: single-column, two-column, three-column
 *   — Content types: headings (h1–h3), paragraphs/divs, images, links, buttons
 *   — Dividers: <hr> and border-bottom styled cells
 *   — Spacers: empty cells with a height attribute or height CSS property
 *   — Lists: <ul>/<ol> elements
 *
 * Usage:
 *   $php = HtmlImporter::convert(file_get_contents('legacy_email.html'));
 *   file_put_contents('rebuilt_email.php', $php);
 *
 *   // With options:
 *   $php = HtmlImporter::convert($html, namespace: 'MyApp\\Mail', outputFile: 'output.html');
 */
class HtmlImporter
{
    // ── Public API ────────────────────────────────────────────────────────────

    public static function convert(
        string $html,
        string $namespace  = '',
        string $outputFile = 'output.html',
    ): string {
        $importer = new self($html);
        return $importer->generate($namespace, $outputFile);
    }

    // ── Internal state ────────────────────────────────────────────────────────

    private \DOMDocument $dom;
    private \DOMXPath    $xpath;
    private array        $theme    = [];
    private array        $sections = [];
    private array        $images   = [];
    private array        $links    = [];

    private function __construct(string $html)
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Prepend XML encoding declaration so DOMDocument handles UTF-8 correctly
        // without the deprecated mb_convert_encoding(…, 'HTML-ENTITIES') approach.
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $this->xpath = new \DOMXPath($this->dom);

        $this->extractTheme();
        $this->extractSections();
    }

    // ── Theme extraction ──────────────────────────────────────────────────────

    private function extractTheme(): void
    {
        // Background colour (body bg or outer table bg)
        $body     = $this->xpath->query('//body')->item(0);
        $bodyBg   = $body ? $this->cssValue($this->attr($body, 'style'), 'background-color') : '';
        if (!$bodyBg) {
            $outerTable = $this->xpath->query('//table[1]')->item(0);
            $bodyBg     = $outerTable ? $this->cssValue($this->attr($outerTable, 'style'), 'background-color') : '';
        }

        // Container bg (first inner table with an explicit background)
        $containerBg = '';
        $allTables   = $this->xpath->query('//table');
        foreach ($allTables as $tbl) {
            $bg = $this->cssValue($this->attr($tbl, 'style'), 'background-color');
            if ($bg && $bg !== $bodyBg) {
                $containerBg = $bg;
                break;
            }
        }

        // Primary colour: first heading or first link colour
        $primaryColor = '';
        foreach (['//h1', '//h2', '//a'] as $query) {
            $node = $this->xpath->query($query)->item(0);
            if ($node) {
                $c = $this->cssValue($this->attr($node, 'style'), 'color');
                if ($c) { $primaryColor = $c; break; }
            }
        }

        // Font family: from body style or first text node
        $fontFamily = $this->cssValue($this->attr($body, 'style'), 'font-family');
        if (!$fontFamily) {
            foreach ($this->xpath->query('//td') as $td) {
                $f = $this->cssValue($this->attr($td, 'style'), 'font-family');
                if ($f) { $fontFamily = $f; break; }
            }
        }

        // Base font size
        $baseFontSize = '';
        foreach ($this->xpath->query('//td') as $td) {
            $f = $this->cssValue($this->attr($td, 'style'), 'font-size');
            if ($f && $f !== '0' && $f !== '1px') { $baseFontSize = $f; break; }
        }

        // Container width: largest table width attribute or CSS
        $containerWidth = 600;
        $maxW = 0;
        foreach ($allTables as $tbl) {
            $wAttr = (int) $this->attr($tbl, 'width');
            $wCss  = (int) preg_replace('/[^0-9]/', '', $this->cssValue($this->attr($tbl, 'style'), 'width') ?? '');
            $w     = max($wAttr, $wCss);
            if ($w > $maxW) { $maxW = $w; }
        }
        if ($maxW >= 400 && $maxW <= 900) {
            $containerWidth = $maxW;
        }

        $this->theme = [
            'bgColor'        => $bodyBg        ?: '#f0f0f0',
            'containerBg'    => $containerBg   ?: '#ffffff',
            'primaryColor'   => $primaryColor  ?: '#333333',
            'fontFamily'     => $fontFamily    ? "'{$fontFamily}'" : "Arial, 'Helvetica Neue', Helvetica, sans-serif",
            'baseFontSize'   => $baseFontSize  ?: '14px',
            'containerWidth' => $containerWidth,
        ];
    }

    // ── Section extraction ────────────────────────────────────────────────────

    private function extractSections(): void
    {
        // Top-level rows are the structural sections of the email
        // Strategy: find all <tr> elements that are direct children of a top-level <table>
        $topTable = $this->findOuterContainer();
        if (!$topTable) {
            $this->sections = [['type' => 'unknown', 'raw' => $this->dom->saveHTML()]];
            return;
        }

        $rows = $this->xpath->query('.//tr', $topTable);
        foreach ($rows as $row) {
            $this->sections[] = $this->analyseRow($row);
        }
    }

    private function findOuterContainer(): ?\DOMNode
    {
        // The outermost table that has the container width
        $cw     = $this->theme['containerWidth'];
        $tables = $this->xpath->query('//table');
        foreach ($tables as $tbl) {
            $w = (int) $this->attr($tbl, 'width')
                ?: (int) preg_replace('/[^0-9]/', '', $this->cssValue($this->attr($tbl, 'style'), 'max-width') ?? '');
            if ($w === $cw || abs($w - $cw) <= 5) {
                return $tbl;
            }
        }
        // Fallback: first table
        return $this->xpath->query('//table')->item(0);
    }

    private function analyseRow(\DOMNode $row): array
    {
        $tds = [];
        foreach ($row->childNodes as $child) {
            if ($child->nodeName === 'td') {
                $tds[] = $child;
            }
        }

        $colCount = count($tds);

        // Single TD → check if it's a spacer, divider, or content section
        if ($colCount === 1) {
            return $this->analyseSingleCell($tds[0]);
        }

        // Multiple TDs → multi-column layout
        return [
            'type'    => 'columns',
            'columns' => $colCount,
            'cells'   => array_map(fn($td) => $this->extractCellContent($td), $tds),
        ];
    }

    private function analyseSingleCell(\DOMNode $td): array
    {
        $style  = $this->attr($td, 'style');
        $height = $this->attr($td, 'height')
            ?: preg_replace('/[^0-9]/', '', $this->cssValue($style, 'height') ?? '');
        $text = trim($td->textContent ?? '');

        // Spacer: empty or &nbsp; cell with explicit height
        if (($text === '' || $text === "\xc2\xa0") && $height) {
            return ['type' => 'spacer', 'height' => $height . 'px'];
        }

        // Divider: border-bottom style or <hr> inside
        if ($this->cssValue($style, 'border-bottom')
            || $this->xpath->query('.//hr', $td)->length > 0) {
            $color = $this->extractBorderColor($style);
            return ['type' => 'divider', 'color' => $color];
        }

        // Nested table with multiple columns → nested section
        $innerTables = $this->xpath->query('.//table', $td);
        if ($innerTables->length > 0) {
            $innerRow = $this->xpath->query('.//tr', $innerTables->item(0))->item(0);
            if ($innerRow) {
                return $this->analyseRow($innerRow);
            }
        }

        // Regular content section
        return [
            'type'    => 'section',
            'content' => $this->extractCellContent($td),
            'bg'      => $this->cssValue($style, 'background-color') ?: '',
        ];
    }

    private function extractCellContent(\DOMNode $td): array
    {
        $items = [];
        foreach ($td->childNodes as $node) {
            $item = $this->parseNode($node);
            if ($item !== null) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private function parseNode(\DOMNode $node): ?array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent ?? '');
            return $text !== '' ? ['type' => 'text', 'tag' => 'span', 'content' => htmlspecialchars($text, ENT_QUOTES)] : null;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $tag   = strtolower($node->nodeName);
        $style = $this->attr($node, 'style');

        switch ($tag) {
            case 'img':
                $src = $this->attr($node, 'src');
                $alt = $this->attr($node, 'alt');
                $w   = $this->attr($node, 'width') ?: $this->cssValue($style, 'width');
                $this->images[] = ['src' => $src, 'alt' => $alt, 'width' => $w];
                return ['type' => 'image', 'src' => $src, 'alt' => $alt, 'width' => $w];

            case 'a':
                $href    = $this->attr($node, 'href');
                $bg      = $this->cssValue($style, 'background-color');
                $display = $this->cssValue($style, 'display');
                $isBtn   = ($bg && $bg !== 'transparent' && ($display === 'inline-block' || $display === 'block'));
                $text    = trim($node->textContent ?? '');
                $this->links[] = ['href' => $href, 'text' => $text];
                if ($isBtn) {
                    return ['type' => 'button', 'label' => $text, 'href' => $href, 'bg' => $bg];
                }
                return ['type' => 'link', 'href' => $href, 'text' => $text, 'style' => $style];

            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                return ['type' => 'heading', 'tag' => $tag, 'content' => $this->innerHtml($node), 'style' => $style];

            case 'p': case 'div': case 'span':
                $inner = $this->innerHtml($node);
                if (trim(strip_tags($inner)) === '') {
                    return null;
                }
                return ['type' => 'text', 'tag' => $tag === 'span' ? 'div' : $tag, 'content' => $inner, 'style' => $style];

            case 'ul': case 'ol':
                $items = [];
                foreach ($this->xpath->query('./li', $node) as $li) {
                    $items[] = trim($this->innerHtml($li));
                }
                return ['type' => 'list', 'ordered' => $tag === 'ol', 'items' => $items];

            case 'hr':
                return ['type' => 'divider', 'color' => $this->cssValue($style, 'border-color') ?: ''];

            case 'table':
                // Nested table — recurse into rows
                $innerContent = [];
                foreach ($this->xpath->query('./tbody/tr|./tr', $node) as $row) {
                    $innerContent[] = $this->analyseRow($row);
                }
                return $innerContent ? ['type' => 'nested', 'sections' => $innerContent] : null;

            default:
                $inner = trim($node->textContent ?? '');
                return $inner !== '' ? ['type' => 'text', 'tag' => 'div', 'content' => htmlspecialchars($inner, ENT_QUOTES)] : null;
        }
    }

    // ── PHP code generation ───────────────────────────────────────────────────

    private function generate(string $namespace, string $outputFile): string
    {
        $ns      = $namespace ? "use {$namespace};\n" : '';
        $theme   = $this->theme;
        $vars    = [];
        $imgCode = '';
        $lnkCode = '';
        $skel    = '';
        $asm     = '';
        $imgIdx  = 0;
        $lnkIdx  = 0;

        // Unique images → variables
        $seenImgs = [];
        foreach ($this->images as $img) {
            $src = $img['src'];
            if (isset($seenImgs[$src])) continue;
            $seenImgs[$src] = 'img' . (++$imgIdx);
            $wStyle = $img['width'] ? "'img' => ['width' => '" . $img['width'] . "px', 'display' => 'block', 'border' => '0']" : "'img' => ['display' => 'block', 'border' => '0']";
            $imgCode .= "\$img{$imgIdx} = new Image(\n    " . var_export($src, true) . ",\n    " . var_export($img['alt'], true) . ",\n    [{$wStyle}],\n);\n";
        }

        // Unique links → variables
        $seenLinks = [];
        foreach ($this->links as $lnk) {
            $href = $lnk['href'];
            if (isset($seenLinks[$href])) continue;
            $seenLinks[$href] = 'link' . (++$lnkIdx);
            $lnkCode .= "\$link{$lnkIdx} = new Anchor(" . var_export($href, true) . ");\n";
        }

        // Skeleton + assembly
        $skel .= "\$email = new EmailDocument(\$sheet->emailStyle());\n";
        $skel .= "\$email->body = new Body();\n";
        $skel .= "\$email->body->setCSS(\$sheet->responsiveCss());\n\n";

        foreach ($this->sections as $idx => $section) {
            $name = 's' . ($idx + 1);
            [$skelLine, $asmLine] = $this->generateSection($name, $section, $seenImgs, $seenLinks);
            $skel .= $skelLine;
            $asm  .= $asmLine;
        }

        $skel .= "\n// ── Build ──────────────────────────────────────────────────────────────────\n";
        $skel .= "\$base = \$email->build();\n";
        $skel .= "\$html = \$t->resolve(\$base, 'fr');\n";
        $skel .= "file_put_contents(__DIR__ . '/{$outputFile}', \$html);\n";
        $skel .= "echo \"Generated: {$outputFile}\\n\";\n";

        $cw   = $theme['containerWidth'];
        $code = "<?php\n\n";
        $code .= "/**\n * Imported from HTML — review and adjust as needed.\n * Generated by HtmlImporter on " . date('Y-m-d') . "\n */\n\n";
        $code .= $this->generateRequires();
        $code .= "\nuse Rlnks\\MailTree\\Anchor;\n";
        $code .= "use Rlnks\\MailTree\\Body;\n";
        $code .= "use Rlnks\\MailTree\\EmailDocument;\n";
        $code .= "use Rlnks\\MailTree\\Image;\n";
        $code .= "use Rlnks\\MailTree\\StyleSheet;\n";
        $code .= "use Rlnks\\MailTree\\Text;\n";
        $code .= "use Rlnks\\MailTree\\Translator;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Button;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Divider;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Section;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Spacer;\n";
        $code .= $ns . "\n";

        $code .= "// ── 1. Theme ───────────────────────────────────────────────────────────────\n\n";
        $code .= "\$sheet = new StyleSheet([\n";
        foreach ($theme as $k => $v) {
            $code .= "    " . var_export($k, true) . " => " . var_export($v, true) . ",\n";
        }
        $code .= "]);\n\n";

        $code .= "// ── 2. Translator ─────────────────────────────────────────────────────────\n\n";
        $code .= "\$t = new Translator();\n";
        $code .= "// \$t->setLocale('fr');\n";
        $code .= "// \$t->bindMany(['key' => 'value']);\n\n";

        if ($imgCode) {
            $code .= "// ── 3. Images ──────────────────────────────────────────────────────────────\n\n";
            $code .= $imgCode . "\n";
        }

        if ($lnkCode) {
            $code .= "// ── 4. Links ───────────────────────────────────────────────────────────────\n\n";
            $code .= $lnkCode . "\n";
        }

        $code .= "// ── 5. Skeleton + Content ─────────────────────────────────────────────────\n\n";
        $code .= $skel;

        if ($asm) {
            $code .= "\n// ── 6. Assembly ───────────────────────────────────────────────────────────\n\n";
            $code .= $asm;
        }

        return $code;
    }

    private function generateSection(string $name, array $section, array $seenImgs, array $seenLinks): array
    {
        $type = $section['type'] ?? 'unknown';

        switch ($type) {
            case 'spacer':
                $h = $section['height'];
                return [
                    "\$email->body->{$name} = Spacer::make('{$h}');\n",
                    '',
                ];

            case 'divider':
                $col  = $section['color'] ? ", color: '{$section['color']}'" : '';
                return [
                    "\$email->body->{$name} = Divider::make(sheet: \$sheet{$col});\n",
                    '',
                ];

            case 'columns':
                $n    = $section['columns'];
                $cols = $section['cells'] ?? [];
                $preset = $n === 2 ? 'TwoColumn' : ($n === 3 ? 'ThreeColumn' : "NColumn::make({$n},");
                $use    = $n === 2 ? "use Rlnks\\MailTree\\Preset\\TwoColumn;\n" : ($n === 3 ? "use Rlnks\\MailTree\\Preset\\ThreeColumn;\n" : '');
                $skel   = "\$email->body->{$name} = {$preset}::make(sheet: \$sheet);\n";
                $asm    = '';
                foreach ($cols as $i => $cellContent) {
                    $colName = 'col' . ($i + 1);
                    foreach ($cellContent as $ci => $item) {
                        $asm .= $this->generateContentAssignment("\$email->body->{$name}->{$colName}", $item, $ci, $seenImgs, $seenLinks);
                    }
                }
                return [$skel, $asm];

            case 'section':
                $bgLine = '';
                if (!empty($section['bg'])) {
                    $bg = $section['bg'];
                    $bgLine = "\$email->body->{$name}->setStyle(['container' => ['background-color' => '{$bg}']]);\n";
                }
                $skel = "\$email->body->{$name} = Section::make(sheet: \$sheet);\n";
                $asm  = $bgLine;
                foreach (($section['content'] ?? []) as $ci => $item) {
                    $asm .= $this->generateContentAssignment("\$email->body->{$name}->body", $item, $ci, $seenImgs, $seenLinks);
                }
                return [$skel, $asm];

            default:
                return ["// TODO: section '{$name}' (type: {$type}) — convert manually\n", ''];
        }
    }

    private function generateContentAssignment(string $parent, array $item, int $idx, array $seenImgs, array $seenLinks): string
    {
        $type = $item['type'] ?? '';
        $prop = 'item' . ($idx + 1);

        switch ($type) {
            case 'heading':
                $tag     = $item['tag'];
                $content = addslashes($item['content']);
                return "{$parent}->{$prop} = new Text('{$content}', '{$tag}');\n";

            case 'text':
                $tag     = $item['tag'] ?? 'div';
                $content = addslashes($item['content'] ?? '');
                return "{$parent}->{$prop} = new Text('{$content}', '{$tag}');\n";

            case 'image':
                $src  = $item['src'] ?? '';
                $var  = $seenImgs[$src] ?? null;
                if ($var) {
                    return "{$parent}->{$prop} = \${$var};\n";
                }
                $alt = addslashes($item['alt'] ?? '');
                return "{$parent}->{$prop} = new Image('" . addslashes($src) . "', '{$alt}');\n";

            case 'button':
                $label = addslashes($item['label'] ?? 'Click here');
                $href  = addslashes($item['href'] ?? '#');
                $bg    = $item['bg'] ?? '';
                $bgArg = $bg ? ", bgColor: '{$bg}'" : '';
                return "{$parent}->{$prop} = Button::make('{$label}', '{$href}'{$bgArg}, sheet: \$sheet);\n";

            case 'link':
                $href = addslashes($item['href'] ?? '#');
                $text = addslashes($item['text'] ?? '');
                return "{$parent}->{$prop} = (new Anchor('{$href}'))->setChild('label', new Text('{$text}', 'span'));\n";

            case 'list':
                $items = array_map(fn($i) => "'" . addslashes($i) . "'", $item['items'] ?? []);
                $arr   = implode(', ', $items);
                $ord   = !empty($item['ordered']) ? ', ordered: true' : '';
                return "// {$parent}->{$prop} = BulletList::make([{$arr}]{$ord}, sheet: \$sheet);\n";

            case 'divider':
                return "{$parent}->{$prop} = Divider::make(sheet: \$sheet);\n";

            default:
                return "// TODO: {$parent}->{$prop} — type '{$type}'\n";
        }
    }

    private function generateRequires(): string
    {
        return implode("\n", [
            "require_once __DIR__ . '/src/Renderable.php';",
            "require_once __DIR__ . '/src/HasChildren.php';",
            "require_once __DIR__ . '/src/HasStyle.php';",
            "require_once __DIR__ . '/src/helpers.php';",
            "require_once __DIR__ . '/src/EmailDocument.php';",
            "require_once __DIR__ . '/src/Body.php';",
            "require_once __DIR__ . '/src/Container.php';",
            "require_once __DIR__ . '/src/Column.php';",
            "require_once __DIR__ . '/src/Text.php';",
            "require_once __DIR__ . '/src/Image.php';",
            "require_once __DIR__ . '/src/Anchor.php';",
            "require_once __DIR__ . '/src/StyleSheet.php';",
            "require_once __DIR__ . '/src/Translator.php';",
            "require_once __DIR__ . '/src/Preset/Spacer.php';",
            "require_once __DIR__ . '/src/Preset/Divider.php';",
            "require_once __DIR__ . '/src/Preset/Button.php';",
            "require_once __DIR__ . '/src/Preset/Section.php';",
            "require_once __DIR__ . '/src/Preset/TwoColumn.php';",
            "require_once __DIR__ . '/src/Preset/ThreeColumn.php';",
        ]) . "\n";
    }

    // ── DOM helpers ───────────────────────────────────────────────────────────

    private function attr(\DOMNode $node, string $attr): string
    {
        if (!($node instanceof \DOMElement)) return '';
        return $node->getAttribute($attr) ?? '';
    }

    private function cssValue(string $style, string $prop): string
    {
        if (!preg_match('/' . preg_quote($prop, '/') . '\s*:\s*([^;]+)/i', $style, $m)) {
            return '';
        }
        return trim($m[1]);
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $this->dom->saveHTML($child);
        }
        return trim($html);
    }

    private function extractBorderColor(string $style): string
    {
        $val = $this->cssValue($style, 'border-bottom')
            ?: $this->cssValue($style, 'border-color')
            ?: $this->cssValue($style, 'border');
        if (preg_match('/#[0-9a-fA-F]{3,6}|rgb\([^)]+\)/', $val, $m)) {
            return $m[0];
        }
        return '';
    }
}
