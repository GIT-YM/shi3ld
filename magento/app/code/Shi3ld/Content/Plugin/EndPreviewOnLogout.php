<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Magento\Backend\Model\Auth;
use Shi3ld\Content\Model\PreviewSession;

class EndPreviewOnLogout
{
    public function __construct(private PreviewSession $preview, private \Shi3ld\Content\Model\Editor\Preview $cmsPreview,
        private \Magento\Framework\Stdlib\CookieManagerInterface $cookies, private \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $metadata)
    {
    }

    public function beforeLogout(Auth $auth): void
    {
        $this->preview->end();
        $this->cmsPreview->end();
        $this->cookies->deleteCookie('shi3ld_editor_available', $this->metadata->createCookieMetadata(['path' => '/', 'domain' => '', 'secure' => true, 'samesite' => 'Lax']));
    }
}
