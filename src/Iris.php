<?php

declare(strict_types=1);

namespace Ux2Dev\Iris;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\Accounts;
use Ux2Dev\Iris\Resources\Agent;
use Ux2Dev\Iris\Resources\BulkPayments;
use Ux2Dev\Iris\Resources\ConsentGate;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\Resources\Payments;
use Ux2Dev\Iris\Resources\Reports;

/**
 * Framework-agnostic entry point for the IRIS Solutions SDK. Instantiate
 * once per merchant with a PSR-18 client and PSR-17 factories, then reach
 * endpoints through the resource accessors.
 */
final class Iris
{
    private readonly IrisTransport $core;
    private readonly IrisTransport $payByLinkTransport;
    private readonly Credentials $credentials;

    private ?PayByLink $payByLink = null;
    private ?Agent $agent = null;
    private ?Accounts $accounts = null;
    private ?Payments $payments = null;
    private ?BulkPayments $bulkPayments = null;
    private ?Reports $reports = null;
    private ?ConsentGate $consentGate = null;

    public function __construct(
        private readonly MerchantConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $this->core = new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            $httpClient,
            $requestFactory,
            $streamFactory,
        );

        $this->payByLinkTransport = new IrisTransport(
            $config->environment->payByLinkBaseUrl(),
            $httpClient,
            $requestFactory,
            $streamFactory,
        );

        $this->credentials = new Credentials($config);
    }

    public function config(): MerchantConfig
    {
        return $this->config;
    }

    public function user(string $userHash): UserScope
    {
        return new UserScope($this->core, $this->credentials, $userHash);
    }

    public function payByLink(): PayByLink
    {
        return $this->payByLink ??= new PayByLink($this->payByLinkTransport, $this->credentials, $this->config);
    }

    public function agent(): Agent
    {
        return $this->agent ??= new Agent($this->core, $this->credentials);
    }

    public function accounts(): Accounts
    {
        return $this->accounts ??= new Accounts($this->core, $this->credentials);
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments($this->core, $this->credentials);
    }

    public function bulkPayments(): BulkPayments
    {
        return $this->bulkPayments ??= new BulkPayments($this->core, $this->credentials);
    }

    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->core, $this->credentials);
    }

    public function consentGate(): ConsentGate
    {
        return $this->consentGate ??= new ConsentGate($this->core, $this->credentials);
    }
}
