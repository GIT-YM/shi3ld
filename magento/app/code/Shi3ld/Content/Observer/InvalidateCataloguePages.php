<?php

namespace Shi3ld\Content\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class InvalidateCataloguePages implements ObserverInterface, IdentityInterface
{
    public function __construct(private ManagerInterface $events) {}

    public function execute(Observer $observer): void
    {
        // Lists also need refreshing when a newly visible product was absent
        // from their previous cached result, including an empty catalogue.
        $this->events->dispatch('clean_cache_by_tags', ['object' => $this]);
    }

    public function getIdentities(): array
    {
        return [Product::CACHE_TAG];
    }
}
