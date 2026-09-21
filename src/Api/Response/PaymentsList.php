<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentsList
{
    /**
     * @param PaymentListEntry[] $payments
     */
    public function __construct(
        public int $size,
        public int $page,
        public array $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            size: $data['size'],
            page: $data['page'],
            payments: array_map(fn (array $p) => PaymentListEntry::fromArray($p), $data['payments'] ?? []),
        );
    }
}
