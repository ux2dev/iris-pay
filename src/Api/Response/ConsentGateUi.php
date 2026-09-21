<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ConsentGateUi
{
    public function __construct(
        public int $id,
        public int $accountId,
        public string $expirationDate,
        public int $timeout,
        public bool $showValidUntil,
        public bool $showFrequencyPerDay,
        public ?string $expirationMessage,
        public ?string $successMessage,
        public bool $deleted,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            accountId: (int) $data['accountId'],
            expirationDate: (string) $data['expirationDate'],
            timeout: (int) $data['timeout'],
            showValidUntil: (bool) $data['showValidUntil'],
            showFrequencyPerDay: (bool) $data['showFrequencyPerDay'],
            expirationMessage: $data['expirationMessage'] ?? null,
            successMessage: $data['successMessage'] ?? null,
            deleted: (bool) ($data['deleted'] ?? false),
        );
    }
}
