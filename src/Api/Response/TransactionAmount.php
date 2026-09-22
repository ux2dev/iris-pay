<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class TransactionAmount
{
    public function __construct(
        public float $amount,
        public ?string $currency,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? null,
        );
    }
}
