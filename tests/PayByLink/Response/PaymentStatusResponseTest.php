<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;

test('creates payment status response from API response array', function () {
    $response = PaymentStatusResponse::fromArray([
        'currency' => 'EUR',
        'date' => '2025-10-08T13:02:20.901Z',
        'description' => 'Test payment',
        'orderId' => 'ORD-001',
        'payerBank' => 'Gringotts Bank',
        'payerIban' => 'BG28TEST91556789845726',
        'payerName' => 'John Doe',
        'receiverIban' => 'BG31UNCR70001526254645',
        'status' => 'CONFIRMED',
        'sum' => 9.99,
    ]);

    expect($response->status)->toBe(PaymentStatus::Confirmed);
    expect($response->sum)->toBe(9.99);
    expect($response->payerName)->toBe('John Doe');
    expect($response->currency)->toBe('EUR');
    expect($response->orderId)->toBe('ORD-001');
});

test('handles null optional fields', function () {
    $response = PaymentStatusResponse::fromArray([
        'currency' => 'EUR',
        'date' => '2025-10-08T13:02:20.901Z',
        'description' => 'Test',
        'payerBank' => '',
        'payerIban' => '',
        'payerName' => '',
        'receiverIban' => 'BG31UNCR70001526254645',
        'status' => 'WAITING',
        'sum' => 0,
    ]);

    expect($response->status)->toBe(PaymentStatus::Waiting);
    expect($response->orderId)->toBeNull();
});
