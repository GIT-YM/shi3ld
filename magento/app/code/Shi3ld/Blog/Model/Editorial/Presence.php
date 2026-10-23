<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\User\Model\User;

class Presence
{
    public function __construct(private ResourceConnection $database, private LockManagerInterface $locks) {}

    public function heartbeat(int $id, User $actor, bool $takeover = false, string $type = 'article'): array
    {
        $name = 'BLOG_EDIT_'.$type.'_'.$id;
        if (! $this->locks->lock($name, 0)) {
            return ['owned' => false, 'actor' => 'Another editor', 'expires_at' => gmdate('Y-m-d H:i:s', time() + 10)];
        }
        try {
            $db = $this->database->getConnection();
            $row = $this->get($id, $type);
            if (! $row || $takeover || (int) $row['actor_id'] === (int) $actor->getId() || strtotime($row['expires_at'].' UTC') <= time()) {
                $row = ['post_id' => $id, 'entity_type' => $type, 'actor_id' => (int) $actor->getId(), 'actor' => (string) $actor->getUserName(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 90)];
                $db->insertOnDuplicate($this->table(), $row, ['actor_id', 'actor', 'expires_at']);
            }

            return $row + ['owned' => (int) $row['actor_id'] === (int) $actor->getId()];
        } finally {
            $this->locks->unlock($name);
        }
    }

    public function assertEditor(int $id, int $actorId, string $type = 'article'): void
    {
        $row = $this->get($id, $type);
        if ($row && (int) $row['actor_id'] !== $actorId && strtotime($row['expires_at'].' UTC') > time()) {
            throw new LocalizedException(__('%1 is editing this article. Wait for their editing lease or ask a publisher to take over.', $row['actor']));
        }
    }

    public function release(int $id, int $actorId, string $type = 'article'): void
    {
        $this->database->getConnection()->delete($this->table(), ['post_id = ?' => $id, 'actor_id = ?' => $actorId, 'entity_type = ?' => $type]);
    }

    private function get(int $id, string $type): array|false
    {
        $db = $this->database->getConnection();

        return $db->fetchRow($db->select()->from($this->table())->where('post_id = ?', $id)->where('entity_type = ?', $type));
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_presence');
    }
}
