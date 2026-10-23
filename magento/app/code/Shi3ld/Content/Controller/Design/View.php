<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Design;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Shi3ld\Content\Model\Editor\Preview;

class View implements HttpGetActionInterface
{
    public function __construct(private Preview $preview, private Http $request,
        private PageFactory $pages, private ForwardFactory $forwards) {}

    public function execute()
    {
        $draft = $this->preview->get();
        if (! $draft || $draft['type'] !== 'asset' || (int) $draft['id'] !== (int) $this->request->getParam('id')) {
            return $this->forwards->create()->forward('noroute');
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set($draft['payload']['title']);
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        return $page;
    }
}
