<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Psr\Log\LoggerInterface;
use Shi3ld\Content\Model\SiteSitemaps;

class Lifecycle
{
    public function __construct(
        private TypeListInterface $cache,
        private SiteSitemaps $sitemaps,
        private LoggerInterface $logger
    ) {}

    public function refresh(): void
    {
        foreach (['block_html', 'full_page'] as $type) {
            $this->cache->cleanType($type);
        }
        try {
            $this->sitemaps->generate();
        } catch (\Throwable $exception) {
            $this->logger->error('Blog content saved; sitemap refresh will be retried by the existing sitemap cron.', ['exception' => $exception]);
        }
    }
}
