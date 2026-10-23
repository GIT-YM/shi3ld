<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Controller\Adminhtml\Transfer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Psr\Log\LoggerInterface;
use Shi3ld\Catalog\Model\Transfer\Exporter;

class Export extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Catalog::transfer';

    public function __construct(Context $context, private Exporter $exporter, private FileFactory $files, private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect
    {
        try {
            $skuInput = $this->getRequest()->getParam('skus', '');
            if (!is_string($skuInput) || strlen($skuInput) > 65536) {
                throw new \RuntimeException('Enter a valid list of SKUs.');
            }
            $skus = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $skuInput)))));
            $path = $this->exporter->export($skus, $this->getRequest()->getParam('samples') === '1', $this->getRequest()->getParam('prices') === '1', $this->getRequest()->getParam('stock') === '1');
            return $this->files->create(basename($path), ['type' => 'filename', 'value' => 'shi3ld/catalogue-transfer/' . basename($path), 'rm' => false], DirectoryList::VAR_DIR, 'application/zip');
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to export Shi3ld catalogue.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
    }
}
