<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Category
{
    public function __construct(
        public string $code,
        public string $name,
        public string $otherSide,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(code: $data['code'], name: $data['name'], otherSide: $data['otherSide']);
    }
}
