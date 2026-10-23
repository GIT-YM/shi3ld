<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Article;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Repository;

class Create extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(Context $context, private Repository $posts,
        private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $title = $this->getRequest()->getParam('title');
            if (! is_string($title)) {
                throw new LocalizedException(__('Enter an article title.'));
            }
            $id = $this->posts->create($title, (string) $this->_auth->getUser()->getName());
            $this->messageManager->addSuccessMessage(__('Article created as an unpublished draft.'));

            return $this->resultRedirectFactory->create()->setPath('shi3ld_content/editor/index', ['type' => 'article', 'id' => $id]);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to create blog article.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The article could not be created. Please try again.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
