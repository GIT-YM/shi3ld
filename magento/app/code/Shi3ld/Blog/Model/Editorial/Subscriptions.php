<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class Subscriptions
{
    public function __construct(private ResourceConnection $database, private Settings $settings, private Guard $guard,
        private Tokens $tokens, private Metadata $metadata, private Delivery $delivery, private StoreManagerInterface $stores) {}

    public function request(array $input, int $storeId): void
    {
        $settings = $this->settings->get();
        if (! $settings['subscriptions_enabled'] || ! $settings['delivery_enabled']) {
            throw new \InvalidArgumentException('Email subscriptions are not currently available.');
        }
        $this->guard->limit('subscribe', 3, 3600);
        $email = mb_strtolower($this->metadata->text($input['email'] ?? '', 254));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($input['consent'])) {
            throw new \InvalidArgumentException('Enter a valid email address and confirm your subscription preference.');
        }
        $ids = $this->categories($input['category_ids'] ?? []);
        $hash = hash('sha256', $email);
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('email_hash = ?', $hash)->where('store_id = ?', $storeId));
        if ($row && $row['status'] === 'confirmed') {
            if ($this->guard->globalOnce('subscription-manage:'.$hash.':'.gmdate('Y-m-d'))) {
                $this->delivery->enqueue((int) $row['subscriber_id'], 'manage', ['title' => 'Manage your Shi3ld article subscription', 'message' => 'Use the link below to change your topics or unsubscribe.', 'url' => $this->manageUrl($row), 'button' => 'Manage subscription'], hash('sha256', 'manage:'.$row['subscriber_id'].':'.gmdate('Y-m-d')));
            }

            return;
        }
        $token = bin2hex(random_bytes(32));
        $data = ['email' => $email, 'email_hash' => $hash, 'store_id' => $storeId, 'category_ids' => json_encode($ids, JSON_THROW_ON_ERROR), 'status' => 'pending', 'token_hash' => hash('sha256', $token), 'token_expires' => gmdate('Y-m-d H:i:s', time() + 172800), 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($row) {
            $id = (int) $row['subscriber_id'];
            $db->update($this->table(), $data, ['subscriber_id = ?' => $id]);
        } else {
            $db->insert($this->table(), $data);
            $id = (int) $db->lastInsertId($this->table());
        }
        $url = $this->stores->getStore($storeId)->getUrl('', ['_direct' => 'blog/reader/view', '_query' => ['mode' => 'confirm', 'token' => $token], '_nosid' => true]);
        $this->delivery->enqueue($id, 'confirm', ['title' => 'Confirm your Shi3ld article subscription', 'message' => 'Confirm that you want to receive article digests. This link expires in 48 hours.', 'url' => $url, 'button' => 'Review and confirm subscription', 'token_hash' => hash('sha256', $token)], hash('sha256', 'confirm:'.$id.':'.$token));
    }

    public function inspect(string $mode, string $token): array
    {
        $db = $this->database->getConnection();
        if ($mode === 'confirm') {
            if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
                throw new \InvalidArgumentException('This confirmation link is invalid or expired.');
            }
            $row = $db->fetchRow($db->select()->from($this->table())->where('token_hash = ?', hash('sha256', $token))->where('status = ?', 'pending')->where('token_expires >= ?', gmdate('Y-m-d H:i:s')));
        } else {
            $parts = explode('.', $token);
            $row = count($parts) === 2 && ctype_digit($parts[0]) ? $db->fetchRow($db->select()->from($this->table())->where('subscriber_id = ?', (int) $parts[0])) : false;
            if ($row && ! hash_equals($this->tokens->sign('subscription-manage', $row['subscriber_id'].'|'.$row['email_hash']), $parts[1])) {
                $row = false;
            }
        }
        if (! $row) {
            throw new \InvalidArgumentException('This subscription link is invalid or expired.');
        }

        return $row;
    }

    public function confirm(string $token): void
    {
        $row = $this->inspect('confirm', $token);
        $this->database->getConnection()->update($this->table(), ['status' => 'confirmed', 'token_hash' => '', 'token_expires' => null, 'last_digest_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], ['subscriber_id = ?' => $row['subscriber_id'], 'status = ?' => 'pending']);
    }

    public function manage(string $token, array $input, bool $unsubscribe): void
    {
        $row = $this->inspect('manage', $token);
        $data = $unsubscribe ? ['status' => 'unsubscribed'] : ['category_ids' => json_encode($this->categories($input['category_ids'] ?? []), JSON_THROW_ON_ERROR)];
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->database->getConnection()->update($this->table(), $data, ['subscriber_id = ?' => $row['subscriber_id']]);
    }

    public function manageUrl(array $row): string
    {
        return $this->stores->getStore((int) $row['store_id'])->getUrl('', ['_direct' => 'blog/reader/view', '_query' => ['mode' => 'manage', 'token' => $row['subscriber_id'].'.'.$this->tokens->sign('subscription-manage', $row['subscriber_id'].'|'.$row['email_hash'])], '_nosid' => true]);
    }

    public function overview(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->table(), ['status', 'count' => new \Zend_Db_Expr('COUNT(*)')])->group('status'));
    }

    private function categories(mixed $input): array
    {
        $ids = $this->metadata->ids($input);
        if (! $ids) {
            return [];
        }$db = $this->database->getConnection();
        $valid = array_map('intval', $db->fetchCol($db->select()->from($this->database->getTableName('shi3ld_blog_category'), ['category_id'])->where('is_active = ?', 1)->where('category_id IN (?)', $ids)));
        if (array_diff($ids, $valid)) {
            throw new \InvalidArgumentException('Choose active article categories.');
        }

        return $ids;
    }

    private function table(): string
    {
        return $this->database->getTableName('shi3ld_blog_subscriber');
    }
}
