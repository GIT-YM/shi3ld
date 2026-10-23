<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Security\Model\AdminSessionsManager;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Content\Api\EditorEntityInterface;
use Shi3ld\Content\Model\Editor\Preview;

class AssetEditor implements EditorEntityInterface
{
    public function __construct(private Library $library, private ResourceConnection $database,
        private StoreManagerInterface $stores, private UrlInterface $backend,
        private Auth $auth, private AdminSessionsManager $sessions,
        private Preview $preview) {}

    public function load(int $id): DataObject
    {
        $row = $this->library->row($id);

        return new DataObject(['id' => $id, 'title' => $row['title'], 'identifier' => $row['asset_key']]);
    }

    public function snapshot(int $id): array
    {
        return $this->library->snapshot($id);
    }

    public function publish(int $id, array $payload): void
    {
        $this->library->publish($id, $payload);
    }

    public function validate(array $payload): array
    {
        return [];
    }

    public function url(int $id): string
    {
        $payload = $this->snapshot($id);
        $kind = $payload['builder']['asset']['kind'] ?? 'section';
        $target = $payload['builder']['asset']['target'] ?? 'none';
        $path = $kind === 'global' || ($kind === 'template' && in_array($target, ['header', 'footer'], true)) ? '' : 'site-preview/design/view/id/'.$id;

        return $this->stores->getDefaultStoreView()->getUrl('', ['_direct' => $path, '_nosid' => true]);
    }

    public function navigation(): array
    {
        return array_map(fn ($row) => ['id' => (int) $row['asset_id'], 'title' => ucfirst($row['kind']).': '.$row['title']], array_slice($this->library->all(), 0, 100));
    }

    public function lock(int $id): void
    {
        $db = $this->database->getConnection();
        $db->fetchOne($db->select()->from($this->library->table(), ['asset_id'])->where('asset_id = ?', $id)->forUpdate(true));
    }

    public function configuration(int $id): array
    {
        $payload = $this->snapshot($id);
        $row = $this->library->row($id);
        $preview = $this->preview->start(['type' => 'asset', 'id' => $id, 'identifier' => $row['asset_key'], 'payload' => $payload], $this->auth->getUser(), (int) $this->sessions->getCurrentSession()->getId());
        $target = $payload['builder']['asset']['target'] ?? 'all';

        return ['assetKind' => $row['kind'], 'assetKey' => $row['asset_key'], 'styleOnly' => $row['kind'] === 'global', 'nativeUrl' => null,
            'selector' => '[data-shi3ld-region="'.(in_array($target, ['header', 'footer'], true) ? $target : 'page').'"]',
            'backUrl' => $this->backend->getUrl('shi3ld_content/library/index'), 'liveUrl' => $this->url($id).'?shi3ld_preview='.$preview];
    }
}
