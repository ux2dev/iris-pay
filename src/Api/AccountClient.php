<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\CreateConsentData;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\Consent;
use Ux2Dev\Iris\Api\Response\ConsentDetails;
use Ux2Dev\Iris\Api\Response\TokenInfo;
use Ux2Dev\Iris\Api\Response\Transaction;
use Ux2Dev\Iris\Api\Response\TransactionList;

final class AccountClient extends BaseClient
{
    /** @return BankInfo[] */
    public function listBanks(string $userHash, ?string $country = null): array
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson('/api/8/banks', $this->userHeaders($userHash), $query);

        return array_map(fn (array $b) => BankInfo::fromArray($b), $data);
    }

    public function getBank(string $userHash, string $bankHash, ?string $country = null): BankInfo
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson("/api/8/banks/{$bankHash}", $this->userHeaders($userHash), $query);

        return BankInfo::fromArray($data);
    }

    public function getBankSca(string $userHash, string $bankHash): BankSca
    {
        $data = $this->postEmpty("/api/8/bank/{$bankHash}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    /** @return BankAccount[] */
    public function listIbans(string $userHash, bool $consentDetails = false): array
    {
        $headers = $this->userHeaders($userHash);
        if ($consentDetails) {
            $headers['consent-details'] = 'true';
        }

        $data = $this->getJson('/api/8/ibans', $headers);

        return array_map(fn (array $a) => BankAccount::fromArray($a), $data);
    }

    public function deleteIban(string $userHash, int $ibanId): void
    {
        $this->deleteVoid("/api/8/iban/{$ibanId}", $this->userHeaders($userHash));
    }

    public function getBalance(string $userHash, int $ibanId): BalanceList
    {
        $data = $this->getJson("/api/8/balance/{$ibanId}", $this->userHeaders($userHash));

        return BalanceList::fromArray($data);
    }

    public function listTransactions(
        string $userHash,
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }

        $data = $this->getJson("/api/8/transactions/{$ibanId}", $this->userHeaders($userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function listPagedTransactions(
        string $userHash,
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $nextPageUrl = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }
        if ($nextPageUrl !== null) { $query['nextPageUrl'] = $nextPageUrl; }

        $data = $this->getJson("/api/8/paged-transactions/{$ibanId}", $this->userHeaders($userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function getTransaction(string $userHash, int $ibanId, string $transactionId): Transaction
    {
        $data = $this->getJson("/api/8/transactions/{$ibanId}/{$transactionId}", $this->userHeaders($userHash));

        return Transaction::fromArray($data);
    }

    /** @return TokenInfo[] */
    public function listTokens(string $userHash, ?string $country = null): array
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson('/api/8/tokens', $this->userHeaders($userHash), $query);

        return array_map(fn (array $t) => TokenInfo::fromArray($t), $data);
    }

    public function createConsent(string $userHash, CreateConsentData $data): BankSca
    {
        $response = $this->postJson('/api/8/consent', $data->toArray(), $this->userHeaders($userHash));

        return BankSca::fromArray($response);
    }

    /** @return Consent[] */
    public function getConsents(string $userHash, int $ibanId): array
    {
        $data = $this->getJson("/api/8/consents/{$ibanId}", $this->userHeaders($userHash));
        $consents = $data['consents'] ?? $data;

        return array_map(fn (array $c) => Consent::fromArray($c), is_array($consents) ? $consents : []);
    }

    public function getConsentDetails(string $iban): ConsentDetails
    {
        $data = $this->getJson("/api/8/consent/iban/{$iban}", $this->agentHeaders());

        return ConsentDetails::fromArray($data);
    }
}
