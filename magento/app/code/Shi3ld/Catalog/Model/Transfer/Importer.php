<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\ConfigInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class Importer
{
    public function __construct(
        private Package $packages,
        private Attributes $attributes,
        private Environment $environment,
        private ProductRepositoryInterface $products,
        private ProductFactory $productFactory,
        private CategoryRepositoryInterface $categories,
        private CategoryFactory $categoryFactory,
        private CategoryCollectionFactory $categoryCollection,
        private StoreManagerInterface $stores,
        private SourceRepositoryInterface $sources,
        private SourceItemInterfaceFactory $sourceItemFactory,
        private SourceItemsSaveInterface $sourceItems,
        private InventoryContext $inventoryContext,
        private OptionInterfaceFactory $optionFactory,
        private OptionValueInterfaceFactory $optionValues,
        private ProductAttributeMediaGalleryEntryInterfaceFactory $galleryEntries,
        private ImageContentInterfaceFactory $imageContents,
        private Filesystem $filesystem,
        private TaxClassRepositoryInterface $taxClasses,
        private SearchCriteriaBuilder $criteria,
        private LockManagerInterface $locks,
        private IndexerRegistry $indexers,
        private ConfigInterface $indexerConfig,
        private CacheManager $caches,
        private ManagerInterface $events
    ) {
    }

    public function preview(string $file, bool $prices = false, bool $stock = false, bool $allowSameEnvironment = false): array
    {
        $data = $this->packages->read($file);
        $errors = $this->attributes->errors($data);
        $flow = $this->environment->getFlowError((string) ($data['source']['environment'] ?? ''), $allowSameEnvironment);
        if ($flow !== null) {
            $errors[] = $flow;
        }
        if ($prices && (empty($data['includes']['prices']) || ($data['source']['currency'] ?? '') !== $this->environment->getCurrency())) {
            $errors[] = 'Price import requires packaged prices and matching base currencies.';
        }
        if ($stock && empty($data['includes']['stock'])) {
            $errors[] = 'The package does not contain stock quantities.';
        }

        $categoryPaths = [];
        foreach ($data['categories'] as $category) {
            $path = $this->pathKey($category['path'] ?? null);
            if ($path === null || ($category['url_key'] ?? null) !== end($category['path']) || !is_string($category['name'] ?? null)
                || $category['name'] === '' || mb_strlen($category['name']) > 255 || isset($categoryPaths[$path])) {
                $errors[] = 'Invalid or duplicate category definition.';
                continue;
            }
            $categoryPaths[$path] = true;
        }
        foreach (array_keys($categoryPaths) as $path) {
            if (str_contains($path, '/') && !isset($categoryPaths[substr($path, 0, strrpos($path, '/'))])) {
                $errors[] = 'A category is missing its parent: ' . $path;
            }
        }

        $rows = [];
        $records = [];
        $fingerprints = [];
        $assets = [];
        $newCount = 0;
        $knownSources = [];
        $knownTaxClasses = [];
        $sets = $this->attributes->sets();
        foreach ($data['products'] as $record) {
            if (!is_array($record) || !is_string($record['sku'] ?? null) || trim($record['sku']) === ''
                || mb_strlen($record['sku']) > 64 || preg_match('/[\x00-\x1f\x7f]/', $record['sku'])
                || isset($records[$record['sku']]) || !in_array($record['type'] ?? '', ['simple', 'configurable'], true)
                || !is_string($record['name'] ?? null) || trim($record['name']) === '' || mb_strlen($record['name']) > 255
                || !isset($data['attribute_sets'][$record['attribute_set'] ?? ''])
                || !is_array($record['values'] ?? null) || !is_array($record['prices'] ?? null)
                || !is_array($record['inventory'] ?? null) || !is_array($record['media'] ?? null)
                || !is_array($record['categories'] ?? null)
                || (($record['tax_class'] ?? null) !== null && (!is_string($record['tax_class']) || mb_strlen($record['tax_class']) > 255))
                || !in_array($record['status'] ?? null, [1, 2], true) || !in_array($record['visibility'] ?? null, [1, 2, 3, 4], true)
            ) {
                $errors[] = 'A product record has invalid or duplicate fields.';
                continue;
            }
            $sku = $record['sku'];
            $records[$sku] = $record;
            if ($this->environment->getName() === 'production' && str_starts_with($sku, 'SHI3LD-SAMPLE-')) {
                $errors[] = 'Development sample products cannot be promoted into production: ' . $sku;
            }
            $existing = $this->find($sku);
            if ($existing) {
                if ($existing->getSku() !== $sku) {
                    $errors[] = 'Use the same SKU letter case on both environments: ' . $sku;
                }
                if ($existing->getTypeId() !== $record['type'] || ($sets[$record['attribute_set']] ?? null) !== (int) $existing->getAttributeSetId()) {
                    $errors[] = 'An existing SKU has a different product type or attribute set: ' . $sku;
                }
                if (!in_array((int) $this->stores->getDefaultStoreView()->getWebsiteId(), array_map('intval', $existing->getWebsiteIds()), true)) {
                    $errors[] = 'An existing SKU belongs to another website: ' . $sku;
                }
                $fingerprints[$sku] = [$existing->getId(), $existing->getUpdatedAt(), $existing->getTypeId(), $existing->getAttributeSetId()];
            } else {
                $newCount++;
                $fingerprints[$sku] = null;
            }
            if (($prices || !$existing) && !empty($record['tax_class']) && !isset($knownTaxClasses[$record['tax_class']])) {
                try {
                    $this->taxClassId($record['tax_class']);
                    $knownTaxClasses[$record['tax_class']] = true;
                } catch (\RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
            foreach ($record['values'] as $code => $value) {
                if ((!in_array($code, Attributes::CONTENT, true) && !isset($data['attributes'][$code]))
                    || $this->attributes->isPrice($code, $data['attributes'])
                ) {
                    $errors[] = 'An unsupported content attribute was supplied for ' . $sku . ': ' . $code;
                    continue;
                }
                $input = $data['attributes'][$code]['input'] ?? 'text';
                if ($input === 'multiselect') {
                    if ($value !== null && (!is_array($value) || count(array_filter($value, 'is_string')) !== count($value))) {
                        $errors[] = 'Invalid multiselect value for ' . $sku . ': ' . $code;
                    }
                } elseif ($value !== null && !is_scalar($value)) {
                    $errors[] = 'Invalid attribute value for ' . $sku . ': ' . $code;
                }
                if (in_array($input, ['select', 'multiselect'], true) && $value !== null) {
                    foreach (is_array($value) ? $value : [$value] as $label) {
                        if (!in_array($label, $data['attributes'][$code]['options'], true)) {
                            $errors[] = 'An option is missing from the package for ' . $sku . ': ' . $code;
                        }
                    }
                }
            }
            foreach ($record['prices'] as $code => $value) {
                if (!$this->attributes->isPrice($code, $data['attributes']) || ($value !== null && !is_scalar($value))) {
                    $errors[] = 'An unsupported price attribute was supplied for ' . $sku . ': ' . $code;
                } elseif ($value !== null && !in_array($code, ['special_from_date', 'special_to_date'], true)
                    && (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0)) {
                    $errors[] = 'A price must be a non-negative number for ' . $sku . ': ' . $code;
                }
            }
            if ($prices && $record['type'] === 'simple' && (!isset($record['prices']['price']) || !is_numeric($record['prices']['price']) || (float) $record['prices']['price'] < 0)) {
                $errors[] = 'A simple product has no valid packaged price: ' . $sku;
            }
            foreach ($record['categories'] ?? [] as $path) {
                $key = $this->pathKey($path);
                if ($key === null || !isset($categoryPaths[$key])) {
                    $errors[] = 'A product references an unknown category: ' . $sku;
                }
            }
            foreach ($record['media'] as $image) {
                $assets[$image['asset']] = true;
                if (!is_array($image['roles'] ?? null) || array_diff($image['roles'], ['image', 'small_image', 'thumbnail', 'swatch_image'])
                    || !is_string($image['label'] ?? null) || mb_strlen($image['label']) > 255) {
                    $errors[] = 'Invalid image roles or label for ' . $sku;
                }
            }
            if ($stock) {
                foreach ($record['inventory'] as $item) {
                    if (!is_string($item['source_code'] ?? null) || !is_numeric($item['quantity'] ?? null)
                        || (float) $item['quantity'] < 0 || !in_array($item['status'] ?? null, [0, 1], true)) {
                        $errors[] = 'Invalid stock data for ' . $sku;
                        continue;
                    }
                    if (!isset($knownSources[$item['source_code']])) {
                        try {
                            $this->sources->get($item['source_code']);
                            $knownSources[$item['source_code']] = true;
                        } catch (NoSuchEntityException $exception) {
                            $errors[] = 'Configure the target inventory source before importing: ' . $item['source_code'];
                        }
                    }
                }
            }
            $rows[] = ['sku' => $sku, 'name' => $record['name'], 'type' => $record['type'], 'action' => $existing ? 'Update content' : 'Create product'];
        }
        foreach ($records as $sku => $record) {
            if ($record['type'] !== 'configurable') {
                continue;
            }
            $config = $record['configurable'] ?? [];
            if (!is_array($config) || !is_array($config['attributes'] ?? null) || !is_array($config['children'] ?? null)
                || !$config['attributes'] || !$config['children']) {
                $errors[] = 'A configurable product has no attributes or variants: ' . $sku;
                continue;
            }
            foreach ($config['attributes'] as $code) {
                if (($data['attributes'][$code]['input'] ?? '') !== 'select' || ($data['attributes'][$code]['scope'] ?? null) !== 1) {
                    $errors[] = 'Configurable products require global dropdown attributes: ' . $sku;
                }
            }
            $combinations = [];
            foreach ($config['children'] as $childSku) {
                $child = $records[$childSku] ?? null;
                if (!$child || $child['type'] !== 'simple') {
                    $errors[] = 'A configurable product references a missing simple variant: ' . $sku;
                    continue;
                }
                $combination = [];
                foreach ($config['attributes'] as $code) {
                    if (!isset($child['values'][$code])) {
                        $errors[] = 'A variant has no value for ' . $code . ': ' . $childSku;
                    }
                    $combination[] = $child['values'][$code] ?? null;
                }
                $key = json_encode($combination, JSON_THROW_ON_ERROR);
                if (isset($combinations[$key])) {
                    $errors[] = 'A configurable product has duplicate variant combinations: ' . $sku;
                }
                $combinations[$key] = true;
            }
        }
        ksort($fingerprints);
        $sets = $this->attributes->sets();
        return [
            'source' => $data['source'], 'created_at' => $data['created_at'] ?? '', 'target' => $this->environment->getName(),
            'new' => $newCount, 'update' => count($rows) - $newCount, 'rows' => $rows,
            'categories' => count($categoryPaths), 'images' => count($assets),
            'new_attributes' => array_values(array_filter(array_keys($data['attributes']), fn (string $code): bool => !$this->attributes->get($code)->getId())),
            'new_sets' => array_values(array_diff(array_keys($data['attribute_sets']), array_keys($sets))),
            'prices' => $prices, 'stock' => $stock, 'new_disabled' => (!$prices || !$stock) ? $newCount : 0,
            'fingerprint' => hash('sha256', json_encode($fingerprints, JSON_THROW_ON_ERROR)),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_filter($data['warnings'] ?? [], 'is_string')),
        ];
    }

    public function apply(string $file, bool $prices, bool $stock, string $fingerprint, string $confirmHost = '', bool $allowSameEnvironment = false, ?callable $progress = null): array
    {
        if (!$this->locks->lock('SHI3LD_CATALOGUE_TRANSFER', 0)) {
            throw new \RuntimeException('Another catalogue import is running. Try again after it finishes.');
        }
        $originalStore = $this->stores->getStore()->getId();
        try {
            $this->stores->setCurrentStore(0);
            $plan = $this->preview($file, $prices, $stock, $allowSameEnvironment);
            if ($plan['errors']) {
                throw new \RuntimeException(implode("\n", $plan['errors']));
            }
            if (!hash_equals($plan['fingerprint'], $fingerprint)) {
                throw new \RuntimeException('Target products changed after preview. Upload and review the package again.');
            }
            if ($this->environment->getName() === 'production' && !hash_equals($this->environment->getHost(), $confirmHost)) {
                throw new \RuntimeException('Type the production hostname to confirm this import.');
            }
            $data = $this->packages->read($file);
            $sets = $this->attributes->apply($data);
            $categoryIds = $this->applyCategories($data['categories']);
            $records = $data['products'];
            usort($records, fn (array $a, array $b): int => ($a['type'] === 'configurable' ? 1 : 0) <=> ($b['type'] === 'configurable' ? 1 : 0));
            $saved = [];
            $inventory = [];
            foreach ($records as $record) {
                $product = $this->find($record['sku']);
                $new = $product === null;
                if ($new) {
                    $product = $this->productFactory->create()->setStoreId(0)->setSku($record['sku'])
                        ->setTypeId($record['type'])->setAttributeSetId($sets[$record['attribute_set']])
                        ->setWebsiteIds([(int) $this->stores->getDefaultStoreView()->getWebsiteId()])
                        ->setStatus($prices && $stock ? $record['status'] : 2)->setVisibility($record['visibility'])
                        ->setPrice(0)->setStockData($record['type'] === 'configurable'
                            ? ['is_in_stock' => 1, 'manage_stock' => 0, 'use_config_manage_stock' => 0]
                            : ['qty' => 0, 'is_in_stock' => 0, 'manage_stock' => 1, 'use_config_manage_stock' => 0]);
                } else {
                    $product->unsetData('stock_data');
                    $extension = $product->getExtensionAttributes();
                    if ($extension !== null && method_exists($extension, 'setStockItem')) {
                        $extension->setStockItem(null);
                    }
                }
                $product->setName($record['name']);
                foreach ($record['values'] as $code => $value) {
                    $product->setData($code, isset($data['attributes'][$code]) ? $this->attributes->decodeValue($code, $value) : $value);
                }
                if ($prices) {
                    foreach ($record['prices'] as $code => $value) {
                        $product->setData($code, $value);
                    }
                }
                if ($new || $prices) {
                    $product->setTaxClassId($this->taxClassId($record['tax_class'] ?? null));
                }
                $assigned = array_map(fn (array $path): int => $categoryIds[implode('/', $path)], $record['categories'] ?? []);
                $product->setCategoryIds(array_values(array_unique(array_merge(array_map('intval', $product->getCategoryIds() ?? []), $assigned))));
                $this->applyImages($product, $record['media'], $file);
                if ($record['type'] === 'configurable') {
                    $this->applyConfiguration($product, $record['configurable'], $saved);
                }
                $product = $new ? $this->products->save($product)
                    : $this->inventoryContext->saveExisting((string) $product->getSku(), fn (): Product => $this->products->save($product));
                $saved[$record['sku']] = $product;
                if ($stock) {
                    foreach ($record['inventory'] as $item) {
                        $sourceItem = $this->sourceItemFactory->create();
                        $sourceItem->setSku((string) $product->getSku());
                        $sourceItem->setSourceCode($item['source_code']);
                        $sourceItem->setQuantity((float) $item['quantity']);
                        $sourceItem->setStatus($item['status']);
                        $inventory[] = $sourceItem;
                    }
                }
                if ($progress !== null) {
                    $progress($record['sku']);
                }
            }
            if ($inventory) {
                $this->sourceItems->execute($inventory);
            }
            $this->refresh();
            return ['created' => $plan['new'], 'updated' => $plan['update'], 'saved' => array_keys($saved), 'new_disabled' => $plan['new_disabled']];
        } finally {
            $this->stores->setCurrentStore($originalStore);
            $this->locks->unlock('SHI3LD_CATALOGUE_TRANSFER');
        }
    }

    private function find(string $sku): ?Product
    {
        try {
            return $this->products->get($sku, false, 0, true);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }

    private function pathKey(mixed $path): ?string
    {
        if (!is_array($path) || !$path || count($path) > 20 || !array_is_list($path)) {
            return null;
        }
        foreach ($path as $part) {
            if (!is_string($part) || $part === '' || mb_strlen($part) > 255 || preg_match('#[/\\\\\x00-\x1f]#', $part) || in_array($part, ['.', '..'], true)) {
                return null;
            }
        }
        return implode('/', $path);
    }

    private function applyCategories(array $records): array
    {
        usort($records, fn (array $a, array $b): int => count($a['path']) <=> count($b['path']));
        $ids = [];
        $root = (int) $this->stores->getDefaultStoreView()->getRootCategoryId();
        foreach ($records as $record) {
            $path = $record['path'];
            array_pop($path);
            $parentId = $path ? $ids[implode('/', $path)] : $root;
            $match = $this->categoryCollection->create()->setStoreId(0)->addAttributeToFilter('url_key', $record['url_key'])
                ->addAttributeToFilter('parent_id', $parentId)->getFirstItem();
            $category = $match->getId() ? $this->categories->get((int) $match->getId(), 0) : $this->categoryFactory->create()->setStoreId(0)->setParentId($parentId);
            foreach (['name', 'url_key', 'description', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
                $category->setData($field, $record[$field] ?? null);
            }
            if (!$match->getId()) {
                $category->setIsActive((bool) ($record['is_active'] ?? true))->setIncludeInMenu((bool) ($record['include_in_menu'] ?? true));
            }
            $category = $this->categories->save($category);
            $ids[implode('/', $record['path'])] = (int) $category->getId();
        }
        return $ids;
    }

    private function applyConfiguration(Product $product, array $config, array $saved): void
    {
        $existing = [];
        $extensions = $product->getExtensionAttributes();
        foreach ($extensions->getConfigurableProductOptions() ?? [] as $option) {
            $existing[(int) $option->getAttributeId()] = $option;
        }
        $options = [];
        foreach ($config['attributes'] as $position => $code) {
            $attribute = $this->attributes->get($code);
            $option = $existing[(int) $attribute->getId()] ?? $this->optionFactory->create();
            $option->setAttributeId((string) $attribute->getId())->setLabel($attribute->getFrontendLabel())->setPosition($position)->setIsUseDefault(true);
            $values = [];
            foreach ($config['children'] as $sku) {
                $value = (int) $saved[$sku]->getData($code);
                $values[$value] = $this->optionValues->create()->setValueIndex($value);
            }
            $options[] = $option->setValues(array_values($values));
        }
        $extensions->setConfigurableProductOptions($options);
        $extensions->setConfigurableProductLinks(array_map(fn (string $sku): int => (int) $saved[$sku]->getId(), $config['children']));
        $product->setExtensionAttributes($extensions);
    }

    private function applyImages(Product $product, array $images, string $file): void
    {
        if (!$images) {
            return;
        }
        $entries = $product->getMediaGalleryEntries() ?? [];
        $known = [];
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        foreach ($entries as $entry) {
            if ($entry->getMediaType() === 'image') {
                $path = $directory->getAbsolutePath('catalog/product/' . ltrim((string) $entry->getFile(), '/'));
                if (is_file($path)) {
                    $known[hash_file('sha256', $path)] = $entry;
                }
            }
        }
        $claimedRoles = [];
        foreach ($images as $image) {
            $claimedRoles = array_merge($claimedRoles, $image['roles']);
        }
        foreach ($entries as $entry) {
            $entry->setTypes(array_values(array_diff($entry->getTypes() ?? [], $claimedRoles)));
        }
        foreach ($images as $image) {
            $entry = $known[$image['sha256']] ?? null;
            if ($entry === null) {
                $content = $this->imageContents->create()->setName(basename($image['asset']))->setType($image['mime'])
                    ->setBase64EncodedData(base64_encode($this->packages->image($file, $image['asset'])));
                $entry = $this->galleryEntries->create()->setMediaType('image')->setContent($content);
                $entries[] = $entry;
                $known[$image['sha256']] = $entry;
            }
            $entry->setLabel($image['label'])->setPosition((int) ($image['position'] ?? 0))->setDisabled((bool) ($image['disabled'] ?? false))->setTypes($image['roles']);
        }
        $product->setMediaGalleryEntries($entries);
    }

    private function taxClassId(?string $name): int
    {
        if ($name === null || $name === '') {
            return 0;
        }
        $criteria = $this->criteria->addFilter('class_name', $name)->addFilter('class_type', 'PRODUCT')->create();
        foreach ($this->taxClasses->getList($criteria)->getItems() as $class) {
            return (int) $class->getClassId();
        }
        throw new \RuntimeException('Configure the target product tax class and its tax rules before importing: ' . $name);
    }

    private function refresh(): void
    {
        foreach ($this->indexerConfig->getIndexers() as $id => $config) {
            if (str_starts_with($id, 'catalog') || $id === 'inventory') {
                $this->indexers->get($id)->reindexAll();
            }
        }
        $this->caches->clean(['config', 'eav', 'layout', 'block_html', 'full_page']);
        $this->events->dispatch('shi3ld_catalogue_transfer_applied');
    }
}
