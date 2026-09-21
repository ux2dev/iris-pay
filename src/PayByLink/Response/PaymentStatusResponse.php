<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentStatusResponse
{
    public function __construct(
        public ?string $currency,
        public ?string $date,
        public ?string $description,
        public ?string $orderId,
        public ?string $payerBank,
        public ?string $payerIban,
        public ?string $payerName,
        public ?string $receiverIban,
        public PaymentStatus $status,
        public ?float $sum,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->status === PaymentStatus::Confirmed;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isWaiting(): bool
    {
        return $this->status === PaymentStatus::Waiting;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? null,
            date: $data['date'] ?? null,
            description: $data['description'] ?? null,
            orderId: $data['orderId'] ?? null,
            payerBank: $data['payerBank'] ?? null,
            payerIban: $data['payerIban'] ?? null,
            payerName: $data['payerName'] ?? null,
            receiverIban: $data['receiverIban'] ?? null,
            status: PaymentStatus::from($data['status']),
            sum: isset($data['sum']) ? (float) $data['sum'] : null,
        );
    }
}
