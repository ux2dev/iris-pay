<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

function createClient(array $responses): PayByLinkClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PayByLinkClient(
        config: new MerchantConfig(
            environment: Environment::Development,
            publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
        ),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('getBanks returns array of Bank objects', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
                'name' => 'Gringotts',
                'fullName' => 'Gringotts Bank',
                'bic' => 'example',
                'services' => 'Account Information Services and Payment Initiation Services',
                'country' => 'bulgaria',
            ],
        ])),
    ]);

    $banks = $client->getBanks();

    expect($banks)->toHaveCount(1);
    expect($banks[0])->toBeInstanceOf(Bank::class);
    expect($banks[0]->name)->toBe('Gringotts');
});

test('createPaymentLink returns PaymentLinkResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'accountId' => 'acc-123',
            'paymentHash' => 'hash-456',
            'paymentLink' => 'https://dev.paybyclick.irispay.bg/payment/hash-456',
        ])),
    ]);

    $response = $client->createPaymentLink(
        sum: 9.99,
        description: 'Test payment',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );

    expect($response)->toBeInstanceOf(PaymentLinkResponse::class);
    expect($response->paymentHash)->toBe('hash-456');
});

test('createPaymentLink validates sum is positive', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 0,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'sum must be greater than 0');

test('createPaymentLink validates description length', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: str_repeat('a', 241),
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'description must not exceed 240 characters');

test('createPaymentLink validates hookUrl is https', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'http://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'hookUrl must use https://');

test('createPaymentLink validates redirectUrl is https', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'http://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'redirectUrl must use https://');

test('getQrCode returns image bytes', function () {
    $imageBytes = 'fake-jpeg-bytes';
    $client = createClient([
        new Response(200, ['Content-Type' => 'image/jpeg'], $imageBytes),
    ]);

    $result = $client->getQrCode('hash-456');

    expect($result)->toBe($imageBytes);
});

test('getPaymentStatus returns PaymentStatusResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
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
        ])),
    ]);

    $response = $client->getPaymentStatus('hash-456');

    expect($response)->toBeInstanceOf(PaymentStatusResponse::class);
    expect($response->isConfirmed())->toBeTrue();
});

test('refund returns RefundResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'bankScaType' => 'REDIRECT_URL',
            'url' => 'https://bank.example.com/authorize/123',
        ])),
    ]);

    $response = $client->refund(
        paymentHash: 'hash-456',
        refundType: RefundType::Full,
        sum: 9.99,
        remittanceDescription: 'Full refund',
        webhookUrl: 'https://example.com/refund-webhook',
    );

    expect($response)->toBeInstanceOf(RefundResponse::class);
    expect($response->url)->toBe('https://bank.example.com/authorize/123');
});

test('deactivate sends PUT request without error', function () {
    $client = createClient([
        new Response(200),
    ]);

    $client->deactivate('hash-456');

    // No exception means success
    expect(true)->toBeTrue();
});

test('throws InvalidResponseException on non-2xx response', function () {
    $client = createClient([
        new Response(400, [], json_encode(['error' => 'Bad Request'])),
    ]);

    $client->getBanks();
})->throws(InvalidResponseException::class);
