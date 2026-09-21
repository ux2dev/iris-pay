<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Webhook\WebhookParser;
use Ux2Dev\Iris\Webhook\WebhookPayload;

test('parses CONFIRMED status from query params', function () {
    $result = WebhookParser::parse(['status' => 'CONFIRMED']);
    expect($result)->toBe(PaymentStatus::Confirmed);
});

test('parses FAILED status from query params', function () {
    $result = WebhookParser::parse(['status' => 'FAILED']);
    expect($result)->toBe(PaymentStatus::Failed);
});

test('throws on missing status parameter', function () {
    WebhookParser::parse([]);
})->throws(InvalidResponseException::class, 'Missing status parameter');

test('throws on invalid status value', function () {
    WebhookParser::parse(['status' => 'INVALID']);
})->throws(InvalidResponseException::class, 'Unknown payment status');

test('payload() returns full WebhookPayload with paymentHash and orderId', function () {
    $payload = WebhookParser::payload([
        'status' => 'CONFIRMED',
        'paymentHash' => 'abc123',
        'orderId' => 'ORD-1',
        'extra' => 'keep-me',
    ]);

    expect($payload)->toBeInstanceOf(WebhookPayload::class)
        ->and($payload->status)->toBe(PaymentStatus::Confirmed)
        ->and($payload->paymentHash)->toBe('abc123')
        ->and($payload->orderId)->toBe('ORD-1')
        ->and($payload->queryParams)->toBe([
            'status' => 'CONFIRMED',
            'paymentHash' => 'abc123',
            'orderId' => 'ORD-1',
            'extra' => 'keep-me',
        ]);
});

test('payload() handles missing optional fields', function () {
    $payload = WebhookParser::payload(['status' => 'FAILED']);

    expect($payload->status)->toBe(PaymentStatus::Failed)
        ->and($payload->paymentHash)->toBeNull()
        ->and($payload->orderId)->toBeNull();
});

test('payload() throws on missing status', function () {
    WebhookParser::payload(['paymentHash' => 'abc']);
})->throws(InvalidResponseException::class, 'Missing status parameter');

test('payload() accepts hashedOrderId and stores it on the payload', function () {
    $payload = WebhookParser::payload(['status' => 'CONFIRMED'], 'abc-hashed');

    expect($payload->hashedOrderId)->toBe('abc-hashed');
});
