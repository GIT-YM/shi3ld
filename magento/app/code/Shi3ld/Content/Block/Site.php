<?php

namespace Shi3ld\Content\Block;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Cms\Block\Block;
use Magento\Framework\DataObject\IdentityInterface;

class Site extends AbstractProduct implements IdentityInterface
{
    private array $catalogueCollections = [];

    public function __construct(Context $context, private CollectionFactory $products, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getProducts(int $limit = 6, bool $paginate = false): Collection
    {
        $requestedPage = filter_var($this->getRequest()->getParam('p', 1), FILTER_VALIDATE_INT);
        $page = $paginate && $requestedPage !== false ? max(1, $requestedPage) : 1;

        $collection = $this->products->create()
            ->addAttributeToSelect(['name', 'price', 'small_image', 'url_key', 'url_path'])
            ->addStoreFilter()
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]])
            ->addMinimalPrice()->addFinalPrice()->addTaxPercents()->addUrlRewrite()
            ->setOrder('entity_id', 'DESC')->setPageSize($limit)->setCurPage($page);

        $this->catalogueCollections[] = $collection;

        return $collection;
    }

    public function cms(string $identifier): string
    {
        return $this->getLayout()->createBlock(Block::class)->setBlockId($identifier)->toHtml();
    }

    public function pager(Collection $collection): string
    {
        return $this->getLayout()->createBlock(\Magento\Theme\Block\Html\Pager::class)
            ->setAvailableLimit([24 => 24])->setLimit(24)->setShowPerPage(false)
            ->setCollection($collection)->toHtml();
    }

    public function getIdentities(): array
    {
        $identities = [Product::CACHE_TAG, \Magento\Cms\Model\Block::CACHE_TAG, \Magento\Cms\Model\Page::CACHE_TAG];

        foreach ($this->catalogueCollections as $collection) {
            foreach ($collection as $product) {
                $identities = array_merge($identities, $product->getIdentities());
            }
        }

        return array_unique($identities);
    }
}
