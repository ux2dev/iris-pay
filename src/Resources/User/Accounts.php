<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Request\CreateConsentData;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\Consent;
use Ux2Dev\Iris\Api\Response\TokenInfo;
use Ux2Dev\Iris\Api\Response\Transaction;
use Ux2Dev\Iris\Api\Response\TransactionList;

final class Accounts extends UserResource
{
    /** @return BankInfo[] */
    public function listBanks(?string $country = null): array
    {
        $query = [];
        if ($country !== null) {
            $query['country'] = $country;
        }

        $data = $this->transport->get('/api/8/banks', $this->credentials->user($this->userHash), $query);

        return array_map(fn (array $b) => BankInfo::fromArray($b), $data);
    }

    public function getBank(string $bankHash, ?string $country = null): BankInfo
    {
        $query = [];
        if ($country !== null) {
            $query['country'] = $country;
        }

        $data = $this->transport->get("/api/8/banks/{$bankHash}", $this->credentials->user($this->userHash), $query);

        return BankInfo::fromArray($data);
    }

    public function getBankSca(string $bankHash): BankSca
    {
        $data = $this->transport->postEmpty("/api/8/bank/{$bankHash}", $this->credentials->user($this->userHash));

        return BankSca::fromArray($data);
    }

    /** @return BankAccount[] */
    public function listIbans(bool $consentDetails = false): array
    {
        $headers = $this->credentials->user($this->userHash);
        if ($consentDetails) {
            $headers['consent-details'] = 'true';
        }

        $data = $this->transport->get('/api/8/ibans', $headers);

        return array_map(fn (array $a) => BankAccount::fromArray($a), $data);
    }

    public function deleteIban(int $ibanId): void
    {
        $this->transport->delete("/api/8/iban/{$ibanId}", $this->credentials->user($this->userHash));
    }

    public function getBalance(int $ibanId): BalanceList
    {
        $data = $this->transport->get("/api/8/balance/{$ibanId}", $this->credentials->user($this->userHash));

        return BalanceList::fromArray($data);
    }

    public function listTransactions(
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) {
            $query['dateFrom'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $query['dateTo'] = $dateTo;
        }

        $data = $this->transport->get("/api/8/transactions/{$ibanId}", $this->credentials->user($this->userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function listPagedTransactions(
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $nextPageUrl = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) {
            $query['dateFrom'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $query['dateTo'] = $dateTo;
        }
        if ($nextPageUrl !== null) {
            $query['nextPageUrl'] = $nextPageUrl;
        }

        $data = $this->transport->get("/api/8/paged-transactions/{$ibanId}", $this->credentials->user($this->userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function getTransaction(int $ibanId, string $transactionId): Transaction
    {
        $data = $this->transport->get("/api/8/transactions/{$ibanId}/{$transactionId}", $this->credentials->user($this->userHash));

        return Transaction::fromArray($data);
    }

    /** @return TokenInfo[] */
    public function listTokens(?string $country = null): array
    {
        $query = [];
        if ($country !== null) {
            $query['country'] = $country;
        }

        $data = $this->transport->get('/api/8/tokens', $this->credentials->user($this->userHash), $query);

        return array_map(fn (array $t) => TokenInfo::fromArray($t), $data);
    }

    public function createConsent(CreateConsentData $data): BankSca
    {
        $response = $this->transport->post('/api/8/consent', $data->toArray(), $this->credentials->user($this->userHash));

        return BankSca::fromArray($response);
    }

    /** @return Consent[] */
    public function getConsents(int $ibanId): array
    {
        $data = $this->transport->get("/api/8/consents/{$ibanId}", $this->credentials->user($this->userHash));
        $consents = $data['consents'] ?? $data;

        return array_map(fn (array $c) => Consent::fromArray($c), is_array($consents) ? $consents : []);
    }
}
