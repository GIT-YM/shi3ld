<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Editorial\Metadata;
use Shi3ld\Blog\Model\Editorial\Taxonomy;
use Shi3ld\Blog\Model\Editorial\Translations;

class SitemapItems implements ItemProviderInterface
{
    public function __construct(private Repository $posts, private ResourceConnection $database,
        private SitemapItemInterfaceFactory $items, private Urls $urls,
        private Translations $translations, private Metadata $metadata,
        private Taxonomy $taxonomy, private StoreManagerInterface $stores) {}

    public function getItems($storeId): array
    {
        $db = $this->database->getConnection();
        $posts = $db->fetchAll($db->select()->from($this->posts->table('post'), ['post_id', 'slug', 'updated_at'])
            ->where('is_published = ?', 1)->where('published_at <= ?', gmdate('Y-m-d H:i:s')));
        if (! $posts) {
            return [];
        }
        $result = [$this->item('blog', max(array_column($posts, 'updated_at')))];
        $default = (int) $this->stores->getDefaultStoreView()->getId();
        foreach ($posts as $post) {
            $base = $this->posts->post((int) $post['post_id']);
            $localized = $this->translations->localized('post', $base, (int) $storeId);
            if ((int) $storeId !== $default && empty($localized['translation_store_id'])) {
                continue;
            }
            $canonical = $this->metadata->get($localized)['canonical'];
            $actual = $this->urls->url('post', $localized['slug'], (int) $storeId);
            if ($canonical !== '' && rtrim($canonical, '/') !== rtrim($actual, '/') && rtrim($canonical, '/') !== '/'.rtrim($this->urls->path('post', $localized['slug']), '/')) {
                continue;
            }
            $updated = $post['updated_at'];
            foreach ($this->translations->all('post', (int) $post['post_id']) as $translation) {
                if ((int) $translation['store_id'] === (int) $storeId && $translation['is_published']) {
                    $updated = max($updated, $translation['updated_at']);
                }
            }
            $result[] = $this->item($this->urls->path('post', $localized['slug']), $updated);
        }
        $categories = $this->posts->categories(true);
        foreach ($categories as $category) {
            if (! $this->posts->listing(['store_id' => (int) $storeId, 'category_ids' => [(int) $category['category_id']]], 1, 1)['total']) {
                continue;
            }
            $category = $this->translations->localized('category', $category, (int) $storeId);
            if ((int) $storeId !== $default && empty($category['translation_store_id'])) {
                continue;
            }
            $result[] = $this->item($this->urls->path('category', $category['slug']), $category['updated_at']);
        }
        foreach (['author' => 'author_id', 'tag' => 'tag_id'] as $type => $key) {
            foreach ($this->taxonomy->all($type, true) as $row) {
                $options = ['store_id' => (int) $storeId, $type === 'author' ? 'author_id' : 'tag_ids' => $type === 'author' ? (int) $row[$key] : [(int) $row[$key]]];
                if ($this->posts->listing($options, 1, 1)['total']) {
                    $result[] = $this->item($this->urls->path($type, $row['slug']), $row['updated_at']);
                }
            }
        }

        return $result;
    }

    private function item(string $url, string $updated): SitemapItemInterface
    {
        return $this->items->create(['url' => $url, 'updatedAt' => $updated, 'priority' => '0.5', 'changeFrequency' => 'weekly']);
    }
}
