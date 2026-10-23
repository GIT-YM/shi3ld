<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Siteaccess;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Shi3ld\Content\Model\PreviewSession;

class Stop extends Action implements HttpPostActionInterface
{
    public function __construct(Context $context, private PreviewSession $preview)
    {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $this->preview->end();
        $this->messageManager->addSuccessMessage(__('Your private preview has ended.'));
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_auth->isLoggedIn();
    }
}
