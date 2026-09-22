<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentSearchResult
{
    /**
     * @param PaymentSearchEntry[] $payments
     */
    public function __construct(
        public int $pages,
        public int $page,
        public int $elementsSize,
        public array $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pages: $data['pages'],
            page: $data['page'],
            elementsSize: $data['elementsSize'],
            payments: array_map(fn (array $p) => PaymentSearchEntry::fromArray($p), $data['payments'] ?? []),
        );
    }
}
