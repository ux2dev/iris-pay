<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BalanceList
{
    /** @param Balance[] $balances */
    public function __construct(public array $balances) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            balances: array_map(fn (array $b) => Balance::fromArray($b), $data['balances'] ?? []),
        );
    }
}
