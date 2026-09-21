<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PsuIdType;
use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankSca
{
    public function __construct(
        public ?string $startUrl,
        public ?string $endUrl,
        public ?string $formText,
        public ?string $loadingText,
        public PsuIdType $psuIdType,
        public ScaType $sca,
        public bool $gatherPsu,
        public bool $hasAuthorization,
        public bool $externalApp,
        public ?string $authorizationId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            startUrl: $data['startUrl'] ?? null,
            endUrl: $data['endUrl'] ?? null,
            formText: $data['formText'] ?? null,
            loadingText: $data['loadingText'] ?? null,
            psuIdType: PsuIdType::from($data['psuIdType']),
            sca: ScaType::from($data['sca']),
            gatherPsu: (bool) $data['gatherPsu'],
            hasAuthorization: (bool) $data['hasAuthorization'],
            externalApp: (bool) $data['externalApp'],
            authorizationId: $data['authorizationId'] ?? null,
        );
    }
}
