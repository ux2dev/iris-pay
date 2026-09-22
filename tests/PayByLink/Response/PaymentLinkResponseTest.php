<?php

declare(strict_types=1);

use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;

test('creates payment link response from API response array', function () {
    $response = PaymentLinkResponse::fromArray([
        'accountId' => 'acc-123',
        'paymentHash' => 'hash-456',
        'paymentLink' => 'https://paybyclick.irispay.bg/payment/hash-456',
    ]);

    expect($response->accountId)->toBe('acc-123');
    expect($response->paymentHash)->toBe('hash-456');
    expect($response->paymentLink)->toBe('https://paybyclick.irispay.bg/payment/hash-456');
});
