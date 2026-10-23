<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Content\Api\EditorEntityInterface;

class CategoryLocale implements EditorEntityInterface
{
    public function __construct(private Translations $translations, private Metadata $metadata, private ResourceConnection $database,
        private StoreManagerInterface $stores) {}

    public function load(int $id): DataObject
    {
        $row = $this->translations->row($id);
        if ($row['entity_type'] !== 'category') {
            throw new \InvalidArgumentException('Choose a category translation.');
        }$payload = $this->snapshot($id);

        return new DataObject(['id' => $id, 'identifier' => $payload['slug'], 'title' => $payload['title']]);
    }

    public function snapshot(int $id): array
    {
        return $this->translations->snapshot($id);
    }

    public function validate(array $payload): array
    {
        $slug = $this->metadata->text($payload['slug'] ?? '', 180);
        if ($slug !== '' && ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new \InvalidArgumentException('Use lowercase letters, numbers and hyphens for a category URL.');
        }

        return ['slug' => $slug, 'description' => $this->metadata->text($payload['description'] ?? '', 5000)];
    }

    public function publish(int $id, array $payload): void
    {
        $this->translations->publish($id, $payload);
    }

    public function url(int $id): string
    {
        $row = $this->translations->row($id);

        return $this->stores->getStore((int) $row['store_id'])->getUrl('', ['_direct' => 'blog/category/view/id/'.$row['entity_id'], '_nosid' => true]);
    }

    public function navigation(): array
    {
        return [];
    }

    public function lock(int $id): void
    {
        $db = $this->database->getConnection();
        $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_blog_translation'), ['translation_id'])->where('translation_id = ?', $id)->forUpdate(true));
    }

    public function configuration(int $id): array
    {
        return [];
    }
}
