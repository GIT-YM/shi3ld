<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Framework\App\ResourceConnection;

class State
{
    public function __construct(private ResourceConnection $database) {}

    public function get(string $type, int $id): ?array
    {
        $db = $this->database->getConnection();
        $json = $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_builder_page'), ['settings'])->where('entity_type = ?', $type)->where('entity_id = ?', $id));

        return $json ? json_decode($json, true, 32, JSON_THROW_ON_ERROR) : null;
    }

    public function save(string $type, int $id, array $settings): void
    {
        $this->database->getConnection()->insertOnDuplicate($this->database->getTableName('shi3ld_builder_page'),
            ['entity_type' => $type, 'entity_id' => $id, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR)], ['settings']);
    }
}
