<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\ConsentErrorCode;

final readonly class ConsentsStatus
{
    /**
     * @param array<mixed> $consents
     * @param ConsentErrorCode[] $errorCodes
     */
    public function __construct(
        public array $consents,
        public array $errorCodes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            consents: $data['consents'] ?? [],
            errorCodes: array_map(fn (string $c) => ConsentErrorCode::from($c), $data['errorCodes'] ?? []),
        );
    }
}
