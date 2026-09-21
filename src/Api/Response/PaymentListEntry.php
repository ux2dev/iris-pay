<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentListEntry
{
    public function __construct(
        public string $date,
        public string $description,
        public ?string $payerName,
        public ?string $payerIban,
        public float $sum,
        public PaymentStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            description: $data['description'],
            payerName: $data['payerName'] ?? null,
            payerIban: $data['payerIban'] ?? null,
            sum: (float) $data['sum'],
            status: PaymentStatus::from($data['status']),
        );
    }
}
