<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

final readonly class PaymentLinkResponse
{
    public function __construct(
        public string $accountId,
        public string $paymentHash,
        public string $paymentLink,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: $data['accountId'],
            paymentHash: $data['paymentHash'],
            paymentLink: $data['paymentLink'],
        );
    }
}
