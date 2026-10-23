<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\Data\OptionSourceInterface;
use Shi3ld\Content\Model\Builder\Library;

class CardOptions implements OptionSourceInterface
{
    public function __construct(private Library $designs) {}

    public function toOptionArray(): array
    {
        $items = [['value' => '', 'label' => 'Theme card layout']];
        foreach ($this->designs->all(true) as $row) {
            if ($row['kind'] === 'card') {
                $items[] = ['value' => $row['asset_key'], 'label' => $row['title']];
            }
        }

        return $items;
    }
}
