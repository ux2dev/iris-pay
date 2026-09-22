<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ActiveUsersDetails
{
    /**
     * @param int[] $bankAccounts
     * @param string[] $users
     */
    public function __construct(
        public array $bankAccounts,
        public array $users,
        public int $pages,
        public int $page,
        public int $elementsSize,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankAccounts: $data['bankAccounts'] ?? [],
            users: $data['users'] ?? [],
            pages: $data['pages'],
            page: $data['page'],
            elementsSize: $data['elementsSize'],
        );
    }
}
