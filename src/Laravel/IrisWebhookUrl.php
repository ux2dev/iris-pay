<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use RuntimeException;

final class IrisWebhookUrl
{
    public static function hashOrderId(string $orderId): string
    {
        return hash('sha256', $orderId);
    }

    public static function sign(string $hashedOrderId): string
    {
        $secret = config('iris.webhook.secret');

        if ($secret === null || $secret === '') {
            throw new RuntimeException('iris.webhook.secret is not configured');
        }

        return hash_hmac('sha256', $hashedOrderId, (string) $secret);
    }

    public static function for(string $orderId, ?string $baseUrl = null): string
    {
        $hashedOrderId = self::hashOrderId($orderId);
        $signature = self::sign($hashedOrderId);
        $prefix = config('iris.routes.prefix', 'iris');
        $base = rtrim($baseUrl ?? (string) config('app.url'), '/');

        return "{$base}/{$prefix}/webhook/{$hashedOrderId}/{$signature}";
    }
}
