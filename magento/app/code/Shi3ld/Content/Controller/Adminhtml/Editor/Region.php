<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Cms\Model\BlockFactory;

class Region extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';
    protected $_publicActions = ['region'];
    public function __construct(Context $context, private BlockFactory $blocks) { parent::__construct($context); }
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $region = $this->getRequest()->getParam('region');
        if (!in_array($region, ['header','footer'], true)) {
            throw new \Magento\Framework\Exception\NotFoundException(__('Choose a valid shared region.'));
        }
        $block = $this->blocks->create()->getCollection()->addFieldToFilter('identifier', 'shi3ld_global_'.$region)->getFirstItem();
        return $this->resultRedirectFactory->create()->setPath('*/*/index', ['type' => 'block', 'id' => $block->getId()]);
    }
}
