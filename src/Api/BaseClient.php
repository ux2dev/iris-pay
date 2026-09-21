<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;

abstract class BaseClient
{
    public function __construct(
        protected readonly MerchantConfig $config,
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
    ) {
        if ($config->agentHash === null) {
            throw new ConfigurationException('agentHash is required for IRIS Core API');
        }

        $this->validateHeaderValue($config->agentHash, 'agentHash');
    }

    protected function baseUrl(): string
    {
        return $this->config->environment->webSdkBaseUrl();
    }

    /** @return array<string, string> */
    protected function agentHeaders(): array
    {
        return ['x-agent-hash' => $this->config->agentHash];
    }

    /** @return array<string, string> */
    protected function userHeaders(string $userHash): array
    {
        $this->validateHeaderValue($userHash, 'userHash');

        return ['x-user-hash' => $userHash];
    }

    /** @return array<string, string> */
    protected function bothHeaders(string $userHash): array
    {
        return array_merge($this->agentHeaders(), $this->userHeaders($userHash));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function getJson(string $path, array $headers = [], array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->send($request));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postJson(string $path, array $body, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->send($request));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postEmpty(string $path, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->send($request));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function postForString(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Accept', '*/*');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->send($request);
        $this->assertSuccess($response);

        $token = trim((string) $response->getBody(), '"');

        if ($token === '') {
            throw new InvalidResponseException('Empty response from IRIS API');
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function putJson(string $path, array $body = [], array $headers = [], array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('PUT', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        if ($body) {
            $request = $request->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->send($request));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    protected function putVoid(string $path, array $headers = [], array $query = []): void
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('PUT', $url)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->send($request));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function deleteVoid(string $path, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->send($request));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    protected function postVoid(string $path, array $body, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->send($request));
    }

    /**
     * Send an HTTP request, wrapping transport-level failures in NetworkException.
     */
    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Network error communicating with IRIS API', 0, $e);
        }
    }

    /** @param array<string, mixed> $query */
    private function buildUrl(string $path, array $query = []): string
    {
        $url = $this->baseUrl() . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->throwForStatus($statusCode);
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidResponseException('Invalid JSON response from IRIS API', ['status' => $statusCode]);
        }

        return $data;
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->throwForStatus($statusCode);
        }
    }

    private function throwForStatus(int $statusCode): never
    {
        $data = ['status' => $statusCode];

        if ($statusCode >= 400 && $statusCode < 500) {
            throw new ApiClientException("HTTP {$statusCode} from IRIS API", $data);
        }

        throw new ApiServerException("HTTP {$statusCode} from IRIS API", $data);
    }

    private function validateHeaderValue(string $value, string $name): void
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new ConfigurationException("{$name} contains invalid characters");
        }
    }
}
