<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\ReportClient;
use Ux2Dev\Iris\Api\Request\ActiveUsersDetailsData;
use Ux2Dev\Iris\Api\Request\PaymentSearchData;
use Ux2Dev\Iris\Api\Response\ActiveUsers;
use Ux2Dev\Iris\Api\Response\ActiveUsersDetails;
use Ux2Dev\Iris\Api\Response\BankMaintenance;
use Ux2Dev\Iris\Api\Response\PaymentSearchResult;
use Ux2Dev\Iris\Api\Response\PaymentsList;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\PaymentStatus;

function createReportClient(array $responses): ReportClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new ReportClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('listPayments returns PaymentsList', function () {
    $client = createReportClient([
        new Response(200, [], json_encode([
            'size' => 30,
            'page' => 0,
            'payments' => [
                [
                    'date' => '2025-01-15T10:00:00Z',
                    'description' => 'Test payment',
                    'payerName' => 'John Doe',
                    'payerIban' => 'BG12SEND',
                    'sum' => 150.50,
                    'status' => 'CONFIRMED',
                ],
            ],
        ])),
    ]);

    $result = $client->listPayments('user-hash');

    expect($result)->toBeInstanceOf(PaymentsList::class);
    expect($result->size)->toBe(30);
    expect($result->page)->toBe(0);
    expect($result->payments)->toHaveCount(1);
    expect($result->payments[0]->description)->toBe('Test payment');
    expect($result->payments[0]->sum)->toBe(150.50);
    expect($result->payments[0]->status)->toBe(PaymentStatus::Confirmed);
});

test('searchPayments returns PaymentSearchResult', function () {
    $client = createReportClient([
        new Response(200, [], json_encode([
            'pages' => 5,
            'page' => 0,
            'elementsSize' => 100,
            'payments' => [
                [
                    'date' => '2025-02-01T12:00:00Z',
                    'description' => 'Search result',
                    'payerName' => 'Jane Doe',
                    'payerIban' => 'BG12PAYER',
                    'payerBankName' => 'Test Bank',
                    'payerBankCountry' => 'BG',
                    'sum' => 200.00,
                    'status' => 'WAITING',
                    'payeeName' => 'Receiver Ltd',
                    'payeeIban' => 'BG12PAYEE',
                    'currency' => 'BGN',
                    'remittanceDescription' => 'Invoice 123',
                    'paymentType' => 'DOMESTIC_CREDIT_TRANSFER',
                ],
            ],
        ])),
    ]);

    $data = new PaymentSearchData(page: 0, size: 30, agentHash: 'test-agent-hash');
    $result = $client->searchPayments($data);

    expect($result)->toBeInstanceOf(PaymentSearchResult::class);
    expect($result->pages)->toBe(5);
    expect($result->elementsSize)->toBe(100);
    expect($result->payments)->toHaveCount(1);
    expect($result->payments[0]->payeeName)->toBe('Receiver Ltd');
    expect($result->payments[0]->paymentType)->toBe(\Ux2Dev\Iris\Api\Enum\PaymentType::DomesticCreditTransfer);
});

test('getActiveUsers returns ActiveUsers', function () {
    $client = createReportClient([
        new Response(200, [], json_encode([
            'bankAccounts' => 42,
            'users' => 15,
        ])),
    ]);

    $result = $client->getActiveUsers('2025-01-01', '2025-01-31');

    expect($result)->toBeInstanceOf(ActiveUsers::class);
    expect($result->bankAccounts)->toBe(42);
    expect($result->users)->toBe(15);
});

test('getBankMaintenance returns array of BankMaintenance', function () {
    $client = createReportClient([
        new Response(200, [], json_encode([
            [
                'bankName' => 'Test Bank',
                'maintenanceMessage' => 'Scheduled maintenance',
                'fromDate' => '2025-03-01T00:00:00Z',
                'toDate' => '2025-03-01T06:00:00Z',
            ],
            [
                'bankName' => 'Another Bank',
                'maintenanceMessage' => 'System upgrade',
                'fromDate' => '2025-03-02T22:00:00Z',
                'toDate' => '2025-03-03T04:00:00Z',
            ],
        ])),
    ]);

    $result = $client->getBankMaintenance();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(BankMaintenance::class);
    expect($result[0]->bankName)->toBe('Test Bank');
    expect($result[0]->maintenanceMessage)->toBe('Scheduled maintenance');
    expect($result[1]->bankName)->toBe('Another Bank');
});

test('getActiveUsersDetails returns ActiveUsersDetails', function () {
    $client = createReportClient([
        new Response(200, [], json_encode([
            'bankAccounts' => [101, 102, 103],
            'users' => ['user-hash-1', 'user-hash-2'],
            'pages' => 3,
            'page' => 0,
            'elementsSize' => 50,
        ])),
    ]);

    $data = new ActiveUsersDetailsData(
        agentHash: 'test-agent-hash',
        fromDate: '2025-01-01',
        toDate: '2025-01-31',
    );
    $result = $client->getActiveUsersDetails($data);

    expect($result)->toBeInstanceOf(ActiveUsersDetails::class);
    expect($result->bankAccounts)->toBe([101, 102, 103]);
    expect($result->users)->toBe(['user-hash-1', 'user-hash-2']);
    expect($result->pages)->toBe(3);
    expect($result->elementsSize)->toBe(50);
});
