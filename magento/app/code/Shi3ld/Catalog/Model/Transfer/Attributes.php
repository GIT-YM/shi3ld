<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\AttributeSetManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeSetInterfaceFactory;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Magento\Eav\Setup\EavSetupFactory;

class Attributes
{
    public const CONTENT = ['description', 'short_description', 'url_key', 'meta_title', 'meta_keyword', 'meta_description', 'weight', 'country_of_manufacture', 'news_from_date', 'news_to_date'];
    public const PRICE = ['price', 'special_price', 'special_from_date', 'special_to_date', 'msrp', 'cost'];
    private const RESERVED = ['sku', 'name', 'status', 'visibility', 'type_id', 'entity_id', 'attribute_set_id', 'website_ids', 'category_ids', 'tax_class_id', 'media_gallery', 'stock_data', 'quantity_and_stock_status', 'extension_attributes', 'custom_attributes', 'qty', 'is_in_stock', 'manage_stock', 'use_config_manage_stock', 'created_at', 'updated_at', 'options'];
    private const INPUT_TYPES = ['text' => 'varchar', 'textarea' => 'text', 'select' => 'int', 'multiselect' => 'varchar', 'boolean' => 'int', 'date' => 'datetime', 'price' => 'decimal'];

    public function __construct(
        private Config $eav,
        private ProductAttributeManagementInterface $productAttributes,
        private AttributeOptionManagementInterface $options,
        private AttributeOptionInterfaceFactory $optionFactory,
        private AttributeSetManagementInterface $attributeSets,
        private AttributeSetInterfaceFactory $setFactory,
        private CollectionFactory $setCollection,
        private EavSetupFactory $setup
    ) {
    }

    public function get(string $code): \Magento\Eav\Model\Entity\Attribute\AbstractAttribute
    {
        return $this->eav->getAttribute(Product::ENTITY, $code);
    }

    public function sets(): array
    {
        $sets = [];
        foreach ($this->setCollection->create()->setEntityTypeFilter($this->eav->getEntityType(Product::ENTITY)->getId()) as $set) {
            $sets[$set->getAttributeSetName()] = (int) $set->getId();
        }
        return $sets;
    }

    public function describeSet(int $setId): array
    {
        $result = [];
        foreach ($this->productAttributes->getAttributes($setId) as $attribute) {
            $code = $attribute->getAttributeCode();
            if (!(int) $attribute->getIsUserDefined() || !isset(self::INPUT_TYPES[$attribute->getFrontendInput()])
                || in_array($code, array_merge(self::RESERVED, self::CONTENT, self::PRICE), true)
            ) {
                continue;
            }
            $model = $this->get($code);
            $source = (string) $model->getSourceModel();
            $backend = (string) $model->getBackendModel();
            if (($source !== '' && !is_a($source, \Magento\Eav\Model\Entity\Attribute\Source\Table::class, true)
                    && $source !== \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class)
                || !in_array($backend, ['', \Magento\Catalog\Model\Attribute\Backend\DefaultBackend::class, \Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend::class, \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class, \Magento\Eav\Model\Entity\Attribute\Backend\Datetime::class], true)
            ) {
                continue;
            }
            $labels = [];
            if (in_array($model->getFrontendInput(), ['select', 'multiselect'], true)) {
                foreach ($model->getSource()->getAllOptions(false) as $option) {
                    if (!is_array($option['value']) && (string) $option['value'] !== '') {
                        $labels[] = (string) $option['label'];
                    }
                }
            }
            $result[$code] = [
                'label' => (string) $model->getFrontendLabel(), 'input' => $model->getFrontendInput(),
                'type' => $model->getBackendType(), 'scope' => (int) $model->getIsGlobal(),
                'required' => (bool) $model->getIsRequired(), 'options' => array_values(array_unique($labels)),
            ];
        }
        return $result;
    }

    public function isPrice(string $code, array $definitions): bool
    {
        return in_array($code, self::PRICE, true) || ($definitions[$code]['input'] ?? '') === 'price';
    }

