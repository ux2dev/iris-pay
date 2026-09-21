<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Api\AccountClient;
use Ux2Dev\Iris\Api\AgentClient;
use Ux2Dev\Iris\Api\BulkPaymentClient;
use Ux2Dev\Iris\Api\ConsentGateClient;
use Ux2Dev\Iris\Api\PaymentClient;
use Ux2Dev\Iris\Api\ReportClient;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;

final class IrisManager
{
    private string $currentMerchant;

    /** @var array<string, MerchantConfig> */
    private array $configs = [];

    /** @var array<string, PayByLinkClient> */
    private array $payByLinkClients = [];

    /** @var array<string, AgentClient> */
    private array $agentClients = [];

    /** @var array<string, AccountClient> */
    private array $accountClients = [];

    /** @var array<string, PaymentClient> */
    private array $paymentClients = [];

    /** @var array<string, BulkPaymentClient> */
    private array $bulkPaymentClients = [];

    /** @var array<string, ReportClient> */
    private array $reportClients = [];

    /** @var array<string, ConsentGateClient> */
    private array $consentGateClients = [];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $this->currentMerchant = $config['default'] ?? 'main';
    }

    public function merchant(string $name): self
    {
        $clone = clone $this;
        $clone->currentMerchant = $name;

        return $clone;
    }

    public function getConfig(): MerchantConfig
    {
        return $this->resolveConfig($this->currentMerchant);
    }

    public function payByLink(): PayByLinkClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->payByLinkClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->payByLinkClients[$name] = new PayByLinkClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->payByLinkClients[$name];
    }

    public function agent(): AgentClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->agentClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->agentClients[$name] = new AgentClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->agentClients[$name];
    }

    public function account(): AccountClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->accountClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->accountClients[$name] = new AccountClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->accountClients[$name];
    }

    public function payment(): PaymentClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->paymentClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->paymentClients[$name] = new PaymentClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->paymentClients[$name];
    }

    public function bulkPayment(): BulkPaymentClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->bulkPaymentClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->bulkPaymentClients[$name] = new BulkPaymentClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->bulkPaymentClients[$name];
    }

    public function report(): ReportClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->reportClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->reportClients[$name] = new ReportClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->reportClients[$name];
    }

    public function consentGate(): ConsentGateClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->consentGateClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->consentGateClients[$name] = new ConsentGateClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->consentGateClients[$name];
    }

    private function resolveConfig(string $name): MerchantConfig
    {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        $merchants = $this->config['merchants'] ?? [];

        if (!isset($merchants[$name])) {
            throw new ConfigurationException("Merchant \"{$name}\" is not configured");
        }

        $m = $merchants[$name];

        $this->configs[$name] = new MerchantConfig(
            environment: Environment::from($m['environment'] ?? 'production'),
            publicHash: $m['public_hash'] ?? null,
            agentHash: $m['agent_hash'] ?? null,
            adminHash: $m['admin_hash'] ?? null,
            currency: Currency::from($m['currency'] ?? 'EUR'),
            language: Language::from($m['language'] ?? 'bg'),
        );

        return $this->configs[$name];
    }
}
