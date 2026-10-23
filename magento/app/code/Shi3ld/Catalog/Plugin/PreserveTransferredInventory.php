<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Plugin;

use Magento\CatalogInventory\Observer\SaveInventoryDataObserver;
use Magento\Framework\Event\Observer;
use Shi3ld\Catalog\Model\Transfer\InventoryContext;

class PreserveTransferredInventory
{
    public function __construct(private InventoryContext $context)
    {
    }

    public function aroundExecute(SaveInventoryDataObserver $subject, callable $proceed, Observer $observer): void
    {
        if (!$this->context->preserves((string) $observer->getEvent()->getProduct()->getSku())) {
            $proceed($observer);
        }
    }
}
