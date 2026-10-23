<?php

declare(strict_types=1);

namespace Shi3ld\Content\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Shi3ld\Content\Model\Editor\SharedDesign;
use Psr\Log\LoggerInterface;

class ShareWebsiteDesign implements ObserverInterface
{
    public function __construct(private SharedDesign $design, private LoggerInterface $logger) {}

    public function execute(Observer $observer): void
    {
        $block = $observer->getEvent()->getData('object');
        if ($block && in_array($block->getIdentifier(), ['shi3ld_global_header','shi3ld_global_footer'], true)) {
            try { $this->design->export(); }
            catch (\Throwable $exception) { $this->logger->error('Unable to share the published website design.', ['exception' => $exception]); }
        }
    }
}
