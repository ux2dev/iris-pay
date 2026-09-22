<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Consent
{
    /** @param string[] $consentPermissions */
    public function __construct(
        public int $ibanId,
        public ?string $consentId,
        public ?string $iban,
        public ?array $consentPermissions,
        public ?string $validUntil,
        public int $frequencyPerDay,
        public ?string $givenAt,
        public ?string $status,
        public ?string $country,
        public ?string $bankHash,
        public ?string $bankName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ibanId: (int) $data['ibanId'],
            consentId: $data['consentId'] ?? null,
            iban: $data['iban'] ?? null,
            consentPermissions: $data['consentPermissions'] ?? null,
            validUntil: $data['validUntil'] ?? null,
            frequencyPerDay: (int) ($data['frequencyPerDay'] ?? 0),
            givenAt: $data['givenAt'] ?? null,
            status: $data['status'] ?? null,
            country: $data['country'] ?? null,
            bankHash: $data['bankHash'] ?? null,
            bankName: $data['bankName'] ?? null,
        );
    }
}
