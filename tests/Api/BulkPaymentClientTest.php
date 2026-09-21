<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\BulkPaymentClient;
use Ux2Dev\Iris\Api\Request\BulkEntry;
use Ux2Dev\Iris\Api\Request\BulkPaymentData;
use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\BulkPayment;
use Ux2Dev\Iris\Api\Response\BulkPaymentsList;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\PaymentStatus;

function createBulkPaymentClient(array $responses): BulkPaymentClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new BulkPaymentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('createBulkPayment returns PaymentResponse', function () {
    $client = createBulkPaymentClient([
        new Response(200, [], json_encode([
            'code' => 'BULK-001', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1,
        ])),
    ]);

    $result = $client->createBulkPayment('user-hash', new BulkPaymentData(
        bankHash: 'bank-1',
        currency: 'BGN',
        hookHash: 'hook-1',
        emailNotification: false,
        senderIban: 'BG12SEND',
        psuId: null,
        requestedExecutionDate: null,
        payments: [
            new BulkEntry(receiverIban: 'BG12RECV1', receiverName: 'Receiver 1', remittanceDescription: 'Payment 1', sum: 100.00),
            new BulkEntry(receiverIban: 'BG12RECV2', receiverName: 'Receiver 2', remittanceDescription: 'Payment 2', sum: 200.00),
        ],
    ));

    expect($result)->toBeInstanceOf(PaymentResponse::class);
    expect($result->code)->toBe('BULK-001');
});

test('getBulkStatus returns BulkPayment', function () {
    $client = createBulkPaymentClient([
        new Response(200, [], json_encode([
            'date' => '2025-01-15T10:00:00Z',
            'status' => 'CONFIRMED',
            'reasonForFail' => null,
            'payerBank' => ['bankHash' => 'b1', 'name' => 'Bank', 'country' => 'bulgaria'],
            'payments' => [
                [
                    'payeeName' => 'Receiver 1', 'description' => 'Payment 1', 'sum' => 100.00,
                    'payeeIban' => 'BG12RECV1', 'currency' => 'BGN', 'status' => 'CONFIRMED',
                ],
                [
                    'payeeName' => 'Receiver 2', 'description' => 'Payment 2', 'sum' => 200.00,
                    'payeeIban' => 'BG12RECV2', 'currency' => 'BGN', 'status' => 'CONFIRMED',
                ],
            ],
        ])),
    ]);

    $result = $client->getBulkStatus('hook-1');

    expect($result)->toBeInstanceOf(BulkPayment::class);
    expect($result->status)->toBe(PaymentStatus::Confirmed);
    expect($result->payments)->toHaveCount(2);
    expect($result->payments[0]->payeeName)->toBe('Receiver 1');
    expect($result->payments[1]->sum)->toBe(200.00);
    expect($result->payerBank->name)->toBe('Bank');
});

test('searchBulkPayments returns BulkPaymentsList', function () {
    $client = createBulkPaymentClient([
        new Response(200, [], json_encode([
            'pages' => 5,
            'page' => 0,
            'payments' => [
                [
                    'date' => '2025-01-15T10:00:00Z',
                    'status' => 'CONFIRMED',
                    'reasonForFail' => null,
                    'payerBank' => null,
                    'payments' => [
                        [
                            'payeeName' => 'Receiver 1', 'description' => 'Payment 1', 'sum' => 100.00,
                            'payeeIban' => 'BG12RECV1', 'currency' => 'BGN', 'status' => 'CONFIRMED',
                        ],
                    ],
                    'paymentType' => 'BULK_DOMESTIC_CREDIT_TRANSFER',
                ],
            ],
        ])),
    ]);

    $result = $client->searchBulkPayments(new PaymentSearchData(page: 0, size: 10));

    expect($result)->toBeInstanceOf(BulkPaymentsList::class);
    expect($result->pages)->toBe(5);
    expect($result->page)->toBe(0);
    expect($result->payments)->toHaveCount(1);
    expect($result->payments[0]->status)->toBe(PaymentStatus::Confirmed);
    expect($result->payments[0]->paymentType)->toBe(\Ux2Dev\Iris\Api\Enum\PaymentType::BulkDomesticCreditTransfer);
    expect($result->payments[0]->payments)->toHaveCount(1);
});
