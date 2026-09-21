<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\AgentClient;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;
use Ux2Dev\Iris\Webhook\WebhookParser;

function createSecurityTestClient(array $responses): AgentClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

function createSecurityPayByLinkClient(array $responses): PayByLinkClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PayByLinkClient(
        config: new MerchantConfig(environment: Environment::Development, publicHash: 'test-public'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

// --- Network Error Wrapping ---

test('BaseClient wraps network errors as NetworkException', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('GET', 'https://example.com')),
    ]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    $client = new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );

    $client->listUsers();
})->throws(NetworkException::class);

test('PayByLinkClient wraps network errors as NetworkException', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('GET', 'https://example.com')),
    ]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    $client = new PayByLinkClient(
        config: new MerchantConfig(environment: Environment::Development, publicHash: 'test-public'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );

    $client->getBanks();
})->throws(NetworkException::class);

test('NetworkException preserves original exception', function () {
    $original = new ConnectException('DNS failure', new GuzzleRequest('GET', 'https://example.com'));
    $mock = new MockHandler([$original]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    $client = new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );

    try {
        $client->listUsers();
    } catch (NetworkException $e) {
        expect($e->getPrevious())->toBe($original);
    }
});

// --- 4xx vs 5xx Split ---

test('4xx response throws ApiClientException', function () {
    $client = createSecurityTestClient([
        new Response(404, [], json_encode(['error' => 'Not Found'])),
    ]);

    $client->listUsers();
})->throws(ApiClientException::class);

test('401 response throws ApiClientException', function () {
    $client = createSecurityTestClient([
        new Response(401, [], json_encode(['error' => 'Unauthorized'])),
    ]);

    $client->listUsers();
})->throws(ApiClientException::class);

test('5xx response throws ApiServerException', function () {
    $client = createSecurityTestClient([
        new Response(503, [], json_encode(['error' => 'Service Unavailable'])),
    ]);

    $client->listUsers();
})->throws(ApiServerException::class);

test('ApiClientException and ApiServerException extend InvalidResponseException', function () {
    expect(is_subclass_of(ApiClientException::class, InvalidResponseException::class))->toBeTrue();
    expect(is_subclass_of(ApiServerException::class, InvalidResponseException::class))->toBeTrue();
});

test('PayByLinkClient throws ApiClientException on 400', function () {
    $client = createSecurityPayByLinkClient([
        new Response(400, [], json_encode(['error' => 'Bad Request'])),
    ]);

    $client->getBanks();
})->throws(ApiClientException::class);

test('PayByLinkClient throws ApiServerException on 500', function () {
    $client = createSecurityPayByLinkClient([
        new Response(500, [], json_encode(['error' => 'Internal Server Error'])),
    ]);

    $client->getBanks();
})->throws(ApiServerException::class);

// --- Body Redaction ---

test('exception responseData does not contain body', function () {
    $client = createSecurityTestClient([
        new Response(400, [], json_encode(['iban' => 'BG12SENSITIVE', 'name' => 'Secret Person'])),
    ]);

    try {
        $client->listUsers();
    } catch (ApiClientException $e) {
        expect($e->getResponseData())->not->toHaveKey('body');
        expect($e->getResponseData())->toHaveKey('status');
        expect($e->getResponseData()['status'])->toBe(400);
    }
});

// --- CRLF Header Injection Prevention ---

test('rejects userHash with CRLF characters', function () {
    $client = createSecurityTestClient([new Response(200, [], '[]')]);

    $client->getKycStatus("user-hash\r\nX-Injected: true");
})->throws(ConfigurationException::class, 'contains invalid characters');

test('rejects agentHash with CRLF characters', function () {
    $factory = new HttpFactory();

    new AgentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: "agent\r\nhack"),
        httpClient: new Client(),
        requestFactory: $factory,
        streamFactory: $factory,
    );
})->throws(ConfigurationException::class, 'contains invalid characters');

// --- WebhookParser SDK Exceptions ---

test('WebhookParser throws InvalidResponseException on missing status', function () {
    WebhookParser::parse([]);
})->throws(InvalidResponseException::class, 'Missing status parameter');

test('WebhookParser throws InvalidResponseException on unknown status', function () {
    WebhookParser::parse(['status' => 'HACKED']);
})->throws(InvalidResponseException::class, 'Unknown payment status');

// --- Empty Response Protection ---

test('postForString rejects empty response', function () {
    $client = createSecurityTestClient([
        new Response(200, [], ''),
    ]);

    $client->createUserToken('user-hash');
})->throws(InvalidResponseException::class, 'Empty response');
