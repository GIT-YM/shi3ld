<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\TranslitUrl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;

class Urls
{
    private const RESERVED = ['category', 'post', 'index', 'search', 'sitemap', 'view', 'admin', 'rss', 'author', 'tag', 'reader', 'interaction', 'conversion'];

    public function __construct(
        private ResourceConnection $database, private TranslitUrl $translit,
        private StoreManagerInterface $stores, private UrlFinderInterface $finder,
        private UrlPersistInterface $persist, private UrlRewriteFactory $rewrites
    ) {}

    public function path(string $type, string $slug): string
    {
        return in_array($type, ['category', 'author', 'tag'], true) ? 'blog/'.$type.'/'.$slug : 'blog/'.$slug;
    }

    public function storeId(): int
    {
        return (int) $this->stores->getStore()->getId();
    }

    public function url(string $type, string $slug, ?int $storeId = null): string
    {
        $store = $storeId === null ? $this->stores->getStore() : $this->stores->getStore($storeId);

        return $store->getUrl('', ['_direct' => $this->path($type, $slug), '_nosid' => true]);
    }

    public function slug(string $type, string $requested, string $title, int $id = 0, ?int $storeId = null): string
    {
        $automatic = trim($requested) === '';
        $base = trim(mb_substr((string) $this->translit->filter($automatic ? $title : $requested), 0, 160), '-');
        if ($base === '') {
            $base = $type === 'category' ? 'category' : 'article';
        }
        if (in_array($base, self::RESERVED, true)) {
            $base .= '-article';
        }
        for ($suffix = 1; $suffix <= 10000; $suffix++) {
            $candidate = $base.($suffix > 1 ? '-'.$suffix : '');
            if ($this->available($type, $candidate, $id, $storeId)) {
                return $candidate;
            }
            if (! $automatic) {
                throw new LocalizedException(__('This URL key is already in use. Choose another, or leave it empty to generate one.'));
            }
        }
        throw new LocalizedException(__('A unique URL could not be generated. Enter a different URL key.'));
    }

    private function available(string $type, string $slug, int $id, ?int $storeId = null): bool
    {
        if (! in_array($type, ['post', 'category', 'author', 'tag'], true)) {
            throw new \InvalidArgumentException('Choose a valid blog entity.');
        }
        $table = $this->database->getTableName('shi3ld_blog_'.$type);
        $key = $type.'_id';
        $connection = $this->database->getConnection();
        if ($connection->fetchOne($connection->select()->from($table, [$key])->where('slug = ?', $slug)->where($key.' != ?', $id))) {
            return false;
        }
        $translations = $connection->select()->from($this->database->getTableName('shi3ld_blog_translation'), ['translation_id'])->where('entity_type = ?', $type)->where('slug = ?', $slug)->where('entity_id != ?', $id);
        if ($storeId !== null) {
            $translations->where('store_id = ?', $storeId);
        }
        if ($connection->fetchOne($translations)) {
            return false;
        }
        foreach ($this->stores->getStores() as $store) {
            if ($storeId !== null && (int) $store->getId() !== $storeId) {
                continue;
            }
            $rewrite = $this->finder->findOneByData(['request_path' => $this->path($type, $slug), 'store_id' => (int) $store->getId()]);
            if ($rewrite && ($rewrite->getEntityType() !== 'shi3ld_blog_'.$type || (int) $rewrite->getEntityId() !== $id)) {
                return false;
            }
        }

        return true;
    }

    /** Rebuild only this entity's rewrites; all historical URLs point directly at the latest URL. */
    public function sync(string $type, int $id, string $slug, bool $active): void
    {
        $entityType = 'shi3ld_blog_'.$type;
        $path = $this->path($type, $slug);
        $filters = ['entity_type' => $entityType, 'entity_id' => $id];
        if (! $active) {
            return; // Keep historical paths reserved; the controller returns 404 for unpublished content.
        }
        $old = $this->finder->findAllByData($filters);
        $this->persist->deleteByData($filters);
        $rules = [];
        foreach ($this->stores->getStores() as $store) {
            $storeId = (int) $store->getId();
            $translated = $this->database->getConnection()->fetchOne($this->database->getConnection()->select()->from($this->database->getTableName('shi3ld_blog_translation'), ['slug'])->where('entity_type = ?', $type)->where('entity_id = ?', $id)->where('store_id = ?', $storeId)->where('is_published = ?', 1));
            $storePath = $translated ? $this->path($type, $translated) : $path;
            $paths = [$storePath => false];
            foreach ($old as $rewrite) {
                if ((int) $rewrite->getStoreId() === $storeId && $rewrite->getRequestPath() !== $storePath) {
                    $paths[$rewrite->getRequestPath()] = true;
                }
            }
            foreach ($paths as $requestPath => $redirect) {
                $rule = $this->rewrites->create()->setEntityType($entityType)->setEntityId($id)
                    ->setStoreId($storeId)->setRequestPath($requestPath)->setIsAutogenerated(1)
                    ->setTargetPath($redirect ? $storePath : 'blog/'.$type.'/view/id/'.$id)
                    ->setRedirectType($redirect ? 301 : 0);
                $rules[] = $rule;
            }
        }
        $this->persist->replace($rules);
    }
}
