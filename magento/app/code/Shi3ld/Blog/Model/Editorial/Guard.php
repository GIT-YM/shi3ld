<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Lock\LockManagerInterface;

class Guard
{
    public function __construct(private ResourceConnection $database, private LockManagerInterface $locks,
        private RemoteAddress $remote, private Tokens $tokens) {}

    public function limit(string $purpose, int $maximum, int $seconds): void
    {
        $key = $this->key($purpose.':'.intdiv(time(), $seconds));
        $lock = 'BLOG_GUARD_'.substr($key, 0, 30);
        if (! $this->locks->lock($lock, 0)) {
            throw new \InvalidArgumentException('Please wait a moment before trying again.');
        }
        try {
            $db = $this->database->getConnection();
            $table = $this->table();
            $row = $db->fetchRow($db->select()->from($table)->where('guard_key = ?', $key));
            if ((int) ($row['hits'] ?? 0) >= $maximum) {
                throw new \InvalidArgumentException('Too many requests. Please try again later.');
            }
            $db->insertOnDuplicate($table, ['guard_key' => $key, 'hits' => (int) ($row['hits'] ?? 0) + 1, 'expires_at' => gmdate('Y-m-d H:i:s', time() + $seconds + 60)], ['hits', 'expires_at']);
        } finally {
            $this->locks->unlock($lock);
        }
    }

    public function once(string $purpose, int $seconds = 86400): bool
    {
        $key = $this->key($purpose.':'.intdiv(time(), $seconds));
        $sql = 'INSERT IGNORE INTO '.$this->database->getConnection()->quoteIdentifier($this->table()).' (`guard_key`,`hits`,`expires_at`) VALUES (?,1,?)';

        return $this->database->getConnection()->query($sql, [$key, gmdate('Y-m-d H:i:s', time() + $seconds + 60)])->rowCount() === 1;
    }

    public function globalOnce(string $purpose, int $seconds = 86400): bool
    {
        $sql = 'INSERT IGNORE INTO '.$this->database->getConnection()->quoteIdentifier($this->table()).' (`guard_key`,`hits`,`expires_at`) VALUES (?,1,?)';

        return $this->database->getConnection()->query($sql, [$this->tokens->sign('guard', $purpose), gmdate('Y-m-d H:i:s', time() + $seconds)])->rowCount() === 1;
    }

    private function key(string $purpose): string
    {
        return $this->tokens->sign('guard', $purpose.'|'.(string) $this->remote->getRemoteAddress());
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_guard');
    }
}
