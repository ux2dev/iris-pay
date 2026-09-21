<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\PaymentClient;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\Payment;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;

function createPaymentClient(array $responses): PaymentClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PaymentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('createDirectPayment returns PaymentResponse', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['code' => 'PAY-001', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ]);

    $result = $client->createDirectPayment('user-hash', new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(PaymentResponse::class);
    expect($result->code)->toBe('PAY-001');
});

test('createDirectInitiate returns BankScaUrl', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['url' => 'https://bank.example.com/sca', 'bankSCA' => 'REDIRECT_URL'])),
    ]);

    $result = $client->createDirectInitiate('user-hash', new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(BankScaUrl::class);
    expect($result->url)->toBe('https://bank.example.com/sca');
});

test('createIbanPayment returns PaymentResponse', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['code' => 'PAY-002', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ]);

    $result = $client->createIbanPayment('user-hash', new IbanPaymentData(
        fromIbanId: 1, receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 50.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(PaymentResponse::class);
    expect($result->code)->toBe('PAY-002');
});

test('confirmPaymentWithResult returns PaymentConfirmResult', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['result' => 'SUCCESS'])),
    ]);

    $result = $client->confirmPaymentWithResult('user-hash', 'PAY-001', 1);

    expect($result)->toBeInstanceOf(PaymentConfirmResult::class);
    expect($result->isSuccess())->toBeTrue();
});

test('getStatusByHookHash returns Payment', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode([
            'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver', 'payerName' => 'Sender',
            'payerBank' => ['bankHash' => 'b1', 'name' => 'Bank', 'country' => 'bulgaria'],
            'payeeBank' => null, 'description' => 'Test', 'sum' => '100.00',
            'payerIban' => 'BG12SEND', 'payeeIban' => 'BG12RECV', 'id' => null,
            'currency' => 'BGN', 'status' => 'CONFIRMED', 'reasonForFail' => null, 'authorised' => true,
        ])),
    ]);

    $result = $client->getStatusByHookHash('hook-1');

    expect($result)->toBeInstanceOf(Payment::class);
    expect($result->status)->toBe(\Ux2Dev\Iris\Enum\PaymentStatus::Confirmed);
});

test('getStatusByCode returns PaymentStatusDetail', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode([
            'payment' => [
                'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver', 'payerName' => null,
                'payerBank' => null, 'payeeBank' => null, 'description' => 'Test', 'sum' => '100',
                'payerIban' => null, 'payeeIban' => 'BG12RECV', 'id' => null,
                'currency' => 'BGN', 'status' => 'WAITING', 'reasonForFail' => null, 'authorised' => false,
            ],
            'urlSca' => ['startUrl' => 'https://bank.example.com', 'endUrl' => null, 'externalApp' => false],
            'ready' => false,
        ])),
    ]);

    $result = $client->getStatusByCode('user-hash', 'PAY-001');

    expect($result)->toBeInstanceOf(PaymentStatusDetail::class);
    expect($result->ready)->toBeFalse();
});
