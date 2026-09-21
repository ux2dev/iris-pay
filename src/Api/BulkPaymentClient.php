<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\BulkBudgetData;
use Ux2Dev\Iris\Api\Request\BulkBudgetIbanData;
use Ux2Dev\Iris\Api\Request\BulkIbanPaymentData;
use Ux2Dev\Iris\Api\Request\BulkPaymentData;
use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\BulkPayment;
use Ux2Dev\Iris\Api\Response\BulkPaymentsList;
use Ux2Dev\Iris\Api\Response\PaymentResponse;

final class BulkPaymentClient extends BaseClient
{
    public function createBulkPayment(string $userHash, BulkPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/bulk-payments/payment', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBulkIbanPayment(string $userHash, BulkIbanPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/bulk-payments/request', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBulkBudgetPayment(string $userHash, BulkBudgetData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/bulk-payments/budget-payment', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBulkBudgetIbanPayment(string $userHash, BulkBudgetIbanData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/bulk-payments/budget-request', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function getBulkStatus(string $hookHash): BulkPayment
    {
        $data = $this->getJson("/api/8/bulk-payments/status/{$hookHash}", $this->agentHeaders());

        return BulkPayment::fromArray($data);
    }

    public function searchBulkPayments(PaymentSearchData $data): BulkPaymentsList
    {
        $response = $this->postJson('/api/8/bulk-payments/search', $data->toArray(), $this->agentHeaders());

        return BulkPaymentsList::fromArray($response);
    }
}
