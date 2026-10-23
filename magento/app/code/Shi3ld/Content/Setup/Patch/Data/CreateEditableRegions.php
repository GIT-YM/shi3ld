<?php

declare(strict_types=1);

namespace Shi3ld\Content\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Framework\App\State;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class CreateEditableRegions implements DataPatchInterface
{
    public function __construct(
        private BlockFactory $blocks, private BlockRepositoryInterface $repository,
        private State $state, private LayoutFactory $layouts, private Emulation $emulation,
        private StoreManagerInterface $stores
    ) {}

    public function apply(): self
    {
        $store = $this->stores->getDefaultStoreView();
        $this->state->emulateAreaCode('frontend', function () use ($store): void {
            $this->emulation->startEnvironmentEmulation((int) $store->getId(), 'frontend', true);
            try {
                foreach (['header','footer'] as $region) {
                    $identifier = 'shi3ld_global_'.$region;
                    if ($this->blocks->create()->getCollection()->addFieldToFilter('identifier', $identifier)->getSize()) {
                        continue;
                    }
                    $block = $this->layouts->create()->createBlock(Template::class)->setArea('frontend')
                        ->setTemplate('Shi3ld_Content::'.$region.'.phtml');
                    $html = $block->toHtml();
                    $previous = libxml_use_internal_errors(true);
                    $document = new \DOMDocument('1.0', 'UTF-8');
                    try {
                        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
                        $root = (new \DOMXPath($document))->query('//*[@data-shi3ld-region="'.$region.'"]')->item(0);
                        if (!$root) {
                            throw new \RuntimeException('The editable '.$region.' region was not found.');
                        }
                        $links = [];
                        foreach ((new \DOMXPath($document))->query('.//*[@href]', $root) as $link) {
                            $url = $link->getAttribute('href');
                            $base = rtrim($store->getBaseUrl(), '/').'/';
                            if (str_starts_with($url, $base)) {
                                $token = 'SHI3LDREGIONLINK'.count($links).'TOKEN';
                                $links[$token] = "{{store url='".substr($url, strlen($base))."'}}";
                                $link->setAttribute('href', $token);
                            }
                        }
                        $content = '';
                        foreach ($root->childNodes as $child) {
                            $content .= $document->saveHTML($child);
                        }
                        $content = strtr($content, $links);
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                    $entity = $this->blocks->create()->setIdentifier($identifier)->setTitle('Website '.ucfirst($region))
                        ->setContent($content)->setIsActive(true)->setStores([0]);
                    $this->repository->save($entity);
                }
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        });
        return $this;
    }

    public static function getDependencies(): array { return [EnableVisualPages::class]; }
    public function getAliases(): array { return []; }
}