    public function encodeValue(string $code, mixed $value): mixed
    {
        $attribute = $this->get($code);
        if ($value === null || $value === '') {
            return null;
        }
        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            $labels = [];
            foreach (explode(',', (string) $value) as $id) {
                $label = $attribute->getSource()->getOptionText($id);
                if ($label === false || is_array($label)) {
                    throw new \RuntimeException('Unable to resolve the ' . $code . ' attribute option.');
                }
                $labels[] = (string) $label;
            }
            return $attribute->getFrontendInput() === 'select' ? $labels[0] : $labels;
        }
        if (!is_scalar($value)) {
            throw new \RuntimeException('Unsupported product attribute value: ' . $code);
        }
        return $value;
    }

    public function decodeValue(string $code, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $attribute = $this->get($code);
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            return $value;
        }
        $map = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $map[mb_strtolower((string) $option['label'])] = $option['value'];
        }
        $values = $attribute->getFrontendInput() === 'multiselect' ? $value : [$value];
        $ids = [];
        foreach ($values as $label) {
            if (!isset($map[mb_strtolower($label)])) {
                throw new \RuntimeException('Missing option for ' . $code . ': ' . $label);
            }
            $ids[] = $map[mb_strtolower($label)];
        }
        return $attribute->getFrontendInput() === 'multiselect' ? implode(',', $ids) : $ids[0];
    }

    public function errors(array $catalogue): array
    {
        $errors = [];
        foreach ($catalogue['attributes'] as $code => $definition) {
            if (!preg_match('/\A[a-z][a-z0-9_]{0,59}\z/D', (string) $code) || in_array($code, array_merge(self::RESERVED, self::CONTENT, self::PRICE), true)
                || !is_array($definition) || !isset(self::INPUT_TYPES[$definition['input'] ?? ''])
                || !in_array($definition['type'] ?? '', ['varchar', 'text', 'int', 'decimal', 'datetime'], true)
                || !in_array($definition['scope'] ?? null, [0, 1, 2], true)
                || !is_string($definition['label'] ?? null) || $definition['label'] === '' || mb_strlen($definition['label']) > 255
                || !is_array($definition['options'] ?? null) || count($definition['options']) > 1000
            ) {
                $errors[] = 'Invalid custom attribute definition: ' . $code;
                continue;
            }
            foreach ($definition['options'] as $label) {
                if (!is_string($label) || $label === '' || mb_strlen($label) > 255) {
                    $errors[] = 'Invalid option label for attribute: ' . $code;
                }
            }
            $validLabels = array_filter($definition['options'], 'is_string');
            if (count(array_unique(array_map('mb_strtolower', $validLabels))) !== count($validLabels)) {
                $errors[] = 'Option labels must be unique ignoring letter case: ' . $code;
            }
            $attribute = $this->get($code);
            if ($attribute->getId() && (!(int) $attribute->getIsUserDefined() || $attribute->getFrontendInput() !== $definition['input']
                    || $attribute->getBackendType() !== $definition['type'] || (int) $attribute->getIsGlobal() !== $definition['scope'])) {
                $errors[] = 'The target attribute has a different input type, storage type or scope: ' . $code;
            }
        }
        foreach ($catalogue['attribute_sets'] as $name => $codes) {
            if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 255 || !is_array($codes)) {
                $errors[] = 'Invalid attribute set definition.';
                continue;
            }
            foreach ($codes as $code) {
                if (!is_string($code) || !isset($catalogue['attributes'][$code])) {
                    $errors[] = 'An attribute set references an unknown custom attribute.';
                }
            }
        }
        return $errors;
    }

    public function apply(array $catalogue): array
    {
        $setup = $this->setup->create();
        foreach ($catalogue['attributes'] as $code => $definition) {
            if (!$this->get($code)->getId()) {
                $setup->addAttribute(Product::ENTITY, $code, [
                    'type' => $definition['type'], 'input' => $definition['input'], 'label' => $definition['label'],
                    'global' => $definition['scope'], 'required' => $definition['required'], 'user_defined' => true,
                    'visible' => true, 'used_in_product_listing' => true,
                    'source' => $definition['input'] === 'boolean' ? \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class : null,
                    'backend' => match ($definition['input']) {
                        'multiselect' => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                        'date' => \Magento\Eav\Model\Entity\Attribute\Backend\Datetime::class,
                        default => null,
                    },
                ]);
                $this->eav->clear();
            }
            if (in_array($definition['input'], ['select', 'multiselect'], true)) {
                $known = [];
                foreach ($this->options->getItems((int) $this->eav->getEntityType(Product::ENTITY)->getId(), $code) as $option) {
                    $known[mb_strtolower((string) $option->getLabel())] = true;
                }
                foreach ($definition['options'] as $position => $label) {
                    if (!isset($known[mb_strtolower($label)])) {
                        $option = $this->optionFactory->create()->setLabel($label)->setSortOrder($position)->setIsDefault(false);
                        $this->options->add((int) $this->eav->getEntityType(Product::ENTITY)->getId(), $code, $option);
                        $known[mb_strtolower($label)] = true;
                    }
                }
                $this->eav->clear();
            }
        }
        $sets = $this->sets();
        $default = (int) $this->eav->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
        foreach ($catalogue['attribute_sets'] as $name => $codes) {
            if (!isset($sets[$name])) {
                $set = $this->setFactory->create()->setAttributeSetName($name)->setSortOrder(100);
                $created = $this->attributeSets->create(Product::ENTITY, $set, $default);
                $sets[$name] = (int) $created->getAttributeSetId();
            }
            $groupId = (int) $setup->getDefaultAttributeGroupId(Product::ENTITY, $sets[$name]);
            $assigned = [];
            foreach ($this->productAttributes->getAttributes($sets[$name]) as $attribute) {
                $assigned[$attribute->getAttributeCode()] = true;
            }
            foreach ($codes as $position => $code) {
                if (!isset($assigned[$code])) {
                    $setup->addAttributeToSet(Product::ENTITY, $sets[$name], $groupId, (int) $this->get($code)->getId(), $position + 100);
                }
            }
        }
        $this->eav->clear();
        return $sets;
    }
}
