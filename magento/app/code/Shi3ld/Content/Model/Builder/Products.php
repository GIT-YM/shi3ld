<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Products
{
    public function __construct(private CollectionFactory $collections,
        private Image $images, private PriceCurrencyInterface $currency,
        private Escaper $escaper) {}

    public function html(int $limit = 6, array $skus = []): string
    {
        $products = $this->collections->create()->addAttributeToSelect(['name', 'sku', 'price', 'small_image'])->addStoreFilter()
            ->addAttributeToFilter('status', 1)->addAttributeToFilter('visibility', ['in' => [2, 4]])->setPageSize(max(1, min(24, $limit)));
        if ($skus) {
            $products->addAttributeToFilter('sku', ['in' => array_slice($skus, 0, 24)]);
        }
        $products->addPriceData();
        $html = '<div class="blog-grid" style="--blog-cols:3;--blog-tablet-cols:2;--blog-mobile-cols:1">';
        foreach ($products as $product) {
            $url = $this->escaper->escapeUrl($product->getProductUrl());
            $name = $this->escaper->escapeHtml($product->getName());
            $image = $this->escaper->escapeUrl($this->images->init($product, 'category_page_grid')->getUrl());
            $amount = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            $price = (in_array($product->getTypeId(), ['configurable', 'bundle', 'grouped'], true) ? 'From ' : '')
                .$this->currency->format($amount, false);
            $html .= '<article class="blog-card"><a class="blog-card-image" href="'.$url.'"><img src="'.$image.'" alt="'.$this->escaper->escapeHtmlAttr($product->getName()).'" loading="lazy"></a>'
                .'<div class="blog-card-copy"><h3><a href="'.$url.'">'.$name.'</a></h3><p>'.$this->escaper->escapeHtml($price).'</p><a class="blog-read-more" href="'.$url.'">View product ↗</a></div></article>';
        }

        return $html.'</div>';
    }
}
