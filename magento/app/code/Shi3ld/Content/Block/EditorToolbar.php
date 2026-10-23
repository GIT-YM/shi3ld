<?php

declare(strict_types=1);

namespace Shi3ld\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Cms\Model\Page;

class EditorToolbar extends Template
{
    public function __construct(Context $context, private FrontNameResolver $adminName, private Page $page, array $data = []) { parent::__construct($context, $data); }

    public function getLocateUrl(): string
    {
        return '/'.$this->adminName->getFrontName().'/shi3ld_content/editor/locate/?page_id='.(int) $this->page->getId();
    }
}
