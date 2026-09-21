<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentResponse
{
    public function __construct(
        public string $code,
        public string $expiryDate,
        public ?string $confirmUrl,
        public ?int $ibanId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            expiryDate: $data['expiryDate'],
            confirmUrl: $data['confirmUrl'] ?? null,
            ibanId: isset($data['ibanId']) ? (int) $data['ibanId'] : null,
        );
    }
}
