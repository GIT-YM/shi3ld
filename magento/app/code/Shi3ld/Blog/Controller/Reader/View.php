<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Reader;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public function __construct(private PageFactory $pages) {}

    public function execute(): Page
    {
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set('Your article subscription');
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        return $page->setHeader('Cache-Control', 'private, no-store', true)->setHeader('Referrer-Policy', 'no-referrer', true)->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
    }
}
