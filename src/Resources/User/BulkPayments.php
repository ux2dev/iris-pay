<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Request\BulkBudgetData;
use Ux2Dev\Iris\Api\Request\BulkBudgetIbanData;
use Ux2Dev\Iris\Api\Request\BulkIbanPaymentData;
use Ux2Dev\Iris\Api\Request\BulkPaymentData;
use Ux2Dev\Iris\Api\Response\PaymentResponse;

final class BulkPayments extends UserResource
{
    public function create(BulkPaymentData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/bulk-payments/payment', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createIban(BulkIbanPaymentData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/bulk-payments/request', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBudget(BulkBudgetData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/bulk-payments/budget-payment', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBudgetIban(BulkBudgetIbanData $data): PaymentResponse
    {
        $response = $this->transport->post('/api/8/bulk-payments/budget-request', $data->toArray(), $this->credentials->user($this->userHash));

        return PaymentResponse::fromArray($response);
    }
}
