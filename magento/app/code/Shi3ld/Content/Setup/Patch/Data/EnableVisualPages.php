<?php

namespace Shi3ld\Content\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Shi3ld\Content\Model\VisualPages;

class EnableVisualPages implements DataPatchInterface
{
    public function __construct(private VisualPages $visualPages) {}

    public function apply(): self
    {
        $this->visualPages->enable();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [IntegratePublicSite::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
