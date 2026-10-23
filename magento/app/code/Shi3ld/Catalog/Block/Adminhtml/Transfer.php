<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth;
use Shi3ld\Catalog\Model\Transfer\Environment;
use Shi3ld\Catalog\Model\Transfer\Jobs;

class Transfer extends Template
{
    public function __construct(Context $context, private Environment $environment, private Jobs $jobs, private Auth $auth, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getEnvironmentName(): string
    {
        return $this->environment->getName();
    }

    public function getTargetHost(): string
    {
        return $this->environment->getHost();
    }

    public function getUploadLimit(): string
    {
        return (string) ini_get('upload_max_filesize');
    }

    public function getTransfer(): ?array
    {
        $id = $this->getRequest()->getParam('job');
        if (!is_string($id) || $id === '') {
            return null;
        }
        try {
            return $this->jobs->get($id, (int) $this->auth->getUser()->getId());
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function getTransfers(): array
    {
        return $this->jobs->recent((int) $this->auth->getUser()->getId());
    }
}
