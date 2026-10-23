<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;

class Translations
{
    public function __construct(private ResourceConnection $database, private StoreManagerInterface $stores,
        private Repository $posts, private Urls $urls, private Metadata $metadata) {}

    public function row(int $id): array
    {
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('translation_id = ?', $id));
        if (! $row) {
            throw new NoSuchEntityException(__('This translation no longer exists.'));
        }

        return $row;
    }

    public function all(?string $type = null, ?int $entityId = null): array
    {
        $db = $this->database->getConnection();
        $select = $db->select()->from($this->table())->order('translation_id DESC');
        if ($type !== null) {
            $select->where('entity_type = ?', $type);
        }if ($entityId !== null) {
            $select->where('entity_id = ?', $entityId);
        }

        return $db->fetchAll($select);
    }

    public function create(string $type, int $id, int $storeId): int
    {
        if (! in_array($type, ['post', 'category'], true) || ! $storeId || $storeId === (int) $this->stores->getDefaultStoreView()->getId() || ! $this->stores->getStore($storeId)->isActive()) {
            throw new \InvalidArgumentException('Choose a configured secondary store view. The default view uses the original content.');
        }
        foreach ($this->all($type, $id) as $row) {
            if ((int) $row['store_id'] === $storeId) {
                return (int) $row['translation_id'];
            }
        }
        if ($type === 'post') {
            $post = $this->posts->post($id);
            $payload = ['html' => $post['content'], 'content_heading' => ''];
            foreach (['title', 'slug', 'meta_title', 'meta_description', 'excerpt', 'featured_image', 'featured_alt', 'author', 'is_featured', 'category_ids'] as $key) {
                $payload[$key] = $post[$key];
            }
            if ($post['editorial'] !== null) {
                $payload['editorial'] = $this->metadata->get($post);
            }
            $db = $this->database->getConnection();
            $builder = $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_builder_page'), ['settings'])->where('entity_type = ?', 'article')->where('entity_id = ?', $id));
            if ($builder) {
                $payload['builder'] = json_decode($builder, true, 32, JSON_THROW_ON_ERROR);
            }
        } else {
            $category = $this->posts->category($id);
            $payload = ['title' => $category['title'], 'html' => '<section><p>'.htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8').'</p></section>', 'slug' => $category['slug'], 'description' => $category['description'], 'meta_title' => $category['meta_title'], 'meta_description' => $category['meta_description'], 'content_heading' => ''];
        }
        $db = $this->database->getConnection();
        $db->insert($this->table(), ['entity_type' => $type, 'entity_id' => $id, 'store_id' => $storeId, 'slug' => $this->urls->slug($type, '', $payload['title'], $id, $storeId), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);

        return (int) $db->lastInsertId($this->table());
    }

    public function snapshot(int $id): array
    {
        return json_decode($this->row($id)['payload'], true, 32, JSON_THROW_ON_ERROR);
    }

    public function publish(int $id, array $payload): void
    {
        $row = $this->row($id);
        $slug = $this->urls->slug($row['entity_type'], $payload['slug'] ?? '', $payload['title'], (int) $row['entity_id'], (int) $row['store_id']);
        $payload['slug'] = $slug;
        $this->database->getConnection()->update($this->table(), ['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'slug' => $slug, 'is_published' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')], ['translation_id = ?' => $id]);
        $base = $row['entity_type'] === 'post' ? $this->posts->post((int) $row['entity_id']) : $this->posts->category((int) $row['entity_id']);
        $this->urls->sync($row['entity_type'], (int) $row['entity_id'], $base['slug'], true);
    }

    public function localized(string $type, array $base, ?int $storeId = null): array
    {
        $storeId ??= (int) $this->stores->getStore()->getId();
        $id = (int) $base[$type.'_id'];
        foreach ($this->all($type, $id) as $row) {
            if ((int) $row['store_id'] === $storeId && (int) $row['is_published']) {
                $payload = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
                $base = array_replace($base, $payload);
                if (isset($payload['html'])) {
                    $base['content'] = $payload['html'];
                }
                if (isset($payload['editorial'])) {
                    $base['editorial'] = json_encode($payload['editorial'], JSON_THROW_ON_ERROR);
                }
                $base['translation_store_id'] = $storeId;
                $base['updated_at'] = max((string) ($base['updated_at'] ?? ''), $row['updated_at']);
                if ($type === 'post') {
                    $base['author'] = $this->metadata->authorName($base);
                }

                return $base;
            }
        }if ($type === 'post') {
            $base['author'] = $this->metadata->authorName($base);
        }

        return $base;
    }

    public function preview(array $base, ?array $draft): ?array
    {
        if (! $draft || $draft['type'] !== 'blog_locale') {
            return null;
        }$row = $this->row((int) $draft['id']);
        if ((int) $row['entity_id'] !== (int) $base['post_id'] || (int) $row['store_id'] !== (int) $this->stores->getStore()->getId()) {
            return null;
        }
        $payload = $draft['payload'];
        $result = array_replace($base, $payload, ['content' => $payload['html']]);
        if (isset($payload['editorial'])) {
            $result['editorial'] = json_encode($payload['editorial'], JSON_THROW_ON_ERROR);
        }

        return $result;
    }

    public function stores(): array
    {
        $default = (int) $this->stores->getDefaultStoreView()->getId();
        $result = [];
        foreach ($this->stores->getStores() as $store) {
            if ($store->isActive()) {
                $result[] = ['id' => (int) $store->getId(), 'name' => $store->getName(), 'code' => $store->getCode(), 'default' => (int) $store->getId() === $default];
            }
        }

        return $result;
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_translation');
    }
}
