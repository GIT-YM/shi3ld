<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Access;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Shi3ld\Content\Model\PreviewSession;
use Shi3ld\Content\Model\SiteAccess;

class Check implements HttpGetActionInterface
{
    public function __construct(private RawFactory $results, private SiteAccess $siteAccess, private PreviewSession $preview)
    {
    }

    public function execute(): Raw
    {
        return $this->results->create()
            ->setHttpResponseCode(!$this->siteAccess->isOffline() || $this->preview->isAllowed() ? 204 : 401)
            ->setHeader('Cache-Control', 'private, no-store, max-age=0', true)
            ->setContents('');
    }
}
