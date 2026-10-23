<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\BlockFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Entities
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private BlockRepositoryInterface $blocks,
        private PageFactory $pageFactory,
        private BlockFactory $blockFactory,
        private StoreManagerInterface $stores,
        private array $providers = []
    ) {}

    public function load(string $type, int $id): \Magento\Cms\Api\Data\PageInterface|\Magento\Cms\Api\Data\BlockInterface|\Magento\Framework\DataObject
    {
        if (isset($this->providers[$type])) {
            return $this->providers[$type]->load($id);
        }
        if ($id <= 0 || !in_array($type, ['page', 'block'], true)) {
            throw new LocalizedException(__('Choose a valid CMS page or shared region.'));
        }
        $entity = $type === 'page' ? $this->pageFactory->create() : $this->blockFactory->create();
        $entity->getResource()->load($entity, $id);
        if (!$entity->getId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('This CMS page or block no longer exists.'));
        }
        if ($type === 'block' && !in_array($entity->getIdentifier(), ['shi3ld_global_header','shi3ld_global_footer'], true)) {
            throw new LocalizedException(__('This block is managed with the native CMS block editor.'));
        }
        return $entity;
    }

    public function snapshot(string $type, int $id): array
    {
        if (isset($this->providers[$type])) {
            return $this->providers[$type]->snapshot($id);
        }
        $entity = $this->load($type, $id);
        return [
            'html' => (string) $entity->getContent(), 'title' => (string) $entity->getTitle(),
            'meta_title' => $type === 'page' ? (string) $entity->getMetaTitle() : '',
            'meta_description' => $type === 'page' ? (string) $entity->getMetaDescription() : '',
            'content_heading' => $type === 'page' ? (string) $entity->getContentHeading() : '',
        ];
    }

    public function publish(string $type, int $id, array $payload): void
    {
        if (isset($this->providers[$type])) {
            $this->providers[$type]->publish($id, $payload);
            return;
        }
        $entity = $this->load($type, $id);
        $entity->setContent($payload['html'])->setTitle($payload['title']);
        if ($type === 'page') {
            $entity->setMetaTitle($payload['meta_title'])->setMetaDescription($payload['meta_description'])
                ->setContentHeading($payload['content_heading']);
            $this->pages->save($entity);
        } else {
            $this->blocks->save($entity);
        }
    }

    public function url(string $type, int $id): string
    {
        if (isset($this->providers[$type])) {
            return $this->providers[$type]->url($id);
        }
        $store = $this->stores->getDefaultStoreView();
        $entity = $this->load($type, $id);
        $path = $type === 'block' || $entity->getIdentifier() === 'shi3ld-home' ? '' : $entity->getIdentifier();
        return $store->getUrl('', ['_direct' => $path, '_nosid' => true]);
    }

    public function navigation(): array
    {
        $result = [];
        foreach ($this->pageFactory->create()->getCollection()->addFieldToFilter('is_active', 1) as $page) {
            $result[] = ['type' => 'page', 'id' => (int) $page->getId(), 'title' => $page->getTitle()];
        }
        foreach ($this->blockFactory->create()->getCollection()->addFieldToFilter('identifier', ['in' => ['shi3ld_global_header','shi3ld_global_footer']]) as $block) {
            $result[] = ['type' => 'block', 'id' => (int) $block->getId(), 'title' => $block->getTitle()];
        }
        foreach ($this->providers as $type => $provider) {
            foreach ($provider->navigation() as $item) {
                $result[] = ['type' => $type] + $item;
            }
        }
        return $result;
    }

    public function validateExtras(string $type, array $payload): array
    {
        return isset($this->providers[$type]) ? $this->providers[$type]->validate($payload) : [];
    }

    public function configuration(string $type, int $id): array
    {
        return isset($this->providers[$type]) ? $this->providers[$type]->configuration($id) : [];
    }

    public function lock(string $type, int $id, \Magento\Framework\App\ResourceConnection $database): void
    {
        if (isset($this->providers[$type])) {
            $this->providers[$type]->lock($id);
            return;
        }
        $table = $database->getTableName($type === 'page' ? 'cms_page' : 'cms_block');
        $key = $type === 'page' ? 'page_id' : 'block_id';
        $database->getConnection()->fetchOne($database->getConnection()->select()->from($table, [$key])->where($key.' = ?', $id)->forUpdate(true));
    }

    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
