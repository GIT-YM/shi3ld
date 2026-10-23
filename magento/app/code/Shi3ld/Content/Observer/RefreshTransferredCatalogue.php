<?php

declare(strict_types=1);

namespace Shi3ld\Content\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Shi3ld\Content\Model\SiteSitemaps;

class RefreshTransferredCatalogue implements ObserverInterface
{
    public function __construct(private SiteSitemaps $sitemaps)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->sitemaps->generate();
    }
}
