<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\ActiveUsersDetailsData;
use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\ActiveUsers;
use Ux2Dev\Iris\Api\Response\ActiveUsersDetails;
use Ux2Dev\Iris\Api\Response\BankMaintenance;
use Ux2Dev\Iris\Api\Response\PaymentSearchResult;
use Ux2Dev\Iris\Api\Response\PaymentsList;

final class ReportClient extends BaseClient
{
    public function listPayments(string $userHash, int $page = 0, int $size = 30, ?string $status = null): PaymentsList
    {
        $body = [
            'page' => $page,
            'size' => $size,
            'status' => $status,
            'userHash' => $userHash,
        ];

        $response = $this->postJson('/api/8/payments', $body, $this->agentHeaders());

        return PaymentsList::fromArray($response);
    }

    public function searchPayments(PaymentSearchData $data): PaymentSearchResult
    {
        $response = $this->postJson('/api/8/payments/search-last-updated', $data->toArray());

        return PaymentSearchResult::fromArray($response);
    }

    public function getActiveUsers(string $fromDate, string $toDate, ?string $validUntil = null): ActiveUsers
    {
        $body = [
            'agentHash' => $this->config->agentHash,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];

        if ($validUntil !== null) {
            $body['validUntil'] = $validUntil;
        }

        $response = $this->postJson('/api/8/reports/active-users', $body, $this->agentHeaders());

        return ActiveUsers::fromArray($response);
    }

    public function getActiveUsersDetails(ActiveUsersDetailsData $data): ActiveUsersDetails
    {
        $response = $this->postJson('/api/8/reports/active-users-details', $data->toArray(), $this->agentHeaders());

        return ActiveUsersDetails::fromArray($response);
    }

    /** @return BankMaintenance[] */
    public function getBankMaintenance(): array
    {
        $response = $this->getJson('/api/8/reports/bank-maintenance');

        return array_map(fn (array $item) => BankMaintenance::fromArray($item), $response);
    }
}
