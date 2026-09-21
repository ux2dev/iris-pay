<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Hook
{
    public function __construct(
        public string $hookHash,
        public int $closeSelf,
        public int $redirectTimer,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            hookHash: $data['hookHash'],
            closeSelf: (int) $data['closeSelf'],
            redirectTimer: (int) $data['redirectTimer'],
        );
    }
}
