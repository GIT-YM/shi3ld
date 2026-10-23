<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\State;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class Renderer
{
    public function __construct(private FilterProvider $filters, private State $state, private Emulation $emulation, private StoreManagerInterface $stores) {}

    public function render(string $html, ?int $storeId = null): string
    {
        $storeId ??= (int) ($this->state->getAreaCode() === 'frontend' ? $this->stores->getStore()->getId() : $this->stores->getDefaultStoreView()->getId());
        if ($this->state->getAreaCode() === 'frontend') {
            return $this->filters->getPageFilter()->setStoreId($storeId)->filter($html);
        }

        return $this->state->emulateAreaCode('frontend', function () use ($html, $storeId): string {
            $this->emulation->startEnvironmentEmulation($storeId, 'frontend', true);
            try {
                return $this->filters->getPageFilter()->setStoreId($storeId)->filter($html);
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        });
    }
}
