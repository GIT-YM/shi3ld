<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;

class Notes
{
    public function __construct(private ResourceConnection $database, private Metadata $metadata) {}

    public function all(int $postId): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->table())->where('post_id = ?', $postId)->order('note_id DESC')->limit(100));
    }

    public function add(int $postId, string $actor, string $body): void
    {
        $body = $this->metadata->text($body, 3000);
        if ($body === '') {
            throw new \InvalidArgumentException('Enter an editorial note.');
        }
        $this->database->getConnection()->insert($this->table(), ['post_id' => $postId, 'actor' => $actor, 'body' => $body]);
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_note');
    }
}
