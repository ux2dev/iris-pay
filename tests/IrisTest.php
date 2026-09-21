<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Iris;
use Ux2Dev\Iris\UserScope;

function makeIris(): Iris
{
    $factory = new HttpFactory();

    return new Iris(
        new MerchantConfig(
            environment: Environment::Development,
            publicHash: 'pub-1',
            agentHash: 'agent-1',
            adminHash: 'admin-1',
        ),
        new Client(),
        $factory,
        $factory,
    );
}

/**
 * @param Response[] $responses
 * @param array<int, array{request: \Psr\Http\Message\RequestInterface}>|null $history
 */
function mockedIris(array $responses, ?array &$history = null): Iris
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();

    return new Iris(
        new MerchantConfig(
            environment: Environment::Development,
            publicHash: 'pub-1',
            agentHash: 'agent-1',
            adminHash: 'admin-1',
        ),
        new Client(['handler' => $stack]),
        $factory,
        $factory,
    );
}

test('exposes every root resource', function () {
    $iris = makeIris();

    expect($iris->payByLink())->toBeInstanceOf(\Ux2Dev\Iris\Resources\PayByLink::class)
        ->and($iris->agent())->toBeInstanceOf(\Ux2Dev\Iris\Resources\Agent::class)
        ->and($iris->accounts())->toBeInstanceOf(\Ux2Dev\Iris\Resources\Accounts::class)
        ->and($iris->payments())->toBeInstanceOf(\Ux2Dev\Iris\Resources\Payments::class)
        ->and($iris->bulkPayments())->toBeInstanceOf(\Ux2Dev\Iris\Resources\BulkPayments::class)
        ->and($iris->reports())->toBeInstanceOf(\Ux2Dev\Iris\Resources\Reports::class)
        ->and($iris->consentGate())->toBeInstanceOf(\Ux2Dev\Iris\Resources\ConsentGate::class);
});

test('caches each root resource', function () {
    $iris = makeIris();

    expect($iris->agent())->toBe($iris->agent())
        ->and($iris->payByLink())->toBe($iris->payByLink());
});

test('user returns a UserScope carrying the hash', function () {
    $scope = makeIris()->user('user-1');

    expect($scope)->toBeInstanceOf(UserScope::class)
        ->and($scope->userHash())->toBe('user-1');
});

test('exposes every user-scoped resource', function () {
    $scope = makeIris()->user('user-1');

    expect($scope->accounts())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\Accounts::class)
        ->and($scope->payments())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\Payments::class)
        ->and($scope->bulkPayments())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\BulkPayments::class)
        ->and($scope->agent())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\Agent::class)
        ->and($scope->reports())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\Reports::class)
        ->and($scope->consentGate())->toBeInstanceOf(\Ux2Dev\Iris\Resources\User\ConsentGate::class);
});

test('caches user-scoped resources within one scope', function () {
    $scope = makeIris()->user('user-1');

    expect($scope->accounts())->toBe($scope->accounts());
});

test('returns a distinct scope per user hash', function () {
    $iris = makeIris();

    expect($iris->user('user-1'))->not->toBe($iris->user('user-2'))
        ->and($iris->user('user-2')->userHash())->toBe('user-2');
});

test('exposes the config', function () {
    expect(makeIris()->config()->publicHash)->toBe('pub-1');
});

test('payByLink requests go to the PayByLink host', function () {
    $history = [];
    $iris = mockedIris([
        new Response(200, [], json_encode([['bankHash' => 'b1', 'name' => 'Bank One']])),
    ], $history);

    $iris->payByLink()->getBanks();

    expect($history[0]['request']->getUri()->__toString())
        ->toStartWith(Environment::Development->payByLinkBaseUrl());
});

test('user-scoped requests go to the core host', function () {
    $history = [];
    $iris = mockedIris([
        new Response(200, [], '[]'),
    ], $history);

    $iris->user('u1')->accounts()->listIbans();

    expect($history[0]['request']->getUri()->__toString())
        ->toStartWith(Environment::Development->webSdkBaseUrl());
});

test('root resource requests other than payByLink go to the core host', function () {
    $history = [];
    $iris = mockedIris([
        new Response(200, [], json_encode(['pages' => 1, 'size' => 10, 'currPage' => 0])),
    ], $history);

    $iris->agent()->listUsers();

    expect($history[0]['request']->getUri()->__toString())
        ->toStartWith(Environment::Development->webSdkBaseUrl());
});
