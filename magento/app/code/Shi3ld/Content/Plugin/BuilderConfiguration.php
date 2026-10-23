<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Magento\Framework\AuthorizationInterface;
use Shi3ld\Content\Model\Builder\Commands;
use Shi3ld\Content\Model\Editor\Entities;

class BuilderConfiguration
{
    public function __construct(private Commands $commands, private AuthorizationInterface $authorization) {}

    public function afterConfiguration(Entities $subject, array $config, string $type, int $id): array
    {
        return $config + $this->commands->configuration($this->authorization->isAllowed('Shi3ld_Content::design'));
    }
}
