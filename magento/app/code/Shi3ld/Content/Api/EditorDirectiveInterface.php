<?php

declare(strict_types=1);

namespace Shi3ld\Content\Api;

interface EditorDirectiveInterface
{
    public function allows(string $directive): bool;
}
