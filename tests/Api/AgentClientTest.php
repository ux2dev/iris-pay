<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\AgentClient;
use Ux2Dev\Iris\Api\Enum\IdentityStatus;
use Ux2Dev\Iris\Api\Request\CreateHookData;
use Ux2Dev\Iris\Api\Request\SignupData;
use Ux2Dev\Iris\Api\Response\EmailAccount;
use Ux2Dev\Iris\Api\Response\Hook;
use Ux2Dev\Iris\Api\Response\IdentificationAccount;
use Ux2Dev\Iris\Api\Response\UsersList;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ConfigurationException;

function createAgentClient(array $responses): AgentClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('requires agentHash in config', function () {
    $factory = new HttpFactory();
    new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, publicHash: 'pub-only'),
        httpClient: new Client(),
        requestFactory: $factory,
        streamFactory: $factory,
    );
})->throws(ConfigurationException::class, 'agentHash is required');

test('signup returns IdentificationAccount', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode([
            'userHash' => 'user-hash-123', 'idUrl' => 'https://example.com/id',
            'identityStatusUrl' => null, 'identityToken' => null, 'identified' => 'NOT_STARTED',
        ])),
    ]);

    $result = $client->signup(new SignupData(
        agentHash: 'test-agent-hash', companyName: 'Test Co', uic: '123456789',
        name: 'John', middleName: 'M', family: 'Doe', identityHash: 'id-hash', email: 'john@example.com',
    ));

    expect($result)->toBeInstanceOf(IdentificationAccount::class);
    expect($result->userHash)->toBe('user-hash-123');
    expect($result->identified)->toBe(IdentityStatus::NotStarted);
});

test('createHook returns Hook', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode(['hookHash' => 'hook-hash-456', 'closeSelf' => 0, 'redirectTimer' => 5])),
    ]);

    $result = $client->createHook(new CreateHookData(url: 'https://example.com/webhook', agentHash: 'test-agent-hash'));

    expect($result)->toBeInstanceOf(Hook::class);
    expect($result->hookHash)->toBe('hook-hash-456');
});

test('createUserToken returns string token', function () {
    $client = createAgentClient([
        new Response(200, ['Content-Type' => 'text/plain'], '"token-abc-123"'),
    ]);

    $token = $client->createUserToken('user-hash-123');
    expect($token)->toBe('token-abc-123');
});

test('listUsers returns UsersList', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode([
            'pages' => 1, 'size' => 30, 'currPage' => 0,
            'list' => [[
                'name' => 'John', 'middleName' => 'M', 'family' => 'Doe', 'email' => 'john@example.com',
                'uic' => '123456789', 'dateCreated' => '2025-01-01T00:00:00Z', 'userHash' => 'user-hash-123', 'accounts' => 2,
            ]],
        ])),
    ]);

    $result = $client->listUsers();

    expect($result)->toBeInstanceOf(UsersList::class);
    expect($result->list)->toHaveCount(1);
    expect($result->list[0]->name)->toBe('John');
});

test('checkUserByEmail returns EmailAccount', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode(['userHash' => 'user-hash-123', 'name' => 'John', 'lastname' => 'Doe', 'surname' => 'M'])),
    ]);

    $result = $client->checkUserByEmail('john@example.com');

    expect($result)->toBeInstanceOf(EmailAccount::class);
    expect($result->userHash)->toBe('user-hash-123');
});

test('deleteUser sends DELETE without error', function () {
    $client = createAgentClient([new Response(200)]);
    $client->deleteUser('user-hash-123');
    expect(true)->toBeTrue();
});
