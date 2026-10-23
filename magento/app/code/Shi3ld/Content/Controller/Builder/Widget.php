<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Builder;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;
use Shi3ld\Blog\Block\Widget\Articles;
use Shi3ld\Content\Model\Builder\Products;

class Widget implements HttpGetActionInterface
{
    public function __construct(private JsonFactory $results, private Http $request,
        private LayoutInterface $layout, private Products $products) {}

    public function execute(): Json
    {
        $kind = $this->request->getParam('kind');
        $limit = max(1, min(24, (int) $this->request->getParam('limit', 6)));
        $html = '';
        if ($kind === 'products') {
            $html = $this->products->html($limit);
        } elseif ($kind === 'blog') {
            $options = $this->request->getParams();
            $options['limit'] = $limit;
            $options['heading'] ??= '';
            $options['view_all'] ??= 0;
            $html = $this->layout->createBlock(Articles::class)->configurePublic($options)->toHtml();
        }

        return $this->results->create()->setHeader('Cache-Control', 'private, no-store', true)->setData(['html' => $html]);
    }
}
