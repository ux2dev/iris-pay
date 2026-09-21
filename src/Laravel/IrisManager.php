<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Iris;

/**
 * Laravel integration. Resolves merchant configuration from `config/iris.php`
 * and exposes a lazy, cached {@see Iris} instance per merchant.
 */
final class IrisManager
{
    /** @var array<string, Iris> */
    private array $instances = [];

    /** @var array<string, MerchantConfig> */
    private array $configs = [];

    private string $currentMerchant;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->currentMerchant = (string) ($config['default'] ?? 'main');
    }

    public function merchant(string $name): self
    {
        $clone = clone $this;
        $clone->currentMerchant = $name;

        return $clone;
    }

    public function currentMerchant(): string
    {
        return $this->currentMerchant;
    }

    public function client(): Iris
    {
        return $this->instances[$this->currentMerchant] ??= $this->build($this->currentMerchant);
    }

    public function getConfig(): MerchantConfig
    {
        return $this->resolveConfig($this->currentMerchant);
    }

    /**
     * Forward any Iris accessor — payByLink(), user(), agent() — straight through.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }

    private function build(string $merchant): Iris
    {
        $config = $this->resolveConfig($merchant);
        $factory = new HttpFactory();

        return new Iris(
            $config,
            $this->httpClient ?? new Client(['timeout' => $config->timeout]),
            $this->requestFactory ?? $factory,
            $this->streamFactory ?? $factory,
        );
    }

    private function resolveConfig(string $name): MerchantConfig
    {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        $merchants = (array) ($this->config['merchants'] ?? []);

        if (! isset($merchants[$name]) || ! is_array($merchants[$name])) {
            throw new ConfigurationException("Merchant \"{$name}\" is not configured");
        }

        $m = $merchants[$name];

        return $this->configs[$name] = new MerchantConfig(
            environment: Environment::from($m['environment'] ?? 'production'),
            publicHash: $m['public_hash'] ?? null,
            agentHash: $m['agent_hash'] ?? null,
            adminHash: $m['admin_hash'] ?? null,
            currency: Currency::from($m['currency'] ?? 'EUR'),
            language: Language::from($m['language'] ?? 'bg'),
            timeout: (int) ($m['timeout'] ?? 30),
        );
    }
}
