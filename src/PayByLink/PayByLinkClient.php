<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

final class PayByLinkClient
{
    public function __construct(
        private readonly MerchantConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        if ($config->publicHash === null) {
            throw new ConfigurationException('publicHash is required for PayByLink API');
        }
    }

    /** @return Bank[] */
    public function getBanks(): array
    {
        $data = $this->sendGet("/backend/payment/banks/{$this->config->publicHash}");

        return array_map(fn (array $bank) => Bank::fromArray($bank), $data);
    }

    /**
     * @param string[] $bankHashes
     */
    public function createPaymentLink(
        float $sum,
        string $description,
        string $toIban,
        string $hookUrl,
        string $redirectUrl,
        ?array $bankHashes = null,
        ?string $name = null,
        ?string $orderId = null,
        ?Currency $currency = null,
        ?Language $lang = null,
        bool $repayable = false,
    ): PaymentLinkResponse {
        $this->validateSum($sum);
        $this->validateDescription($description);
        $this->validateHttpsUrl($hookUrl, 'hookUrl');
        $this->validateHttpsUrl($redirectUrl, 'redirectUrl');

        if ($name !== null && mb_strlen($name) > 34) {
            throw new ConfigurationException('name must not exceed 34 characters');
        }

        $body = [
            'currency' => ($currency ?? $this->config->currency)->value,
            'description' => $description,
            'hookUrl' => $hookUrl,
            'redirectUrl' => $redirectUrl,
            'sum' => $sum,
            'toIban' => $toIban,
            'repayable' => $repayable,
        ];

        if ($bankHashes !== null) {
            $body['bankHashes'] = $bankHashes;
        }
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($orderId !== null) {
            $body['orderId'] = $orderId;
        }
        if ($lang !== null) {
            $body['lang'] = $lang->value;
        }

        $data = $this->sendPost("/backend/payment/external/{$this->config->publicHash}", $body);

        return PaymentLinkResponse::fromArray($data);
    }

    public function getQrCode(string $paymentHash): string
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUrl() . "/backend/payment/qr/{$paymentHash}",
        )->withHeader('Accept', '*/*');

        $response = $this->send($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            if ($statusCode >= 400 && $statusCode < 500) {
                throw new ApiClientException(
                    "HTTP {$statusCode} from QR code endpoint",
                    ['status' => $statusCode],
                );
            }
            throw new ApiServerException(
                "HTTP {$statusCode} from QR code endpoint",
                ['status' => $statusCode],
            );
        }

        return (string) $response->getBody();
    }

    public function getPaymentStatus(string $paymentHash): PaymentStatusResponse
    {
        $data = $this->sendGet("/backend/payment/status/{$paymentHash}");

        return PaymentStatusResponse::fromArray($data);
    }

    public function refund(
        string $paymentHash,
        RefundType $refundType,
        float $sum,
        string $remittanceDescription,
        string $webhookUrl,
        ?string $psuId = null,
    ): RefundResponse {
        $this->validateSum($sum);
        $this->validateHttpsUrl($webhookUrl, 'webhookUrl');

        $body = [
            'paymentHash' => $paymentHash,
            'refundType' => $refundType->value,
            'remittanceDescription' => $remittanceDescription,
            'sum' => $sum,
            'webhookUrl' => $webhookUrl,
        ];

        if ($psuId !== null) {
            $body['psuId'] = $psuId;
        }

        $data = $this->sendPost("/backend/payment/external/refund/{$this->config->publicHash}", $body);

        return RefundResponse::fromArray($data);
    }

    public function deactivate(string $paymentHash): void
    {
        $body = ['paymentHash' => $paymentHash];

        $request = $this->requestFactory->createRequest(
            'PUT',
            $this->baseUrl() . "/backend/payment/inactive/{$this->config->publicHash}",
        )
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        $response = $this->send($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            if ($statusCode >= 400 && $statusCode < 500) {
                throw new ApiClientException(
                    "HTTP {$statusCode} from deactivate endpoint",
                    ['status' => $statusCode],
                );
            }
            throw new ApiServerException(
                "HTTP {$statusCode} from deactivate endpoint",
                ['status' => $statusCode],
            );
        }
    }

    private function baseUrl(): string
    {
        return $this->config->environment->payByLinkBaseUrl();
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Network error communicating with IRIS API', 0, $e);
        }
    }

    /** @return array<string, mixed> */
    private function sendGet(string $path): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        $response = $this->send($request);

        return $this->parseJsonResponse($response);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sendPost(string $path, array $body): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        $response = $this->send($request);

        return $this->parseJsonResponse($response);
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            if ($statusCode >= 400 && $statusCode < 500) {
                throw new ApiClientException(
                    "HTTP {$statusCode} from IRIS API",
                    ['status' => $statusCode],
                );
            }
            throw new ApiServerException(
                "HTTP {$statusCode} from IRIS API",
                ['status' => $statusCode],
            );
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidResponseException('Invalid JSON response from IRIS API');
        }

        return $data;
    }

    private function validateSum(float $sum): void
    {
        if ($sum <= 0) {
            throw new ConfigurationException('sum must be greater than 0');
        }
    }

    private function validateDescription(string $description): void
    {
        if ($description === '') {
            throw new ConfigurationException('description must not be empty');
        }
        if (mb_strlen($description) > 240) {
            throw new ConfigurationException('description must not exceed 240 characters');
        }
    }

    private function validateHttpsUrl(string $url, string $field): void
    {
        if (!str_starts_with($url, 'https://')) {
            throw new ConfigurationException("{$field} must use https://");
        }
    }
}
