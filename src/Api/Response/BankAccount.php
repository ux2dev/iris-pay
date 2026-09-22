<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BankAccount
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $iban,
        public ?string $product,
        public ?string $ownerName,
        public ?string $currency,
        public bool $hasAuthorization,
        public ?string $bankHash,
        public ?string $bankName,
        public ?string $liteLogoUrl,
        public ?string $darkLogoUrl,
        public ?string $country,
        public string $dateCreate,
        public ?ConsentsStatus $consents,
        public bool $fulfilled,
        public ?string $validUntil,
        public int $frequencyPerDay,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: $data['name'] ?? null,
            iban: $data['iban'] ?? null,
            product: $data['product'] ?? null,
            ownerName: $data['ownerName'] ?? null,
            currency: $data['currency'] ?? null,
            hasAuthorization: (bool) ($data['hasAuthorization'] ?? false),
            bankHash: $data['bankHash'] ?? null,
            bankName: $data['bankName'] ?? null,
            liteLogoUrl: $data['liteLogoUrl'] ?? null,
            darkLogoUrl: $data['darkLogoUrl'] ?? null,
            country: $data['country'] ?? null,
            dateCreate: $data['dateCreate'],
            consents: isset($data['consents']) ? ConsentsStatus::fromArray($data['consents']) : null,
            fulfilled: (bool) ($data['fulfilled'] ?? false),
            validUntil: $data['validUntil'] ?? null,
            frequencyPerDay: (int) ($data['frequencyPerDay'] ?? 0),
        );
    }
}
