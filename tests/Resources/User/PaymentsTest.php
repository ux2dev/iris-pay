<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\Enum\IdentifierType;
use Ux2Dev\Iris\Api\Request\BudgetDirectData;
use Ux2Dev\Iris\Api\Request\BudgetPaymentData;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\User\Payments;

function userPayments(array $responses, ?array &$history = null): Payments
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'agent-1');

    return new Payments(
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

$bankScaBody = [
    'startUrl' => 'https://bank.test/start', 'endUrl' => 'https://bank.test/end',
    'formText' => null, 'loadingText' => null, 'psuIdType' => 'USERNAME',
    'sca' => 'REDIRECT_URL', 'gatherPsu' => false, 'hasAuthorization' => false,
    'externalApp' => false, 'authorizationId' => null,
];

$paymentBody = [
    'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver', 'payerName' => null,
    'payerBank' => null, 'payeeBank' => null, 'description' => 'Test', 'sum' => '100',
    'payerIban' => null, 'payeeIban' => 'BG12RECV', 'id' => null,
    'currency' => 'BGN', 'status' => 'WAITING', 'reasonForFail' => null, 'authorised' => false,
];

test('createDirect posts DirectPaymentData and returns PaymentResponse', function () use ($paymentBody) {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['code' => 'PAY-001', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ], $history);

    $data = new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    );

    $result = $resource->createDirect($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($result->code)->toBe('PAY-001')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/direct')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('createDirectInitiate posts DirectPaymentData and returns BankScaUrl', function () {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['url' => 'https://bank.example.com/sca', 'bankSCA' => 'REDIRECT_URL'])),
    ], $history);

    $data = new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    );

    $result = $resource->createDirectInitiate($data);

    expect($result)->toBeInstanceOf(BankScaUrl::class)
        ->and($result->url)->toBe('https://bank.example.com/sca')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/direct-initiate')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('createIban posts IbanPaymentData and returns PaymentResponse', function () {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['code' => 'PAY-002', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ], $history);

    $data = new IbanPaymentData(
        fromIbanId: 1, receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 50.00, currency: 'BGN', hookHash: 'hook-1',
    );

    $result = $resource->createIban($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($result->code)->toBe('PAY-002')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/iban')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('confirm issues a PUT and returns nothing', function () {
    $history = [];
    $resource = userPayments([new Response(200, [], '{}')], $history);

    $resource->confirm('code-1', 42);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['code' => 'code-1', 'ibanId' => 42]);
});

test('confirmWithResult posts to the payment path and returns PaymentConfirmResult', function () {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['result' => 'SUCCESS'])),
    ], $history);

    $result = $resource->confirmWithResult('PAY-001', 1);

    expect($result)->toBeInstanceOf(PaymentConfirmResult::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('confirmSms issues a PUT to the verify-sms path and returns nothing', function () {
    $history = [];
    $resource = userPayments([new Response(200, [], '{}')], $history);

    $resource->confirmSms('code-1', 42, 'sms-1');

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/verify-sms')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['code' => 'code-1', 'ibanId' => 42, 'smsCode' => 'sms-1']);
});

test('statusByCode passes the code as a query parameter', function () use ($paymentBody) {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode([
            'payment' => $paymentBody,
            'urlSca' => ['startUrl' => 'https://bank.example.com', 'endUrl' => null, 'externalApp' => false],
            'ready' => false,
        ])),
    ], $history);

    $result = $resource->statusByCode('code-1');

    expect($result)->toBeInstanceOf(PaymentStatusDetail::class)
        ->and($result->ready)->toBeFalse()
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/status')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('code=code-1');
});

test('authorization gets the nested iban path and returns BankSca', function () use ($bankScaBody) {
    $history = [];
    $resource = userPayments([new Response(200, [], json_encode($bankScaBody))], $history);

    $result = $resource->authorization(42);

    expect($result)->toBeInstanceOf(BankSca::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/ibans/42')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('sca posts with no body to the sca path', function () use ($bankScaBody) {
    $history = [];
    $resource = userPayments([new Response(200, [], json_encode($bankScaBody))], $history);

    $result = $resource->sca(42);

    expect($result)->toBeInstanceOf(BankSca::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/sca/42')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe('');
});

test('createBudget posts BudgetPaymentData and returns PaymentResponse', function () {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['code' => 'PAY-003', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ], $history);

    $data = new BudgetPaymentData(
        fromIbanId: 1, receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 75.00, currency: 'BGN', hookHash: 'hook-1',
        identifier: '1234567890', identifierType: IdentifierType::Egn,
    );

    $result = $resource->createBudget($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($result->code)->toBe('PAY-003')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/budget-request')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('createBudgetDirect posts BudgetDirectData and returns PaymentResponse', function () {
    $history = [];
    $resource = userPayments([
        new Response(200, [], json_encode(['code' => 'PAY-004', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ], $history);

    $data = new BudgetDirectData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 80.00, currency: 'BGN', hookHash: 'hook-1',
        identifier: '1234567890', identifierType: IdentifierType::Eik,
    );

    $result = $resource->createBudgetDirect($data);

    expect($result)->toBeInstanceOf(PaymentResponse::class)
        ->and($result->code)->toBe('PAY-004')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/budget-request/no-iban')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});
