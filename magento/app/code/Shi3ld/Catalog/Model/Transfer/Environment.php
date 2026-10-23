<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;

class Environment
{
    public function __construct(private State $state, private StoreManagerInterface $stores)
    {
    }

    public function getName(): string
    {
        $name = getenv('SHI3LD_ENVIRONMENT') ?: ($this->state->getMode() === State::MODE_PRODUCTION ? 'production' : 'development');
        if (!in_array($name, ['development', 'staging', 'production'], true)) {
            throw new \RuntimeException('SHI3LD_ENVIRONMENT must be development, staging or production.');
        }
        return $name;
    }

    public function getHost(): string
    {
        return (string) parse_url($this->stores->getDefaultStoreView()->getBaseUrl(), PHP_URL_HOST);
    }

    public function getCurrency(): string
    {
        return (string) $this->stores->getDefaultStoreView()->getBaseCurrencyCode();
    }

    public function getFlowError(string $source, bool $allowSameEnvironment = false): ?string
    {
        $target = $this->getName();
        if (($source === 'development' && $target === 'staging')
            || ($source === 'staging' && $target === 'production')
            || ($allowSameEnvironment && $source === $target)
        ) {
            return null;
        }
        return sprintf('This package is from %s; the target is %s. The supported promotion flow is development → staging → production.', $source, $target);
    }
}
