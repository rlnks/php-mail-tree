<?php

namespace Rlnks\MailTree;

/**
 * Renders a list of items using a builder callback.
 *
 * The builder receives each item and its zero-based index and must return
 * either a Renderable node or a raw HTML string. All results are concatenated
 * in order — no wrapper element is added.
 *
 * ForLoopBlock itself is a Renderable, so it can be assigned as a named child
 * anywhere in the tree and participates in the style cascade.
 *
 * Usage — simple text list:
 *   $list = ForLoopBlock::make(
 *       ['Fast', 'Cross-client', 'Accessible'],
 *       fn($item, $i) => new Text("• {$item}", 'div', ['div' => ['margin' => '0 0 6px 0']]),
 *   );
 *   $section->body->features = $list;
 *
 * Usage — dynamic product rows inside a TwoColumn:
 *   $products = [['title' => 'Widget', 'price' => '$9'], ['title' => 'Gadget', 'price' => '$19']];
 *   $row->col1->products = ForLoopBlock::make($products,
 *       fn($p, $i) => new Text("{$p['title']} — {$p['price']}", 'div'),
 *   );
 *
 * Usage — DataTable rows built dynamically:
 *   $rows = array_map(fn($line) => [$line['name'], $line['qty'], $line['price']], $orderLines);
 *   $section->body->table = DataTable::make(headers: ['Item', 'Qty', 'Price'], rows: $rows, sheet: $sheet);
 *
 * Note: for complex template repetition (identical structure, different data) prefer
 * building the items array first and passing it to a preset like DataTable, BulletList,
 * or PricingTable. Use ForLoopBlock when the structure itself varies per item.
 */
class ForLoopBlock implements Renderable
{
    private \Closure $builder;

    private function __construct(
        private readonly array $items,
        callable               $builder,
    ) {
        $this->builder = $builder instanceof \Closure
            ? $builder
            : \Closure::fromCallable($builder);
    }

    /**
     * Create a loop block.
     *
     * @param array    $items   The dataset to iterate over.
     * @param callable $builder fn(mixed $item, int $index): Renderable|string
     *                          Return a Renderable node or a raw HTML string per item.
     */
    public static function make(array $items, callable $builder): static
    {
        return new static($items, $builder);
    }

    public function build(array $style = [], int $indent = 0): string
    {
        $html = '';
        foreach ($this->items as $index => $item) {
            $node = ($this->builder)($item, $index);
            if ($node instanceof Renderable) {
                $html .= $node->build($style, $indent);
            } elseif (is_string($node)) {
                $html .= $node;
            }
        }
        return $html;
    }
}
