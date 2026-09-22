<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;

/**
 * Low-level HTTP transport for one IRIS host. Resources build the path,
 * body and auth headers and call one of the verb methods. This class knows
 * nothing about credentials.
 */
final class IrisTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $headers = [], array $query = []): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->buildUrl($path, $query))
            ->withHeader('Accept', 'application/json');

        return $this->parseJsonResponse($this->send($this->withHeaders($request, $headers)));
    }

    /** @param array<string, string> $headers */
    public function getRaw(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $path)
            ->withHeader('Accept', '*/*');

        $response = $this->send($this->withHeaders($request, $headers));
        $this->assertSuccess($response);

        return (string) $response->getBody();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function post(string $path, array $body, array $headers = []): array
    {
        return $this->parseJsonResponse($this->send($this->jsonRequest('POST', $path, $body, $headers, skipEmptyBody: false)));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function postEmpty(string $path, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        return $this->parseJsonResponse($this->send($this->withHeaders($request, $headers)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function postVoid(string $path, array $body, array $headers = []): void
    {
        $this->assertSuccess($this->send($this->jsonRequest('POST', $path, $body, $headers, skipEmptyBody: false)));
    }

    /** @param array<string, string> $headers */
    public function postForString(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Accept', '*/*');

        $response = $this->send($this->withHeaders($request, $headers));
        $this->assertSuccess($response);

        $value = trim((string) $response->getBody(), '"');

        if ($value === '') {
            throw new InvalidResponseException('Empty response from IRIS API');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = [], array $headers = [], array $query = []): array
    {
        return $this->parseJsonResponse($this->send($this->jsonRequest('PUT', $path, $body, $headers, $query, skipEmptyBody: true)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    public function putVoid(string $path, array $body = [], array $headers = [], array $query = []): void
    {
        $this->assertSuccess($this->send($this->jsonRequest('PUT', $path, $body, $headers, $query, skipEmptyBody: true)));
    }

    /** @param array<string, string> $headers */
    public function delete(string $path, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        $this->assertSuccess($this->send($this->withHeaders($request, $headers)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    private function jsonRequest(
        string $method,
        string $path,
        array $body,
        array $headers,
        array $query = [],
        bool $skipEmptyBody = false,
    ): RequestInterface {
        $request = $this->requestFactory->createRequest($method, $this->buildUrl($path, $query))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        if (!$skipEmptyBody || $body !== []) {
            $request = $request->withBody(
                $this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)),
            );
        }

        return $this->withHeaders($request, $headers);
    }

    /** @param array<string, string> $headers */
    private function withHeaders(RequestInterface $request, array $headers): RequestInterface
    {
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

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
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $this->assertSuccess($response);

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data)) {
            throw new InvalidResponseException(
                'Invalid JSON response from IRIS API',
                ['status' => $response->getStatusCode()],
            );
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
}
