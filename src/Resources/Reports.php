<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Request\ActiveUsersDetailsData;
use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\ActiveUsers;
use Ux2Dev\Iris\Api\Response\ActiveUsersDetails;
use Ux2Dev\Iris\Api\Response\BankMaintenance;
use Ux2Dev\Iris\Api\Response\PaymentSearchResult;

final class Reports extends Resource
{
    public function searchPayments(PaymentSearchData $data): PaymentSearchResult
    {
        $response = $this->transport->post('/api/8/payments/search-last-updated', $data->toArray());

        return PaymentSearchResult::fromArray($response);
    }

    public function activeUsers(string $fromDate, string $toDate, ?string $validUntil = null): ActiveUsers
    {
        $body = [
            'agentHash' => $this->credentials->agent()['x-agent-hash'],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];

        if ($validUntil !== null) {
            $body['validUntil'] = $validUntil;
        }

        $response = $this->transport->post('/api/8/reports/active-users', $body, $this->credentials->agent());

        return ActiveUsers::fromArray($response);
    }

    public function activeUsersDetails(ActiveUsersDetailsData $data): ActiveUsersDetails
    {
        $response = $this->transport->post('/api/8/reports/active-users-details', $data->toArray(), $this->credentials->agent());

        return ActiveUsersDetails::fromArray($response);
    }

    /** @return BankMaintenance[] */
    public function bankMaintenance(): array
    {
        $response = $this->transport->get('/api/8/reports/bank-maintenance');

        return array_map(fn (array $item) => BankMaintenance::fromArray($item), $response);
    }
}
