<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BulkPaymentsList
{
    /**
     * @param BulkPaymentV2[] $payments
     */
    public function __construct(
        public int $pages,
        public int $page,
        public array $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pages: (int) $data['pages'],
            page: (int) $data['page'],
            payments: array_map(
                fn (array $entry) => BulkPaymentV2::fromArray($entry),
                $data['payments'] ?? [],
            ),
        );
    }
}
