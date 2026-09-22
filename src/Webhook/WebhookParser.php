<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Webhook;

use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Exception\InvalidResponseException;

final class WebhookParser
{
    /**
     * Parses just the status for backward compatibility.
     *
     * @param array<string, string> $queryParams
     */
    public static function parse(array $queryParams): PaymentStatus
    {
        return self::payload($queryParams)->status;
    }

    /**
     * Parses the full webhook payload.
     *
     * @param array<string, string> $queryParams
     */
    public static function payload(array $queryParams, ?string $hashedOrderId = null): WebhookPayload
    {
        if (!isset($queryParams['status'])) {
            throw new InvalidResponseException('Missing status parameter in webhook query string');
        }

        $status = PaymentStatus::tryFrom($queryParams['status']);

        if ($status === null) {
            throw new InvalidResponseException("Unknown payment status: {$queryParams['status']}");
        }

        return new WebhookPayload(
            status: $status,
            hashedOrderId: $hashedOrderId,
            paymentHash: $queryParams['paymentHash'] ?? null,
            orderId: $queryParams['orderId'] ?? null,
            queryParams: $queryParams,
        );
    }
}
