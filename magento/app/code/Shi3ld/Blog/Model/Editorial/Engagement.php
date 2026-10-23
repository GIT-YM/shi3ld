<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;

class Engagement
{
    public const EVENTS = ['view', 'product_click', 'service_click', 'enquiry', 'helpful_yes', 'helpful_no'];

    public function __construct(private ResourceConnection $database, private Guard $guard, private Metadata $metadata, private Settings $settings) {}

    public function record(int $postId, string $event, ?string $reference = null): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException('Choose a valid article event.');
        }
        if ($reference !== null) {
            if (! $this->guard->globalOnce('conversion:'.$postId.':'.$reference, 15552000)) {
                return;
            }
        } elseif (! $this->guard->once((str_starts_with($event, 'helpful_') ? 'helpful' : $event).':'.$postId)) {
            return;
        }
        $db = $this->database->getConnection();
        $sql = 'INSERT INTO '.$db->quoteIdentifier($this->table('metric')).' (`post_id`,`day`,`event`,`total`) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE `total` = `total` + 1';
        $db->query($sql, [$postId, gmdate('Y-m-d'), $event]);
    }

    public function summary(int $postId): array
    {
        $db = $this->database->getConnection();
        $rows = $db->fetchAll($db->select()->from($this->table('metric'), ['event', 'total' => new \Zend_Db_Expr('SUM(total)')])->where('post_id = ?', $postId)->group('event'));
        $result = array_fill_keys(self::EVENTS, 0);
        foreach ($rows as $row) {
            $result[$row['event']] = (int) $row['total'];
        }

        return $result;
    }

    public function comments(int $postId): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->table('feedback'), ['feedback_id', 'name', 'body', 'created_at'])->where('post_id = ?', $postId)->where('kind = ?', 'comment')->where('status = ?', 'approved')->order('feedback_id ASC')->limit(100));
    }

    public function comment(array $post, array $input, int $storeId): void
    {
        if (! $this->settings->get()['comments_enabled'] || ! $this->metadata->get($post)['allow_comments']) {
            throw new \InvalidArgumentException('Comments are closed for this article.');
        }
        $this->guard->limit('comment', 5, 3600);
        $name = $this->metadata->text($input['name'] ?? '', 100);
        $body = $this->metadata->text($input['body'] ?? '', 2000);
        if ($name === '' || mb_strlen($body) < 5 || preg_match_all('~https?://~i', $body) > 2) {
            throw new \InvalidArgumentException('Enter your name and a comment between 5 and 2,000 characters, with at most two links.');
        }
        $this->database->getConnection()->insert($this->table('feedback'), ['post_id' => $post['post_id'], 'store_id' => $storeId, 'kind' => 'comment', 'name' => $name, 'body' => $body, 'status' => 'pending']);
    }

    public function moderation(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->table('feedback'))->order('feedback_id DESC')->limit(200));
    }

    public function moderate(int $id, string $status, string $previous): void
    {
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Choose a moderation status.');
        }
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table('feedback'))->where('feedback_id = ?', $id));
        if (! $row || $row['status'] !== $previous) {
            throw new \InvalidArgumentException('This comment changed. Refresh the moderation list.');
        }
        $db->update($this->table('feedback'), ['status' => $status], ['feedback_id = ?' => $id, 'status = ?' => $previous]);
    }

    public function analytics(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from(['m' => $this->table('metric')], ['post_id', 'event', 'total' => new \Zend_Db_Expr('SUM(m.total)')])->joinLeft(['p' => $this->table('post')], 'p.post_id = m.post_id', ['title'])->group(['m.post_id', 'm.event', 'p.title'])->order('total DESC')->limit(600));
    }

    private function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }
}
