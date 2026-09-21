<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ActiveUsers
{
    public function __construct(
        public int $bankAccounts,
        public int $users,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankAccounts: $data['bankAccounts'],
            users: $data['users'],
        );
    }
}
