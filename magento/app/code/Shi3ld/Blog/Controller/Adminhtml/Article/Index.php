<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Article;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    protected string $screen = 'articles';

    protected string $heading = 'Blog Articles';

    public function __construct(Context $context, private PageFactory $pages)
    {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pages->create();
        $page->setActiveMenu('Shi3ld_Blog::'.$this->screen);
        $page->getConfig()->getTitle()->prepend(__($this->heading));
        $page->getLayout()->getBlock('shi3ld.blog.admin')?->setTemplate('Shi3ld_Blog::admin/'.$this->screen.'.phtml');

        return $page;
    }
}
