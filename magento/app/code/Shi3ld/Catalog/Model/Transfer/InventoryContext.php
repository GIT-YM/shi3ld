<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

class InventoryContext
{
    private ?string $sku = null;

    public function preserves(string $sku): bool
    {
        return $this->sku === $sku;
    }

    public function saveExisting(string $sku, callable $save): mixed
    {
        $previous = $this->sku;
        $this->sku = $sku;
        try {
            return $save();
        } finally {
            $this->sku = $previous;
        }
    }
}
