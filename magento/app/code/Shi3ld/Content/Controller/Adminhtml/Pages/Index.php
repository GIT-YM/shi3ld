<?php

namespace Shi3ld\Content\Controller\Adminhtml\Pages;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(Context $context, private PageFactory $pages)
    {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pages->create();
        $page->setActiveMenu('Shi3ld_Content::website');
        $page->getConfig()->getTitle()->prepend(__('Shi3ld Website Pages'));
        return $page;
    }
}
