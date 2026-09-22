<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BankReference
{
    public function __construct(
        public ?string $bankHash,
        public ?string $name,
        public ?string $country,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'] ?? null,
            name: $data['name'] ?? null,
            country: $data['country'] ?? null,
        );
    }
}
