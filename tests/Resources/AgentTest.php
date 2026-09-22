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
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\Agent;

function agentResource(array $responses, ?array &$history = null): Agent
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'agent-1');

    return new Agent(
        new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            new Client(['handler' => $stack]),
            $factory,
            $factory,
        ),
        new Credentials($config),
    );
}

test('listUsers sends the agent header and pagination query', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode(['pages' => 1, 'size' => 10, 'currPage' => 2])),
    ], $history);

    $resource->listUsers(page: 2, size: 10, text: 'ivan');

    $request = $history[0]['request'];
    expect($request->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($request->getUri()->getQuery())->toBe('page=2&size=10&text=ivan');
});

test('checkUserByEmail posts the email with the agent header', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode(['userHash' => 'user-hash-123', 'name' => 'John', 'lastname' => 'Doe', 'surname' => 'M'])),
    ], $history);

    $resource->checkUserByEmail('a@b.test');

    expect((string) $history[0]['request']->getBody())->toBe('{"email":"a@b.test"}')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});

test('addRedirectToHook issues a PUT with the hook query and no auth header', function () {
    $history = [];
    $resource = agentResource([new Response(204)], $history);

    $resource->addRedirectToHook('hook-1', 'https://shop.test/back');

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('PUT')
        ->and($request->getUri()->getPath())->toBe('/api/8/redirect')
        ->and($request->hasHeader('x-agent-hash'))->toBeFalse();
});

test('updateKyc posts the submission and status', function () {
    $history = [];
    $resource = agentResource([new Response(204)], $history);

    $resource->updateKyc('sub-1', 'APPROVED');

    expect(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['submissionId' => 'sub-1', 'status' => 'APPROVED']);
});

test('signup posts without an auth header', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode([
            'userHash' => 'user-hash-123',
            'idUrl' => null,
            'identityStatusUrl' => null,
            'identityToken' => null,
            'identified' => 'NOT_STARTED',
        ])),
    ], $history);

    $resource->signup(new \Ux2Dev\Iris\Api\Request\SignupData(
        agentHash: 'agent-1',
        companyName: 'Test Co',
        uic: '123456789',
        name: 'John',
        middleName: 'M',
        family: 'Doe',
        identityHash: 'id-hash',
        email: 'a@b.test',
    ));

    expect($history[0]['request']->hasHeader('x-agent-hash'))->toBeFalse()
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/signup');
});

test('createHook posts to the createhook path', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode(['hookHash' => 'hook-hash-456', 'closeSelf' => 0, 'redirectTimer' => 5])),
    ], $history);

    $resource->createHook(new \Ux2Dev\Iris\Api\Request\CreateHookData(
        url: 'https://shop.test/hook',
        agentHash: 'agent-1',
    ));

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/8/createhook');
});

test('signupAgent posts to the agent signup path', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode([
            'userHash' => 'user-hash-123',
            'idUrl' => null,
            'identityStatusUrl' => null,
            'identityToken' => null,
            'identified' => 'NOT_STARTED',
        ])),
    ], $history);

    $resource->signupAgent(new \Ux2Dev\Iris\Api\Request\SignupAgentData(
        agentHash: 'agent-1',
        companyName: 'Test Co',
        uic: '123456789',
        name: 'John',
        middleName: 'M',
        family: 'Doe',
        email: 'a@b.test',
    ));

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/8/signup/agent');
});
