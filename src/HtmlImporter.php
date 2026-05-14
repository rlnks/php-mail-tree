<?php

namespace Rlnks\MailTree;

use Rlnks\MailTree\Preset;

/**
 * HTML → MailTree PHP code converter (bottom-up, style-propagating).
 *
 * Parses an existing HTML email from the innermost <td> elements upward,
 * groups columns into section rows, propagates common inline styles to the
 * container/section level, and generates ready-to-run MailTree PHP code.
 *
 * Algorithm
 * ─────────
 * 1.  Find the outermost email table (by max width).
 * 2.  Collect each top-level <tr> as a candidate section.
 * 3.  For each <tr>:
 *       a. Collect <td> children; skip margin columns (narrow + empty).
 *       b. Parse each <td>'s inline style into a prop→value map.
 *       c. Extract HTML content from each <td> (images, headings, text, links, lists).
 *       d. Find properties COMMON to ALL <td>s → propagate up to section/container.
 *       e. Subtract common props → each <td> keeps only its unique overrides.
 * 4.  Map propagated common styles to MailTree keys:
 *       background-color             → container style
 *       font-family / font-size /
 *         color / line-height        → section-level text cascade
 *       padding / text-align /
 *         vertical-align             → body column style
 * 5.  Map unique per-column styles → individual column setStyle() calls.
 * 6.  Name sections sequentially: section1, section2, …
 *     Name columns:                 col1, col2, … (or body for single-col)
 *     Name content items:           item1, item2, …
 *
 * Usage:
 *   $php = HtmlImporter::convert(file_get_contents('legacy.html'));
 *   file_put_contents('rebuilt.php', $php);
 *
 *   $php = HtmlImporter::convert($html, outputFile: 'output.html');
 */
