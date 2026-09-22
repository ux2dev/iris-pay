<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

use Ux2Dev\Iris\Enum\BankScaType;

final readonly class RefundResponse
{
    public function __construct(
        public BankScaType $bankScaType,
        public string $url,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankScaType: BankScaType::from($data['bankScaType']),
            url: $data['url'],
        );
    }
}
