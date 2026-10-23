<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Article;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Lifecycle;
use Shi3ld\Blog\Model\Repository;

class Unpublish extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_publish';

    public function __construct(Context $context, private Repository $posts,
        private Lifecycle $lifecycle, private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $fingerprint = $this->getRequest()->getParam('fingerprint', '');
            $this->posts->unpublish((int) $this->getRequest()->getParam('id'), is_string($fingerprint) ? $fingerprint : '');
            $this->lifecycle->refresh();
            $this->messageManager->addSuccessMessage(__('Article unpublished. It is now hidden from visitors, widgets and sitemaps.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to unpublish blog article.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The article could not be unpublished. Please try again.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
