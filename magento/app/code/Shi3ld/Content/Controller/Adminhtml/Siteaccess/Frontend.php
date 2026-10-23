<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Siteaccess;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Security\Model\AdminSessionsManager;
use Psr\Log\LoggerInterface;
use Shi3ld\Content\Model\PreviewSession;
use Shi3ld\Content\Model\SiteAccess;

/**
 * Same-origin browser check: recognise an existing Admin session without a login redirect.
 * Magento's predispatch observers still enforce any enabled second factor.
 */
class Frontend extends Action implements HttpGetActionInterface
{
    protected $_publicActions = ['frontend'];

    public function __construct(
        Context $context,
        private JsonFactory $results,
        private SiteAccess $siteAccess,
        private PreviewSession $preview,
        private AdminSessionsManager $adminSessions,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->results->create()
            ->setHeader('Cache-Control', 'private, no-store, max-age=0', true);

        if (!$this->siteAccess->redirectsAdministrators()
            || !$this->getRequest()->isXmlHttpRequest()
            || $this->getRequest()->getHeader('Sec-Fetch-Site') === 'cross-site'
            || !$this->_auth->isLoggedIn()
        ) {
            return $result->setData(['allowed' => false]);
        }

        try {
            // Apply the same account/session checks used by protected Admin requests.
            $this->_auth->getUser()->reload();
            $this->_auth->getAuthStorage()->prolong();
            if (!$this->_auth->isLoggedIn()) {
                return $result->setData(['allowed' => false]);
            }

            $this->preview->start($this->_auth->getUser(), (int) $this->adminSessions->getCurrentSession()->getId());
            return $result->setData(['allowed' => true]);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to recognise a Shi3ld administrator frontend session.', ['exception' => $exception]);
            return $result->setData(['allowed' => false]);
        }
    }

    protected function _isAllowed(): bool
    {
        // Guests only receive allowed=false; this action never renders Admin content.
        return true;
    }
}
