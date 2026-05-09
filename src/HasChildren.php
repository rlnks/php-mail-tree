<?php

namespace Rlnks\MailTree;

trait HasChildren
{
    protected array $children = [];

    public function __set(string $name, mixed $value): void
    {
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

    protected function renderChildren(array $style, int $indent): string
    {
        $html = '';
        foreach ($this->children as $child) {
            if ($child instanceof Renderable) {
                $html .= $child->build($style, $indent);
            }
        }
        return $html;
    }
}
