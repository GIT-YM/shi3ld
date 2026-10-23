<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Security\Model\AdminSessionsManager;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Model\Editor\Entities;
use Shi3ld\Content\Model\PreviewSession;
use Shi3ld\Content\Model\SiteAccess;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';
    protected $_publicActions = ['index'];

    public function __construct(
        Context $context, private Documents $documents, private Entities $entities,
        private LayoutInterface $layout, private RawFactory $results, private FormKey $formKey,
        private SiteAccess $siteAccess, private PreviewSession $access, private AdminSessionsManager $sessions
    ) { parent::__construct($context); }

    public function execute(): Raw
    {
        $type = (string) $this->getRequest()->getParam('type', 'page');
        $id = (int) $this->getRequest()->getParam('id');
        $entity = $this->entities->load($type, $id);
        if ($this->siteAccess->isOffline()) {
            $this->access->start($this->_auth->getUser(), (int) $this->sessions->getCurrentSession()->getId());
        }
        $navigation = $this->entities->navigation();
        if (!array_filter($navigation, fn ($item) => $item['type'] === $type && (int) $item['id'] === $id)) {
            $navigation[] = ['type' => $type, 'id' => $id, 'title' => $entity->getTitle()];
        }
        foreach ($navigation as &$item) {
            $item['key'] = $item['type'].'-'.$item['id'];
            $item['url'] = $this->getUrl('*/*/index', ['type' => $item['type'], 'id' => $item['id']]);
        }
        unset($item);
        $region = in_array($type, ['page','article'], true) ? 'page' : str_replace('shi3ld_global_', '', $entity->getIdentifier());
        $config = [
            'framework' => 'Magento', 'actor' => (int) $this->_auth->getUser()->getId(), 'key' => $type.'-'.$id, 'title' => $entity->getTitle(), 'commandUrl' => $this->getUrl('*/*/command', ['type' => $type, 'id' => $id]),
            'csrf' => $this->formKey->getFormKey(), 'csrfName' => 'form_key', 'state' => $this->documents->get($type, $id),
            'liveUrl' => $this->entities->url($type, $id), 'selector' => '[data-shi3ld-region="'.$region.'"]',
            'backUrl' => $this->getUrl('shi3ld_content/pages/index'), 'navigation' => $navigation,
            'canPublish' => $this->_authorization->isAllowed('Shi3ld_Content::visual_publish'), 'pageBuilder' => true,
            'nativeUrl' => $this->getUrl($type === 'page' ? 'cms/page/edit' : 'cms/block/edit', [$type === 'page' ? 'page_id' : 'block_id' => $id]),
        ];
        $config = array_replace($config, $this->entities->configuration($type, $id));
        $html = $this->layout->createBlock(Template::class)->setTemplate('Shi3ld_Content::editor.phtml')->setData('editor_config', $config)->toHtml();
        return $this->results->create()->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'private, no-store, max-age=0', true)->setContents($html);
    }
}
