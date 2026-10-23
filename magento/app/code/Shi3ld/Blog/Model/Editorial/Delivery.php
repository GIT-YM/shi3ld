<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilderFactory;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;

class Delivery
{
    public function __construct(private ResourceConnection $database, private EncryptorInterface $encryption,
        private Settings $settings, private TransportBuilderFactory $transports, private Escaper $escaper,
        private LockManagerInterface $locks, private LoggerInterface $logger,
        private Repository $posts, private Urls $urls) {}

    public function enqueue(int $subscriberId, string $kind, array $payload, string $key): void
    {
        $db = $this->database->getConnection();
        $db->insertOnDuplicate($this->table('mail'), ['subscriber_id' => $subscriberId, 'kind' => $kind, 'dedupe_key' => $key, 'payload' => $this->encryption->encrypt(json_encode($payload, JSON_THROW_ON_ERROR)), 'next_attempt_at' => gmdate('Y-m-d H:i:s')], ['dedupe_key']);
    }

    public function run(): void
    {
        if (! $this->settings->get()['delivery_enabled'] || ! $this->locks->lock('SHI3LD_BLOG_DELIVERY', 0)) {
            return;
        }
        try {
            $db = $this->database->getConnection();
            $query = $db->select()->from($this->table('mail'))->where('state IN (?)', ['queued', 'retry'])->where('next_attempt_at <= ?', gmdate('Y-m-d H:i:s'))->order('mail_id ASC')->limit(30);
            if (! $this->settings->get()['subscriptions_enabled']) {
                $query->where('kind != ?', 'digest');
            }$rows = $db->fetchAll($query);
            foreach ($rows as $job) {
                try {
                    $subscriber = $db->fetchRow($db->select()->from($this->table('subscriber'))->where('subscriber_id = ?', (int) $job['subscriber_id']));
                    $payload = json_decode($this->encryption->decrypt($job['payload']), true, 16, JSON_THROW_ON_ERROR);
                    $eligible = $subscriber && ($job['kind'] === 'confirm' ? $subscriber['status'] === 'pending' && hash_equals($subscriber['token_hash'], $payload['token_hash'] ?? '') && strtotime($subscriber['token_expires'].' UTC') >= time() : $subscriber['status'] === 'confirmed');
                    if (! $eligible) {
                        $db->update($this->table('mail'), ['state' => 'cancelled'], ['mail_id = ?' => $job['mail_id']]);

                        continue;
                    }
                    if ($job['kind'] === 'digest') {
                        $options = ['sort' => 'manual', 'articles' => array_map('intval', $payload['articles'] ?? []), 'store_id' => (int) $subscriber['store_id']];
                        $categories = json_decode($subscriber['category_ids'] ?: '[]', true, 8, JSON_THROW_ON_ERROR);
                        if ($categories) {
                            $options['category_ids'] = $categories;
                        }
                        $articles = $this->posts->listing($options, 1, 24)['items'];
                        if (! $articles) {
                            $db->update($this->table('mail'), ['state' => 'cancelled'], ['mail_id = ?' => $job['mail_id']]);
                            $db->update($this->table('subscriber'), ['last_digest_at' => $payload['through']], ['subscriber_id = ?' => $subscriber['subscriber_id']]);

                            continue;
                        }
                        $payload['entries'] = '<ul>';
                        foreach ($articles as $article) {
                            $payload['entries'] .= '<li><a href="'.$this->escaper->escapeUrl($this->urls->url('post', $article['slug'], (int) $subscriber['store_id'])).'">'.$this->escaper->escapeHtml($article['title']).'</a></li>';
                        }$payload['entries'] .= '</ul>';
                    }
                    $transport = $this->transports->create()->setTemplateIdentifier('shi3ld_blog_update')->setTemplateOptions(['area' => 'frontend', 'store' => (int) $subscriber['store_id']])->setTemplateVars($payload)
                        ->setFromByScope('general', (int) $subscriber['store_id'])->addTo($subscriber['email'])->getTransport();
                    $transport->sendMessage();
                    $db->update($this->table('mail'), ['state' => 'sent', 'sent_at' => gmdate('Y-m-d H:i:s'), 'attempts' => (int) $job['attempts'] + 1], ['mail_id = ?' => $job['mail_id']]);
                    if ($job['kind'] === 'digest') {
                        $db->update($this->table('subscriber'), ['last_digest_at' => $payload['through']], ['subscriber_id = ?' => $subscriber['subscriber_id']]);
                    }
                } catch (\Throwable $exception) {
                    $attempts = (int) $job['attempts'] + 1;
                    $delay = [60, 300, 900, 3600, 14400][min(4, $attempts - 1)];
                    $db->update($this->table('mail'), ['state' => $attempts >= 5 ? 'failed' : 'retry', 'attempts' => $attempts, 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay)], ['mail_id = ?' => $job['mail_id']]);
                    $this->logger->error('Blog email delivery failed.', ['mail_id' => (int) $job['mail_id'], 'error_type' => $exception::class]);
                }
            }
        } finally {
            $this->locks->unlock('SHI3LD_BLOG_DELIVERY');
        }
    }

    public function queue(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->table('mail'), ['mail_id', 'kind', 'state', 'attempts', 'next_attempt_at', 'sent_at', 'created_at'])->order('mail_id DESC')->limit(100));
    }

    public function retry(int $id): void
    {
        $this->database->getConnection()->update($this->table('mail'), ['state' => 'queued', 'attempts' => 0, 'next_attempt_at' => gmdate('Y-m-d H:i:s')], ['mail_id = ?' => $id, 'state = ?' => 'failed']);
    }

    private function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }
}
