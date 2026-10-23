<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryOptions implements OptionSourceInterface
{
    public function __construct(private Repository $posts) {}

    public function toOptionArray(): array
    {
        return array_map(fn ($row) => ['value' => (string) $row['category_id'], 'label' => $row['title'].($row['is_active'] ? '' : ' (hidden)')], $this->posts->categories(false));
    }
}
