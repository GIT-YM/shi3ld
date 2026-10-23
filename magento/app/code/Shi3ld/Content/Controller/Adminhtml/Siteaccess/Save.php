<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Siteaccess;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Security\Model\AdminSessionsManager;
use Psr\Log\LoggerInterface;
use Shi3ld\Content\Model\PreviewSession;
use Shi3ld\Content\Model\SiteAccess;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::site_access';

    public function __construct(
        Context $context,
        private SiteAccess $siteAccess,
        private LoggerInterface $logger,
        private PreviewSession $preview,
        private AdminSessionsManager $adminSessions
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        try {
            $status = $this->getRequest()->getParam('status');
            if (in_array($status, ['offline', SiteAccess::REDIRECT_TO_FRONTEND], true)) {
                $message = $this->getRequest()->getParam('message', '');
                if (!is_string($message)) {
                    throw new LocalizedException(__('Enter a valid offline message.'));
                }
                $automatic = $status === SiteAccess::REDIRECT_TO_FRONTEND;
                $this->siteAccess->takeOffline($message, (int) $this->_auth->getUser()->getId(), $automatic);
                if ($automatic) {
                    $this->preview->start($this->_auth->getUser(), (int) $this->adminSessions->getCurrentSession()->getId());
                    $this->messageManager->addSuccessMessage(__('The website is offline. Signed-in Magento administrators can view the frontend automatically.'));
                } else {
                    $this->messageManager->addSuccessMessage(__('The website is offline. Start a private preview below to browse it.'));
                }
            } elseif ($status === 'online') {
                $this->siteAccess->putOnline();
                $this->messageManager->addSuccessMessage(__('The website is online for everyone.'));
            } else {
                throw new LocalizedException(__('Choose a valid website status.'));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to change Shi3ld site access.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The website status could not be saved. Please try again.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
