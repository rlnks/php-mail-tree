<?php

namespace Rlnks\MailTree;

trait HasStyle
{
    private bool $hidden = false;

    public function hide(): static
    {
        $this->hidden = true;
        return $this;
    }

    public function show(): static
    {
        $this->hidden = false;
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function getStyle(): array
    {
        return $this->style;
    }

    public function setStyle(array $style): void
    {
        $this->style = array_replace_recursive($this->style, $style);
    }

    private function cssString(array $props): string
    {
        return implode('', array_map(
            static fn(string $k, mixed $v): string => "{$k}:{$v};",
            array_keys($props),
            array_values($props),
        ));
    }
}
