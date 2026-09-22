<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;
use Ux2Dev\Iris\Resources\PayByLink;

function payByLinkResource(array $responses, ?array &$history = null): PayByLink
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();
    $config = new MerchantConfig(
        environment: Environment::Development,
        publicHash: 'pub-1',
        currency: Currency::EUR,
    );

    return new PayByLink(
        new IrisTransport(
            $config->environment->payByLinkBaseUrl(),
            new Client(['handler' => $stack]),
            $factory,
            $factory,
        ),
        new Credentials($config),
        $config,
    );
}

test('getBanks returns Bank objects from the public-hash path', function () {
    $history = [];
    $resource = payByLinkResource([
        new Response(200, [], json_encode([['bankHash' => 'b1', 'name' => 'Bank One']])),
    ], $history);

    $banks = $resource->getBanks();

    expect($banks)->toHaveCount(1)
        ->and($banks[0])->toBeInstanceOf(Bank::class)
        ->and((string) $history[0]['request']->getUri())
        ->toBe('https://dev.paybyclick.irispay.bg/backend/payment/banks/pub-1');
});

test('createLink posts to the external endpoint and returns PaymentLinkResponse', function () {
    $history = [];
    $resource = payByLinkResource([
        new Response(200, [], json_encode(['accountId' => 'acc-1', 'paymentHash' => 'ph-1', 'paymentLink' => 'https://pay.test/ph-1'])),
    ], $history);

    $result = $resource->createLink(
        sum: 12.50,
        description: 'Order 1',
        toIban: 'BG18RZBB91550123456789',
        hookUrl: 'https://shop.test/hook',
        redirectUrl: 'https://shop.test/thanks',
    );

    $body = json_decode((string) $history[0]['request']->getBody(), true);

    expect($result)->toBeInstanceOf(PaymentLinkResponse::class)
        ->and($body['currency'])->toBe('EUR')
        ->and($body['sum'])->toBe(12.5)
        ->and((string) $history[0]['request']->getUri())
        ->toBe('https://dev.paybyclick.irispay.bg/backend/payment/external/pub-1');
});

test('createLink rejects a non-positive sum', function () {
    $resource = payByLinkResource([]);

    expect(fn () => $resource->createLink(
        sum: 0.0,
        description: 'x',
        toIban: 'BG18',
        hookUrl: 'https://a.test/h',
        redirectUrl: 'https://a.test/r',
    ))->toThrow(ConfigurationException::class, 'sum must be greater than 0');
});

test('createLink rejects a non-https hook url', function () {
    $resource = payByLinkResource([]);

    expect(fn () => $resource->createLink(
        sum: 1.0,
        description: 'x',
        toIban: 'BG18',
        hookUrl: 'http://a.test/h',
        redirectUrl: 'https://a.test/r',
    ))->toThrow(ConfigurationException::class, 'hookUrl must use https://');
});

test('createLink rejects a name longer than 34 characters', function () {
    $resource = payByLinkResource([]);

    expect(fn () => $resource->createLink(
        sum: 1.0,
        description: 'x',
        toIban: 'BG18',
        hookUrl: 'https://a.test/h',
        redirectUrl: 'https://a.test/r',
        name: str_repeat('a', 35),
    ))->toThrow(ConfigurationException::class, 'name must not exceed 34 characters');
});

test('getQrCode returns raw bytes', function () {
    $resource = payByLinkResource([new Response(200, [], "\x89PNG\r\n")]);

    expect($resource->getQrCode('ph-1'))->toBe("\x89PNG\r\n");
});

test('getStatus returns PaymentStatusResponse', function () {
    $resource = payByLinkResource([
        new Response(200, [], json_encode(['paymentHash' => 'ph-1', 'status' => 'CONFIRMED'])),
    ]);

    expect($resource->getStatus('ph-1'))->toBeInstanceOf(PaymentStatusResponse::class);
});

test('refund posts to the refund endpoint', function () {
    $history = [];
    $resource = payByLinkResource([
        new Response(200, [], json_encode(['bankScaType' => 'REDIRECT_URL', 'url' => 'https://bank.test/authorize/rh-1'])),
    ], $history);

    $result = $resource->refund(
        paymentHash: 'ph-1',
        refundType: RefundType::Full,
        sum: 5.0,
        remittanceDescription: 'refund',
        webhookUrl: 'https://shop.test/hook',
    );

    expect($result)->toBeInstanceOf(RefundResponse::class)
        ->and((string) $history[0]['request']->getUri())
        ->toBe('https://dev.paybyclick.irispay.bg/backend/payment/external/refund/pub-1');
});

test('deactivate issues a PUT and returns nothing', function () {
    $history = [];
    $resource = payByLinkResource([new Response(204)], $history);

    $resource->deactivate('ph-1');

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())
        ->toBe('https://dev.paybyclick.irispay.bg/backend/payment/inactive/pub-1');
});

test('throws when publicHash is absent', function () {
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'a1');
    $resource = new PayByLink(
        new IrisTransport('https://dev.paybyclick.irispay.bg', new Client(), $factory, $factory),
        new Credentials($config),
        $config,
    );

    expect(fn () => $resource->getBanks())
        ->toThrow(ConfigurationException::class, 'publicHash is required');
});
