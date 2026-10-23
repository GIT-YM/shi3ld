<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Framework\Acl\Builder;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use Psr\Log\LoggerInterface;
use Shi3ld\Content\Model\Editor\Documents;

class Workflow
{
    public function __construct(private ResourceConnection $database,
        private Documents $documents, private UserFactory $users,
        private \Magento\User\Model\ResourceModel\User $userResource, private Builder $acl,
        private Runtime $runtime, private TypeListInterface $cache,
        private LockManagerInterface $locks, private LoggerInterface $logger) {}

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_builder_workflow');
    }

    public function get(string $type, int $id): ?array
    {
        return $this->database->getConnection()->fetchRow($this->database->getConnection()->select()->from($this->table())
            ->where('entity_type = ?', $type)->where('entity_id = ?', $id)) ?: null;
    }

    public function request(string $type, int $id, int $version, User $actor, ?string $when = null): array
    {
        $current = $this->documents->get($type, $id);
        if (! $version || $current['version'] !== $version) {
            throw new \InvalidArgumentException('Save the current draft before submitting it.');
        }
        $scheduled = null;
        if ($when !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?Z?$/D', $when)) {
                throw new \InvalidArgumentException('Enter a UTC publication date and time.');
            }
            $time = strtotime($when.(str_ends_with($when, 'Z') ? '' : ' UTC'));
            if (! $time || $time <= time()) {
                throw new \InvalidArgumentException('The scheduled publication must be in the future.');
            }
            $scheduled = gmdate('Y-m-d H:i:s', $time);
        }
        $this->database->getConnection()->insertOnDuplicate($this->table(), ['entity_type' => $type, 'entity_id' => $id, 'version' => $version,
            'state' => $when ? 'scheduled' : 'review', 'actor_id' => (int) $actor->getId(), 'requested_by' => (string) $actor->getUserName(),
            'scheduled_at' => $scheduled, 'note' => null], ['version', 'state', 'actor_id', 'requested_by', 'scheduled_at', 'note']);

        return $this->get($type, $id);
    }

    public function cancel(string $type, int $id): void
    {
        $this->database->getConnection()->update($this->table(), ['state' => 'cancelled', 'note' => 'Cancelled by an editor.'], ['entity_type = ?' => $type, 'entity_id = ?' => $id]);
    }

    public function pending(): array
    {
        return $this->database->getConnection()->fetchAll($this->database->getConnection()->select()->from($this->table())
            ->where('state IN (?)', ['review', 'scheduled'])->order('updated_at DESC')->limit(100));
    }

    public function run(): void
    {
        if (! $this->locks->lock('SHI3LD_BUILDER_SCHEDULE', 0)) {
            return;
        }
        try {
            $db = $this->database->getConnection();
            $rows = $db->fetchAll($db->select()->from($this->table())->where('state = ?', 'scheduled')->where('scheduled_at <= ?', gmdate('Y-m-d H:i:s'))->limit(50));
            foreach ($rows as $row) {
                try {
                    $user = $this->users->create();
                    $this->userResource->load($user, (int) $row['actor_id']);
                    if (! $user->getId() || ! (int) $user->getIsActive() || ! $this->acl->getAcl()->isAllowed($user->getAclRole(), 'Shi3ld_Content::visual_publish')) {
                        throw new \InvalidArgumentException('The scheduling user no longer has publication permission.');
                    }
                    $this->documents->publish($row['entity_type'], (int) $row['entity_id'], (int) $row['version'], 'Scheduled: '.$user->getUserName());
                    $db->update($this->table(), ['state' => 'published', 'note' => null], ['workflow_id = ?' => $row['workflow_id']]);
                } catch (\Throwable $exception) {
                    $this->logger->error('Scheduled builder publication did not run.', ['exception' => $exception]);
                    $db->update($this->table(), ['state' => 'failed', 'note' => mb_substr($exception->getMessage(), 0, 2000)], ['workflow_id = ?' => $row['workflow_id']]);
                }
            }
            // Minute-level invalidation makes date-dependent banners and template rules change on time.
            $timed = false;
            foreach ($this->runtime->assets() as $asset) {
                $conditions = $asset['builder']['conditions'] ?? [];
                if (! empty($conditions['start']) || ! empty($conditions['end']) || str_contains($asset['html'], 'data-builder-conditions')) {
                    $timed = true;
                    break;
                }
            }
            if (! $timed) {
                $timed = (bool) $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_builder_page'), ['setting_id'])->where('settings LIKE ?', '%"start":"2%')->orWhere('settings LIKE ?', '%"end":"2%')->orWhere('settings LIKE ?', '%"has_date_rules":true%'));
            }
            if ($timed) {
                $this->cache->cleanType('full_page');
            }
        } finally {
            $this->locks->unlock('SHI3LD_BUILDER_SCHEDULE');
        }
    }
}
