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

    // ── Parent back-reference (wired automatically by __set) ─────────────────

    /** @internal Called by the parent's __set when this node is assigned as a child. */
    public function _setParentRef(object $parent, string $key): void
    {
        $this->parentRef = $parent;
        $this->parentKey = $key;
    }

    // ── Node movement ─────────────────────────────────────────────────────────

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

    private function _shiftInParent(int $delta): void
    {
        if ($this->parentRef === null || $this->parentKey === null) {
            return;
        }
        $current = $this->parentRef->_childIndex($this->parentKey);
        $this->parentRef->_moveChildToIndex($this->parentKey, $current + $delta);
    }

    // ── Internal helpers — public so sibling instances can call them ──────────

    /** @internal Return the zero-based position of $key in this node's child list. */
    public function _childIndex(string $key): int
    {
        $keys = array_keys($this->children);
        $pos  = array_search($key, $keys, true);
        return $pos !== false ? (int) $pos : 0;
    }

    /** @internal Reposition a named child to the given absolute index (clamped). */
    public function _moveChildToIndex(string $key, int $index): void
    {
        if (!array_key_exists($key, $this->children)) {
            return;
        }

        $value  = $this->children[$key];
        unset($this->children[$key]);

        $index  = max(0, min(count($this->children), $index));
        $before = array_slice($this->children, 0, $index, true);
        $after  = array_slice($this->children, $index, null, true);

        $this->children = $before + [$key => $value] + $after;
    }
}
