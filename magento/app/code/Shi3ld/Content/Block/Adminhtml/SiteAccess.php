<?php

declare(strict_types=1);

namespace Shi3ld\Content\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth;
use Shi3ld\Content\Model\PreviewSession;
use Shi3ld\Content\Model\SiteAccess as AccessState;

class SiteAccess extends Template
{
    public function __construct(
        Context $context,
        private AccessState $siteAccess,
        private PreviewSession $preview,
        private Auth $auth,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isOffline(): bool
    {
        return $this->siteAccess->isOffline();
    }

    public function getOfflineMessage(): string
    {
        return $this->siteAccess->getMessage();
    }

    public function redirectsAdministrators(): bool
    {
        return $this->siteAccess->redirectsAdministrators();
    }

    public function canChangeStatus(): bool
    {
        return $this->_authorization->isAllowed('Shi3ld_Content::site_access');
    }

    public function isPreviewing(): bool
    {
        return $this->isOffline() && $this->preview->isAllowed();
    }

    public function getPreviewFormKey(): string
    {
        return $this->getFormKey();
    }

    public function showOfflineNotice(): bool
    {
        return $this->auth->isLoggedIn() && $this->isOffline();
    }
}
