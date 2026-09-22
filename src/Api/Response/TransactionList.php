<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class TransactionList
{
    /**
     * @param Transaction[] $transactions
     * @param Balance[] $balances
     * @param CategorySum[] $categorySums
     */
    public function __construct(
        public array $transactions,
        public array $balances,
        public array $categorySums,
        public ?string $nextPageUrl,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactions: array_map(fn (array $t) => Transaction::fromArray($t), $data['transactions'] ?? []),
            balances: array_map(fn (array $b) => Balance::fromArray($b), $data['balances'] ?? []),
            categorySums: array_map(fn (array $c) => CategorySum::fromArray($c), $data['categorySums'] ?? []),
            nextPageUrl: $data['nextPageUrl'] ?? null,
        );
    }
}
