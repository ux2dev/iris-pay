<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ConsentDetails
{
    public function __construct(
        public string $dateCreated,
        public ?string $iban,
        public ?string $currency,
        public ?string $ownerName,
        public ?string $consentStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            dateCreated: $data['dateCreated'],
            iban: $data['iban'] ?? null,
            currency: $data['currency'] ?? null,
            ownerName: $data['ownerName'] ?? null,
            consentStatus: $data['consentStatus'] ?? null,
        );
    }
}
