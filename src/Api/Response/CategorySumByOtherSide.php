<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class CategorySumByOtherSide
{
    public function __construct(
        public string $otherSide,
        public float $sum,
        public string $currency,
        public int $transactionCount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            otherSide: $data['otherSide'],
            sum: (float) $data['sum'],
            currency: $data['currency'],
            transactionCount: (int) $data['transactionCount'],
        );
    }
}
