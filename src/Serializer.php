<?php

namespace Rlnks\MailTree;

use Rlnks\MailTree\Preset\BulletList;
use Rlnks\MailTree\Preset\Button;
use Rlnks\MailTree\Preset\DataTable;
use Rlnks\MailTree\Preset\SocialBar;
use Rlnks\MailTree\Preset\StepIndicator;

/**
 * Serializes an EmailDocument tree to a plain PHP array / JSON string,
 * and reconstructs it from the same format.
 *
 * Each node type is identified by a 'type' field. Container/Column preset
 * roots carry an optional '_tag' field that identifies the semantic preset
 * (e.g. 'Spacer', 'TwoColumn') for use by visual builders — it has no
 * effect on rendering.
 *
 * Leaf-only presets (Button, BulletList, DataTable, StepIndicator, SocialBar)
 * store their resolved construction params and are reconstructed via their
 * factory methods, so rendering is identical across serialize/deserialize.
 *
 * Usage:
 *   $array = Serializer::toArray($doc);
 *   $json  = Serializer::toJson($doc);
 *
 *   $doc   = Serializer::fromArray($array, $sheet);
 *   $doc   = Serializer::fromJson($json, $sheet);
 *
 * Extending:
 *   Subclass Serializer and override hydrateNode() to handle custom node types.
 *   Call parent::hydrateNode() for all standard types.
 */
class Serializer
{
    // ── Serialization ──────────────────────────────────────────────────────────

    public static function toArray(EmailDocument $doc): array
    {
        return $doc->toArray();
    }

    public static function toJson(EmailDocument $doc, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode(static::toArray($doc), $flags);
    }

    // ── Deserialization ────────────────────────────────────────────────────────

    public static function fromArray(array $data, ?StyleSheet $sheet = null): EmailDocument
    {
        if (($data['type'] ?? '') !== 'EmailDocument') {
            throw new \InvalidArgumentException("Root node must be type 'EmailDocument'.");
        }
        return static::hydrateEmailDocument($data, $sheet);
    }

