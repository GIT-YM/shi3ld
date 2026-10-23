<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Security\Model\AdminSessionsManager;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Editorial\Metadata;
use Shi3ld\Content\Api\EditorEntityInterface;
use Shi3ld\Content\Model\Editor\Preview;

class ArticleEditor implements EditorEntityInterface
{
    public function __construct(
        private Repository $posts, private Urls $urls, private ResourceConnection $database,
        private Filesystem $filesystem, private UrlInterface $backendUrl,
        private StoreManagerInterface $stores,
        private Auth $auth,
        private AdminSessionsManager $sessions,
        private Preview $preview, private Metadata $metadata
    ) {}

    public function load(int $id): DataObject
    {
        $post = $this->posts->post($id);

        return new DataObject($post + ['id' => $id, 'identifier' => $post['slug']]);
    }

    public function snapshot(int $id): array
    {
        $post = $this->posts->post($id);
        $payload = ['html' => $post['content'], 'title' => $post['title'], 'meta_title' => $post['meta_title'],
            'meta_description' => (string) $post['meta_description'], 'content_heading' => '',
            'slug' => $post['slug'], 'excerpt' => (string) $post['excerpt'], 'featured_image' => $post['featured_image'],
            'featured_alt' => $post['featured_alt'], 'author' => $post['author'], 'is_featured' => (int) $post['is_featured'],
            'category_ids' => $post['category_ids']];
        if ($post['editorial'] !== null) {
            $payload['editorial'] = $this->metadata->get($post);
        }

        return $payload;
    }

    public function publish(int $id, array $payload): void
    {
        $this->posts->publish($id, $payload);
    }

    public function url(int $id): string
    {
        return $this->urls->url('post', $this->posts->post($id)['slug'], (int) $this->stores->getDefaultStoreView()->getId());
    }

    public function navigation(): array
    {
        return array_map(fn ($row) => ['id' => (int) $row['post_id'], 'title' => 'Article: '.$row['title']], $this->posts->listing([], 1, 100, false)['items']);
    }

    public function lock(int $id): void
    {
        $db = $this->database->getConnection();
        $db->fetchOne($db->select()->from($this->posts->table('post'), ['post_id'])->where('post_id = ?', $id)->forUpdate(true));
    }

    public function validate(array $payload): array
    {
        $result = [];
        foreach (['slug' => 180, 'excerpt' => 3000, 'featured_image' => 512, 'featured_alt' => 300, 'author' => 150] as $key => $limit) {
            $value = $payload[$key] ?? '';
            if (! is_string($value) || mb_strlen($value) > $limit) {
                throw new LocalizedException(__('Enter a valid article %1.', str_replace('_', ' ', $key)));
            }
            $result[$key] = trim($value);
        }
        if ($result['slug'] !== '' && ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $result['slug'])) {
            throw new LocalizedException(__('Use lowercase letters, numbers and hyphens for the article URL key. Leave it empty to generate one.'));
        }
        $image = $result['featured_image'];
        if (preg_match('~^\{\{media\s+url=[\x22\x27]?([^\x22\x27}]+)[\x22\x27]?\}\}$~D', $image, $match)) {
            $image = $match[1];
        }
        if ($image !== '' && (! preg_match('~^wysiwyg/[a-zA-Z0-9_./-]+\.(?:jpe?g|png|gif|webp)$~iD', $image)
            || str_contains($image, '..') || ! $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile($image))) {
            throw new LocalizedException(__('Choose a featured image from the Magento gallery.'));
        }
        $result['featured_image'] = $image;
        $ids = $payload['category_ids'] ?? [];
        if (! is_array($ids) || count($ids) > 100) {
            throw new LocalizedException(__('Choose valid blog categories.'));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $validIds = array_map(fn ($row) => (int) $row['category_id'], $this->posts->categories(false));
        if (array_diff($ids, $validIds)) {
            throw new LocalizedException(__('One of the selected blog categories no longer exists.'));
        }
        $result['category_ids'] = $ids;
        $result['is_featured'] = empty($payload['is_featured']) ? 0 : 1;
        if (array_key_exists('editorial', $payload)) {
            $result['editorial'] = $this->metadata->validate((array) $payload['editorial']);
        }

        return $result;
    }

    public function configuration(int $id): array
    {
        $previewId = $this->preview->start(['type' => 'article', 'id' => $id, 'identifier' => $this->posts->post($id)['slug'],
            'payload' => $this->snapshot($id)], $this->auth->getUser(), (int) $this->sessions->getCurrentSession()->getId());

        return ['article' => true, 'articlePublished' => (bool) $this->posts->post($id)['is_published'], 'pageBuilder' => true, 'nativeUrl' => null,
            'categories' => $this->posts->categories(false), 'backUrl' => $this->backendUrl->getUrl('shi3ld_blog/article/index'),
            'liveUrl' => $this->url($id).'?shi3ld_preview='.$previewId];
    }
}
