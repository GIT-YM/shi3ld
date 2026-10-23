<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class Documents
{
    public function __construct(private ResourceConnection $database, private Entities $entities, private Markup $markup) {}

    public function get(string $type, int $id): array
    {
        $live = $this->entities->snapshot($type, $id);
        $row = $this->row($type, $id);
        return [
            'payload' => $row ? json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR) : $live,
            'version' => (int) ($row['version'] ?? 0),
            'has_draft' => $row && $row['payload'] !== json_encode($live, JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'] ?? null,
            'updated_by' => $row['updated_by'] ?? null,
            'revisions' => $this->revisions($type, $id),
            'live_hash' => $this->entities->hash($live),
        ];
    }

    public function save(string $type, int $id, array $payload, int $version, string $author, string $action = 'draft', ?string $liveHash = null): array
    {
        $connection = $this->database->getConnection();
        $connection->beginTransaction();
        try {
            $live = $this->entities->snapshot($type, $id);
            $row = $this->row($type, $id, true);
            if ((int) ($row['version'] ?? 0) !== $version) {
                throw new LocalizedException(__('Someone else changed this draft. Reload the editor before saving.'));
            }
            if (!$row && ($liveHash === null || !hash_equals($this->entities->hash($live), $liveHash))) {
                throw new LocalizedException(__('The published content changed. Reload the editor before saving.'));
            }
            $baseline = $live['html'].($row ? json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR)['html'] : '');
            if ($action === 'restore') {
                $baseline .= $payload['html'];
            }
            $payload = $this->validate($payload, $baseline, false, $type);
            if (!$row) {
                $this->record($type, $id, $live, 'baseline', $author);
            }
            $data = [
                'entity_type' => $type, 'entity_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'base_hash' => $action === 'restore' || !$row ? $this->entities->hash($live) : $row['base_hash'],
                'version' => $version + 1, 'updated_by' => $author, 'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
            $connection->insertOnDuplicate($this->table('document'), $data, ['payload','base_hash','version','updated_by','updated_at']);
            $this->record($type, $id, $payload, $action, $author);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    public function publish(string $type, int $id, int $version, string $author): array
    {
        $connection = $this->database->getConnection();
        $connection->beginTransaction();
        try {
            $row = $this->row($type, $id, true);
            if (!$row || (int) $row['version'] !== $version) {
                throw new LocalizedException(__('Save the current draft before publishing.'));
            }
            $this->entities->lock($type, $id, $this->database);
            $live = $this->entities->snapshot($type, $id);
            if (!hash_equals($row['base_hash'], $this->entities->hash($live))) {
                throw new LocalizedException(__('The published page changed outside this draft. Reload and restore the draft as a new revision before publishing.'));
            }
            $saved = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
            $payload = $this->validate($saved, $live['html'].$saved['html'], false, $type);
            $this->record($type, $id, $live, 'before_publish', $author);
            $this->entities->publish($type, $id, $payload);
            $published = $this->entities->snapshot($type, $id);
            $connection->update($this->table('document'), [
                'payload' => json_encode($published, JSON_THROW_ON_ERROR), 'base_hash' => $this->entities->hash($published),
                'version' => $version + 1, 'updated_by' => $author, 'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['document_id = ?' => $row['document_id']]);
            $this->record($type, $id, $published, 'published', $author);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    public function revision(string $type, int $id, int $revision): array
    {
        $row = $this->database->getConnection()->fetchRow($this->database->getConnection()->select()
            ->from($this->table('revision'))->where('entity_type = ?', $type)->where('entity_id = ?', $id)->where('revision_id = ?', $revision));
        if (!$row) {
            throw new LocalizedException(__('This revision does not belong to this page.'));
        }
        return json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
    }

    public function validate(array $payload, string $baseline, bool $render = false, string $type = 'page'): array
    {
        $result = [];
        foreach (['html' => 524288, 'title' => 255, 'meta_title' => 255, 'meta_description' => 1000, 'content_heading' => 255] as $key => $limit) {
            $value = $payload[$key] ?? '';
            if (!is_string($value) || mb_strlen($value) > $limit || ($key === 'title' && trim($value) === '')) {
                throw new LocalizedException(__('Enter a valid %1.', str_replace('_', ' ', $key)));
            }
            $result[$key] = $key === 'html' ? $this->markup->validate($value, $baseline, $render) : $value;
        }
        return $result + $this->entities->validateExtras($type, $payload);
    }

    private function row(string $type, int $id, bool $lock = false): array|false
    {
        $select = $this->database->getConnection()->select()->from($this->table('document'))
            ->where('entity_type = ?', $type)->where('entity_id = ?', $id);
        if ($lock) {
            $select->forUpdate(true);
        }
        return $this->database->getConnection()->fetchRow($select);
    }

    private function revisions(string $type, int $id): array
    {
        return $this->database->getConnection()->fetchAll($this->database->getConnection()->select()
            ->from($this->table('revision'), ['revision_id','action','created_by','created_at'])
            ->where('entity_type = ?', $type)->where('entity_id = ?', $id)->order('revision_id DESC')->limit(100));
    }

    private function record(string $type, int $id, array $payload, string $action, string $author): void
    {
        $this->database->getConnection()->insert($this->table('revision'), [
            'entity_type' => $type, 'entity_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'action' => $action, 'created_by' => $author,
        ]);
    }

    private function table(string $suffix): string
    {
        return $this->database->getTableName('shi3ld_cms_'.$suffix);
    }
}
