<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Controller\Adminhtml\Transfer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Shi3ld\Catalog\Model\Transfer\Jobs;

class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Catalog::transfer';

    public function __construct(Context $context, private Jobs $jobs)
    {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        try {
            $upload = $this->getRequest()->getFiles('package');
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
                throw new \RuntimeException('Choose a catalogue ZIP within the server upload limit.');
            }
            $job = $this->jobs->prepare($upload['tmp_name'], (int) $this->_auth->getUser()->getId(), $this->getRequest()->getParam('prices') === '1', $this->getRequest()->getParam('stock') === '1');
            return $this->resultRedirectFactory->create()->setPath('*/*/index', ['job' => $job['id']]);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
    }
}
