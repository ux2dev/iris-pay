<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ConsentGateStatus
{
    public function __construct(
        public int $ibanId,
        public string $iban,
        public bool $fulfilled,
        public ?string $validUntil,
        public int $frequencyPerDay,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ibanId: (int) $data['ibanId'],
            iban: (string) $data['iban'],
            fulfilled: (bool) $data['fulfilled'],
            validUntil: $data['validUntil'] ?? null,
            frequencyPerDay: (int) ($data['frequencyPerDay'] ?? 0),
        );
    }
}
