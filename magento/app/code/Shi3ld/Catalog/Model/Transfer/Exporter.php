<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class Exporter
{
    public function __construct(
        private CollectionFactory $collection,
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private Configurable $configurable,
        private Attributes $attributes,
        private Package $packages,
        private Environment $environment,
        private Filesystem $filesystem,
        private StoreManagerInterface $stores,
        private SourceItemRepositoryInterface $inventory,
        private SearchCriteriaBuilder $criteria,
        private TaxClassRepositoryInterface $taxClasses
    ) {
    }

    public function export(array $skus = [], bool $includeSamples = false, bool $prices = false, bool $stock = false, ?string $name = null): string
    {
        $collection = $this->collection->create()->setStoreId(0)->addAttributeToSelect('sku')
            ->addWebsiteFilter((int) $this->stores->getDefaultStoreView()->getWebsiteId());
        if ($skus) {
            $collection->addFieldToFilter('sku', ['in' => $skus]);
        } elseif (!$includeSamples) {
            $collection->addFieldToFilter('sku', ['nlike' => 'SHI3LD-SAMPLE-%']);
        }
        $selected = [];
        foreach ($collection as $product) {
            $selected[$product->getSku()] = (int) $product->getId();
        }
        if ($skus && array_diff($skus, array_keys($selected))) {
            throw new \RuntimeException('Some selected SKUs do not exist in this environment.');
        }
        if (!$selected) {
            throw new \RuntimeException('No products were selected.');
        }
        foreach ($selected as $id) {
            $children = $this->configurable->getChildrenIds($id)[0] ?? [];
            if ($children) {
                foreach ($this->collection->create()->addFieldToFilter('entity_id', ['in' => array_values($children)]) as $child) {
                    $selected[$child->getSku()] = (int) $child->getId();
                }
            }
        }
        if (count($selected) > Package::MAX_PRODUCTS) {
            throw new \RuntimeException('Select at most ' . Package::MAX_PRODUCTS . ' products including variants.');
        }
        if (!$includeSamples && array_filter(array_keys($selected), fn (string $sku): bool => str_starts_with($sku, 'SHI3LD-SAMPLE-'))) {
            throw new \RuntimeException('Select Include development samples to export sample SKUs.');
        }

        $setNames = array_flip($this->attributes->sets());
        $sets = [];
        $definitions = [];
        $records = [];
        $categoryRecords = [];
        $images = [];
        $warnings = [];
        foreach ($selected as $sku => $id) {
            $product = $this->products->get($sku, false, 0, true);
            if (!in_array($product->getTypeId(), ['simple', 'configurable'], true) || $product->getOptions()) {
                throw new \RuntimeException('Transfer supports simple/configurable products without custom options. Unsupported SKU: ' . $sku);
            }
            $setName = $setNames[(int) $product->getAttributeSetId()] ?? null;
            if ($setName === null) {
                throw new \RuntimeException('Unable to resolve the attribute set for ' . $sku);
            }
            if (!isset($sets[$setName])) {
                $setDefinitions = $this->attributes->describeSet((int) $product->getAttributeSetId());
                $definitions = array_replace($definitions, $setDefinitions);
                $sets[$setName] = array_keys($setDefinitions);
            }
            $values = [];
            $priceValues = [];
            foreach (array_unique(array_merge(Attributes::CONTENT, Attributes::PRICE, $sets[$setName])) as $code) {
                if ($this->attributes->isPrice($code, $definitions)) {
                    if ($prices) {
                        $priceValues[$code] = $product->getData($code);
                    }
                } elseif (isset($definitions[$code])) {
                    $values[$code] = $this->attributes->encodeValue($code, $product->getData($code));
                } else {
                    $values[$code] = $product->getData($code);
                }
            }
            $supported = array_merge(Attributes::CONTENT, Attributes::PRICE, array_keys($definitions), ['sku', 'name', 'status', 'visibility', 'image', 'small_image', 'thumbnail', 'swatch_image']);
            foreach ($product->getAttributes() as $attribute) {
                $code = $attribute->getAttributeCode();
                if ((int) $attribute->getIsUserDefined() && !in_array($code, $supported, true)
                    && $product->getData($code) !== null && $product->getData($code) !== '') {
                    $warnings[$code] = 'Custom attribute ' . $code . ' uses unsupported storage or behaviour and is excluded. Transfer its data separately.';
                }
            }
            $assignedCategories = [];
            foreach ($product->getCategoryIds() as $categoryId) {
                $path = $this->describeCategory((int) $categoryId, $categoryRecords);
                if ($path) {
                    $assignedCategories[] = $path;
                }
            }
            $config = null;
            if ($product->getTypeId() === 'configurable') {
                $codes = [];
                foreach ($product->getTypeInstance()->getConfigurableAttributes($product) as $attribute) {
                    $codes[] = (string) $attribute->getProductAttribute()->getAttributeCode();
                }
                $childIds = array_values($this->configurable->getChildrenIds($id)[0] ?? []);
                $config = ['attributes' => $codes, 'children' => array_keys(array_intersect($selected, $childIds))];
            }
            $sourceItems = [];
            if ($stock) {
                foreach ($this->inventory->getList($this->criteria->addFilter('sku', $sku)->create())->getItems() as $item) {
                    $sourceItems[] = ['source_code' => $item->getSourceCode(), 'quantity' => (float) $item->getQuantity(), 'status' => (int) $item->getStatus()];
                }
            }
            $taxClass = (int) $product->getTaxClassId();
            $records[] = [
                'sku' => $sku, 'type' => $product->getTypeId(), 'attribute_set' => $setName,
                'name' => $product->getName(), 'status' => (int) $product->getStatus(), 'visibility' => (int) $product->getVisibility(),
                'tax_class' => $taxClass ? $this->taxClasses->get($taxClass)->getClassName() : null,
                'values' => $values, 'categories' => $assignedCategories, 'configurable' => $config,
                'media' => $this->describeImages($product, $images), 'prices' => $priceValues, 'inventory' => $sourceItems,
            ];
        }
        uasort($categoryRecords, fn (array $a, array $b): int => count($a['path']) <=> count($b['path']));
        $data = [
            'format' => 'shi3ld.catalogue', 'version' => 1, 'created_at' => gmdate(DATE_ATOM),
            'source' => ['environment' => $this->environment->getName(), 'host' => $this->environment->getHost(), 'currency' => $this->environment->getCurrency()],
            'includes' => ['prices' => $prices, 'stock' => $stock, 'samples' => $includeSamples],
            'warnings' => array_values($warnings),
            'attributes' => $definitions, 'attribute_sets' => $sets, 'categories' => array_values($categoryRecords), 'products' => $records,
        ];
        return $this->packages->write($data, $images, $name ?? 'catalogue-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip');
    }

    private function describeCategory(int $id, array &$records): array
    {
        $root = (int) $this->stores->getDefaultStoreView()->getRootCategoryId();
        $ancestors = [];
        $seen = [];
        while ($id > 1 && $id !== $root) {
            if (isset($seen[$id]) || count($seen) >= 20) {
                throw new \RuntimeException('A category hierarchy contains a cycle or is too deep.');
            }
            $seen[$id] = true;
            $item = $this->categories->get($id, 0);
            array_unshift($ancestors, $item);
            $id = (int) $item->getParentId();
        }
        $path = [];
        foreach ($ancestors as $item) {
            $key = (string) $item->getUrlKey();
            if ($key === '') {
                throw new \RuntimeException('A category has no URL key: ' . $item->getName());
            }
            $path[] = $key;
            $records[implode('/', $path)] = [
                'path' => $path, 'name' => $item->getName(), 'url_key' => $key,
                'description' => $item->getDescription(), 'meta_title' => $item->getMetaTitle(),
                'meta_description' => $item->getMetaDescription(), 'meta_keywords' => $item->getMetaKeywords(),
                'is_active' => (bool) $item->getIsActive(), 'include_in_menu' => (bool) $item->getIncludeInMenu(),
            ];
        }
        return $path;
    }

    private function describeImages(Product $product, array &$images): array
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $root = realpath($directory->getAbsolutePath('catalog/product'));
        $result = [];
        foreach ($product->getMediaGalleryEntries() ?? [] as $entry) {
            if ($entry->getMediaType() !== 'image') {
                throw new \RuntimeException('Product videos require a separate transfer: ' . $product->getSku());
            }
            $file = realpath($directory->getAbsolutePath('catalog/product/' . ltrim((string) $entry->getFile(), '/')));
            if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
                throw new \RuntimeException('A product image file is missing: ' . $product->getSku());
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
            if (!isset(Package::IMAGE_TYPES[$mime])) {
                throw new \RuntimeException('Catalogue images must be JPEG, PNG or GIF: ' . $product->getSku());
            }
            $hash = hash_file('sha256', $file);
            $asset = 'media/' . $hash . '.' . Package::IMAGE_TYPES[$mime];
            $images[$asset] = $file;
            $result[] = [
                'asset' => $asset, 'sha256' => $hash, 'mime' => $mime, 'label' => (string) $entry->getLabel(),
                'position' => (int) $entry->getPosition(), 'disabled' => (bool) $entry->getDisabled(),
                'roles' => array_values(array_intersect($entry->getTypes() ?? [], ['image', 'small_image', 'thumbnail', 'swatch_image'])),
            ];
        }
        return $result;
    }
}
