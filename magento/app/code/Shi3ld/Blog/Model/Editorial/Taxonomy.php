<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Shi3ld\Blog\Model\Urls;

class Taxonomy
{
    public function __construct(private ResourceConnection $database, private Urls $urls, private Metadata $metadata) {}

    public function all(string $type, bool $public = false): array
    {
        $this->type($type);
        $db = $this->database->getConnection();
        $select = $db->select()->from($this->table($type))->order($type === 'author' ? 'name ASC' : 'name ASC');
        if ($public && $type === 'author') {
            $select->where('is_active = ?', 1);
        }

        return $db->fetchAll($select);
    }

    public function get(string $type, int $id): array
    {
        $this->type($type);
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table($type))->where($type.'_id = ?', $id));
        if (! $row) {
            throw new NoSuchEntityException(__('This blog %1 no longer exists.', $type));
        }

        return $row;
    }

    public function save(string $type, int $id, array $input): int
    {
        $this->type($type);
        $name = $this->metadata->text($input['name'] ?? '', $type === 'author' ? 150 : 100);
        if ($name === '') {
            throw new \InvalidArgumentException('Enter a name.');
        }
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            if ($id) {
                $db->fetchOne($db->select()->from($this->table($type), [$type.'_id'])->where($type.'_id = ?', $id)->forUpdate(true));
                $old = $this->get($type, $id);
                if (! hash_equals($this->fingerprint($old), (string) ($input['fingerprint'] ?? ''))) {
                    throw new \InvalidArgumentException('This record changed. Reload before saving.');
                }
            }
            $data = ['name' => $name, 'slug' => $this->urls->slug($type, (string) ($input['slug'] ?? ($old['slug'] ?? '')), $name, $id), 'updated_at' => gmdate('Y-m-d H:i:s')];
            if ($type === 'author') {
                $data += ['biography' => $this->metadata->text($input['biography'] ?? '', 5000), 'expertise' => $this->metadata->text($input['expertise'] ?? '', 300), 'image' => $this->metadata->image($this->metadata->text($input['image'] ?? '', 512)), 'is_active' => empty($input['is_active']) ? 0 : 1];
            }
            if ($id) {
                $db->update($this->table($type), $data, [$type.'_id = ?' => $id]);
            } else {
                $db->insert($this->table($type), $data);
                $id = (int) $db->lastInsertId($this->table($type));
            }
            $this->urls->sync($type, $id, $data['slug'], true);
            $db->commit();

            return $id;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function tagsFor(int $postId): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from(['t' => $this->table('tag')])->joinInner(['p' => $this->table('post_tag')], 'p.tag_id = t.tag_id', [])->where('p.post_id = ?', $postId)->order('t.name ASC'));
    }

    public function fingerprint(array $row): string
    {
        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }

    private function type(string $type): void
    {
        if (! in_array($type, ['author', 'tag'], true)) {
            throw new \InvalidArgumentException('Choose authors or tags.');
        }
    }

    private function table(string $type): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$type);
    }
}
