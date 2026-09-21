<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankScaUrl
{
    public function __construct(
        public string $url,
        public ScaType $bankSCA,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            bankSCA: ScaType::from($data['bankSCA']),
        );
    }
}
