<?php

namespace Shi3ld\Content\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

class Pages extends Template
{
    public function __construct(Context $context, private CollectionFactory $pages, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getWebsitePages(): \Magento\Cms\Model\ResourceModel\Page\Collection
    {
        return $this->pages->create()->addFieldToFilter('identifier', ['in' => ['shi3ld-home', 'about', 'shop']]);
    }

    public function getSharedRegions(): array
    {
        $regions = [];
        foreach (['header','footer'] as $region) {
            $regions[$region] = $this->getUrl('shi3ld_content/editor/region', ['region' => $region]);
        }
        return $regions;
    }
}
