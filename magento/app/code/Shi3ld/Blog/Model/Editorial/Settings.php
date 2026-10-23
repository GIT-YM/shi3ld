<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;

class Settings
{
    public function __construct(private ResourceConnection $database) {}

    public function get(): array
    {
        $db = $this->database->getConnection();
        $json = $db->fetchOne($db->select()->from($this->table(), ['value'])->where('name = ?', 'reader'));

        return array_replace(['comments_enabled' => false, 'subscriptions_enabled' => false, 'delivery_enabled' => false, 'digest' => 'weekly', 'retention_days' => 180], $json ? json_decode($json, true, 8, JSON_THROW_ON_ERROR) : []);
    }

    public function save(array $input): void
    {
        $value = [];
        foreach (['comments_enabled', 'subscriptions_enabled', 'delivery_enabled'] as $name) {
            $value[$name] = ! empty($input[$name]);
        }
        if ($value['subscriptions_enabled'] && ! $value['delivery_enabled']) {
            throw new \InvalidArgumentException('Enable email delivery before enabling subscriptions and digests.');
        }
        if (! in_array($input['digest'] ?? '', ['daily', 'weekly'], true)) {
            throw new \InvalidArgumentException('Choose a daily or weekly digest.');
        }
        $value['digest'] = $input['digest'];
        $value['retention_days'] = max(30, min(730, (int) ($input['retention_days'] ?? 180)));
        $this->database->getConnection()->insertOnDuplicate($this->table(), ['name' => 'reader', 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => gmdate('Y-m-d H:i:s')], ['value', 'updated_at']);
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_setting');
    }
}
