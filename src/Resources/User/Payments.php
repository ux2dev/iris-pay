<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Request\BudgetDirectData;
use Ux2Dev\Iris\Api\Request\BudgetPaymentData;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;

final class Payments extends UserResource
{
    public function createDirect(DirectPaymentData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/payment/direct', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createDirectInitiate(DirectPaymentData $data): BankScaUrl
    {
        $response = $this->transport->post('/api/8/payment/direct-initiate', $data->toArray(), $this->credentials->user($this->userHash));

        return BankScaUrl::fromArray($response);
    }

    public function createIban(IbanPaymentData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/payment/iban', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function confirm(string $code, int $ibanId): void
    {
        $this->transport->put('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->credentials->user($this->userHash));
    }

    public function confirmWithResult(string $code, int $ibanId): PaymentConfirmResult
    {
        $response = $this->transport->post('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->credentials->user($this->userHash));

        return PaymentConfirmResult::fromArray($response);
    }

    public function confirmSms(string $code, int $ibanId, string $smsCode): void
    {
        $this->transport->put('/api/8/payment/verify-sms', [
            'code' => $code, 'ibanId' => $ibanId, 'smsCode' => $smsCode,
        ], $this->credentials->user($this->userHash));
    }

    public function statusByCode(string $code): PaymentStatusDetail
    {
        $data = $this->transport->get('/api/8/payment/status', $this->credentials->user($this->userHash), ['code' => $code]);

        return PaymentStatusDetail::fromArray($data);
    }

    public function authorization(int $ibanId): BankSca
    {
        $data = $this->transport->get("/api/8/payment/ibans/{$ibanId}", $this->credentials->user($this->userHash));

        return BankSca::fromArray($data);
    }

    public function sca(int $ibanId): BankSca
    {
        $data = $this->transport->postEmpty("/api/8/payment/sca/{$ibanId}", $this->credentials->user($this->userHash));

        return BankSca::fromArray($data);
    }

    public function createBudget(BudgetPaymentData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/budget-request', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBudgetDirect(BudgetDirectData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/budget-request/no-iban', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }
}