    public static function fromJson(string $json, ?StyleSheet $sheet = null): EmailDocument
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return static::fromArray($data, $sheet);
    }

    // ── Hydration dispatch ─────────────────────────────────────────────────────

    protected static function hydrateNode(array $data, ?StyleSheet $sheet): object
    {
        return match ($data['type'] ?? '') {
            'EmailDocument'    => static::hydrateEmailDocument($data, $sheet),
            'Body'             => static::hydrateBody($data, $sheet),
            'Container'        => static::hydrateContainer($data, $sheet),
            'Column'           => static::hydrateColumn($data, $sheet),
            'Text'             => static::hydrateText($data, $sheet),
            'Image'            => static::hydrateImage($data),
            'Anchor'           => static::hydrateAnchor($data, $sheet),
            'ConditionalBlock' => static::hydrateConditionalBlock($data, $sheet),
            'RawHtml'          => static::hydrateRawHtml($data),
            'Button'           => static::hydrateButton($data),
            'BulletList'       => static::hydrateBulletList($data),
            'DataTable'        => static::hydrateDataTable($data),
            'StepIndicator'    => static::hydrateStepIndicator($data),
            'SocialBar'        => static::hydrateSocialBar($data),
            default            => throw new \InvalidArgumentException(
                "Unknown node type: '" . ($data['type'] ?? '') . "'. "
                . "Subclass Serializer and override hydrateNode() to handle custom types."
            ),
        };
    }

    protected static function hydrateChildren(array $childrenData, ?StyleSheet $sheet, object $parent): void
    {
        foreach ($childrenData as $key => $childData) {
            $child = static::hydrateNode($childData, $sheet);
            $parent->$key = $child;
        }
    }

    // ── Node hydrators ─────────────────────────────────────────────────────────

    protected static function hydrateEmailDocument(array $d, ?StyleSheet $sheet): EmailDocument
    {
        $doc = new EmailDocument($sheet);
        if ($d['subject']   ?? '' !== '') { $doc->setSubject($d['subject']); }
        if ($d['preheader'] ?? '' !== '') { $doc->setPreheader($d['preheader']); }
        if ($d['lang']      ?? '' !== '') { $doc->setLang($d['lang']); }
        if ($d['hidden']    ?? false)     { $doc->hide(); }
        foreach ($d['links'] ?? [] as $link) {
            $doc->addLink($link['href'], $link['rel']);
        }
        static::hydrateChildren($d['children'] ?? [], $sheet, $doc);
        return $doc;
    }

    protected static function hydrateBody(array $d, ?StyleSheet $sheet): Body
    {
        $node = new Body($d['style'] ?? []);
        if ($d['css']       ?? '' !== '') { $node->setCSS($d['css']); }
        if ($d['preheader'] ?? '' !== '') { $node->setPreheader($d['preheader']); }
        if ($d['hidden']    ?? false)     { $node->hide(); }
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateContainer(array $d, ?StyleSheet $sheet): Container
    {
        $node = new Container($d['style'] ?? [], $d['mso'] ?? false);
        if ($d['class']     ?? '' !== '') { $node->setClass($d['class']); }
        if ($d['id']        ?? '' !== '') { $node->setID($d['id']); }
        if ($d['responsive']?? '' !== '') { $node->setResponsive($d['responsive']); }
        if ($d['_tag']      ?? '' !== '') { $node->setPreset($d['_tag']); }
        if ($d['hidden']    ?? false)     { $node->hide(); }
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateColumn(array $d, ?StyleSheet $sheet): Column
    {
        $node = new Column($d['style'] ?? []);
        if ($d['class']  ?? '' !== '') { $node->setClass($d['class']); }
        if ($d['_tag']   ?? '' !== '') { $node->setPreset($d['_tag']); }
        if ($d['hidden'] ?? false)     { $node->hide(); }
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateText(array $d, ?StyleSheet $sheet): Text
    {
        $node = new Text($d['text'] ?? '', $d['tag'] ?? null, $d['style'] ?? []);
        if ($d['hidden'] ?? false) { $node->hide(); }
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateImage(array $d): Image
    {
        $node = new Image($d['src'] ?? '', $d['alt'] ?? '', $d['style'] ?? []);
        if ($d['hidden'] ?? false) { $node->hide(); }
        return $node;
    }

    protected static function hydrateAnchor(array $d, ?StyleSheet $sheet): Anchor
    {
        $node = new Anchor($d['href'] ?? '', $d['style'] ?? []);
        if ($d['hidden'] ?? false) { $node->hide(); }
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateConditionalBlock(array $d, ?StyleSheet $sheet): ConditionalBlock
    {
        $node = new ConditionalBlock($d['condition'] ?? 'mso');
        static::hydrateChildren($d['children'] ?? [], $sheet, $node);
        return $node;
    }

    protected static function hydrateRawHtml(array $d): RawHtml
    {
        return RawHtml::make($d['html'] ?? '');
    }

    protected static function hydrateButton(array $d): Button
    {
        return Button::make(
            label:        $d['label']        ?? '',
            href:         $d['href']         ?? '',
            bgColor:      $d['bgColor']      ?? '',
            textColor:    $d['textColor']    ?? '#ffffff',
            width:        $d['width']        ?? 0,
            height:       $d['height']       ?? 0,
            fontSize:     $d['fontSize']     ?? '',
            borderRadius: $d['borderRadius'] ?? '',
            fontFamily:   $d['fontFamily']   ?? '',
        );
    }

    protected static function hydrateBulletList(array $d): BulletList
    {
        return BulletList::make(
            items:       $d['items']       ?? [],
            ordered:     $d['ordered']     ?? false,
            bulletColor: $d['bulletColor'] ?? '',
            textColor:   $d['textColor']   ?? '',
            fontFamily:  $d['fontFamily']  ?? '',
            fontSize:    $d['fontSize']    ?? '',
            lineHeight:  $d['lineHeight']  ?? '',
            itemSpacing: $d['itemSpacing'] ?? '8px',
            bulletWidth: $d['bulletWidth'] ?? '20px',
        );
    }

    protected static function hydrateDataTable(array $d): DataTable
    {
        return DataTable::make(
            headers:     $d['headers']     ?? [],
            rows:        $d['rows']        ?? [],
            footer:      $d['footer']      ?? [],
            headerBg:    $d['headerBg']    ?? '',
            headerColor: $d['headerColor'] ?? '#ffffff',
            rowBg:       $d['rowBg']       ?? '#ffffff',
            altRowBg:    $d['altRowBg']    ?? '#f7f9fc',
            footerBg:    $d['footerBg']    ?? '',
            footerColor: $d['footerColor'] ?? '',
            borderColor: $d['borderColor'] ?? '',
            fontFamily:  $d['fontFamily']  ?? '',
            fontSize:    $d['fontSize']    ?? '',
            cellPadding: $d['cellPadding'] ?? '10px 12px',
        );
    }

    protected static function hydrateStepIndicator(array $d): StepIndicator
    {
        return StepIndicator::make(
            steps:          $d['steps']          ?? [],
            current:        $d['current']        ?? 0,
            activeColor:    $d['activeColor']    ?? '',
            completedColor: $d['completedColor'] ?? '',
            upcomingColor:  $d['upcomingColor']  ?? '#cccccc',
            fontFamily:     $d['fontFamily']     ?? '',
            fontSize:       $d['fontSize']       ?? '12px',
        );
    }

    protected static function hydrateSocialBar(array $d): SocialBar
    {
        return SocialBar::make(
            links:       $d['links']       ?? [],
            iconSize:    $d['iconSize']    ?? 32,
            iconSpacing: $d['iconSpacing'] ?? '8px',
            align:       $d['align']       ?? 'center',
        );
    }
}
