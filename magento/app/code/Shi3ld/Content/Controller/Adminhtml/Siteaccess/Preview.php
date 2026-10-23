<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Siteaccess;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Security\Model\AdminSessionsManager;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Content\Model\PreviewSession;

class Preview extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private PreviewSession $preview,
        private StoreManagerInterface $stores,
        private AdminSessionsManager $adminSessions
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        try {
            $this->preview->start($this->_auth->getUser(), (int) $this->adminSessions->getCurrentSession()->getId());
            return $this->resultRedirectFactory->create()->setUrl($this->stores->getStore()->getBaseUrl());
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_auth->isLoggedIn();
    }
}
