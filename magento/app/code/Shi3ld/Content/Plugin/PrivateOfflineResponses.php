<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Magento\Framework\App\Response\Http;
use Shi3ld\Content\Model\SiteAccess;

class PrivateOfflineResponses
{
    public function __construct(private SiteAccess $siteAccess)
    {
    }

    public function beforeSendResponse(Http $response): void
    {
        if ($this->siteAccess->isOffline()) {
            $response->setNoCacheHeaders();
        }
    }
}
