<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Lifecycle;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;

class Maintenance
{
    public function __construct(private ResourceConnection $database, private Settings $settings, private Subscriptions $subscriptions,
        private Delivery $delivery, private Repository $posts, private Urls $urls, private Metadata $metadata,
        private Escaper $escaper, private LockManagerInterface $locks, private Lifecycle $lifecycle,
        private LoggerInterface $logger) {}

    public function run(): void
    {
        if (! $this->locks->lock('SHI3LD_BLOG_MAINTENANCE', 0)) {
            return;
        }
        try {
            $db = $this->database->getConnection();
            $changed = false;
            foreach ($db->fetchAll($db->select()->from($this->table('post'))->where('is_published = ?', 1)->where('editorial IS NOT NULL')->where("JSON_UNQUOTE(JSON_EXTRACT(editorial,'$.retire_at')) != ''")->where("JSON_UNQUOTE(JSON_EXTRACT(editorial,'$.retire_at')) <= ?", gmdate('Y-m-d'))->limit(100)) as $post) {
                $meta = $this->metadata->get($post);
                if ($meta['retire_at'] !== '' && $meta['retire_at'] <= gmdate('Y-m-d')) {
                    $full = $this->posts->post((int) $post['post_id']);
                    $this->posts->unpublish((int) $post['post_id'], $this->posts->fingerprint($full));
                    $changed = true;
                }
            }
            if ($changed) {
                $this->lifecycle->refresh();
            }
            $settings = $this->settings->get();
            $cutoff = gmdate('Y-m-d H:i:s', time() - $settings['retention_days'] * 86400);
            $db->delete($this->table('guard'), ['expires_at < ?' => gmdate('Y-m-d H:i:s')]);
            $db->delete($this->table('presence'), ['expires_at < ?' => gmdate('Y-m-d H:i:s', time() - 3600)]);
            $db->delete($this->table('metric'), ['day < ?' => substr($cutoff, 0, 10)]);
            $db->delete($this->table('feedback'), ['created_at < ?' => $cutoff, 'status != ?' => 'approved']);
            $db->delete($this->table('mail'), ['created_at < ?' => $cutoff, 'state IN (?)' => ['sent', 'cancelled']]);
            $db->delete($this->table('subscriber'), ['updated_at < ?' => gmdate('Y-m-d H:i:s', time() - 2592000), 'status IN (?)' => ['pending', 'unsubscribed']]);
            if (! $settings['delivery_enabled'] || ! $settings['subscriptions_enabled']) {
                return;
            }
            $seconds = $settings['digest'] === 'daily' ? 86400 : 604800;
            $subscribers = $db->fetchAll($db->select()->from($this->table('subscriber'))->where('status = ?', 'confirmed')->where('last_digest_at <= ?', gmdate('Y-m-d H:i:s', time() - $seconds))->order('last_digest_at ASC')->limit(50));
            foreach ($subscribers as $subscriber) {
                $options = ['sort' => 'updated', 'updated_since' => $subscriber['last_digest_at'], 'store_id' => (int) $subscriber['store_id']];
                $categories = json_decode($subscriber['category_ids'] ?: '[]', true, 8, JSON_THROW_ON_ERROR);
                if ($categories) {
                    $options['category_ids'] = $categories;
                }
                $posts = $this->posts->listing($options, 1, 24)['items'];
                $through = gmdate('Y-m-d H:i:s');
                if (! $posts) {
                    $db->update($this->table('subscriber'), ['last_digest_at' => $through], ['subscriber_id = ?' => $subscriber['subscriber_id']]);

                    continue;
                }
                $this->delivery->enqueue((int) $subscriber['subscriber_id'], 'digest', ['title' => 'Your Shi3ld article digest', 'message' => 'New and updated articles in the topics you follow.', 'articles' => array_map(fn ($post) => (int) $post['post_id'], $posts), 'url' => $this->urls->url('post', '', (int) $subscriber['store_id']), 'button' => 'Browse the journal', 'manage_url' => $this->subscriptions->manageUrl($subscriber), 'through' => $through], hash('sha256', 'digest:'.$subscriber['subscriber_id'].':'.$subscriber['last_digest_at']));
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Blog maintenance could not finish.', ['exception' => $exception]);
        } finally {
            $this->locks->unlock('SHI3LD_BLOG_MAINTENANCE');
        }
    }

    private function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }
}