class HtmlImporter
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public static function convert(
        string $html,
        string $outputFile = 'output.html',
    ): string {
        return (new self($html))->generate($outputFile);
    }

    /**
     * Parse HTML and return a live EmailDocument object (serializable via Serializer).
     * Useful for the builder API: import HTML → serialize to JSON → send to frontend.
     */
    public static function toDocument(string $html, ?StyleSheet $sheet = null): EmailDocument
    {
        return (new self($html))->buildDocument($sheet);
    }

    // ── CSS property → MailTree style-key mapping ──────────────────────────────

    /** Properties that live on the outer container when common to all tds. */
    private const CONTAINER_PROPS = ['background-color', 'border', 'border-top', 'border-bottom'];

    /** Properties that cascade as text style when common. */
    private const TEXT_PROPS = ['color', 'font-family', 'font-size', 'font-weight',
                                 'line-height', 'letter-spacing', 'text-transform'];

    /** Properties that live on the column/cell. */
    private const COLUMN_PROPS = ['padding', 'padding-top', 'padding-bottom', 'padding-left',
                                   'padding-right', 'text-align', 'vertical-align',
                                   'width', 'height', 'border-left', 'border-right'];

    // ── State ──────────────────────────────────────────────────────────────────

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
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $this->xpath = new \DOMXPath($this->dom);

        $this->extractTheme();
        $this->parseSections();
    }

    // ── Theme extraction ───────────────────────────────────────────────────────

    private function extractTheme(): void
    {
        $body  = $this->xpath->query('//body')->item(0);
        $bodyBg = $body ? $this->cssValue($this->attr($body, 'style'), 'background-color') : '';

        // Container bg: first table with a different background
        $containerBg = '';
        foreach ($this->xpath->query('//table') as $tbl) {
            $bg = $this->cssValue($this->attr($tbl, 'style'), 'background-color');
            if ($bg && $bg !== $bodyBg) { $containerBg = $bg; break; }
        }

        // Primary colour: first heading or first link
        $primaryColor = '';
        foreach (['//h1', '//h2', '//a'] as $q) {
            $n = $this->xpath->query($q)->item(0);
            if ($n) {
                $c = $this->cssValue($this->attr($n, 'style'), 'color');
                if ($c) { $primaryColor = $c; break; }
            }
        }

        // Font family: body or first td
        $fontFamily = $this->cssValue($this->attr($body, 'style'), 'font-family');
        if (!$fontFamily) {
            foreach ($this->xpath->query('//td') as $td) {
                $f = $this->cssValue($this->attr($td, 'style'), 'font-family');
                if ($f) { $fontFamily = $f; break; }
            }
        }

        // Base font size: first non-trivial td
        $baseFontSize = '';
        foreach ($this->xpath->query('//td') as $td) {
            $f = $this->cssValue($this->attr($td, 'style'), 'font-size');
            if ($f && !in_array($f, ['0', '1px', '0px'], true)) { $baseFontSize = $f; break; }
        }

        // Container width: prefer pixel-width tables, fall back to largest image
        $containerWidth = 600;
        $maxW = 0;
        foreach ($this->xpath->query('//table') as $tbl) {
            $w = $this->tablePixelWidth($tbl);
            if ($w > $maxW) { $maxW = $w; }
        }
        if ($maxW >= 400 && $maxW <= 900) {
            $containerWidth = $maxW;
        } else {
            // Percentage-based layout: infer from widest image (usually = container width)
            foreach ($this->xpath->query('//img') as $img) {
                $w = (int) $this->attr($img, 'width');
                if ($w >= 400 && $w <= 900 && $w > $maxW) { $maxW = $w; }
            }
            if ($maxW >= 400 && $maxW <= 900) { $containerWidth = $maxW; }
        }

        $this->theme = [
            'bgColor'        => $bodyBg       ?: '#f0f0f0',
            'containerBg'    => $containerBg  ?: '#ffffff',
            'primaryColor'   => $primaryColor ?: '#333333',
            'fontFamily'     => $fontFamily   ?: "Arial, 'Helvetica Neue', Helvetica, sans-serif",
            'baseFontSize'   => $baseFontSize ?: '14px',
            'containerWidth' => $containerWidth,
        ];
    }

    // ── Bottom-up section parsing ──────────────────────────────────────────────

    private function parseSections(): void
    {
        $container = $this->findOuterContainer();
        if (!$container) {
            $this->sections = [['type' => 'unknown']];
            return;
        }

        foreach ($this->xpath->query('./tbody/tr|./tr', $container) as $tr) {
            $this->parseRowIntoSections($tr);
        }
    }

    /**
     * Parse a TR, potentially yielding multiple sections when the row is a
     * structural wrapper. Handles three wrapper patterns:
     *
     *   1. Single TD → multiple sibling tables → recurse into each table's rows
     *   2. Single TD → single table with multiple rows → recurse into each row
     *   3. Single TD → single table with single row → recurse if that row also wraps
     *
     * Falls through to parseRow() only for actual content rows.
     */
    private function parseRowIntoSections(\DOMNode $tr, int $depth = 0): void
    {
        if ($depth > 12) { return; }

        $tds = [];
        foreach ($tr->childNodes as $child) {
            if ($child->nodeName === 'td') { $tds[] = $child; }
        }
        if (empty($tds)) { return; }

        $contentTds = array_values(array_filter($tds, fn($td) => !$this->isMarginColumn($td)));
        if (empty($contentTds)) { return; }

        // Multi-TD → content row (columns layout)
        if (count($contentTds) > 1) {
            $section = $this->parseRow($tr);
            if ($section !== null) { $this->sections[] = $section; }
            return;
        }

        // Single content TD — collect direct child tables
        $td = $contentTds[0];
        $directTables = $this->directChildTables($td);

        // Pattern 1: multiple sibling tables → each is a structural block
        if (count($directTables) > 1) {
            foreach ($directTables as $childTable) {
                foreach ($this->xpath->query('./tbody/tr|./tr', $childTable) as $innerTr) {
                    $this->parseRowIntoSections($innerTr, $depth + 1);
                }
            }
            return;
        }

        // Pattern 2 & 3: exactly one direct child table
        if (count($directTables) === 1) {
            $innerTable = $directTables[0];
            $innerRows  = $this->xpath->query('./tbody/tr|./tr', $innerTable);

            // Pattern 2: multi-row table → structural wrapper
            if ($innerRows->length > 1) {
                foreach ($innerRows as $innerTr) {
                    $this->parseRowIntoSections($innerTr, $depth + 1);
                }
                return;
            }

            // Pattern 3: single-row table — recurse if its single content TD also
            // has direct child tables (another wrapper level)
            if ($innerRows->length === 1) {
                $innerTr   = $innerRows->item(0);
                $innerTds  = [];
                foreach ($innerTr->childNodes as $c) {
                    if ($c->nodeName === 'td' && !$this->isMarginColumn($c)) {
                        $innerTds[] = $c;
                    }
                }
                if (count($innerTds) === 1) {
                    $deepTables = $this->directChildTables($innerTds[0]);
                    if (!empty($deepTables)) {
                        $this->parseRowIntoSections($innerTr, $depth + 1);
                        return;
                    }
                } elseif (count($innerTds) > 1) {
                    // Multi-TD inner row
                    $this->parseRowIntoSections($innerTr, $depth + 1);
                    return;
                }
            }
        }

        // No wrapper pattern matched → this is a content row
        $section = $this->parseRow($tr);
        if ($section !== null) { $this->sections[] = $section; }
    }

    /**
     * Returns direct child <table> elements of a node (skipping text/comment nodes
     * and one level of innocuous wrapper elements like <div>, <center>).
     *
     * @return \DOMNode[]
     */
    private function directChildTables(\DOMNode $node): array
    {
        $tables = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'table') {
                $tables[] = $child;
            } elseif (in_array($child->nodeName, ['div', 'center', 'span'], true)) {
                foreach ($child->childNodes as $gc) {
                    if ($gc->nodeName === 'table') { $tables[] = $gc; }
                }
            }
        }
        return $tables;
    }

    private function findOuterContainer(): ?\DOMNode
    {
        $cw = $this->theme['containerWidth'];

        // Strategy 1: pixel-width table at shallow depth (≤ 3) with substantial content
        $totalImgs = $this->xpath->query('//img')->length;
        foreach ($this->xpath->query('//table') as $tbl) {
            $w = $this->tablePixelWidth($tbl);
            if ($w <= 0 || abs($w - $cw) > 10) { continue; }
            if ($this->nodeDepth($tbl, 'table') > 3) { continue; }
            $tblImgs  = $this->xpath->query('.//img', $tbl)->length;
            $tblText  = strlen(trim(str_replace("\xc2\xa0", '', $tbl->textContent ?? '')));
            $hasContent = ($totalImgs > 0 && $tblImgs >= (int) ceil($totalImgs * 0.6))
                || $tblText > 100;
            if ($hasContent) { return $tbl; }
        }

        // Strategy 2: percentage-based layout — find the shallowest table that
        // contains the most images (highest score = most images at lowest depth)
        $imgs = $this->xpath->query('//img');
        if ($imgs->length > 0) {
            $best      = null;
            $bestScore = -1;
            foreach ($this->xpath->query('//table') as $tbl) {
                $imgCount = $this->xpath->query('.//img', $tbl)->length;
                if ($imgCount === 0) { continue; }
                $depth = $this->nodeDepth($tbl, 'table');
                $score = $imgCount * 100 - $depth * 10;
                if ($score > $bestScore) { $bestScore = $score; $best = $tbl; }
            }
            if ($best !== null) { return $best; }
        }

        return $this->xpath->query('//table')->item(0);
    }

    private function parseRow(\DOMNode $tr): ?array
    {
        // Collect <td> children
        $allTds = [];
        foreach ($tr->childNodes as $child) {
            if ($child->nodeName === 'td') { $allTds[] = $child; }
        }
        if (empty($allTds)) { return null; }

        // Separate margin columns (narrow + empty) from content columns
        $contentTds = array_values(array_filter($allTds, fn($td) => !$this->isMarginColumn($td)));

        if (empty($contentTds)) { return null; }

        // ── Single content column ──────────────────────────────────────────────
        if (count($contentTds) === 1) {
            return $this->parseSingleTd($contentTds[0]);
        }

        // ── Multi-column section ───────────────────────────────────────────────
        return $this->parseMultiColumnRow($contentTds);
    }

    private function isMarginColumn(\DOMNode $td): bool
    {
        $style = $this->attr($td, 'style');
        $w     = (int) $this->attr($td, 'width')
            ?: (int) preg_replace('/[^0-9]/', '', $this->cssValue($style, 'width') ?: '0');

        // Narrow column (< 50px) with only whitespace/nbsp content
        if ($w > 0 && $w < 50) {
            $text = trim(str_replace("\xc2\xa0", '', $td->textContent ?? ''));
            if ($text === '') { return true; }
        }

        return false;
    }

    private function parseSingleTd(\DOMNode $td): array
    {
        $style  = $this->attr($td, 'style');
        $styles = $this->parseInlineStyle($style);
        $h      = (int) $this->attr($td, 'height')
            ?: (int) preg_replace('/[^0-9]/', '', $styles['height'] ?? '0');
        $text   = trim($td->textContent ?? '');

        // Spacer
        if (($text === '' || $text === "\xc2\xa0") && $h > 0) {
            return ['type' => 'spacer', 'height' => $h . 'px'];
        }

        // Divider
        if (isset($styles['border-bottom']) || $this->xpath->query('.//hr', $td)->length > 0) {
            $color = $this->extractBorderColor($style);
            return ['type' => 'divider', 'color' => $color];
        }

        // Nested table → recurse
        $innerTable = $this->xpath->query('.//table', $td)->item(0);
        if ($innerTable) {
            $innerRow = $this->xpath->query('./tbody/tr|./tr', $innerTable)->item(0);
            if ($innerRow) {
                $sub = $this->parseRow($innerRow);
                if ($sub) { return $sub; }
            }
        }

        // Regular section — extract content + propagate styles (single td = no siblings)
        $content    = $this->extractTdContent($td);
        $colStyle   = $this->mapToColumnStyle($styles);
        $contStyle  = $this->mapToContainerStyle($styles);

        return [
            'type'          => 'section',
            'content'       => $content,
            'containerStyle'=> $contStyle,
            'columnStyle'   => $colStyle,
        ];
    }

    private function parseMultiColumnRow(array $tds): array
    {
        $colCount  = count($tds);
        $allStyles = array_map(fn($td) => $this->parseInlineStyle($this->attr($td, 'style')), $tds);

        // ── Style propagation: find common props ───────────────────────────────
        $common  = $this->findCommonStyles($allStyles);
        $unique  = array_map(fn($s) => $this->subtractStyles($s, $common), $allStyles);

        // Map common → container / text level
        $containerStyle = $this->mapToContainerStyle($common);
        $textStyle      = $this->mapToTextStyle($common);

        // Map unique per-column props → column-level styles
        $colStyles = array_map(fn($u) => $this->mapToColumnStyle($u), $unique);

        // Extract content from each td
        $columns = [];
        foreach ($tds as $i => $td) {
            $columns[] = [
                'content'      => $this->extractTdContent($td),
                'columnStyle'  => $colStyles[$i],
            ];
        }

        return [
            'type'           => 'columns',
            'columns'        => $colCount,
            'cols'           => $columns,
            'containerStyle' => $containerStyle,
            'textStyle'      => $textStyle,
        ];
    }

    // ── Style propagation helpers ──────────────────────────────────────────────

    private function parseInlineStyle(string $css): array
    {
        $result = [];
        foreach (explode(';', $css) as $pair) {
            $pair = trim($pair);
            if (!$pair || !str_contains($pair, ':')) { continue; }
            [$prop, $val] = array_map('trim', explode(':', $pair, 2));
            if ($prop !== '' && $val !== '') { $result[$prop] = $val; }
        }
        return $result;
    }

    private function findCommonStyles(array $styleArrays): array
    {
        if (count($styleArrays) <= 1) { return []; }
        $common = $styleArrays[0];
        foreach (array_slice($styleArrays, 1) as $styles) {
            foreach (array_keys($common) as $prop) {
                if (!isset($styles[$prop]) || $styles[$prop] !== $common[$prop]) {
                    unset($common[$prop]);
                }
            }
        }
        return $common;
    }

    private function subtractStyles(array $style, array $toRemove): array
    {
        foreach (array_keys($toRemove) as $prop) { unset($style[$prop]); }
        return $style;
    }

    private function mapToContainerStyle(array $props): array
    {
        $result = [];
        foreach (self::CONTAINER_PROPS as $p) {
            if (isset($props[$p])) { $result[$p] = $props[$p]; }
        }
        return $result ? ['container' => $result] : [];
    }

    private function mapToTextStyle(array $props): array
    {
        $result = [];
        foreach (self::TEXT_PROPS as $p) {
            if (isset($props[$p])) { $result[$p] = $props[$p]; }
        }
        return $result ? ['text' => $result] : [];
    }

    private function mapToColumnStyle(array $props): array
    {
        $result = [];
        foreach (self::COLUMN_PROPS as $p) {
            if (isset($props[$p])) { $result[$p] = $props[$p]; }
        }
        // Also include text props that are unique to this column (not propagated up)
        foreach (self::TEXT_PROPS as $p) {
            if (isset($props[$p])) { $result[$p] = $props[$p]; }
        }
        return $result ? ['column' => $result] : [];
    }

    // ── Content extraction (bottom-up: from leaf elements) ────────────────────

    private function extractTdContent(\DOMNode $td): array
    {
        $items = [];
        foreach ($td->childNodes as $node) {
            $parsed = $this->parseNode($node);
            if ($parsed !== null) { $items[] = $parsed; }
        }
        return $items;
    }

    private function parseNode(\DOMNode $node): ?array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent ?? '');
            return ($text !== '' && $text !== "\xc2\xa0")
                ? ['type' => 'text', 'tag' => 'div', 'content' => htmlspecialchars($text, ENT_QUOTES), 'style' => []]
                : null;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) { return null; }

        $tag      = strtolower($node->nodeName);
        $style    = $this->attr($node, 'style');
        $styles   = $this->parseInlineStyle($style);
        $colStyle = $this->mapToColumnStyle($styles);

        switch ($tag) {
            case 'img':
                $src = $this->attr($node, 'src');
                $alt = $this->attr($node, 'alt');
                $w   = $this->attr($node, 'width') ?: ($styles['width'] ?? '');
                $this->images[$src] ??= ['src' => $src, 'alt' => $alt, 'width' => $w];
                return ['type' => 'image', 'src' => $src, 'alt' => $alt, 'width' => $w, 'style' => $colStyle];

            case 'a':
                $href = $this->attr($node, 'href');
                $bg   = $styles['background-color'] ?? '';
                $disp = $styles['display'] ?? '';
                $text = trim($node->textContent ?? '');
                $isBtn = ($bg && $bg !== 'transparent' && in_array($disp, ['inline-block', 'block'], true));
                $this->links[$href] ??= ['href' => $href, 'text' => $text];
                if ($isBtn) {
                    return ['type' => 'button', 'label' => $text, 'href' => $href, 'bg' => $bg, 'style' => $colStyle];
                }
                // Anchor wrapping an image
                $imgs = $this->xpath->query('./img', $node);
                if ($imgs->length > 0) {
                    $img  = $imgs->item(0);
                    $src  = $this->attr($img, 'src');
                    $alt  = $this->attr($img, 'alt');
                    $iw   = $this->attr($img, 'width');
                    $this->images[$src] ??= ['src' => $src, 'alt' => $alt, 'width' => $iw];
                    return ['type' => 'linked_image', 'src' => $src, 'alt' => $alt, 'width' => $iw, 'href' => $href, 'style' => $colStyle];
                }
                return ['type' => 'link', 'href' => $href, 'text' => $text, 'style' => $colStyle];

            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                return ['type' => 'heading', 'tag' => $tag, 'content' => $this->innerHtml($node), 'style' => $colStyle];

            case 'p':
                $inner = $this->innerHtml($node);
                return trim(strip_tags($inner)) !== '' ? ['type' => 'text', 'tag' => 'div', 'content' => $inner, 'style' => $colStyle] : null;

            case 'div': case 'span':
                $inner = $this->innerHtml($node);
                // Hidden preheader — skip
                if (str_contains($style, 'display:none') || str_contains($style, 'display: none')) { return null; }
                return trim(strip_tags($inner)) !== '' ? ['type' => 'text', 'tag' => 'div', 'content' => $inner, 'style' => $colStyle] : null;

            case 'ul': case 'ol':
                $items = [];
                foreach ($this->xpath->query('./li', $node) as $li) {
                    $items[] = trim($this->innerHtml($li));
                }
                return ['type' => 'list', 'ordered' => $tag === 'ol', 'items' => $items];

            case 'hr':
                return ['type' => 'divider', 'color' => $styles['border-color'] ?? $styles['color'] ?? ''];

            case 'table':
                // Nested table: recurse into first meaningful row
                $innerRows = $this->xpath->query('./tbody/tr|./tr', $node);
                $contents  = [];
                foreach ($innerRows as $innerRow) {
                    $sub = $this->parseRow($innerRow);
                    if ($sub) { $contents[] = $sub; }
                }
                return $contents ? ['type' => 'nested', 'sections' => $contents] : null;

            default:
                $inner = trim($node->textContent ?? '');
                return $inner !== '' ? ['type' => 'text', 'tag' => 'div', 'content' => htmlspecialchars($inner, ENT_QUOTES), 'style' => []] : null;
        }
    }

    // ── PHP code generation ────────────────────────────────────────────────────

    private function generate(string $outputFile): string
    {
        $imgVarMap = [];
        $imgIdx    = 0;
        $imgCode   = '';
        foreach ($this->images as $src => $img) {
            $var = '$img' . (++$imgIdx);
            $imgVarMap[$src] = $var;
            $wStyle = $img['width'] ? "'width' => '{$img['width']}px', 'display' => 'block', 'border' => '0'" : "'display' => 'block', 'border' => '0'";
            $imgCode .= "{$var} = new Image(\n    " . var_export($src, true) . ",\n    " . var_export($img['alt'], true) . ",\n    ['img' => [{$wStyle}]],\n);\n";
        }

        $lnkIdx    = 0;
        $lnkCode   = '';
        foreach ($this->links as $href => $lnk) {
            $lnkCode .= "\$link" . (++$lnkIdx) . " = new Anchor(" . var_export($href, true) . ");\n";
        }

        // Skeleton + assembly code
        $skelCode = "\$email = new EmailDocument(\$sheet);\n";
        $skelCode .= "\$email->body = new Body();\n";
        $skelCode .= "\$email->body->setCSS(\$sheet->responsiveCss());\n\n";

        $asmCode = '';
        $sIdx    = 0;
        foreach ($this->sections as $section) {
            $sName = 'section' . (++$sIdx);
            [$skel, $asm] = $this->generateSectionCode($sName, $section, $imgVarMap);
            $skelCode .= $skel;
            $asmCode  .= $asm;
        }

        $skelCode .= "\n// ── Build ─────────────────────────────────────────────────────────────────\n";
        $skelCode .= "\$base = \$email->build();\n";
        $skelCode .= "\$html = \$t->resolve(\$base, 'fr');\n";
        $skelCode .= "file_put_contents(__DIR__ . '/{$outputFile}', \$html);\n";
        $skelCode .= "echo \"Generated: {$outputFile}\\n\";\n";

        // Assemble the full PHP file
        $cw   = $this->theme['containerWidth'];
        $code = "<?php\n\n/**\n * Imported by HtmlImporter — review and adjust.\n * Generated " . date('Y-m-d') . "\n */\n\n";
        $code .= $this->buildRequires();
        $code .= "\nuse Rlnks\\MailTree\\Anchor;\n";
        $code .= "use Rlnks\\MailTree\\Body;\n";
        $code .= "use Rlnks\\MailTree\\EmailDocument;\n";
        $code .= "use Rlnks\\MailTree\\Image;\n";
        $code .= "use Rlnks\\MailTree\\StyleSheet;\n";
        $code .= "use Rlnks\\MailTree\\Text;\n";
        $code .= "use Rlnks\\MailTree\\Translator;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Button;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Divider;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\BulletList;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Section;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\Spacer;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\TwoColumn;\n";
        $code .= "use Rlnks\\MailTree\\Preset\\ThreeColumn;\n\n";

        $code .= "// ── 1. Theme ───────────────────────────────────────────────────────────────\n\n";
        $code .= "\$sheet = new StyleSheet([\n";
        foreach ($this->theme as $k => $v) {
            $code .= "    " . var_export($k, true) . " => " . var_export($v, true) . ",\n";
        }
        $code .= "]);\n\n";

        $code .= "// ── 2. Translator ─────────────────────────────────────────────────────────\n\n";
        $code .= "\$t = new Translator();\n// \$t->setLocale('fr');\n\n";

        if ($imgCode) {
            $code .= "// ── 3. Images ──────────────────────────────────────────────────────────────\n\n";
            $code .= $imgCode . "\n";
        }
        if ($lnkCode) {
            $code .= "// ── 4. Links ───────────────────────────────────────────────────────────────\n\n";
            $code .= $lnkCode . "\n";
        }

        $code .= "// ── 5. Skeleton + Content ─────────────────────────────────────────────────\n\n";
        $code .= $skelCode;

        if ($asmCode) {
            $code .= "\n// ── 6. Style overrides ────────────────────────────────────────────────────\n\n";
            $code .= $asmCode;
        }

        return $code;
    }

    // ── Object builder (mirrors generate() but instantiates real nodes) ───────

    private function buildDocument(?StyleSheet $sheet): EmailDocument
    {
        $sheet ??= new StyleSheet($this->theme);
        $doc   = new EmailDocument($sheet);
        $doc->body = new Body();
        $doc->body->setCSS($sheet->responsiveCss());

        // Build image pool: src → Image node
        $imgPool = [];
        foreach ($this->images as $src => $img) {
            $style = $img['width']
                ? ['img' => ['width' => $img['width'] . 'px', 'display' => 'block', 'border' => '0']]
                : ['img' => ['display' => 'block', 'border' => '0']];
            $imgPool[$src] = new Image($src, $img['alt'], $style);
        }

        $sIdx = 0;
        foreach ($this->sections as $section) {
            $sName = 'section' . (++$sIdx);
            $node  = $this->buildSection($section, $imgPool, $sheet);
            if ($node !== null) {
                $doc->body->$sName = $node;
            }
        }

        return $doc;
    }

    private function buildSection(array $section, array $imgPool, StyleSheet $sheet): ?object
    {
        $type = $section['type'] ?? 'unknown';

        if ($type === 'spacer') {
            return Preset\Spacer::make($section['height'], $sheet);
        }

        if ($type === 'divider') {
            return Preset\Divider::make(sheet: $sheet, color: $section['color'] ?? '');
        }

        if ($type === 'section') {
            $node = Preset\Section::make(sheet: $sheet);
            if (!empty($section['containerStyle'])) { $node->setStyle($section['containerStyle']); }
            if (!empty($section['columnStyle']))    { $node->body->setStyle(['column' => $section['columnStyle']['column'] ?? []]); }
            $counters = [];
            foreach ($section['content'] as $item) {
                $iName = $this->semanticName($item, $counters);
                $child = $this->buildItem($item, $imgPool, $sheet);
                if ($child !== null) { $node->body->$iName = $child; }
            }
            return $node;
        }

        if ($type === 'columns') {
            $n    = $section['columns'];
            $node = match ($n) {
                2       => Preset\TwoColumn::make(sheet: $sheet),
                3       => Preset\ThreeColumn::make(sheet: $sheet),
                default => Preset\NColumn::make($n, sheet: $sheet),
            };
            if (!empty($section['containerStyle'])) { $node->setStyle($section['containerStyle']); }
            if (!empty($section['textStyle']))       { $node->setStyle($section['textStyle']); }
            foreach (($section['cols'] ?? []) as $ci => $col) {
                $cName = 'col' . ($ci + 1);
                if (!empty($col['columnStyle']) && isset($node->$cName)) {
                    $node->$cName->setStyle($col['columnStyle']);
                }
                $counters = [];
                foreach ($col['content'] as $item) {
                    $iName = $this->semanticName($item, $counters);
                    $child = $this->buildItem($item, $imgPool, $sheet);
                    if ($child !== null && isset($node->$cName)) {
                        $node->$cName->$iName = $child;
                    }
                }
            }
            return $node;
        }

        return null;
    }

    private function buildItem(array $item, array $imgPool, StyleSheet $sheet): ?object
    {
        $style = $item['style'] ?? [];
        switch ($item['type'] ?? '') {
            case 'heading':
            case 'text':
                return new Text($item['content'] ?? '', $item['tag'] ?? 'div', $style);
            case 'image':
                return $imgPool[$item['src']] ?? new Image($item['src'] ?? '', $item['alt'] ?? '');
            case 'linked_image':
                $anchor = new Anchor($item['href'] ?? '');
                $anchor->img = $imgPool[$item['src']] ?? new Image($item['src'] ?? '', $item['alt'] ?? '');
                return $anchor;
            case 'button':
                return Preset\Button::make(
                    label:   $item['label'] ?? '',
                    href:    $item['href']  ?? '',
                    bgColor: $item['bg']    ?? '',
                );
            case 'link':
                $anchor = new Anchor($item['href'] ?? '');
                $anchor->label = new Text($item['text'] ?? '', 'span');
                return $anchor;
            case 'list':
                return Preset\BulletList::make(
                    items:   $item['items']   ?? [],
                    ordered: $item['ordered'] ?? false,
                );
            case 'divider':
                return Preset\Divider::make(sheet: $sheet);
            default:
                return null;
        }
    }

    private function generateSectionCode(string $name, array $section, array $imgVarMap): array
    {
        $type = $section['type'] ?? 'unknown';

        switch ($type) {
            case 'spacer':
                return ["\$email->body->{$name} = Spacer::make('{$section['height']}');\n", ''];

            case 'divider':
                $arg = $section['color'] ? ", color: '{$section['color']}'" : '';
                return ["\$email->body->{$name} = Divider::make(sheet: \$sheet{$arg});\n", ''];

            case 'section':
                $skel     = "\$email->body->{$name} = Section::make(sheet: \$sheet);\n";
                $asm      = $this->generateStyleSetters("\$email->body->{$name}", $section['containerStyle'] ?? []);
                $asm     .= $this->generateStyleSetters("\$email->body->{$name}->body", ['column' => $section['columnStyle']['column'] ?? []]);
                $counters = [];
                foreach ($section['content'] as $item) {
                    $iName = $this->semanticName($item, $counters);
                    $asm  .= $this->generateItemAssignment("\$email->body->{$name}->body", $iName, $item, $imgVarMap);
                }
                return [$skel, $asm];

            case 'columns':
                $n      = $section['columns'];
                $preset = match ($n) { 2 => 'TwoColumn', 3 => 'ThreeColumn', default => "NColumn::make({$n}," };
                $call   = in_array($n, [2, 3]) ? "{$preset}::make(sheet: \$sheet)" : "NColumn::make({$n}, sheet: \$sheet)";
                $skel   = "\$email->body->{$name} = {$call};\n";
                $asm    = $this->generateStyleSetters("\$email->body->{$name}", $section['containerStyle'] ?? []);
                if (!empty($section['textStyle'])) {
                    $asm .= $this->generateStyleSetters("\$email->body->{$name}", $section['textStyle']);
                }
                foreach (($section['cols'] ?? []) as $ci => $col) {
                    $cName    = 'col' . ($ci + 1);
                    $asm     .= $this->generateStyleSetters("\$email->body->{$name}->{$cName}", $col['columnStyle'] ?? []);
                    $counters  = [];
                    foreach ($col['content'] as $item) {
                        $iName = $this->semanticName($item, $counters);
                        $asm  .= $this->generateItemAssignment("\$email->body->{$name}->{$cName}", $iName, $item, $imgVarMap);
                    }
                }
                return [$skel, $asm];

            default:
                return ["// TODO: {$name} (type: {$type})\n", ''];
        }
    }

    /**
     * Returns a type-specific property name for a content item,
     * incrementing a per-type counter stored in $counters.
     *
     * Mapping:
     *   image                    → img1, img2, …
     *   linked_image / link      → link1, link2, …
     *   button                   → btn1, btn2, …
     *   text (p/div)             → para1, para2, …
     *   list                     → list1, list2, …
     *   divider                  → divider1, divider2, …
     *   heading h1               → title1, title2, …
     *   heading h2               → subtitle1, subtitle2, …
     *   heading h3               → subsubtitle1, subsubtitle2, …
     *   heading h4–h6 / other    → heading1, heading2, …
     */
    private function semanticName(array $item, array &$counters): string
    {
        $type   = $item['type'] ?? '';
        $prefix = match ($type) {
            'image'                  => 'img',
            'linked_image', 'link'   => 'link',
            'button'                 => 'btn',
            'text'                   => 'para',
            'list'                   => 'list',
            'divider'                => 'divider',
            'heading'                => match ($item['tag'] ?? '') {
                'h1'    => 'title',
                'h2'    => 'subtitle',
                'h3'    => 'subsubtitle',
                default => 'heading',
            },
            default => 'item',
        };
        $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
        return $prefix . $counters[$prefix];
    }

    private function generateStyleSetters(string $target, array $style): string
    {
        if (empty($style) || empty(array_filter($style))) { return ''; }
        $inner = var_export($style, true);
        return "{$target}->setStyle({$inner});\n";
    }

    private function generateItemAssignment(string $parent, string $iName, array $item, array $imgVarMap): string
    {
        $styleStr = !empty($item['style']) ? ', ' . var_export($item['style'], true) : '';
        switch ($item['type'] ?? '') {
            case 'heading':
                $tag  = $item['tag'];
                return "{$parent}->{$iName} = new Text(" . var_export($item['content'], true) . ", '{$tag}'{$styleStr});\n";
            case 'text':
                $tag  = $item['tag'] ?? 'div';
                return "{$parent}->{$iName} = new Text(" . var_export($item['content'], true) . ", '{$tag}'{$styleStr});\n";
            case 'image':
                $src = $item['src'];
                if (isset($imgVarMap[$src])) {
                    return "{$parent}->{$iName} = {$imgVarMap[$src]};\n";
                }
                return "{$parent}->{$iName} = new Image(" . var_export($src, true) . ", " . var_export($item['alt'], true) . ");\n";
            case 'linked_image':
                $src = $item['src'];
                $imgRef = isset($imgVarMap[$src]) ? "{$imgVarMap[$src]}" : "new Image(" . var_export($src, true) . ", " . var_export($item['alt'] ?? '', true) . ")";
                return "{$parent}->{$iName} = new Anchor(" . var_export($item['href'], true) . ");\n"
                    . "{$parent}->{$iName}->img = {$imgRef};\n";
            case 'button':
                $bg = $item['bg'] ? ", bgColor: '{$item['bg']}'" : '';
                return "{$parent}->{$iName} = Button::make(" . var_export($item['label'], true) . ", " . var_export($item['href'], true) . "{$bg}, sheet: \$sheet);\n";
            case 'link':
                return "{$parent}->{$iName} = new Anchor(" . var_export($item['href'], true) . ");\n"
                    . "{$parent}->{$iName}->label = new Text(" . var_export($item['text'], true) . ", 'span');\n";
            case 'list':
                $items = implode(', ', array_map(fn($i) => var_export($i, true), $item['items'] ?? []));
                $ord   = !empty($item['ordered']) ? ', ordered: true' : '';
                return "{$parent}->{$iName} = BulletList::make([{$items}]{$ord}, sheet: \$sheet);\n";
            case 'divider':
                return "{$parent}->{$iName} = Divider::make(sheet: \$sheet);\n";
            case 'nested':
                return "// TODO: nested table inside {$parent}->{$iName} — convert manually\n";
            default:
                return "// TODO: {$parent}->{$iName} (type: {$item['type']})\n";
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Returns a table's pixel width, or 0 for percentage/unknown widths. */
    private function tablePixelWidth(\DOMNode $tbl): int
    {
        $wAttr = $this->attr($tbl, 'width');
        if (str_contains($wAttr, '%')) { return 0; }
        $w = (int) $wAttr;
        if ($w === 0) {
            $style = $this->attr($tbl, 'style');
            foreach (['width', 'max-width'] as $prop) {
                $val = $this->cssValue($style, $prop);
                if ($val !== '' && !str_contains($val, '%')) {
                    $px = (int) preg_replace('/[^0-9]/', '', $val);
                    if ($px > 0) { $w = $px; break; }
                }
            }
        }
        return $w;
    }

    /** Count how many ancestors of $node have the given tag name. */
    private function nodeDepth(\DOMNode $node, string $tagName): int
    {
        $depth = 0;
        $p = $node->parentNode;
        while ($p) {
            if ($p->nodeName === $tagName) { $depth++; }
            $p = $p->parentNode;
        }
        return $depth;
    }

    private function attr(\DOMNode $node, string $attr): string
    {
        return ($node instanceof \DOMElement) ? ($node->getAttribute($attr) ?? '') : '';
    }

    private function cssValue(string $style, string $prop): string
    {
        if (!preg_match('/' . preg_quote($prop, '/') . '\s*:\s*([^;]+)/i', $style, $m)) { return ''; }
        return trim($m[1]);
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) { $html .= $this->dom->saveHTML($child); }
        return trim($html);
    }

    private function extractBorderColor(string $style): string
    {
        $val = $this->cssValue($style, 'border-bottom')
            ?: $this->cssValue($style, 'border-color')
            ?: $this->cssValue($style, 'border');
        if (preg_match('/#[0-9a-fA-F]{3,6}|rgb\([^)]+\)/', (string) $val, $m)) { return $m[0]; }
        return '';
    }

    private function buildRequires(): string
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
            "require_once __DIR__ . '/src/Preset/BulletList.php';",
            "require_once __DIR__ . '/src/Preset/Section.php';",
            "require_once __DIR__ . '/src/Preset/TwoColumn.php';",
            "require_once __DIR__ . '/src/Preset/ThreeColumn.php';",
        ]) . "\n";
    }
}
