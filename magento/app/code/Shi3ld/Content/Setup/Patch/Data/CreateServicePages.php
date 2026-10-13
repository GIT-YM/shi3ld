<?php
namespace Shi3ld\Content\Setup\Patch\Data;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
class CreateServicePages implements DataPatchInterface
{
    public function __construct(private PageFactory $pageFactory, private PageRepositoryInterface $pages) {}
    public function apply(): self
    {
        $services = [
            'domestic-cleaning' => ['Domestic Cleaning', 'Discuss regular, one-off, deep and end-of-tenancy cleaning for your home.'],
            'commercial-cleaning' => ['Commercial Cleaning', 'Discuss cleaning requirements for offices, retail premises and shared spaces.'],
            'care-home-cleaning' => ['Care Home Cleaning', 'Discuss site requirements, cleaning schedules and the documentation required by your care home.'],
        ];
        foreach ($services as $identifier => [$title, $description]) {
            $page = $this->pageFactory->create();
            if ($page->getCollection()->addFieldToFilter('identifier', $identifier)->getSize()) { continue; }
            $page->setTitle($title)->setIdentifier($identifier)->setIsActive(true)->setStores([0]);
            $page->setPageLayout('1column')->setContentHeading($title);
            $page->setMetaTitle($title . ' | Shi3ld Alliance');
            $page->setContent('<p>' . $description . '</p><p>Availability, service area and quotation details are confirmed by our team.</p><p>Contact Shi3ld Alliance to discuss your requirements.</p>');
            $this->pages->save($page);
        }
        return $this;
    }
    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
