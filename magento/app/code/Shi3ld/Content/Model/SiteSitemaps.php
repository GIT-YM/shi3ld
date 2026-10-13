<?php

namespace Shi3ld\Content\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Model\StoreManagerInterface;

class SiteSitemaps
{
    public function __construct(
        private SitemapFactory $sitemaps,
        private StoreManagerInterface $stores,
        private Filesystem $filesystem,
        private State $state,
        private LockManagerInterface $locks
    ) {}

    public function generate(): void
    {
        if (!$this->locks->lock('SHI3LD_SITE_SITEMAP', 1)) {
            return;
        }
        try {
            $this->state->emulateAreaCode(Area::AREA_FRONTEND, function (): void {
                $store = $this->stores->getDefaultStoreView();
                $map = $this->sitemaps->create()->getCollection()
                    ->addFieldToFilter('sitemap_filename', 'sitemap_commerce.xml')
                    ->addFieldToFilter('store_id', $store->getId())->getFirstItem();
                if (!$map->getId()) {
                    $map->setSitemapFilename('sitemap_commerce.xml')->setSitemapPath('/')->setStoreId($store->getId())->save();
                }
                $map->generateXml();
                $base = rtrim($store->getBaseUrl(), '/');
                $commerce = htmlspecialchars($base.'/sitemap_commerce.xml', ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $services = htmlspecialchars($base.'/services/sitemap.xml', ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                    .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                    .'<sitemap><loc>'.$commerce.'</loc></sitemap>'
                    .'<sitemap><loc>'.$services.'</loc></sitemap></sitemapindex>';
                $this->filesystem->getDirectoryWrite(DirectoryList::PUB)->writeFile('sitemap.xml', $xml);
            });
        } finally {
            $this->locks->unlock('SHI3LD_SITE_SITEMAP');
        }
    }
}
