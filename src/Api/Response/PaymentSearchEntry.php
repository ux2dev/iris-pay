<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PaymentType;
use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentSearchEntry
{
    public function __construct(
        public string $date,
        public string $description,
        public ?string $payerName,
        public ?string $payerIban,
        public string $payerBankName,
        public ?string $payerBankCountry,
        public float $sum,
        public PaymentStatus $status,
        public string $payeeName,
        public string $payeeIban,
        public string $currency,
        public string $remittanceDescription,
        public ?PaymentType $paymentType,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            description: $data['description'],
            payerName: $data['payerName'] ?? null,
            payerIban: $data['payerIban'] ?? null,
            payerBankName: $data['payerBankName'],
            payerBankCountry: $data['payerBankCountry'] ?? null,
            sum: (float) $data['sum'],
            status: PaymentStatus::from($data['status']),
            payeeName: $data['payeeName'],
            payeeIban: $data['payeeIban'],
            currency: $data['currency'],
            remittanceDescription: $data['remittanceDescription'],
            paymentType: isset($data['paymentType']) ? PaymentType::tryFrom($data['paymentType']) : null,
        );
    }
}
