<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

final class PayByLink extends Resource
{
    public function __construct(
        IrisTransport $transport,
        Credentials $credentials,
        private readonly MerchantConfig $config,
    ) {
        parent::__construct($transport, $credentials);
    }

    /** @return Bank[] */
    public function getBanks(): array
    {
        $data = $this->transport->get('/backend/payment/banks/' . $this->credentials->publicHash());

        return array_map(fn (array $bank) => Bank::fromArray($bank), $data);
    }

    /** @param string[]|null $bankHashes */
    public function createLink(
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

        $data = $this->transport->post(
            '/backend/payment/external/' . $this->credentials->publicHash(),
            $body,
        );

        return PaymentLinkResponse::fromArray($data);
    }

    public function getQrCode(string $paymentHash): string
    {
        return $this->transport->getRaw("/backend/payment/qr/{$paymentHash}");
    }

    public function getStatus(string $paymentHash): PaymentStatusResponse
    {
        $data = $this->transport->get("/backend/payment/status/{$paymentHash}");

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

        $data = $this->transport->post(
            '/backend/payment/external/refund/' . $this->credentials->publicHash(),
            $body,
        );

        return RefundResponse::fromArray($data);
    }

    public function deactivate(string $paymentHash): void
    {
        $this->transport->putVoid(
            '/backend/payment/inactive/' . $this->credentials->publicHash(),
            ['paymentHash' => $paymentHash],
        );
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
