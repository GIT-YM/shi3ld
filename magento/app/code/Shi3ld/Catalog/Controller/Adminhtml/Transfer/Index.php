<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Controller\Adminhtml\Transfer;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Catalog::transfer';

    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Shi3ld_Catalog::transfer');
        $page->getConfig()->getTitle()->prepend(__('Catalogue Transfer'));
        return $page;
    }
}
