<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Shi3ld\Content\Model\Editor\Entities;

class Locate extends Action implements HttpGetActionInterface
{
    protected $_publicActions = ['locate'];

    public function __construct(Context $context, private JsonFactory $results, private Entities $entities) { parent::__construct($context); }

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isXmlHttpRequest() || $this->getRequest()->getHeader('Sec-Fetch-Site') === 'cross-site' || !$this->_auth->isLoggedIn()) {
            return $result->setData(['links' => []]);
        }
        $this->_auth->getUser()->reload();
        $this->_auth->getAuthStorage()->prolong();
        $this->_auth->getAuthStorage()->refreshAcl();
        if (!$this->_auth->isLoggedIn() || !$this->_authorization->isAllowed('Shi3ld_Content::visual_edit')) {
            return $result->setData(['links' => []]);
        }
        $links = [];
        $articleId = (int) $this->getRequest()->getParam('article_id');
        if ($articleId > 0) {
            try {
                $this->entities->load('article', $articleId);
                $links[] = ['title' => 'Edit this article', 'url' => $this->getUrl('shi3ld_content/editor/index', ['type' => 'article','id' => $articleId])];
            } catch (\Magento\Framework\Exception\LocalizedException $exception) {
                // A stale storefront link must not reveal deleted content or stop shared-region editing.
            }
        }
        foreach ($this->entities->navigation() as $entity) {
            if ($entity['type'] === 'block' || ($entity['type'] === 'page' && $entity['id'] === (int) $this->getRequest()->getParam('page_id'))) {
                $links[] = ['title' => $entity['type'] === 'page' ? 'Edit this page' : $entity['title'],
                    'url' => $this->getUrl('shi3ld_content/editor/index', ['type' => $entity['type'], 'id' => $entity['id']])];
            }
        }
        return $result->setData(['links' => $links]);
    }

    protected function _isAllowed(): bool { return true; }
}
