<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Security\Model\AdminSessionsManager;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\ArticleEditor;
use Shi3ld\Content\Api\EditorEntityInterface;
use Shi3ld\Content\Model\Editor\Preview;

class TranslationEditor implements EditorEntityInterface
{
    public function __construct(private Translations $translations, private ArticleEditor $articles,
        private ResourceConnection $database, private StoreManagerInterface $stores,
        private UrlInterface $backend, private Auth $auth,
        private AdminSessionsManager $sessions, private Preview $preview) {}

    public function load(int $id): DataObject
    {
        $row = $this->translations->row($id);
        if ($row['entity_type'] !== 'post') {
            throw new \InvalidArgumentException('Open category translations in blog management.');
        }
        $payload = $this->snapshot($id);

        return new DataObject(['id' => $id, 'identifier' => $payload['slug'], 'title' => $payload['title'], 'store_id' => (int) $row['store_id']]);
    }

    public function snapshot(int $id): array
    {
        return $this->translations->snapshot($id);
    }

    public function validate(array $payload): array
    {
        return $this->articles->validate($payload);
    }

    public function publish(int $id, array $payload): void
    {
        $this->translations->publish($id, $payload);
    }

    public function url(int $id): string
    {
        $row = $this->translations->row($id);

        return $this->stores->getStore((int) $row['store_id'])->getUrl('', ['_direct' => 'blog/post/view/id/'.$row['entity_id'], '_nosid' => true, '_query' => ['___store' => $this->stores->getStore((int) $row['store_id'])->getCode()]]);
    }

    public function navigation(): array
    {
        $items = [];
        foreach ($this->translations->all('post') as $row) {
            $payload = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
            $items[] = ['id' => (int) $row['translation_id'], 'title' => $this->stores->getStore((int) $row['store_id'])->getName().': '.$payload['title']];
        }

        return $items;
    }

    public function lock(int $id): void
    {
        $db = $this->database->getConnection();
        $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_blog_translation'), ['translation_id'])->where('translation_id = ?', $id)->forUpdate(true));
    }

    public function configuration(int $id): array
    {
        $payload = $this->snapshot($id);
        $nonce = $this->preview->start(['type' => 'blog_locale', 'id' => $id, 'identifier' => $payload['slug'], 'payload' => $payload], $this->auth->getUser(), (int) $this->sessions->getCurrentSession()->getId());

        return ['article' => true, 'articlePublished' => (bool) $this->translations->row($id)['is_published'], 'pageBuilder' => true, 'nativeUrl' => null, 'selector' => '[data-shi3ld-region="page"]', 'categories' => $this->database->getConnection()->fetchAll($this->database->getConnection()->select()->from($this->database->getTableName('shi3ld_blog_category'))->order('title ASC')),
            'backUrl' => $this->backend->getUrl('shi3ld_blog/studio/index'), 'liveUrl' => $this->url($id).'&shi3ld_preview='.$nonce];
    }
}
