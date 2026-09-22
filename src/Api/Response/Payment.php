<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class Payment
{
    public function __construct(
        public string $date,
        public string $payeeName,
        public ?string $payerName,
        public ?BankReference $payerBank,
        public ?BankReference $payeeBank,
        public string $description,
        public string $sum,
        public ?string $payerIban,
        public string $payeeIban,
        public ?string $id,
        public string $currency,
        public PaymentStatus $status,
        public ?string $reasonForFail,
        public bool $authorised,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            payeeName: $data['payeeName'],
            payerName: $data['payerName'] ?? null,
            payerBank: isset($data['payerBank']) ? BankReference::fromArray($data['payerBank']) : null,
            payeeBank: isset($data['payeeBank']) ? BankReference::fromArray($data['payeeBank']) : null,
            description: $data['description'],
            sum: (string) $data['sum'],
            payerIban: $data['payerIban'] ?? null,
            payeeIban: $data['payeeIban'],
            id: $data['id'] ?? null,
            currency: $data['currency'],
            status: PaymentStatus::from($data['status']),
            reasonForFail: $data['reasonForFail'] ?? null,
            authorised: (bool) ($data['authorised'] ?? false),
        );
    }
}
