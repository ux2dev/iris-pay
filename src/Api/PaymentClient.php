<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\BudgetDirectData;
use Ux2Dev\Iris\Api\Request\BudgetPaymentData;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\Payment;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;

final class PaymentClient extends BaseClient
{
    public function createDirectPayment(string $userHash, DirectPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/payment/direct', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createDirectInitiate(string $userHash, DirectPaymentData $data): BankScaUrl
    {
        $response = $this->postJson('/api/8/payment/direct-initiate', $data->toArray(), $this->userHeaders($userHash));

        return BankScaUrl::fromArray($response);
    }

    public function createIbanPayment(string $userHash, IbanPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/payment/iban', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function confirmPayment(string $userHash, string $code, int $ibanId): void
    {
        $this->putJson('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->userHeaders($userHash));
    }

    public function confirmPaymentWithResult(string $userHash, string $code, int $ibanId): PaymentConfirmResult
    {
        $response = $this->postJson('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->userHeaders($userHash));

        return PaymentConfirmResult::fromArray($response);
    }

    public function confirmPaymentSms(string $userHash, string $code, int $ibanId, string $smsCode): void
    {
        $this->putJson('/api/8/payment/verify-sms', [
            'code' => $code, 'ibanId' => $ibanId, 'smsCode' => $smsCode,
        ], $this->userHeaders($userHash));
    }

    public function getStatusByHookHash(string $hookHash): Payment
    {
        $data = $this->getJson("/api/8/status/{$hookHash}", $this->agentHeaders());

        return Payment::fromArray($data);
    }

    public function getStatusByCode(string $userHash, string $code): PaymentStatusDetail
    {
        $data = $this->getJson('/api/8/payment/status', $this->userHeaders($userHash), ['code' => $code]);

        return PaymentStatusDetail::fromArray($data);
    }

    public function getPaymentAuthorization(string $userHash, int $ibanId): BankSca
    {
        $data = $this->getJson("/api/8/payment/ibans/{$ibanId}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    public function getPaymentSca(string $userHash, int $ibanId): BankSca
    {
        $data = $this->postEmpty("/api/8/payment/sca/{$ibanId}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    public function createBudgetPayment(string $userHash, BudgetPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/budget-request', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBudgetDirectPayment(string $userHash, BudgetDirectData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/budget-request/no-iban', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }
}
