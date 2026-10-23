<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Magento\Cms\Block\Block;
use Magento\Cms\Block\Page;
use Magento\Framework\App\Response\Http;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\PageCache\Model\Config;
use Shi3ld\Content\Model\Builder\Runtime;

class BuilderRendering
{
    public function __construct(private Runtime $runtime) {}

    public function afterToHtml(AbstractBlock $block, string $html): string
    {
        if ($block instanceof Page) {
            $id = (int) $block->getPage()->getId();
            $html = $this->runtime->wrap($html, $this->runtime->settings('page', $id), ['key' => 'page-'.$id]);

            return $this->runtime->template('cms', $html);
        }
        if ($block instanceof Block) {
            $identifier = (string) $block->getBlockId();
            if (in_array($identifier, ['shi3ld_global_header', 'shi3ld_global_footer'], true)) {
                $region = str_replace('shi3ld_global_', '', $identifier);
                $id = $this->runtime->blockId($identifier);
                if ($html !== '') {
                    $html = $this->runtime->wrap($html, $this->runtime->settings('block', $id), ['key' => 'block-'.$id]);
                }

                return $this->runtime->template($region, $html);
            }
        }

        return $html;
    }

    public function afterIsEnabled(Config $subject, bool $enabled): bool
    {
        return $enabled && ! $this->runtime->privateRules();
    }

    public function beforeSendResponse(Http $response): void
    {
        if ($this->runtime->privateRules()) {
            $response->setNoCacheHeaders();
        }
    }
}
