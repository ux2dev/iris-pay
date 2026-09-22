<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class CategorySum
{
    /** @param CategorySumByOtherSide[] $sumByOtherSide */
    public function __construct(
        public float $sum,
        public string $currency,
        public string $code,
        public string $categoryName,
        public int $transactionsCount,
        public array $sumByOtherSide,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sum: (float) $data['sum'],
            currency: $data['currency'],
            code: $data['code'],
            categoryName: $data['categoryName'],
            transactionsCount: (int) $data['transactionsCount'],
            sumByOtherSide: array_map(fn (array $s) => CategorySumByOtherSide::fromArray($s), $data['sumByOtherSide'] ?? []),
        );
    }
}
