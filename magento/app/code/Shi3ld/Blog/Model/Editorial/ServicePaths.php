<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\StoreManagerInterface;

class ServicePaths
{
    public function __construct(private CurlFactory $clients, private CacheInterface $cache,
        private StoreManagerInterface $stores) {}

    public function get(): ?array
    {
        $cached = $this->cache->load('shi3ld_blog_service_paths');
        if ($cached !== false) {
            $value = json_decode($cached, true, 8, JSON_THROW_ON_ERROR);

            return is_array($value) ? $value : null;
        }
        try {
            $client = $this->clients->create();
            $client->setTimeout(2);
            $client->addHeader('Host', (string) parse_url($this->stores->getDefaultStoreView()->getBaseUrl(), PHP_URL_HOST));
            $client->addHeader('X-Forwarded-Proto', 'https');
            $client->get('http://nginx:8080/services/sitemap.xml');
            $xml = $client->getBody();
            if ($client->getStatus() !== 200 || strlen($xml) > 1000000 || stripos($xml, '<!DOCTYPE') !== false) {
                return $this->unavailable();
            }
            $previous = libxml_use_internal_errors(true);
            try {
                $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (! $document) {
                return $this->unavailable();
            }$paths = ['/services', '/services/quote'];
            foreach ($document->xpath('//*[local-name()="loc"]') as $location) {
                $path = parse_url((string) $location, PHP_URL_PATH);
                if (is_string($path) && str_starts_with($path, '/services')) {
                    $paths[] = rtrim($path, '/');
                }
            }
            $paths = array_values(array_unique($paths));
            $this->cache->save(json_encode($paths, JSON_THROW_ON_ERROR), 'shi3ld_blog_service_paths', [], 300);

            return $paths;
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    private function unavailable(): ?array
    {
        $this->cache->save('null', 'shi3ld_blog_service_paths', [], 30);

        return null;
    }
}
