<?php

namespace Rlnks\MailTree;

/**
 * Wraps children in an MSO/IE conditional comment block.
 *
 * The `mso` flag in Container hardcodes the outer-column wrapper pattern for
 * Outlook 2007â€“2019. ConditionalBlock is the general-purpose version: any
 * condition string, any content, placed anywhere in the tree.
 *
 * Common condition strings:
 *   'mso'              â†’ <!--[if mso]>â€¦<![endif]-->
 *   '!mso'             â†’ <!--[if !mso]><!-->â€¦<!--<![endif]-->   (non-Outlook)
 *   'gte mso 9'        â†’ <!--[if gte mso 9]>â€¦<![endif]-->
 *   '(gte mso 9)|(IE)' â†’ <!--[if (gte mso 9)|(IE)]>â€¦<![endif]-->
 *
 * For "show only on non-Outlook clients" set $invertSyntax = true, which uses
 * the <!--[if !mso]><!--> / <!--<![endif]--> pattern instead of the standard one.
 * (The invertSyntax is derived from the condition automatically when it starts with '!'.)
 *
 * Usage:
 *   $block = new ConditionalBlock('mso');
 *   $block->content = new Text('Outlook-only text', 'div');
 *
 *   // Shorthand for non-Outlook content:
 *   $block = new ConditionalBlock('!mso');
 *   $block->paragraph = new Text('Shown in all clients except Outlook', 'p');
 */
class ConditionalBlock implements Renderable
{
    use HasChildren;

    public function __construct(
        private readonly string $condition = 'mso',
    ) {}

    public function toArray(): array
    {
        return [
            'type'      => 'ConditionalBlock',
            'condition' => $this->condition,
            'children'  => (object) $this->childrenToArray(),
        ];
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $t        = str_repeat("\t", $indent);
        $inverted = str_starts_with(ltrim($this->condition), '!');

        if ($inverted) {
            // <!--[if !mso]><!--> â€¦ <!--<![endif]-->
            $open  = "\n{$t}<!--[if {$this->condition}]><!--->";
            $close = "\n{$t}<!---<![endif]-->";
        } else {
            $open  = "\n{$t}<!--[if {$this->condition}]>";
            $close = "\n{$t}<![endif]-->";
        }

        return $open
            . $this->renderChildren($style, $indent)
            . $close;
    }
}
