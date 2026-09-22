<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Balance
{
    public function __construct(
        public float $amount,
        public ?string $currency,
        public ?string $balanceType,
        public ?string $referenceDate,
        public bool $creditLimitIncluded,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? null,
            balanceType: $data['balanceType'] ?? null,
            referenceDate: $data['referenceDate'] ?? null,
            creditLimitIncluded: (bool) ($data['creditLimitIncluded'] ?? false),
        );
    }
}
