<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Response\PaymentsList;

final class Reports extends UserResource
{
    public function listPayments(int $page = 0, int $size = 30, ?string $status = null): PaymentsList
    {
        $response = $this->transport->post('/api/8/payments', [
            'page' => $page,
            'size' => $size,
            'status' => $status,
            'userHash' => $this->userHash,
        ], $this->credentials->agent());

        return PaymentsList::fromArray($response);
    }
}
