<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Setup\Patch\Data;

use Magento\Cms\Model\PageFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\WidgetOptions;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Setup\Patch\Data\CreateEditableRegions;

class AddHomepageArticles implements DataPatchInterface
{
    public function __construct(private PageFactory $pages,
        private Documents $documents, private WidgetOptions $options,
        private LoggerInterface $logger) {}

    public function apply(): self
    {
        $page = $this->pages->create()->getCollection()->addFieldToFilter('identifier', 'shi3ld-home')->getFirstItem();
        if (! $page->getId() || str_contains((string) $page->getContent(), WidgetOptions::WIDGET)) {
            return $this;
        }
        $id = (int) $page->getId();
        $state = $this->documents->get('page', $id);
        if ($state['has_draft']) {
            $this->logger->notice('Homepage has an existing draft. Add the blog section through Add > Blog articles to preserve that draft.');

            return $this;
        }
        $payload = $state['payload'];
        $payload['html'] .= '<div class="shi3ld-pb-row" data-content-type="row" data-appearance="full-width" data-element="main" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image">'
            .'<div class="row-full-width-inner" data-element="inner"><div data-content-type="html" data-appearance="default" data-element="main">'
            .'<section class="container" data-editor-widget="blog-articles">'.$this->options->directive([]).'</section></div></div></div>';
        $saved = $this->documents->save('page', $id, $payload, $state['version'], 'Blog installation', 'draft', $state['live_hash']);
        $this->documents->publish('page', $id, $saved['version'], 'Blog installation');

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateEditableRegions::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
