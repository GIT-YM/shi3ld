<?php

namespace Shi3ld\Content\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;
use Magento\MediaGalleryApi\Api\GetAssetsByPathsInterface;
use Magento\MediaGalleryApi\Api\SaveAssetsInterface;

class VisualPages
{
    public function __construct(
        private PageFactory $pageFactory,
        private PageRepositoryInterface $pages,
        private BlockFactory $blockFactory,
        private BlockRepositoryInterface $blocks,
        private Filesystem $filesystem,
        private WriterInterface $config,
        private GetAssetsByPathsInterface $mediaAssets,
        private SaveAssetsInterface $saveMediaAssets,
        private AssetInterfaceFactory $assetFactory
    ) {}

    public function enable(): array
    {
        $this->copyImages();
        $result = [];
        foreach (['shi3ld-home' => 'home', 'about' => 'about', 'shop' => 'shop'] as $identifier => $template) {
            $collection = $this->pageFactory->create()->getCollection()->addFieldToFilter('identifier', $identifier);
            foreach ($collection as $item) {
                $page = $this->pages->getById($item->getId());
                $legacy = '{{block class="Shi3ld\\Content\\Block\\Site" template="Shi3ld_Content::'.$template.'.phtml"}}';
                if (trim((string) $page->getContent()) !== $legacy) {
                    $result[] = $identifier.': preserved existing content (already visual or independently edited).';

                    continue;
                }
                $backup = json_encode($page->getData(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->writeFile(
                    'shi3ld/cms-backups/'.$identifier.'-'.$page->getId().'-'.gmdate('Ymd-His').'.json', $backup
                );
                $content = match ($template) {
                    'home' => $this->home(),
                    'about' => $this->about(),
                    'shop' => $this->shop(),
                };
                $page->setContent($content);
                $this->pages->save($page);
                $result[] = $identifier.': converted to native Page Builder sections.';
            }
        }
        $this->config->save('cms/pagebuilder/enabled', 1);
        $this->config->save('cms/pagebuilder/enable_content_preview', 1);
        $this->config->save('cms/wysiwyg/enabled', 'enabled');

        return $result;
    }

    private function home(): string
    {
        $hero = $this->row('shi3ld-pb-hero', $this->columns([
            $this->text($this->copy('shi3ld_home_intro')).$this->buttons([
                ['Explore the shop ↗', 'shop', 'primary'], ['Find your cleaning plan ↗', 'services', 'link'],
            ]).$this->text('<p>For homes, workplaces and care environments.<br><strong>One connected alliance.</strong></p>', 'shi3ld-pb-note'),
            $this->image('home-sample.jpg', 'Welcoming living room with cream sofa, oak table and plants', 'shi3ld-pb-hero-image')
                .$this->text('<p>Your space. Your priorities.<br><strong>More room for everyday living.</strong></p>', 'shi3ld-pb-image-note'),
        ]));
        $values = $this->row('shi3ld-pb-values', $this->text('<p>Accessibility products &nbsp; · &nbsp; Domestic cleaning &nbsp; · &nbsp; Commercial cleaning &nbsp; · &nbsp; Care-home cleaning</p>'));
        $products = $this->row('shi3ld-pb-products', $this->text($this->copy('shi3ld_home_products'))
            .$this->products().$this->buttons([['Visit the shop ↗', 'shop', 'link']]));
        $cards = [];
        foreach ([
            ['domestic-cleaning', 'Domestic cleaning', 'At home', 'home-sample.jpg', 'Cleaning around your home, your routine and your priorities. Discuss regular, one-off or more focused cleaning.'],
            ['commercial-cleaning', 'Commercial cleaning', 'At work', 'workplace-sample.jpg', 'Discuss a cleaning schedule for offices, retail premises and shared areas, shaped around your site.'],
            ['care-home-cleaning', 'Care-home cleaning', 'In care', 'care-sample.jpg', 'Site-specific cleaning enquiries for communal and resident areas, discussed with your care-home team.'],
        ] as [$slug, $title, $label, $image, $description]) {
            $cards[] = $this->image($image, $label.' setting')
                .$this->text('<p class="eyebrow">'.$label.'</p><h3>'.$title.'</h3><p>'.$description.'</p>')
                .$this->buttons([['Explore this service ↗', 'services/'.$slug, 'link']]);
        }
        $services = $this->row('shi3ld-pb-services', $this->text($this->copy('shi3ld_home_services'))
            .$this->columns($cards, 'shi3ld-pb-cards').$this->buttons([['All cleaning services ↗', 'services', 'link']]));
        $about = $this->row('shi3ld-pb-about', $this->columns([
            $this->text('<h2>Care in every<br><em>detail.</em></h2><p>Thoughtful support for everyday spaces.</p>', 'shi3ld-pb-brand-panel'),
            $this->text($this->copy('shi3ld_home_about')).$this->buttons([
                ['Meet Shi3ld Alliance ↗', 'about', 'link'], ['Talk about your cleaning needs ↗', 'services/quote', 'link'],
            ]),
        ]));
        $cta = $this->row('shi3ld-pb-cta', $this->columns([
            $this->text('<p class="eyebrow">LET’S TALK ABOUT YOUR SPACE</p><h2>A fresh start begins<br>with a conversation.</h2>'),
            $this->text('<p>Tell us what you need cleaned. We’ll discuss a plan and quotation around your requirements.</p>')
                .$this->buttons([['Request a cleaning quote ↗', 'services/quote', 'primary']]),
        ]));

        return $hero.$values.$products.$services.$about.$cta;
    }

    private function about(): string
    {
        return $this->row('shi3ld-pb-about-page', $this->columns([
            $this->text($this->copy('shi3ld_about_copy')).$this->buttons([
                ['Explore the shop ↗', 'shop', 'primary'], ['Explore cleaning services ↗', 'services', 'link'],
            ]),
            $this->image('home-sample.jpg', 'Welcoming living room', 'shi3ld-pb-about-image'),
        ]));
    }

    private function shop(): string
    {
        $block = $this->blockFactory->create()->getCollection()
            ->addFieldToFilter('identifier', 'shi3ld_shop_catalogue')->getFirstItem();
        if (! $block->getId()) {
            $block = $this->blockFactory->create()->setIdentifier('shi3ld_shop_catalogue')
                ->setTitle('Shop live catalogue — automatic products and categories')->setIsActive(true)->setStores([0])
                ->setContent('{{block class="Shi3ld\\Content\\Block\\Site" template="Shi3ld_Content::catalogue.phtml"}}');
            $block = $this->blocks->save($block);
        }
        $widget = '{{widget type="Magento\\Cms\\Block\\Widget\\Block" template="widget/static_block/default.phtml" block_id="'.$block->getId().'"}}';

        return $this->row('shi3ld-pb-shop-intro', $this->text($this->copy('shi3ld_shop_intro')))
            .$this->row('shi3ld-pb-catalogue', '<div data-content-type="block" data-appearance="default" data-element="main">'.$widget.'</div>');
    }

    private function products(): string
    {
        $conditions = ['1' => ['type' => 'Magento\\CatalogWidget\\Model\\Rule\\Condition\\Combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => '']];
        $encoded = str_replace(['{', '}', '"', '\\'], ['^[', '^]', '`', '|'], json_encode($conditions, JSON_THROW_ON_ERROR));

        return '<div data-content-type="products" data-appearance="grid" data-element="main">'
            .'{{widget type="Magento\\CatalogWidget\\Block\\Product\\ProductsList" template="Magento_CatalogWidget::product/widget/content/grid.phtml" products_count="4" show_pager="0" sort_order="date_newest_top" condition_option="condition" conditions_encoded="'.$encoded.'"}}'
            .'</div>';
    }

    private function row(string $classes, string $content): string
    {
        return '<div data-content-type="row" data-appearance="full-width" data-element="main" class="shi3ld-pb-row '.$classes.'" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image" data-video-loop="true" data-video-play-only-visible="true" data-video-lazy-load="true">'
            .'<div class="row-full-width-inner" data-element="inner">'.$content.'</div></div>';
    }

    private function columns(array $contents, string $classes = 'shi3ld-pb-split'): string
    {
        $html = '<div class="pagebuilder-column-group '.$classes.'" data-content-type="column-group" data-appearance="default" data-grid-size="12" data-element="main">'
            .'<div class="pagebuilder-column-line" data-content-type="column-line" data-appearance="default" data-grid-size="12" data-element="main" style="display:flex;width:100%">';
        foreach ($contents as $content) {
            $html .= '<div class="pagebuilder-column" data-content-type="column" data-appearance="full-height" data-element="main" style="display:flex;flex-direction:column;align-self:stretch;width:'.(100 / count($contents)).'%">'.$content.'</div>';
        }

        return $html.'</div></div>';
    }

    private function text(string $html, string $classes = ''): string
    {
        return '<div data-content-type="text" data-appearance="default" data-element="main" class="'.$classes.'">'.$html.'</div>';
    }

    private function image(string $file, string $alt, string $classes = ''): string
    {
        $src = '{{media url=wysiwyg/shi3ld/'.$file.'}}';

        return '<figure data-content-type="image" data-appearance="full-width" data-element="main" class="'.$classes.'">'
            .'<img class="pagebuilder-mobile-hidden" data-element="desktop_image" src="'.$src.'" alt="'.$alt.'" style="max-width:100%;height:auto">'
            .'<img class="pagebuilder-mobile-only" data-element="mobile_image" src="'.$src.'" alt="'.$alt.'" style="max-width:100%;height:auto"></figure>';
    }

    private function buttons(array $buttons): string
    {
        $html = '<div data-content-type="buttons" data-appearance="inline" data-same-width="false" data-element="main" class="shi3ld-pb-buttons">';
        foreach ($buttons as [$label, $path, $type]) {
            $html .= '<div data-content-type="button-item" data-appearance="default" data-element="main">'
                .'<a class="pagebuilder-button-'.$type.'" href="{{store url=\''.$path.'\'}}" data-link-type="default" data-element="link"><span data-element="link_text">'.$label.'</span></a></div>';
        }

        return $html.'</div>';
    }

    private function copy(string $identifier): string
    {
        return (string) $this->blockFactory->create()->getCollection()->addFieldToFilter('identifier', $identifier)->getFirstItem()->getContent();
    }

    private function copyImages(): void
    {
        $source = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach (['home-sample.jpg', 'workplace-sample.jpg', 'care-sample.jpg'] as $file) {
            $target = 'wysiwyg/shi3ld/'.$file;
            if (! $media->isExist($target)) {
                $media->writeFile($target, $source->readFile('app/design/frontend/Shi3ld/Alliance/web/images/'.$file));
            }
            if (! $this->mediaAssets->execute([$target])) {
                $contents = $media->readFile($target);
                $dimensions = getimagesizefromstring($contents);
                $asset = $this->assetFactory->create([
                    'path' => $target, 'contentType' => 'image/jpeg',
                    'width' => $dimensions[0], 'height' => $dimensions[1],
                    'size' => strlen($contents), 'title' => 'Shi3ld illustrative '.pathinfo($file, PATHINFO_FILENAME),
                ]);
                $this->saveMediaAssets->execute([$asset]);
            }
        }
    }
}
