<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\BankScaType;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

test('creates refund response from API response array', function () {
    $response = RefundResponse::fromArray([
        'bankScaType' => 'REDIRECT_URL',
        'url' => 'https://bank.example.com/authorize/123',
    ]);

    expect($response->bankScaType)->toBe(BankScaType::RedirectUrl);
    expect($response->url)->toBe('https://bank.example.com/authorize/123');
});
