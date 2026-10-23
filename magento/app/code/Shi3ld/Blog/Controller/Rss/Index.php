<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Rss;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Presentation;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;

class Index implements HttpGetActionInterface
{
    public function __construct(private RawFactory $results, private Http $request,
        private Repository $posts, private Urls $urls, private Presentation $presentation,
        private StoreManagerInterface $stores) {}

    public function execute(): Raw
    {
        $options = [];
        if ($category = (int) $this->request->getParam('c', 0)) {
            $options['category_ids'] = [$category];
        }$posts = $this->posts->listing($options, 1, 20)['items'];
        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->startElement('channel');
        $xml->writeElement('title', 'Shi3ld journal');
        $xml->writeElement('link', $this->stores->getStore()->getUrl('', ['_direct' => 'blog', '_nosid' => true]));
        $xml->writeElement('description', 'New articles and practical resources from Shi3ld Alliance.');
        foreach ($posts as $post) {
            $url = $this->urls->url('post', $post['slug']);
            $xml->startElement('item');
            $xml->writeElement('title', $post['title']);
            $xml->writeElement('link', $url);
            $xml->writeElement('guid', $url);
            $xml->writeElement('description', $this->presentation->excerpt($post, 500));
            $xml->writeElement('pubDate', gmdate(DATE_RSS, strtotime($post['published_at'].' UTC')));
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $this->results->create()->setHeader('Content-Type', 'application/rss+xml; charset=UTF-8', true)->setHeader('Cache-Control','public, max-age=300',true)->setContents($xml->outputMemory());
    }
}
