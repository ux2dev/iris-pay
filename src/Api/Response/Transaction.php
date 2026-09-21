<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\CreditDebitIndicator;

final readonly class Transaction
{
    public function __construct(
        public ?string $transactionId,
        public ?string $bookingDate,
        public ?string $creditorIban,
        public ?string $creditorName,
        public ?string $debtorIban,
        public ?string $debtorName,
        public ?string $entryReference,
        public ?string $remittanceInformationUnstructured,
        public TransactionAmount $transactionAmount,
        public ?ExchangeRate $exchangeRate,
        public ?string $valueDate,
        public ?CreditDebitIndicator $creditDebitIndicator,
        public ?Category $category,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: $data['transactionId'] ?? null,
            bookingDate: $data['bookingDate'] ?? null,
            creditorIban: $data['creditorAccount']['iban'] ?? null,
            creditorName: $data['creditorName'] ?? null,
            debtorIban: $data['debtorAccount']['iban'] ?? null,
            debtorName: $data['debtorName'] ?? null,
            entryReference: $data['entryReference'] ?? null,
            remittanceInformationUnstructured: $data['remittanceInformationUnstructured'] ?? null,
            transactionAmount: TransactionAmount::fromArray($data['transactionAmount']),
            exchangeRate: isset($data['exchangeRate']) ? ExchangeRate::fromArray($data['exchangeRate']) : null,
            valueDate: $data['valueDate'] ?? null,
            creditDebitIndicator: isset($data['creditDebitIndicator']) ? CreditDebitIndicator::from($data['creditDebitIndicator']) : null,
            category: isset($data['category']) ? Category::fromArray($data['category']) : null,
        );
    }
}
