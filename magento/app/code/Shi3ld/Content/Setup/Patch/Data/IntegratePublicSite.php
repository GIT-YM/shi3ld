<?php

namespace Shi3ld\Content\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class IntegratePublicSite implements DataPatchInterface
{
    public function __construct(
        private PageFactory $pageFactory,
        private PageRepositoryInterface $pages,
        private BlockFactory $blockFactory,
        private BlockRepositoryInterface $blocks,
        private WriterInterface $config
    ) {}

    public function apply(): self
    {
        $blocks = [
            'shi3ld_home_intro' => ['Homepage introduction', '<p class="eyebrow"><span class="small-line"></span> CARE FOR YOUR SPACE. SUPPORT FOR YOUR DAY.</p><h1>Thoughtful care.<br>Everyday<br><em>independence.</em></h1><p class="lead">Cleaning services and accessibility products, brought together for the spaces and people that matter.</p>'],
            'shi3ld_home_products' => ['Homepage product introduction', '<p class="eyebrow">SUPPORT FOR EVERYDAY LIVING</p><h2>Practical products.<br>A little more possibility.</h2><p>Explore accessibility products for homes, bathrooms and care environments. Check each product’s specifications and installation requirements before choosing.</p>'],
            'shi3ld_home_services' => ['Homepage cleaning introduction', '<p class="eyebrow">THOUGHTFUL CLEANING, IN EVERY SETTING</p><h2>A fresh start<br>for your space.</h2><p>From the comfort of home to workplaces and care environments. Start with your requirements, and discuss a cleaning plan around you.</p>'],
            'shi3ld_home_about' => ['Homepage brand introduction', '<p class="eyebrow">ONE CONNECTED ALLIANCE</p><h2>Different ways to help.<br>The same considered approach.</h2><p class="lead">Shi3ld Alliance brings cleaning services and accessibility products together under one brand. Clear information and a conversation about your needs come first.</p>'],
            'shi3ld_about_copy' => ['About Shi3ld Alliance', '<p class="eyebrow">ABOUT SHI3LD ALLIANCE</p><h1>Spaces that work.<br>Support that <em>matters.</em></h1><p class="lead">Cleaning services and accessibility products, connected by a practical focus on everyday spaces.</p><h2>Two ways to support your day.</h2><p>Explore accessibility products for your home or care environment, or discuss cleaning for a domestic, commercial or care-home setting. Each part of Shi3ld Alliance starts with understanding what you need.</p><h2>Clear details, before you decide.</h2><p>Product specifications, available options and installation requirements help you choose. Cleaning enquiries start a conversation about the scope, schedule and quotation before work is confirmed.</p><p>Contact our team to discuss products, service coverage and the requirements of your space.</p>'],
            'shi3ld_shop_intro' => ['Shop introduction', '<p class="eyebrow">ACCESSIBILITY PRODUCTS</p><h1>Support for<br><em>everyday living.</em></h1><p class="lead">Explore practical accessibility products. Review dimensions, variants, installation requirements and lead times on the individual product page.</p>'],
        ];
        foreach ($blocks as $identifier => [$title, $content]) {
            $existing = $this->blockFactory->create()->getCollection()->addFieldToFilter('identifier', $identifier)->getFirstItem();
            if ($existing->getId()) {
                continue;
            }
            $block = $this->blockFactory->create();
            $block->setIdentifier($identifier)->setTitle($title)->setIsActive(true)->setStores([0])->setContent($content);
            $this->blocks->save($block);
        }

        $pages = [
            'shi3ld-home' => ['Shi3ld Alliance', 'Accessibility products and cleaning services | Shi3ld Alliance', 'Explore accessibility products and domestic, commercial and care-home cleaning with Shi3ld Alliance.', 'home'],
            'shop' => ['Shop accessibility products', 'Shop accessibility products | Shi3ld Alliance', 'Explore accessibility products, specifications, variants and installation information from Shi3ld Alliance.', 'shop'],
            'about' => ['About Shi3ld Alliance', 'About Shi3ld Alliance', 'Discover the approach behind Shi3ld Alliance accessibility products and cleaning services.', 'about'],
        ];
        foreach ($pages as $identifier => [$title, $metaTitle, $description, $template]) {
            $existing = $this->pageFactory->create()->getCollection()->addFieldToFilter('identifier', $identifier)->getFirstItem();
            if ($existing->getId()) {
                continue;
            }
            $page = $this->pageFactory->create();
            $page->setIdentifier($identifier)->setTitle($title)->setMetaTitle($metaTitle)->setMetaDescription($description);
            $page->setIsActive(true)->setStores([0])->setPageLayout('1column')->setContentHeading('');
            $page->setContent('{{block class="Shi3ld\\Content\\Block\\Site" template="Shi3ld_Content::'.$template.'.phtml"}}');
            $this->pages->save($page);
        }

        foreach (['domestic-cleaning', 'commercial-cleaning', 'care-home-cleaning', 'home'] as $identifier) {
            $collection = $this->pageFactory->create()->getCollection()->addFieldToFilter('identifier', $identifier);
            foreach ($collection as $page) {
                $page->setIsActive(false);
                $this->pages->save($page);
            }
        }
        $this->config->save('web/default/cms_home_page', 'shi3ld-home');

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateServicePages::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
