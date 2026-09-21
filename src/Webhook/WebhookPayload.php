<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Webhook;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class WebhookPayload
{
    /**
     * @param array<string, string> $queryParams Raw query parameters as received.
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $hashedOrderId,
        public ?string $paymentHash,
        public ?string $orderId,
        public array $queryParams,
    ) {}
}
