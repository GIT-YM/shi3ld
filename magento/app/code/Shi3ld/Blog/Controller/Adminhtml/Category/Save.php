<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Lifecycle;
use Shi3ld\Blog\Model\Repository;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_publish';

    public function __construct(Context $context, private Repository $posts,
        private Lifecycle $lifecycle, private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $id = $this->posts->saveCategory($id, (array) $this->getRequest()->getPostValue());
            $this->lifecycle->refresh();
            $this->messageManager->addSuccessMessage(__('Blog category saved. Existing article URLs stay unchanged.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to save blog category.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The category could not be saved. Please try again.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index', ['id' => $id ?: null]);
    }
}
