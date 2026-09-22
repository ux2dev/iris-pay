<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\BulkPayment;
use Ux2Dev\Iris\Api\Response\BulkPaymentsList;

final class BulkPayments extends Resource
{
    public function status(string $hookHash): BulkPayment
    {
        $response = $this->transport->get("/api/8/bulk-payments/status/{$hookHash}", $this->credentials->agent());

        return BulkPayment::fromArray($response);
    }

    public function search(PaymentSearchData $data): BulkPaymentsList
    {
        $response = $this->transport->post('/api/8/bulk-payments/search', $data->toArray(), $this->credentials->agent());

        return BulkPaymentsList::fromArray($response);
    }
}
