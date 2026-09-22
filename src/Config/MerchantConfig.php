<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Config;

use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;

final readonly class MerchantConfig
{
    public function __construct(
        public Environment $environment,
        public ?string $publicHash = null,
        public ?string $agentHash = null,
        public ?string $adminHash = null,
        public Currency $currency = Currency::EUR,
        public Language $language = Language::Bulgarian,
        public int $timeout = 30,
    ) {
        if ($publicHash !== null && $publicHash === '') {
            throw new ConfigurationException('publicHash must not be empty when provided');
        }
        if ($agentHash !== null && $agentHash === '') {
            throw new ConfigurationException('agentHash must not be empty when provided');
        }
        if ($adminHash !== null && $adminHash === '') {
            throw new ConfigurationException('adminHash must not be empty when provided');
        }
        if ($timeout < 1) {
            throw new ConfigurationException('timeout must be at least 1 second');
        }
    }

    public function __debugInfo(): array
    {
        return [
            'publicHash' => $this->publicHash !== null ? '[REDACTED]' : null,
            'agentHash' => $this->agentHash !== null ? '[REDACTED]' : null,
            'adminHash' => $this->adminHash !== null ? '[REDACTED]' : null,
            'environment' => $this->environment,
            'currency' => $this->currency,
            'language' => $this->language,
            'timeout' => $this->timeout,
        ];
    }

    public function __serialize(): array
    {
        throw new \LogicException(
            'MerchantConfig must not be serialized as it contains key material'
        );
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException(
            'MerchantConfig must not be unserialized'
        );
    }
}
