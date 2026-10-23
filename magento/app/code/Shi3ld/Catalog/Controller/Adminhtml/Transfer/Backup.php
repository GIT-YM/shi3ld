<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Controller\Adminhtml\Transfer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Shi3ld\Catalog\Model\Transfer\Jobs;

class Backup extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Catalog::transfer';

    public function __construct(Context $context, private Jobs $jobs, private FileFactory $files)
    {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect
    {
        try {
            $id = $this->getRequest()->getParam('job', '');
            if (!is_string($id)) {
                throw new \RuntimeException('Invalid catalogue transfer reference.');
            }
            $job = $this->jobs->get($id, (int) $this->_auth->getUser()->getId());
            if (empty($job['backup'])) {
                throw new \RuntimeException('This transfer has no catalogue snapshot.');
            }
            return $this->files->create($job['backup'], ['type' => 'filename', 'value' => 'shi3ld/catalogue-transfer/' . $job['backup'], 'rm' => false], DirectoryList::VAR_DIR, 'application/zip');
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
    }
}
