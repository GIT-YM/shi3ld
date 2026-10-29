<?php

namespace Shi3ld\Catalog\Console;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedSampleProducts extends Command
{
    public function __construct(
        private State $state,
        private StoreManagerInterface $stores,
        private Config $eav,
        private EavSetupFactory $setup,
        private AttributeOptionManagementInterface $attributeOptions,
        private AttributeOptionInterfaceFactory $attributeOptionFactory,
        private CategoryFactory $categoryFactory,
        private CollectionFactory $categories,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductFactory $productFactory,
        private ProductRepositoryInterface $products,
        private OptionInterfaceFactory $configurableOptionFactory,
        private OptionValueInterfaceFactory $configurableValueFactory,
        private SourceItemInterfaceFactory $sourceItemFactory,
        private SourceItemsSaveInterface $sourceItems
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:catalog:seed-samples')->setDescription('Add 12 configurable development samples, preserving existing products');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->state->getMode() !== State::MODE_DEVELOPER) {
            $output->writeln('<error>Sample catalogue seeding is available in developer mode only.</error>');

            return Command::FAILURE;
        }
        $this->state->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($output): void {
            $this->seed($output);
        });

        return Command::SUCCESS;
    }

    private function seed(OutputInterface $output): void
    {
        $samples = json_decode(file_get_contents(dirname(__DIR__).'/etc/sample-products.json'), true, flags: JSON_THROW_ON_ERROR);
        $store = $this->stores->getDefaultStoreView();
        $root = $this->categoryRepository->get($store->getRootCategoryId(), 0);
        $website = (int) $store->getWebsiteId();
        $attributeSet = (int) $this->eav->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
        $setup = $this->setup->create();
        if (! $this->eav->getAttribute(Product::ENTITY, 'size')->getId()) {
            $setup->addAttribute(Product::ENTITY, 'size', [
                'type' => 'int', 'label' => 'Size', 'input' => 'select',
                'global' => 1, 'required' => false, 'user_defined' => true,
                'visible' => true, 'group' => 'General', 'used_in_product_listing' => true,
            ]);
            $this->eav->clear();
        }
        foreach (['size', 'color'] as $code) {
            $attribute = $this->eav->getAttribute(Product::ENTITY, $code);
            if (! $attribute->getId() || $attribute->getFrontendInput() !== 'select' || (int) $attribute->getIsGlobal() !== 1) {
                throw new \RuntimeException('Expected a global dropdown attribute: '.$code);
            }
            $setup->addAttributeToSet(Product::ENTITY, $attributeSet, 'General', (int) $attribute->getId());
        }
        $sizes = $this->options('size', array_merge(...array_column($samples, 'sizes')));
        $colours = $this->options('color', array_merge(...array_column($samples, 'colours')));
        $categoryIds = [];
        $stock = [];
        foreach ($samples as $sample) {
            if (! isset($categoryIds[$sample['category']])) {
                $categoryIds[$sample['category']] = $this->category($sample['category'], $root);
            }
            $sku = 'SHI3LD-SAMPLE-'.strtoupper($sample['key']);
            if ($this->find($sku)) {
                $output->writeln('Preserved existing sample: '.$sku);

                continue;
            }
            $children = [];
            foreach ($sample['sizes'] as $sizeIndex => $size) {
                foreach ($sample['colours'] as $colour) {
                    $childSku = $sku.'-'.strtoupper($this->slug($size)).'-'.strtoupper($this->slug($colour));
                    $child = $this->find($childSku);
                    if (! $child) {
                        $child = $this->productFactory->create()->setStoreId(0)
                            ->setSku($childSku)->setTypeId('simple')->setAttributeSetId($attributeSet)
                            ->setName($sample['name'].' — '.$size.' / '.$colour)
                            ->setWebsiteIds([$website])->setStatus(1)->setVisibility(1)
                            ->setPrice($sample['price'] + $sizeIndex * 5)->setWeight($sample['weight'])
                            ->setTaxClassId(0)->setData('color', $colours[strtolower($colour)])
                            ->setData('size', $sizes[strtolower($size)])
                            ->setDescription('Choose your preferred size and colour from the available options.')
                            ->setStockData(['qty' => 25, 'is_in_stock' => 1]);
                        $child = $this->products->save($child);
                        $sourceItem = $this->sourceItemFactory->create();
                        $sourceItem->setSku($childSku);
                        $sourceItem->setSourceCode('default');
                        $sourceItem->setQuantity(25);
                        $sourceItem->setStatus(1);
                        $stock[] = $sourceItem;
                    }
                    $children[] = (int) $child->getId();
                }
            }
            $parent = $this->productFactory->create()->setStoreId(0)
                ->setSku($sku)->setTypeId('configurable')->setAttributeSetId($attributeSet)
                ->setName($sample['name'])->setWebsiteIds([$website])
                ->setStatus(1)->setVisibility(4)->setCategoryIds([$categoryIds[$sample['category']]])
                ->setUrlKey('sample-'.$sample['key'])->setTaxClassId(0)
                ->setDescription('<p>Select a size and colour to view the available product options.</p><p>Review the product details before ordering.</p>')
                ->setShortDescription('Available in three sizes and two colours.')
                ->setData('shi3ld_lead_time', '')
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
            $options = [];
            foreach (['size' => $sizes, 'color' => $colours] as $position => $values) {
                $labels = $position === 'size' ? $sample['sizes'] : $sample['colours'];
                $option = $this->configurableOptionFactory->create()
                    ->setAttributeId((string) $this->eav->getAttribute(Product::ENTITY, $position)->getId())
                    ->setLabel($position === 'size' ? 'Size' : 'Colour')
                    ->setPosition($position === 'size' ? 0 : 1)->setIsUseDefault(true);
                $option->setValues(array_map(fn ($label) => $this->configurableValueFactory->create()->setValueIndex($values[strtolower($label)]), $labels));
                $options[] = $option;
            }
            $extensions = $parent->getExtensionAttributes();
            $extensions->setConfigurableProductOptions($options)->setConfigurableProductLinks($children);
            $parent->setExtensionAttributes($extensions);
            $this->products->save($parent);
            $output->writeln('Created '.$sku.' ('.count($children).' variants)');
        }
        if ($stock) {
            $this->sourceItems->execute($stock);
        }
        $output->writeln('<info>Sample catalogue ready. Reindex, regenerate sitemaps and clean storefront caches.</info>');
    }

    private function find(string $sku): ?Product
    {
        try {
            return $this->products->get($sku, false, 0, true);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    private function options(string $code, array $labels): array
    {
        $entity = (int) $this->eav->getEntityType(Product::ENTITY)->getId();
        $existing = [];
        foreach ($this->attributeOptions->getItems($entity, $code) as $option) {
            $existing[strtolower($option->getLabel())] = (int) $option->getValue();
        }
        foreach (array_unique($labels) as $label) {
            if (! isset($existing[strtolower($label)])) {
                $option = $this->attributeOptionFactory->create()->setLabel($label)->setSortOrder(0)->setIsDefault(false);
                $optionId = $this->attributeOptions->add($entity, $code, $option);
                $this->eav->clear();
                $existing[strtolower($label)] = (int) $optionId;
            }
        }

        return $existing;
    }

    private function category(string $label, CategoryInterface $root): int
    {
        $name = $label;
        $category = $this->categories->create()->setStoreId(0)->addAttributeToFilter('url_key', 'sample-'.$this->slug($label))
            ->addAttributeToFilter('parent_id', $root->getId())->getFirstItem();
        if ($category->getId()) {
            return (int) $category->getId();
        }
        $category = $this->categoryFactory->create()->setStoreId(0)->setName($name)
            ->setParentId($root->getId())->setPath($root->getPath())->setIsActive(true)
            ->setIncludeInMenu(true)->setUrlKey('sample-'.$this->slug($label))
            ->setDescription('Explore '.$label.'.');

        return (int) $this->categoryRepository->save($category)->getId();
    }

    private function slug(string $value): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-');
    }
}
