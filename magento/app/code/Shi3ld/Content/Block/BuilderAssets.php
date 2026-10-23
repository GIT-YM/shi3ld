<?php

declare(strict_types=1);

namespace Shi3ld\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Shi3ld\Content\Model\Builder\Runtime;

class BuilderAssets extends Template
{
    public function __construct(Context $context,
        private Runtime $runtime, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function styles(): string
    {
        return $this->runtime->globalHtml();
    }

    public function popups(): string
    {
        return $this->runtime->popups();
    }
}
