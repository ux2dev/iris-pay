<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

final readonly class Bank
{
    public function __construct(
        public string $bankHash,
        public string $name,
        public ?string $fullName,
        public ?string $bic,
        public ?string $services,
        public ?string $country,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'],
            name: $data['name'],
            fullName: $data['fullName'] ?? null,
            bic: $data['bic'] ?? null,
            services: $data['services'] ?? null,
            country: $data['country'] ?? null,
        );
    }
}
