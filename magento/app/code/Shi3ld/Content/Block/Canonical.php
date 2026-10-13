<?php

namespace Shi3ld\Content\Block;

use Magento\Cms\Model\Page;
use Magento\Framework\View\Element\Template;

class Canonical extends Template
{
    public function __construct(Template\Context $context, private Page $page, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getCanonicalUrl(): string
    {
        $identifier = (string) $this->page->getIdentifier();
        if ($identifier === 'shi3ld-home') {
            return $this->getUrl('');
        }

        $query = [];
        $page = (int) $this->getRequest()->getParam('p', 1);
        if ($identifier === 'shop' && $page > 1) {
            $query['p'] = $page;
        }

        return $this->getUrl('', ['_direct' => $identifier, '_query' => $query]);
    }
}
