<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Controller\Adminhtml\Transfer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Shi3ld\Catalog\Model\Transfer\Jobs;

class Apply extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Catalog::transfer';

    public function __construct(Context $context, private Jobs $jobs)
    {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $id = $this->getRequest()->getParam('job', '');
        try {
            $host = $this->getRequest()->getParam('confirm_host', '');
            if (!is_string($id) || !is_string($host)) {
                throw new \RuntimeException('Invalid catalogue transfer confirmation.');
            }
            $job = $this->jobs->apply($id, (int) $this->_auth->getUser()->getId(), $host);
            $this->messageManager->addSuccessMessage(__('Catalogue imported: %1 created, %2 updated. Indexes, storefront caches and sitemap refreshed.', $job['result']['created'], $job['result']['updated']));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index', ['job' => is_string($id) ? $id : '']);
    }
}
