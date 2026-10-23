<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Siteaccess;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    protected $_publicActions = ['index'];

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $page = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Shi3ld_Content::site_access');
        $page->getConfig()->getTitle()->prepend(__('Website Online / Offline'));
        return $page;
    }

    protected function _isAllowed(): bool
    {
        return $this->_auth->isLoggedIn();
    }
}
