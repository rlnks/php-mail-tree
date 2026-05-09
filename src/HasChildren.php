<?php

namespace Rlnks\MailTree;

trait HasChildren
{
    protected array $children = [];
    private ?object $parentRef = null;
    private ?string $parentKey = null;

    public function __set(string $name, mixed $value): void
    {
        if (is_object($value) && method_exists($value, '_setParentRef')) {
            $value->_setParentRef($this, $name);
        }
        $this->children[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        return $this->children[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->children[$name]);
    }

    public function __clone(): void
    {
        // Cloned node is detached from its original parent
        $this->parentRef = null;
        $this->parentKey = null;

        // Deep-clone each child and rewire its parent reference to this clone
        foreach ($this->children as $key => $child) {
            if (is_object($child)) {
                $this->children[$key] = clone $child;
                if (method_exists($this->children[$key], '_setParentRef')) {
                    $this->children[$key]->_setParentRef($this, $key);
                }
            }
        }
    }

    protected function renderChildren(array $style, int $indent): string
    {
        $html = '';
        foreach ($this->children as $child) {
            if ($child instanceof Renderable) {
                if (method_exists($child, 'isHidden') && $child->isHidden()) {
                    continue;
                }
                $html .= $child->build($style, $indent);
            }
        }
        return $html;
    }

    // ── Inspection ────────────────────────────────────────────────────────────

    /** Return a snapshot of all direct children, keyed by their property name. */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Apply a style array to this node and, optionally, to specific named children.
     *
     * All keys except 'children' are treated as standard semantic style keys
     * (container, column, text, h1, div, img, a, …) and are applied to this node
     * via setStyle().
     *
     * The optional 'children' key is a map of child property names → style arrays.
     * Each matching child receives its own setStyle() / applyStyle() call, so you
     * can style a specific child without affecting siblings:
     *
     *   $sheet->define('sectionPromo', [
     *       'container' => ['background-color' => '#003366'],   // → this container
     *       'children'  => [
     *           'body' => [                                      // → ->body only
     *               'column' => ['background-color' => '#003366'],
     *           ],
     *       ],
     *   ]);
     *
     *   $email->body->promo->applyStyle($sheet->get('sectionPromo'));
     *
     * Children that do not exist in the tree are silently skipped.
     * Returns $this for chaining.
     */
    public function applyStyle(array $style): static
    {
        $childStyles = $style['children'] ?? [];
        $selfStyle   = array_diff_key($style, ['children' => null]);

        if ($selfStyle) {
            $this->setStyle($selfStyle);
        }

        foreach ($childStyles as $childName => $childStyle) {
            $child = $this->children[$childName] ?? null;
            if ($child === null) {
                continue;
            }
            if (method_exists($child, 'applyStyle')) {
                $child->applyStyle($childStyle);
            } elseif (method_exists($child, 'setStyle')) {
                $child->setStyle($childStyle);
            }
        }

        return $this;
    }

    // ── Parent back-reference (wired automatically by __set) ─────────────────

    /** @internal Called by the parent's __set when this node is assigned as a child. */
    public function _setParentRef(object $parent, string $key): void
    {
        $this->parentRef = $parent;
        $this->parentKey = $key;
    }

    // ── Node lifecycle ────────────────────────────────────────────────────────

    /**
     * Remove this node from its parent's child list entirely.
     * The detached node is returned so it can be re-attached elsewhere.
     */
    public function detach(): static
    {
        if ($this->parentRef !== null && $this->parentKey !== null) {
            $this->parentRef->_removeChild($this->parentKey);
            $this->parentRef = null;
            $this->parentKey = null;
        }
        return $this;
    }

    /**
     * Swap this node out of its parent with $newNode, preserving key and position.
     * Returns $this (the now-detached, original node).
     */
    public function replaceWith(Renderable $newNode): static
    {
        if ($this->parentRef !== null && $this->parentKey !== null) {
            $key    = $this->parentKey;
            $parent = $this->parentRef;

            if (method_exists($newNode, '_setParentRef')) {
                $newNode->_setParentRef($parent, $key);
            }
            $parent->_replaceChild($key, $newNode);

            $this->parentRef = null;
            $this->parentKey = null;
        }
        return $this;
    }

    /**
     * Deep-clone this node and insert the copy immediately after self in the parent.
     * The duplicate's key is auto-generated as "{key}_2", "{key}_3", etc.
     * Returns the new duplicate.
     */
    public function duplicate(): static
    {
        $dupe = clone $this;   // __clone() deep-clones subtree; parent ref is cleared

        if ($this->parentRef !== null && $this->parentKey !== null) {
            $idx     = $this->parentRef->_childIndex($this->parentKey);
            $dupeKey = $this->parentRef->_uniqueChildKey($this->parentKey);

            $this->parentRef->_insertChildAt($dupeKey, $dupe, $idx + 1);

            if (method_exists($dupe, '_setParentRef')) {
                $dupe->_setParentRef($this->parentRef, $dupeKey);
            }
        }

        return $dupe;
    }

    // ── Movement ──────────────────────────────────────────────────────────────

    /** Move this node earlier (toward the top) in its parent's child list. */
    public function moveUp(int $steps = 1): static
    {
        $this->_shiftInParent(-abs($steps));
        return $this;
    }

    /** Move this node later (toward the bottom) in its parent's child list. */
    public function moveDown(int $steps = 1): static
    {
        $this->_shiftInParent(+abs($steps));
        return $this;
    }

    /** Move this node to an absolute zero-based position in its parent's child list. */
    public function moveToIndex(int $index): static
    {
        if ($this->parentRef !== null && $this->parentKey !== null) {
            $this->parentRef->_moveChildToIndex($this->parentKey, $index);
        }
        return $this;
    }

    /**
     * Move this node to just before a named sibling.
     * The sibling is identified by the property name it was assigned under.
     */
    public function insertBefore(string $siblingKey): static
    {
        if ($this->parentRef === null || $this->parentKey === null
            || $this->parentKey === $siblingKey) {
            return $this;
        }

        $nodeIdx = $this->parentRef->_childIndex($this->parentKey);
        $sibIdx  = $this->parentRef->_childIndex($siblingKey);

        // After removing this node, the sibling shifts left by 1 if it came after us
        $targetIdx = $sibIdx > $nodeIdx ? $sibIdx - 1 : $sibIdx;
        $this->parentRef->_moveChildToIndex($this->parentKey, $targetIdx);

        return $this;
    }

    /**
     * Move this node to just after a named sibling.
     * The sibling is identified by the property name it was assigned under.
     */
    public function insertAfter(string $siblingKey): static
    {
        if ($this->parentRef === null || $this->parentKey === null
            || $this->parentKey === $siblingKey) {
            return $this;
        }

        $nodeIdx = $this->parentRef->_childIndex($this->parentKey);
        $sibIdx  = $this->parentRef->_childIndex($siblingKey);

        // After removing this node, the sibling is at (sibIdx - 1) if it came after us
        $adjustedSib = $nodeIdx < $sibIdx ? $sibIdx - 1 : $sibIdx;
        $this->parentRef->_moveChildToIndex($this->parentKey, $adjustedSib + 1);

        return $this;
    }

    private function _shiftInParent(int $delta): void
    {
        if ($this->parentRef === null || $this->parentKey === null) return;
        $current = $this->parentRef->_childIndex($this->parentKey);
        $this->parentRef->_moveChildToIndex($this->parentKey, $current + $delta);
    }

    // ── Internal helpers — public so sibling instances can call them ──────────

    /** @internal Zero-based position of $key in this node's child list. */
    public function _childIndex(string $key): int
    {
        $keys = array_keys($this->children);
        $pos  = array_search($key, $keys, true);
        return $pos !== false ? (int) $pos : 0;
    }

    /** @internal Reposition a named child to an absolute index (clamped). */
    public function _moveChildToIndex(string $key, int $index): void
    {
        if (!array_key_exists($key, $this->children)) return;

        $value  = $this->children[$key];
        unset($this->children[$key]);

        $index  = max(0, min(count($this->children), $index));
        $before = array_slice($this->children, 0, $index, true);
        $after  = array_slice($this->children, $index, null, true);

        $this->children = $before + [$key => $value] + $after;
    }

    /** @internal Remove a named child without touching parent refs. */
    public function _removeChild(string $key): void
    {
        unset($this->children[$key]);
    }

    /** @internal Replace a named child in-place (same key, same position). */
    public function _replaceChild(string $key, object $node): void
    {
        if (array_key_exists($key, $this->children)) {
            $this->children[$key] = $node;
        }
    }

    /** @internal Insert $node at $index under $key (does not go through __set). */
    public function _insertChildAt(string $key, object $node, int $index): void
    {
        $index  = max(0, min(count($this->children), $index));
        $before = array_slice($this->children, 0, $index, true);
        $after  = array_slice($this->children, $index, null, true);
        $this->children = $before + [$key => $node] + $after;
    }

    /** @internal Return a key derived from $base that doesn't collide with existing children. */
    public function _uniqueChildKey(string $base): string
    {
        if (!array_key_exists($base, $this->children)) return $base;
        $i = 2;
        while (array_key_exists("{$base}_{$i}", $this->children)) {
            $i++;
        }
        return "{$base}_{$i}";
    }
}
