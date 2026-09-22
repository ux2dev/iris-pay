<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class EmailAccount
{
    public function __construct(
        public string $userHash,
        public string $name,
        public string $lastname,
        public string $surname,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userHash: $data['userHash'],
            name: $data['name'],
            lastname: $data['lastname'],
            surname: $data['surname'],
        );
    }
}
