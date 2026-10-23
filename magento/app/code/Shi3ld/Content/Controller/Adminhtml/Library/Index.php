<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Library;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(Context $context, private PageFactory $pages,
        private LayoutInterface $layout, private RawFactory $results,
        private FormKey $formKey)
    {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $page = $this->pages->create()->getCollection()->addFieldToFilter('is_active', 1)->getFirstItem();
        $config = ['title' => 'Shi3ld design library', 'csrf' => $this->formKey->getFormKey(), 'csrfName' => 'form_key',
            'commandUrl' => $this->getUrl('shi3ld_content/editor/command', ['type' => 'page', 'id' => $page->getId()]),
            'editorUrl' => $this->getUrl('shi3ld_content/editor/index'), 'backUrl' => $this->getUrl('shi3ld_content/pages/index'),
            'canDesign' => $this->_authorization->isAllowed('Shi3ld_Content::design'), 'canPublish' => $this->_authorization->isAllowed('Shi3ld_Content::visual_publish')];
        $html = $this->layout->createBlock(Template::class)->setTemplate('Shi3ld_Content::builder-library.phtml')->setData('builder_config', $config)->toHtml();

        return $this->results->create()->setHeader('Content-Type', 'text/html; charset=UTF-8', true)->setHeader('Cache-Control', 'private, no-store', true)->setContents($html);
    }
}
