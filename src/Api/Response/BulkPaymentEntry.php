<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class BulkPaymentEntry
{
    public function __construct(
        public string $payeeName,
        public string $description,
        public float $sum,
        public string $payeeIban,
        public string $currency,
        public PaymentStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            payeeName: $data['payeeName'],
            description: $data['description'],
            sum: (float) $data['sum'],
            payeeIban: $data['payeeIban'],
            currency: $data['currency'],
            status: PaymentStatus::from($data['status']),
        );
    }
}
