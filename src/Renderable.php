<?php

namespace Rlnks\MailTree;

interface Renderable
{
    public function build(array $style = [], int $indent = 0): string;
}
