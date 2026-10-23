<?php

declare(strict_types=1);

namespace Shi3ld\Content\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Backend\Model\Auth;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class EditorAvailable implements ObserverInterface
{
    public function __construct(private Auth $auth, private AuthorizationInterface $authorization, private CookieManagerInterface $cookies, private CookieMetadataFactory $metadata) {}

    public function execute(Observer $observer): void
    {
        if ($this->auth->isLoggedIn() && $this->authorization->isAllowed('Shi3ld_Content::visual_edit') && !$this->cookies->getCookie('shi3ld_editor_available')) {
            $this->cookies->setPublicCookie('shi3ld_editor_available', '1', $this->metadata->createPublicCookieMetadata(['path' => '/', 'domain' => '', 'secure' => true, 'http_only' => false, 'samesite' => 'Lax']));
        }
    }
}
