<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class AgentUser
{
    public function __construct(
        public ?string $name,
        public ?string $middleName,
        public ?string $family,
        public ?string $email,
        public ?string $uic,
        public string $dateCreated,
        public string $userHash,
        public int $accounts,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            middleName: $data['middleName'] ?? null,
            family: $data['family'] ?? null,
            email: $data['email'] ?? null,
            uic: $data['uic'] ?? null,
            dateCreated: $data['dateCreated'],
            userHash: $data['userHash'],
            accounts: (int) $data['accounts'],
        );
    }
}
