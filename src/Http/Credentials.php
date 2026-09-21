<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Http;

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Exception\ConfigurationException;

/**
 * Produces IRIS auth headers from merchant configuration. Each accessor
 * validates the credential it needs at the moment it is asked for, so
 * endpoints that take no auth stay callable on a config that holds none.
 */
final class Credentials
{
    public function __construct(private readonly MerchantConfig $config)
    {
    }

    /** @return array<string, string> */
    public function agent(): array
    {
        return ['x-agent-hash' => $this->require($this->config->agentHash, 'agentHash')];
    }

    /** @return array<string, string> */
    public function user(string $userHash): array
    {
        return ['x-user-hash' => $this->require($userHash, 'userHash')];
    }

    /** @return array<string, string> */
    public function both(string $userHash): array
    {
        return array_merge($this->agent(), $this->user($userHash));
    }

    /** @return array<string, string> */
    public function admin(): array
    {
        return ['x-admin-hash' => $this->require($this->config->adminHash, 'adminHash')];
    }

    /** @return array<string, string> */
    public function adminAgent(): array
    {
        return array_merge($this->admin(), $this->agent());
    }

    public function publicHash(): string
    {
        return $this->require($this->config->publicHash, 'publicHash');
    }

    private function require(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            throw new ConfigurationException("{$name} is required for this operation");
        }

        if (preg_match('/[\r\n]/', $value)) {
            throw new ConfigurationException("{$name} contains invalid characters");
        }

        return $value;
    }
}
