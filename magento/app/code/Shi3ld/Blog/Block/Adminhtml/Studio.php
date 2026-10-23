<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;

class Studio extends Template
{
    public function __construct(Context $context, private AuthorizationInterface $authorization, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function configuration(): array
    {
        return ['url' => $this->getUrl('shi3ld_blog/studio/command'), 'csrf' => $this->getFormKey(), 'canPublish' => $this->authorization->isAllowed('Shi3ld_Content::visual_publish'), 'canDesign' => $this->authorization->isAllowed('Shi3ld_Content::design'),
            'articlesUrl' => $this->getUrl('shi3ld_blog/article/index'), 'categoriesUrl' => $this->getUrl('shi3ld_blog/category/index'), 'libraryUrl' => $this->getUrl('shi3ld_content/library/index'), 'editorUrl' => $this->getUrl('shi3ld_content/editor/index')];
    }
}
