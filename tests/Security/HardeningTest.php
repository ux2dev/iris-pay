<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Iris;
use Ux2Dev\Iris\Resources\Agent;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\Resources\User\Agent as UserAgent;
use Ux2Dev\Iris\Webhook\WebhookParser;

function createSecurityAgentResource(array $responses): Agent
{
    $mock = new MockHandler($responses);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    return new Agent(
        new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $factory,
            $factory,
        ),
        new Credentials($config),
    );
}

function createSecurityPayByLinkResource(array $responses): PayByLink
{
    $mock = new MockHandler($responses);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, publicHash: 'test-public');

    return new PayByLink(
        new IrisTransport(
            $config->environment->payByLinkBaseUrl(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $factory,
            $factory,
        ),
        new Credentials($config),
        $config,
    );
}

function createSecurityUserAgentResource(array $responses, string $userHash = 'user-hash'): UserAgent
{
    $mock = new MockHandler($responses);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    return new UserAgent(
        new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $factory,
            $factory,
        ),
        new Credentials($config),
        $userHash,
    );
}

// --- Network Error Wrapping ---

test('Agent resource wraps network errors as NetworkException', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('GET', 'https://example.com')),
    ]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    $resource = new Agent(
        new IrisTransport($config->environment->webSdkBaseUrl(), $httpClient, $factory, $factory),
        new Credentials($config),
    );

    $resource->listUsers();
})->throws(NetworkException::class);

test('PayByLink resource wraps network errors as NetworkException', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('GET', 'https://example.com')),
    ]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, publicHash: 'test-public');

    $resource = new PayByLink(
        new IrisTransport($config->environment->payByLinkBaseUrl(), $httpClient, $factory, $factory),
        new Credentials($config),
        $config,
    );

    $resource->getBanks();
})->throws(NetworkException::class);

test('NetworkException preserves original exception', function () {
    $original = new ConnectException('DNS failure', new GuzzleRequest('GET', 'https://example.com'));
    $mock = new MockHandler([$original]);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    $resource = new Agent(
        new IrisTransport($config->environment->webSdkBaseUrl(), $httpClient, $factory, $factory),
        new Credentials($config),
    );

    try {
        $resource->listUsers();
    } catch (NetworkException $e) {
        expect($e->getPrevious())->toBe($original);
    }
});

// --- 4xx vs 5xx Split ---

test('4xx response throws ApiClientException', function () {
    $resource = createSecurityAgentResource([
        new Response(404, [], json_encode(['error' => 'Not Found'])),
    ]);

    $resource->listUsers();
})->throws(ApiClientException::class);

test('401 response throws ApiClientException', function () {
    $resource = createSecurityAgentResource([
        new Response(401, [], json_encode(['error' => 'Unauthorized'])),
    ]);

    $resource->listUsers();
})->throws(ApiClientException::class);

test('5xx response throws ApiServerException', function () {
    $resource = createSecurityAgentResource([
        new Response(503, [], json_encode(['error' => 'Service Unavailable'])),
    ]);

    $resource->listUsers();
})->throws(ApiServerException::class);

test('ApiClientException and ApiServerException extend InvalidResponseException', function () {
    expect(is_subclass_of(ApiClientException::class, InvalidResponseException::class))->toBeTrue();
    expect(is_subclass_of(ApiServerException::class, InvalidResponseException::class))->toBeTrue();
});

test('PayByLink resource throws ApiClientException on 400', function () {
    $resource = createSecurityPayByLinkResource([
        new Response(400, [], json_encode(['error' => 'Bad Request'])),
    ]);

    $resource->getBanks();
})->throws(ApiClientException::class);

test('PayByLink resource throws ApiServerException on 500', function () {
    $resource = createSecurityPayByLinkResource([
        new Response(500, [], json_encode(['error' => 'Internal Server Error'])),
    ]);

    $resource->getBanks();
})->throws(ApiServerException::class);

// --- Body Redaction ---

test('exception responseData does not contain body', function () {
    $resource = createSecurityAgentResource([
        new Response(400, [], json_encode(['iban' => 'BG12SENSITIVE', 'name' => 'Secret Person'])),
    ]);

    try {
        $resource->listUsers();
    } catch (ApiClientException $e) {
        expect($e->getResponseData())->not->toHaveKey('body');
        expect($e->getResponseData())->toHaveKey('status');
        expect($e->getResponseData()['status'])->toBe(400);
    }
});

// --- CRLF Header Injection Prevention ---
//
// Header-injection rejection for agentHash, adminHash, publicHash and
// userHash (including every credential regime and a bare \r) is fully unit
// tested at the Credentials layer in tests/Http/CredentialsTest.php. The one
// case kept here is an end-to-end check that a CRLF-bearing user hash is
// rejected before any request is made, going through Iris::user() so it
// also covers UserScope's constructor guard (which routes every
// user-scoped resource's hash through Credentials::user() once, up front).

test('Iris::user() rejects a CRLF-bearing userHash before any request is made', function () {
    $mock = new MockHandler([]);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    $iris = new Iris(
        $config,
        new Client(['handler' => HandlerStack::create($mock)]),
        $factory,
        $factory,
    );

    $iris->user("user-hash\r\nX-Evil: 1");
})->throws(ConfigurationException::class, 'userHash contains invalid characters');

// --- WebhookParser SDK Exceptions ---

test('WebhookParser throws InvalidResponseException on missing status', function () {
    WebhookParser::parse([]);
})->throws(InvalidResponseException::class, 'Missing status parameter');

test('WebhookParser throws InvalidResponseException on unknown status', function () {
    WebhookParser::parse(['status' => 'HACKED']);
})->throws(InvalidResponseException::class, 'Unknown payment status');

// --- Empty Response Protection ---

test('postForString rejects empty response', function () {
    $resource = createSecurityUserAgentResource([
        new Response(200, [], ''),
    ]);

    $resource->createToken();
})->throws(InvalidResponseException::class, 'Empty response');
