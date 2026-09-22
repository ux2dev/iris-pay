<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Video
{
    public function __construct(public ?string $url) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(url: $data['url'] ?? null);
    }
}
