<?php

declare(strict_types=1);

namespace Shi3ld\Content\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\Template;
use Shi3ld\Content\Model\Builder\Css;
use Shi3ld\Content\Model\Builder\Runtime;
use Shi3ld\Content\Model\Builder\Schema;

class BuilderProductLayout implements ObserverInterface
{
    public function __construct(private Http $request, private Runtime $runtime,
        private Css $css, private Schema $schema) {}

    public function execute(Observer $observer): void
    {
        if ($this->request->getFullActionName() !== 'catalog_product_view') {
            return;
        }
        $design = $this->runtime->match('product');
        if (! $design || ($design['builder']['asset']['kind'] ?? '') !== 'product') {
            return;
        }
        $layout = $observer->getEvent()->getLayout();
        if (! $layout || ! $layout->isContainer('content')) {
            return;
        }
        $arrangement = $design['builder']['asset']['product_layout'] ?? 'gallery-left';
        $name = 'shi3ld.builder.product';
        $layout->addContainer($name, 'Shi3ld product arrangement', ['htmlTag' => 'div', 'htmlClass' => 'builder-product-layout layout-'.$arrangement], 'content');
        foreach (['product.info.media', 'product.info.main', 'product.info.details', 'catalog.product.related'] as $child) {
            if ($layout->getBlock($child) || $layout->isContainer($child)) {
                $layout->setChild($name, $child, str_replace('.', '_', $child));
            }
        }
        $css = $this->css->compile($design['builder']['css'] ?? '', '.builder-product-layout');
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8">'.$design['html'], LIBXML_NONET);
            $selectors = ['gallery' => '.product.media', 'information' => '.product-info-main', 'details' => '.product.info.detailed', 'related' => '.block.related'];
            foreach ($document->getElementsByTagName('*') as $element) {
                $slot = $element->getAttribute('data-builder-slot');
                if (! isset($selectors[$slot])) {
                    continue;
                }
                $selector = '.builder-product-layout>'.$selectors[$slot];
                if ($element->hasAttribute('data-builder-css')) {
                    $css .= $this->css->compile($element->getAttribute('data-builder-css'), $selector);
                }
                if ($element->hasAttribute('data-builder-styles')) {
                    foreach ($this->schema->styles(json_decode($element->getAttribute('data-builder-styles'), true, 16, JSON_THROW_ON_ERROR)) as $device => $values) {
                        $rule = $selector.($device === 'hover' ? ':hover' : ($device === 'focus' ? ':focus-visible' : '')).'{';
                        foreach ($values as $property => $value) {
                            $rule .= $property.':'.preg_replace('/\s*!important\s*$/i', '', $value).'!important;';
                        }$rule .= '}';
                        $css .= $device === 'tablet' ? '@media(max-width:1024px){'.$rule.'}' : ($device === 'mobile' ? '@media(max-width:640px){'.$rule.'}' : $rule);
                    }
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($css !== '') {
            $block = $layout->createBlock(Template::class, 'shi3ld.builder.product.styles')->setTemplate('Shi3ld_Content::builder-product-styles.phtml')->setData('builder_css', $css);
            $layout->setChild('before.body.end', 'shi3ld.builder.product.styles', 'builder_product_styles');
        }
    }
}
