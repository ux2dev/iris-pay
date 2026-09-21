<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Api\Request\ConsentGateRequestData;
use Ux2Dev\Iris\Api\Response\ConsentGateResponse;
use Ux2Dev\Iris\Api\Response\ConsentGateStatus;
use Ux2Dev\Iris\Api\Response\ConsentGateUi;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Exception\ConfigurationException;

final class ConsentGateClient extends BaseClient
{
    public function __construct(
        MerchantConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        parent::__construct($config, $httpClient, $requestFactory, $streamFactory);

        if ($config->adminHash === null) {
            throw new ConfigurationException('adminHash is required for Consent Gate API');
        }
    }

    public function createConsentRequest(ConsentGateRequestData $data): ConsentGateResponse
    {
        $response = $this->postJson('/api/cgate/consents-request', $data->toArray(), $this->adminAgentHeaders());

        return ConsentGateResponse::fromArray($response);
    }

    /** @return ConsentGateStatus[] */
    public function getConsents(string $userHash, bool $fulfilled = true): array
    {
        $query = ['fulfilled' => $fulfilled ? 'true' : 'false'];
        $data = $this->getJson("/api/cgate/consents-request/{$userHash}", $this->adminHeaders(), $query);

        return array_map(fn (array $item) => ConsentGateStatus::fromArray($item), $data);
    }

    public function getUiConsentRequest(string $userHash): ConsentGateUi
    {
        $data = $this->getJson('/api/cgate/ui/consents-request', $this->userHeaders($userHash));

        return ConsentGateUi::fromArray($data);
    }

    /** @return array<string, string> */
    private function adminAgentHeaders(): array
    {
        return array_merge(
            ['x-admin-hash' => $this->config->adminHash],
            $this->agentHeaders(),
        );
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        return ['x-admin-hash' => $this->config->adminHash];
    }
}
