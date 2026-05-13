<?php

namespace Rlnks\MailTree;

/**
 * Wraps a pre-built HTML string as a Renderable node.
 *
 * Use this as an escape hatch when the tree primitives cannot express a
 * particular email-safe structure (e.g. a table-based play-button overlay
 * with mso-hide:all). The string is emitted verbatim — the style cascade
 * is intentionally bypassed.
 *
 * Usage:
 *   $col->play = RawHtml::make('<table role="presentation">...</table>');
 */
class RawHtml implements Renderable
{
    private function __construct(private readonly string $html) {}

    public static function make(string $html): static
    {
        return new static($html);
    }

    public function build(array $style = [], int $indent = 0): string
    {
        return $this->html;
    }
}
