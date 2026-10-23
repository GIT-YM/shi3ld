<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Shi3ld\Content\Model\Editor\Preview;
use Shi3ld\Content\Model\Editor\Renderer;

class VisualCms
{
    public function __construct(private Preview $preview, private Renderer $renderer, private \Magento\Cms\Model\BlockFactory $blocks) {}

    public function afterGetPage(\Magento\Cms\Block\Page $block, $page)
    {
        $draft = $this->preview->get();
        if ($draft && $draft['type'] === 'page' && (int) $draft['id'] === (int) $page->getId()) {
            $payload = $draft['payload'];
            $page->setContent($payload['html'])->setTitle($payload['title'])->setMetaTitle($payload['meta_title'])
                ->setMetaDescription($payload['meta_description'])->setContentHeading($payload['content_heading']);
        }
        return $page;
    }

    public function afterToHtml(\Magento\Framework\View\Element\AbstractBlock $block, string $html): string
    {
        if (!$block instanceof \Magento\Cms\Block\Page) {
            return $html;
        }
        return '<div data-shi3ld-region="page" data-shi3ld-page-id="'.(int) $block->getPage()->getId().'">'.$html.'</div>';
    }

    public function aroundToHtml(\Magento\Framework\View\Element\AbstractBlock $block, callable $proceed): string
    {
        $draft = $this->preview->get();
        if ($block instanceof \Magento\Cms\Block\Block && $draft && $draft['type'] === 'block'
            && in_array((string) $block->getBlockId(), [(string) $draft['id'], $draft['identifier']], true)) {
            return $this->renderer->render($draft['payload']['html']) ?: '<!-- Shared website region intentionally empty. -->';
        }
        $html = $proceed();
        if ($block instanceof \Magento\Cms\Block\Block && $html === ''
            && in_array($block->getBlockId(), ['shi3ld_global_header','shi3ld_global_footer'], true)) {
            $model = $this->blocks->create()->load($block->getBlockId());
            if ($model->getId() && $model->isActive()) {
                return '<!-- Shared website region intentionally empty. -->';
            }
        }
        return $html;
    }
}
