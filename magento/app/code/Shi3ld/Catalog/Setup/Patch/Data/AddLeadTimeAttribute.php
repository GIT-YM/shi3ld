<?php
namespace Shi3ld\Catalog\Setup\Patch\Data;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
class AddLeadTimeAttribute implements DataPatchInterface
{
    public function __construct(private ModuleDataSetupInterface $setup, private EavSetupFactory $eavSetupFactory) {}
    public function apply(): self
    {
        $eav = $this->eavSetupFactory->create(['setup' => $this->setup]);
        if (!$eav->getAttributeId(Product::ENTITY, 'shi3ld_lead_time')) {
            $eav->addAttribute(Product::ENTITY, 'shi3ld_lead_time', [
                'type' => 'varchar', 'label' => 'Dispatch / Supplier Lead Time', 'input' => 'text',
                'required' => false, 'user_defined' => true, 'visible' => true,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General', 'visible_on_front' => true, 'used_in_product_listing' => true,
            ]);
        }
        return $this;
    }
    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
