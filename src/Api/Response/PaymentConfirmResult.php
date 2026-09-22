<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentConfirmResult
{
    public function __construct(public string $result) {}

    public function isSuccess(): bool { return $this->result === 'SUCCESS'; }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(result: $data['result']);
    }
}
