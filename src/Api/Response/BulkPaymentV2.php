<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PaymentType;
use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class BulkPaymentV2
{
    /**
     * @param BulkPaymentEntry[] $payments
     */
    public function __construct(
        public string $date,
        public PaymentStatus $status,
        public ?string $reasonForFail,
        public ?BankReference $payerBank,
        public array $payments,
        public PaymentType $paymentType,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            status: PaymentStatus::from($data['status']),
            reasonForFail: $data['reasonForFail'] ?? null,
            payerBank: isset($data['payerBank']) ? BankReference::fromArray($data['payerBank']) : null,
            payments: array_map(
                fn (array $entry) => BulkPaymentEntry::fromArray($entry),
                $data['payments'] ?? [],
            ),
            paymentType: PaymentType::from($data['paymentType']),
        );
    }
}
