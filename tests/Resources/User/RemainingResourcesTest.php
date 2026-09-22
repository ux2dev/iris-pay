<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\Enum\IdentifierType;
use Ux2Dev\Iris\Api\Request\BulkBudgetData;
use Ux2Dev\Iris\Api\Request\BulkBudgetEntry;
use Ux2Dev\Iris\Api\Request\BulkBudgetIbanData;
use Ux2Dev\Iris\Api\Request\BulkEntry;
use Ux2Dev\Iris\Api\Request\BulkIbanPaymentData;
use Ux2Dev\Iris\Api\Request\BulkPaymentData;
use Ux2Dev\Iris\Api\Response\ConsentGateStatus;
use Ux2Dev\Iris\Api\Response\ConsentGateUi;
use Ux2Dev\Iris\Api\Response\IdentificationAccount;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentsList;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;

/** @template T @param class-string<T> $class @return T */
function userResource(string $class, array $responses, ?array &$history = null): object
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
        'user-1',
    );
}

$paymentResponseBody = ['code' => 'PAY-001', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1];

test('Agent::createToken trims the quoted token', function () {
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [
        new Response(200, [], '"tok-1"'),
    ]);

    expect($resource->createToken())->toBe('tok-1');
});

test('Agent::createToken sends the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [
        new Response(200, [], '"tok-1"'),
    ], $history);

    $resource->createToken();

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/usertoken')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('Agent::delete sends both auth headers', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [new Response(204)], $history);

    $resource->delete();

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/agent/user')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('Agent::sendAisEmail posts the three fields with both headers', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [new Response(204)], $history);

    $resource->sendAisEmail('hook-1', 'bank-1', 'a@b.test');

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/agent/ais/email')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['hookHash' => 'hook-1', 'bankHash' => 'bank-1', 'email' => 'a@b.test'])
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('Agent::kycStatus returns IdentificationAccount and sends the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [
        new Response(200, [], json_encode([
            'userHash' => 'user-1', 'idUrl' => null, 'identityStatusUrl' => null,
            'identityToken' => null, 'identified' => 'NOT_STARTED',
        ])),
    ], $history);

    $result = $resource->kycStatus();

    expect($result)->toBeInstanceOf(IdentificationAccount::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/id/status')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('Reports::listPayments sends the agent header and userHash in the body', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Reports::class, [
        new Response(200, [], json_encode(['size' => 5, 'page' => 1, 'payments' => []])),
    ], $history);

    $result = $resource->listPayments(page: 1, size: 5, status: 'CONFIRMED');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($result)->toBeInstanceOf(PaymentsList::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payments')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($history[0]['request']->hasHeader('x-user-hash'))->toBeFalse()
        ->and($body)->toBe(['page' => 1, 'size' => 5, 'status' => 'CONFIRMED', 'userHash' => 'user-1']);
});

test('ConsentGate::uiConsentRequest sends the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\ConsentGate::class, [
        new Response(200, [], json_encode([
            'id' => 10, 'accountId' => 5, 'expirationDate' => '2026-12-31', 'timeout' => 300,
            'showValidUntil' => true, 'showFrequencyPerDay' => false,
            'expirationMessage' => null, 'successMessage' => null, 'deleted' => false,
        ])),
    ], $history);

    $result = $resource->uiConsentRequest();

    expect($result)->toBeInstanceOf(ConsentGateUi::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/cgate/ui/consents-request')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('ConsentGate::getConsents sends the admin header, not the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\ConsentGate::class, [
        new Response(200, [], json_encode([
            ['ibanId' => 1, 'iban' => 'BG12TEST', 'fulfilled' => true, 'validUntil' => '2026-12-31', 'frequencyPerDay' => 4],
        ])),
    ], $history);

    $result = $resource->getConsents();

    expect($result)->toBeArray()
        ->and($result[0])->toBeInstanceOf(ConsentGateStatus::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/cgate/consents-request/user-1')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('fulfilled=true')
        ->and($history[0]['request']->getHeaderLine('x-admin-hash'))->toBe('admin-1')
        ->and($history[0]['request']->hasHeader('x-user-hash'))->toBeFalse();
});

test('ConsentGate::getConsents passes fulfilled=false when requested', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\ConsentGate::class, [
        new Response(200, [], '[]'),
    ], $history);

    $resource->getConsents(fulfilled: false);

    expect($history[0]['request']->getUri()->getQuery())->toBe('fulfilled=false');
});

test('BulkPayments::create posts to the bulk payment path with the user header', function () use ($paymentResponseBody) {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\BulkPayments::class, [
        new Response(200, [], json_encode($paymentResponseBody)),
    ], $history);

    $data = new BulkPaymentData(
        bankHash: 'bank-1', currency: 'BGN', hookHash: 'hook-1', emailNotification: false,
        senderIban: 'BG12SEND', psuId: null, requestedExecutionDate: null,
        payments: [new BulkEntry(receiverIban: 'BG12RECV', receiverName: 'Receiver', remittanceDescription: 'Test', sum: 100.00)],
    );

    $result = $resource->create($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/payment')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('BulkPayments::createIban posts to the bulk iban path with the user header', function () use ($paymentResponseBody) {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\BulkPayments::class, [
        new Response(200, [], json_encode($paymentResponseBody)),
    ], $history);

    $data = new BulkIbanPaymentData(
        currency: 'BGN', hookHash: 'hook-1', emailNotification: false, fromIbanId: 1,
        requestedExecutionDate: null,
        payments: [new BulkEntry(receiverIban: 'BG12RECV', receiverName: 'Receiver', remittanceDescription: 'Test', sum: 100.00)],
    );

    $result = $resource->createIban($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/request')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('BulkPayments::createBudget posts to the bulk budget-payment path with the user header', function () use ($paymentResponseBody) {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\BulkPayments::class, [
        new Response(200, [], json_encode($paymentResponseBody)),
    ], $history);

    $data = new BulkBudgetData(
        bankHash: 'bank-1', currency: 'BGN', hookHash: 'hook-1', emailNotification: false,
        senderIban: 'BG12SEND', psuId: null, requestedExecutionDate: null,
        payments: [new BulkBudgetEntry(
            receiverIban: 'BG12RECV', receiverName: 'Receiver', remittanceDescription: 'Test',
            sum: 100.00, identifier: '1234567890', identifierType: IdentifierType::Egn,
        )],
    );

    $result = $resource->createBudget($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/budget-payment')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('BulkPayments::createBudgetIban posts to the bulk budget-request path with the user header', function () use ($paymentResponseBody) {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\BulkPayments::class, [
        new Response(200, [], json_encode($paymentResponseBody)),
    ], $history);

    $data = new BulkBudgetIbanData(
        currency: 'BGN', hookHash: 'hook-1', emailNotification: false, fromIbanId: 1,
        requestedExecutionDate: null,
        payments: [new BulkBudgetEntry(
            receiverIban: 'BG12RECV', receiverName: 'Receiver', remittanceDescription: 'Test',
            sum: 100.00, identifier: '1234567890', identifierType: IdentifierType::Eik,
        )],
    );

    $result = $resource->createBudgetIban($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/budget-request')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});
