<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentUrls
{
    public function __construct(
        public ?string $startUrl,
        public ?string $endUrl,
        public bool $externalApp,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            startUrl: $data['startUrl'] ?? null,
            endUrl: $data['endUrl'] ?? null,
            externalApp: (bool) ($data['externalApp'] ?? false),
        );
    }
}
