<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\IdentityStatus;

final readonly class IdentificationAccount
{
    public function __construct(
        public string $userHash,
        public ?string $idUrl,
        public ?string $identityStatusUrl,
        public ?string $identityToken,
        public IdentityStatus $identified,
        public ?string $publicHash = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userHash: $data['userHash'],
            idUrl: $data['idUrl'] ?? null,
            identityStatusUrl: $data['identityStatusUrl'] ?? null,
            identityToken: $data['identityToken'] ?? null,
            identified: IdentityStatus::from($data['identified']),
            publicHash: $data['publicHash'] ?? null,
        );
    }
}
