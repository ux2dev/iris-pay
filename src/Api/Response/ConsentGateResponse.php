<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ConsentGateResponse
{
    /** @param array<string, int> $ibansIds */
    public function __construct(
        public string $userHash,
        public string $url,
        public array $ibansIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userHash: (string) $data['userHash'],
            url: (string) $data['url'],
            ibansIds: (array) ($data['ibansIds'] ?? []),
        );
    }
}
