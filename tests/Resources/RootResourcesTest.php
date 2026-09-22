<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\Response\BankMaintenance;
use Ux2Dev\Iris\Api\Response\BulkPayment;
use Ux2Dev\Iris\Api\Response\ConsentDetails;
use Ux2Dev\Iris\Api\Response\Payment;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\Accounts;
use Ux2Dev\Iris\Resources\BulkPayments;
use Ux2Dev\Iris\Resources\ConsentGate;
use Ux2Dev\Iris\Resources\Payments;
use Ux2Dev\Iris\Resources\Reports;

/** @template T @param class-string<T> $class @return T */
function rootResource(string $class, array $responses, ?array &$history = null): object
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();
    $config = new MerchantConfig(
        environment: Environment::Development,
        agentHash: 'agent-1',
        adminHash: 'admin-1',
    );

    return new $class(
        new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            new Client(['handler' => $stack]),
            $factory,
            $factory,
        ),
        new Credentials($config),
    );
}

test('Accounts::getConsentDetails uses the agent header', function () {
    $history = [];
    $resource = rootResource(Accounts::class, [
        new Response(200, [], json_encode(['dateCreated' => '2025-01-01T00:00:00Z'])),
    ], $history);

    expect($resource->getConsentDetails('BG18RZBB9155'))->toBeInstanceOf(ConsentDetails::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/consent/iban/BG18RZBB9155')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});

test('Payments::statusByHook uses the agent header', function () {
    $history = [];
    $resource = rootResource(Payments::class, [
        new Response(200, [], json_encode([
            'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver',
            'description' => 'Test', 'sum' => '100.00', 'payeeIban' => 'BG12RECV',
            'currency' => 'BGN', 'status' => 'CONFIRMED',
        ])),
    ], $history);

    expect($resource->statusByHook('hook-1'))->toBeInstanceOf(Payment::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/status/hook-1');
});

test('BulkPayments::status uses the agent header', function () {
    $history = [];
    $resource = rootResource(BulkPayments::class, [
        new Response(200, [], json_encode([
            'date' => '2025-01-15T10:00:00Z', 'status' => 'CONFIRMED',
            'reasonForFail' => null, 'payerBank' => null, 'payments' => [],
        ])),
    ], $history);

    expect($resource->status('hook-1'))->toBeInstanceOf(BulkPayment::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/status/hook-1');
});

test('Reports::bankMaintenance sends no auth header', function () {
    $history = [];
    $resource = rootResource(Reports::class, [new Response(200, [], '[]')], $history);

    expect($resource->bankMaintenance())->toBe([])
        ->and($history[0]['request']->hasHeader('x-agent-hash'))->toBeFalse();
});

test('Reports::bankMaintenance maps rows to BankMaintenance', function () {
    $resource = rootResource(Reports::class, [
        new Response(200, [], json_encode([[
            'bankName' => 'Test Bank',
            'maintenanceMessage' => 'Scheduled maintenance',
            'fromDate' => '2025-03-01T00:00:00Z',
            'toDate' => '2025-03-01T06:00:00Z',
        ]])),
    ]);

    expect($resource->bankMaintenance()[0])->toBeInstanceOf(BankMaintenance::class);
});

test('Reports::activeUsers includes agentHash in the body', function () {
    $history = [];
    $resource = rootResource(Reports::class, [
        new Response(200, [], json_encode(['bankAccounts' => 42, 'users' => 15])),
    ], $history);

    $resource->activeUsers('2026-01-01', '2026-01-31');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body['agentHash'])->toBe('agent-1')
        ->and($body['fromDate'])->toBe('2026-01-01')
        ->and($body)->not->toHaveKey('validUntil');
});

test('Reports::activeUsers appends validUntil when given', function () {
    $history = [];
    $resource = rootResource(Reports::class, [
        new Response(200, [], json_encode(['bankAccounts' => 42, 'users' => 15])),
    ], $history);

    $resource->activeUsers('2026-01-01', '2026-01-31', '2026-02-28');

    expect(json_decode((string) $history[0]['request']->getBody(), true)['validUntil'])
        ->toBe('2026-02-28');
});

test('ConsentGate::createRequest sends admin and agent headers', function () {
    $history = [];
    $resource = rootResource(ConsentGate::class, [
        new Response(200, [], json_encode([
            'userHash' => 'user-123',
            'url' => 'https://example.com/consent',
            'ibansIds' => ['BG12TEST' => 1],
        ])),
    ], $history);

    $resource->createRequest(new \Ux2Dev\Iris\Api\Request\ConsentGateRequestData(
        userInfo: [
            'firstName' => 'John',
            'middleName' => 'M',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+359888123456',
            'uic' => '1234567890',
        ],
        ibans: ['BG12TEST'],
    ));

    expect($history[0]['request']->getHeaderLine('x-admin-hash'))->toBe('admin-1')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});
