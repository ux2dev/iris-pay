# IRIS Pay Resource-Based Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the eight independently constructed IRIS clients with one `Iris` root object, one HTTP transport composed into thin resource classes, and a `user($hash)` scope — the architecture `ux2dev/prim` uses.

**Architecture:** `IrisTransport` handles HTTP/JSON/errors and knows nothing about credentials. `Credentials` produces auth headers and validates at point of use. `Iris` builds two transports (core + PayByLink) and exposes 7 root resources plus `user($hash)`, which returns a `UserScope` exposing 6 user-scoped resources. All 56 existing endpoint methods survive, verified by a manifest-driven coverage test.

**Tech Stack:** PHP 8.2, PSR-18 client, PSR-17 factories, Pest 4, Guzzle 7 (dev + optional runtime fallback), Laravel 11/12 via Orchestra Testbench 10.

**Spec:** `docs/superpowers/specs/2026-09-21-iris-resource-based-refactor-design.md`

## Global Constraints

- PHP `^8.2`. Every new file starts with `<?php`, a blank line, `declare(strict_types=1);`, a blank line, then `namespace`.
- Root namespace `Ux2Dev\Iris\`, tests `Ux2Dev\Iris\Tests\`.
- PSR-18 `ClientInterface` and PSR-17 `RequestFactoryInterface`/`StreamFactoryInterface` are injected by the caller. Never instantiate Guzzle outside `IrisManager`'s fallback.
- Guzzle must not become a `require` dependency. It stays in `require-dev` and `suggest`.
- All resource classes are `final` and extend `Ux2Dev\Iris\Resources\Resource` (root) or `Ux2Dev\Iris\Resources\User\UserResource` (user-scoped).
- Tests use Pest's `test()`/`expect()` with Guzzle `MockHandler`, matching the existing idiom in `tests/Api/PaymentClientTest.php`.
- No endpoint may be lost: 56 methods total, 23 root + 33 user-scoped.
- Commit messages are a single line, English, `type(scope): summary`, no body, no co-author trailer.
- Run the full suite with `./vendor/bin/pest`.

---

### Task 1: Repository hygiene

Nothing is tracked in git except `LICENSE`, and there is no `.gitignore`. Any commit made before this task would add `vendor/` (50 packages) and a 356 KB `composer.lock`. This runs first for that reason.

**Files:**
- Create: `.gitignore`
- Modify: `composer.json` (the `suggest` block)

**Interfaces:**
- Consumes: nothing
- Produces: nothing consumed by later tasks; unblocks committing them

- [ ] **Step 1: Create `.gitignore`**

```gitignore
/vendor/
composer.lock
.phpunit.cache/
.phpunit.result.cache
.DS_Store
```

- [ ] **Step 2: Verify the ignore rules match**

Run: `git check-ignore -v vendor/autoload.php composer.lock`
Expected: two lines, each naming `.gitignore` as the source.

- [ ] **Step 3: Add the Guzzle suggestion**

In `composer.json`, replace the `suggest` block:

```json
    "suggest": {
        "guzzlehttp/guzzle": "Supplies PSR-18 client + PSR-17 factories out of the box",
        "illuminate/support": "^11.0|^12.0"
    },
```

- [ ] **Step 4: Verify composer.json is still valid**

Run: `composer validate --no-check-publish`
Expected: `./composer.json is valid`

- [ ] **Step 5: Commit**

```bash
git add .gitignore composer.json
git commit -m "chore: add gitignore and suggest guzzle"
```

- [ ] **Step 6: Commit the existing untracked source**

The working tree holds 111 source files and 20 test files that have never been committed. Commit them now as the pre-refactor baseline, so every later task has a diff to review against.

```bash
git add composer.json phpunit.xml src tests docs
git commit -m "chore: commit pre-refactor baseline"
```

- [ ] **Step 7: Verify vendor stayed out**

Run: `git ls-files | grep -c '^vendor/'`
Expected: `0`

---

### Task 2: MerchantConfig timeout

**Files:**
- Modify: `src/Config/MerchantConfig.php`
- Test: `tests/Config/MerchantConfigTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `MerchantConfig::__construct(Environment $environment, ?string $publicHash = null, ?string $agentHash = null, ?string $adminHash = null, Currency $currency = Currency::EUR, Language $language = Language::Bulgarian, int $timeout = 30)`, with public readonly `int $timeout`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Config/MerchantConfigTest.php`:

```php
test('defaults timeout to 30 seconds', function () {
    $config = new MerchantConfig(environment: Environment::Development, publicHash: 'p');

    expect($config->timeout)->toBe(30);
});

test('accepts a custom timeout', function () {
    $config = new MerchantConfig(environment: Environment::Development, publicHash: 'p', timeout: 5);

    expect($config->timeout)->toBe(5);
});

test('rejects a timeout below one second', function () {
    expect(fn () => new MerchantConfig(environment: Environment::Development, publicHash: 'p', timeout: 0))
        ->toThrow(ConfigurationException::class, 'timeout must be at least 1 second');
});

test('allows a config with no credentials at all', function () {
    $config = new MerchantConfig(environment: Environment::Development);

    expect($config->publicHash)->toBeNull()
        ->and($config->agentHash)->toBeNull();
});

test('redacts the timeout-free secrets in debug output', function () {
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'secret');

    expect($config->__debugInfo()['agentHash'])->toBe('[REDACTED]');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Config/MerchantConfigTest.php`
Expected: FAIL — `timeout` is an unknown named argument, and the no-credentials case throws `ConfigurationException`.

- [ ] **Step 3: Implement**

In `src/Config/MerchantConfig.php`, add `public int $timeout = 30` as the last constructor parameter, delete the `$publicHash === null && $agentHash === null` cross-check, and add the timeout guard. The constructor body becomes:

```php
    public function __construct(
        public Environment $environment,
        public ?string $publicHash = null,
        public ?string $agentHash = null,
        public ?string $adminHash = null,
        public Currency $currency = Currency::EUR,
        public Language $language = Language::Bulgarian,
        public int $timeout = 30,
    ) {
        if ($publicHash !== null && $publicHash === '') {
            throw new ConfigurationException('publicHash must not be empty when provided');
        }
        if ($agentHash !== null && $agentHash === '') {
            throw new ConfigurationException('agentHash must not be empty when provided');
        }
        if ($adminHash !== null && $adminHash === '') {
            throw new ConfigurationException('adminHash must not be empty when provided');
        }
        if ($timeout < 1) {
            throw new ConfigurationException('timeout must be at least 1 second');
        }
    }
```

Add `'timeout' => $this->timeout,` to the array returned by `__debugInfo()`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Config/MerchantConfigTest.php`
Expected: PASS

- [ ] **Step 5: Remove the now-obsolete cross-check test**

Search `tests/Config/MerchantConfigTest.php` for a test asserting that a config with neither `publicHash` nor `agentHash` throws. Delete that test — the behaviour is intentionally gone.

Run: `./vendor/bin/pest`
Expected: PASS, full suite.

- [ ] **Step 6: Commit**

```bash
git add src/Config/MerchantConfig.php tests/Config/MerchantConfigTest.php
git commit -m "feat(config): add timeout and drop credential cross-check"
```

---

### Task 3: IrisTransport

**Files:**
- Create: `src/Http/IrisTransport.php`
- Test: `tests/Http/IrisTransportTest.php`

**Interfaces:**
- Consumes: the existing exceptions in `src/Exception/`.
- Produces:

```php
final class Ux2Dev\Iris\Http\IrisTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    /** @param array<string,string> $headers @param array<string,mixed> $query @return array<string,mixed> */
    public function get(string $path, array $headers = [], array $query = []): array
    /** @param array<string,string> $headers */
    public function getRaw(string $path, array $headers = []): string
    /** @param array<string,mixed> $body @param array<string,string> $headers @return array<string,mixed> */
    public function post(string $path, array $body, array $headers = []): array
    /** @param array<string,string> $headers @return array<string,mixed> */
    public function postEmpty(string $path, array $headers = []): array
    /** @param array<string,mixed> $body @param array<string,string> $headers */
    public function postVoid(string $path, array $body, array $headers = []): void
    /** @param array<string,string> $headers */
    public function postForString(string $path, array $headers = []): string
    /** @param array<string,mixed> $body @param array<string,string> $headers @param array<string,mixed> $query @return array<string,mixed> */
    public function put(string $path, array $body = [], array $headers = [], array $query = []): array
    /** @param array<string,mixed> $body @param array<string,string> $headers @param array<string,mixed> $query */
    public function putVoid(string $path, array $body = [], array $headers = [], array $query = []): void
    /** @param array<string,string> $headers */
    public function delete(string $path, array $headers = []): void
}
```

`getRaw()` sends `Accept: */*` and returns the response body untouched — it serves the PayByLink QR endpoint, which returns image bytes rather than JSON. `postForString()` sends `Accept: */*`, trims surrounding double quotes, and rejects an empty result; it serves `/api/8/usertoken`. `putVoid()` takes a body because PayByLink's deactivate endpoint sends one.

- [ ] **Step 1: Write the failing tests**

Create `tests/Http/IrisTransportTest.php`:

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;
use Ux2Dev\Iris\Http\IrisTransport;

function makeTransport(array $responses, ?array &$container = null): IrisTransport
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    if ($container !== null) {
        $stack->push(\GuzzleHttp\Middleware::history($container));
    }
    $factory = new HttpFactory();

    return new IrisTransport(
        'https://api.example.test',
        new Client(['handler' => $stack]),
        $factory,
        $factory,
    );
}

test('get decodes a JSON object', function () {
    $transport = makeTransport([new Response(200, [], json_encode(['a' => 1]))]);

    expect($transport->get('/thing'))->toBe(['a' => 1]);
});

test('get appends the query string and sends headers', function () {
    $history = [];
    $transport = makeTransport([new Response(200, [], '{}')], $history);

    $transport->get('/thing', ['x-agent-hash' => 'agent'], ['page' => 2]);

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toBe('https://api.example.test/thing?page=2')
        ->and($request->getHeaderLine('x-agent-hash'))->toBe('agent');
});

test('getRaw returns the body untouched', function () {
    $transport = makeTransport([new Response(200, [], "\x89PNG\r\n")]);

    expect($transport->getRaw('/qr/abc'))->toBe("\x89PNG\r\n");
});

test('post sends a JSON body', function () {
    $history = [];
    $transport = makeTransport([new Response(200, [], '{"ok":true}')], $history);

    $result = $transport->post('/thing', ['sum' => 10]);

    expect($result)->toBe(['ok' => true])
        ->and((string) $history[0]['request']->getBody())->toBe('{"sum":10}')
        ->and($history[0]['request']->getHeaderLine('Content-Type'))->toBe('application/json');
});

test('postForString trims surrounding quotes', function () {
    $transport = makeTransport([new Response(200, [], '"token-value"')]);

    expect($transport->postForString('/usertoken'))->toBe('token-value');
});

test('postForString rejects an empty body', function () {
    $transport = makeTransport([new Response(200, [], '""')]);

    expect(fn () => $transport->postForString('/usertoken'))
        ->toThrow(InvalidResponseException::class, 'Empty response from IRIS API');
});

test('putVoid sends a body and returns nothing', function () {
    $history = [];
    $transport = makeTransport([new Response(204)], $history);

    $transport->putVoid('/inactive', ['paymentHash' => 'h']);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getBody())->toBe('{"paymentHash":"h"}');
});

test('delete issues a DELETE', function () {
    $history = [];
    $transport = makeTransport([new Response(204)], $history);

    $transport->delete('/iban/1');

    expect($history[0]['request']->getMethod())->toBe('DELETE');
});

test('maps 4xx to ApiClientException', function () {
    $transport = makeTransport([new Response(422, [], '{}')]);

    expect(fn () => $transport->get('/thing'))
        ->toThrow(ApiClientException::class, 'HTTP 422 from IRIS API');
});

test('maps 5xx to ApiServerException', function () {
    $transport = makeTransport([new Response(503, [], '{}')]);

    expect(fn () => $transport->get('/thing'))
        ->toThrow(ApiServerException::class, 'HTTP 503 from IRIS API');
});

test('maps transport failures to NetworkException', function () {
    $transport = makeTransport([
        new ConnectException('refused', new GuzzleRequest('GET', 'https://api.example.test')),
    ]);

    expect(fn () => $transport->get('/thing'))->toThrow(NetworkException::class);
});

test('rejects a non-JSON body', function () {
    $transport = makeTransport([new Response(200, [], 'not json')]);

    expect(fn () => $transport->get('/thing'))->toThrow(InvalidResponseException::class);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Http/IrisTransportTest.php`
Expected: FAIL — `Class "Ux2Dev\Iris\Http\IrisTransport" not found`.

- [ ] **Step 3: Implement**

Create `src/Http/IrisTransport.php`. The private helpers `send()`, `parseJsonResponse()`, `assertSuccess()`, `throwForStatus()` and `buildUrl()` are lifted verbatim from `src/Api/BaseClient.php:212-266`, with `$this->baseUrl()` replaced by `$this->baseUrl`.

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;

/**
 * Low-level HTTP transport for one IRIS host. Resources build the path,
 * body and auth headers and call one of the verb methods. This class knows
 * nothing about credentials.
 */
final class IrisTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $headers = [], array $query = []): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->buildUrl($path, $query))
            ->withHeader('Accept', 'application/json');

        return $this->parseJsonResponse($this->send($this->withHeaders($request, $headers)));
    }

    /** @param array<string, string> $headers */
    public function getRaw(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $path)
            ->withHeader('Accept', '*/*');

        $response = $this->send($this->withHeaders($request, $headers));
        $this->assertSuccess($response);

        return (string) $response->getBody();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function post(string $path, array $body, array $headers = []): array
    {
        return $this->parseJsonResponse($this->send($this->jsonRequest('POST', $path, $body, $headers)));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function postEmpty(string $path, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        return $this->parseJsonResponse($this->send($this->withHeaders($request, $headers)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function postVoid(string $path, array $body, array $headers = []): void
    {
        $this->assertSuccess($this->send($this->jsonRequest('POST', $path, $body, $headers)));
    }

    /** @param array<string, string> $headers */
    public function postForString(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Accept', '*/*');

        $response = $this->send($this->withHeaders($request, $headers));
        $this->assertSuccess($response);

        $value = trim((string) $response->getBody(), '"');

        if ($value === '') {
            throw new InvalidResponseException('Empty response from IRIS API');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = [], array $headers = [], array $query = []): array
    {
        return $this->parseJsonResponse($this->send($this->jsonRequest('PUT', $path, $body, $headers, $query)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    public function putVoid(string $path, array $body = [], array $headers = [], array $query = []): void
    {
        $this->assertSuccess($this->send($this->jsonRequest('PUT', $path, $body, $headers, $query)));
    }

    /** @param array<string, string> $headers */
    public function delete(string $path, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        $this->assertSuccess($this->send($this->withHeaders($request, $headers)));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    private function jsonRequest(
        string $method,
        string $path,
        array $body,
        array $headers,
        array $query = [],
    ): RequestInterface {
        $request = $this->requestFactory->createRequest($method, $this->buildUrl($path, $query))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        if ($body !== []) {
            $request = $request->withBody(
                $this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)),
            );
        }

        return $this->withHeaders($request, $headers);
    }

    /** @param array<string, string> $headers */
    private function withHeaders(RequestInterface $request, array $headers): RequestInterface
    {
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Network error communicating with IRIS API', 0, $e);
        }
    }

    /** @param array<string, mixed> $query */
    private function buildUrl(string $path, array $query = []): string
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $this->assertSuccess($response);

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data)) {
            throw new InvalidResponseException(
                'Invalid JSON response from IRIS API',
                ['status' => $response->getStatusCode()],
            );
        }

        return $data;
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->throwForStatus($statusCode);
        }
    }

    private function throwForStatus(int $statusCode): never
    {
        $data = ['status' => $statusCode];

        if ($statusCode >= 400 && $statusCode < 500) {
            throw new ApiClientException("HTTP {$statusCode} from IRIS API", $data);
        }

        throw new ApiServerException("HTTP {$statusCode} from IRIS API", $data);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Http/IrisTransportTest.php`
Expected: PASS, 12 tests.

- [ ] **Step 5: Commit**

```bash
git add src/Http/IrisTransport.php tests/Http/IrisTransportTest.php
git commit -m "feat(http): add IrisTransport"
```

---

### Task 4: Credentials

**Files:**
- Create: `src/Http/Credentials.php`
- Test: `tests/Http/CredentialsTest.php`

**Interfaces:**
- Consumes: `MerchantConfig` from Task 2.
- Produces:

```php
final class Ux2Dev\Iris\Http\Credentials
{
    public function __construct(private readonly MerchantConfig $config) {}

    /** @return array<string,string> */ public function agent(): array
    /** @return array<string,string> */ public function user(string $userHash): array
    /** @return array<string,string> */ public function both(string $userHash): array
    /** @return array<string,string> */ public function admin(): array
    /** @return array<string,string> */ public function adminAgent(): array
    public function publicHash(): string
}
```

Every accessor throws `ConfigurationException` when its hash is null or contains CR or LF. This replaces `BaseClient::validateHeaderValue()`, which only guarded `agentHash`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Http/CredentialsTest.php`:

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Http\Credentials;

function credentialsFor(?string $agent = null, ?string $admin = null, ?string $public = null): Credentials
{
    return new Credentials(new MerchantConfig(
        environment: Environment::Development,
        publicHash: $public,
        agentHash: $agent,
        adminHash: $admin,
    ));
}

test('agent returns the agent header', function () {
    expect(credentialsFor(agent: 'a1')->agent())->toBe(['x-agent-hash' => 'a1']);
});

test('user returns the user header', function () {
    expect(credentialsFor(agent: 'a1')->user('u1'))->toBe(['x-user-hash' => 'u1']);
});

test('both returns agent and user headers', function () {
    expect(credentialsFor(agent: 'a1')->both('u1'))
        ->toBe(['x-agent-hash' => 'a1', 'x-user-hash' => 'u1']);
});

test('admin returns the admin header', function () {
    expect(credentialsFor(admin: 'ad1')->admin())->toBe(['x-admin-hash' => 'ad1']);
});

test('adminAgent returns admin and agent headers', function () {
    expect(credentialsFor(agent: 'a1', admin: 'ad1')->adminAgent())
        ->toBe(['x-admin-hash' => 'ad1', 'x-agent-hash' => 'a1']);
});

test('publicHash returns the public hash', function () {
    expect(credentialsFor(public: 'p1')->publicHash())->toBe('p1');
});

test('agent throws when agentHash is missing', function () {
    expect(fn () => credentialsFor(public: 'p1')->agent())
        ->toThrow(ConfigurationException::class, 'agentHash is required');
});

test('admin throws when adminHash is missing', function () {
    expect(fn () => credentialsFor(agent: 'a1')->admin())
        ->toThrow(ConfigurationException::class, 'adminHash is required');
});

test('publicHash throws when publicHash is missing', function () {
    expect(fn () => credentialsFor(agent: 'a1')->publicHash())
        ->toThrow(ConfigurationException::class, 'publicHash is required');
});

test('rejects a userHash containing a newline', function () {
    expect(fn () => credentialsFor(agent: 'a1')->user("u1\r\nX-Evil: 1"))
        ->toThrow(ConfigurationException::class, 'userHash contains invalid characters');
});

test('rejects an agentHash containing a newline', function () {
    expect(fn () => credentialsFor(agent: "a1\nX-Evil: 1")->agent())
        ->toThrow(ConfigurationException::class, 'agentHash contains invalid characters');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Http/CredentialsTest.php`
Expected: FAIL — `Class "Ux2Dev\Iris\Http\Credentials" not found`.

- [ ] **Step 3: Implement**

Create `src/Http/Credentials.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Http;

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Exception\ConfigurationException;

/**
 * Produces IRIS auth headers from merchant configuration. Each accessor
 * validates the credential it needs at the moment it is asked for, so
 * endpoints that take no auth stay callable on a config that holds none.
 */
final class Credentials
{
    public function __construct(private readonly MerchantConfig $config)
    {
    }

    /** @return array<string, string> */
    public function agent(): array
    {
        return ['x-agent-hash' => $this->require($this->config->agentHash, 'agentHash')];
    }

    /** @return array<string, string> */
    public function user(string $userHash): array
    {
        return ['x-user-hash' => $this->require($userHash, 'userHash')];
    }

    /** @return array<string, string> */
    public function both(string $userHash): array
    {
        return array_merge($this->agent(), $this->user($userHash));
    }

    /** @return array<string, string> */
    public function admin(): array
    {
        return ['x-admin-hash' => $this->require($this->config->adminHash, 'adminHash')];
    }

    /** @return array<string, string> */
    public function adminAgent(): array
    {
        return array_merge($this->admin(), $this->agent());
    }

    public function publicHash(): string
    {
        return $this->require($this->config->publicHash, 'publicHash');
    }

    private function require(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            throw new ConfigurationException("{$name} is required for this operation");
        }

        if (preg_match('/[\r\n]/', $value)) {
            throw new ConfigurationException("{$name} contains invalid characters");
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Http/CredentialsTest.php`
Expected: PASS, 11 tests.

- [ ] **Step 5: Commit**

```bash
git add src/Http/Credentials.php tests/Http/CredentialsTest.php
git commit -m "feat(http): add Credentials with point-of-use validation"
```

---

### Task 5: Resource base classes and the method manifest

The manifest is the safeguard against silently dropping an endpoint across 13 new files. It lists all 56 methods up front. The coverage test checks every manifest row whose resource class already exists, so it stays green while the resources land one task at a time; Task 13 adds the assertion that all 13 classes exist, which turns it into a full gate.

**Files:**
- Create: `src/Resources/Resource.php`
- Create: `src/Resources/User/UserResource.php`
- Create: `tests/Resources/method-manifest.php`
- Test: `tests/Resources/MethodCoverageTest.php`

**Interfaces:**
- Consumes: `IrisTransport` (Task 3), `Credentials` (Task 4).
- Produces:

```php
abstract class Ux2Dev\Iris\Resources\Resource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
    ) {}
}

abstract class Ux2Dev\Iris\Resources\User\UserResource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
        protected readonly string $userHash,
    ) {}
}
```

- [ ] **Step 1: Create the two base classes**

`src/Resources/Resource.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;

abstract class Resource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
    ) {
    }
}
```

`src/Resources/User/UserResource.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;

abstract class UserResource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
        protected readonly string $userHash,
    ) {
    }
}
```

- [ ] **Step 2: Create the manifest**

`tests/Resources/method-manifest.php` returns a list of `[class, method, requiredParameterCount]`. Required parameters exclude optional ones.

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Resources\Accounts;
use Ux2Dev\Iris\Resources\Agent;
use Ux2Dev\Iris\Resources\BulkPayments;
use Ux2Dev\Iris\Resources\ConsentGate;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\Resources\Payments;
use Ux2Dev\Iris\Resources\Reports;
use Ux2Dev\Iris\Resources\User\Accounts as UserAccounts;
use Ux2Dev\Iris\Resources\User\Agent as UserAgent;
use Ux2Dev\Iris\Resources\User\BulkPayments as UserBulkPayments;
use Ux2Dev\Iris\Resources\User\ConsentGate as UserConsentGate;
use Ux2Dev\Iris\Resources\User\Payments as UserPayments;
use Ux2Dev\Iris\Resources\User\Reports as UserReports;

return [
    // --- Root: PayByLink (6) ---
    [PayByLink::class, 'getBanks', 0],
    [PayByLink::class, 'createLink', 5],
    [PayByLink::class, 'getQrCode', 1],
    [PayByLink::class, 'getStatus', 1],
    [PayByLink::class, 'refund', 5],
    [PayByLink::class, 'deactivate', 1],

    // --- Root: Agent (7) ---
    [Agent::class, 'signup', 1],
    [Agent::class, 'signupAgent', 1],
    [Agent::class, 'createHook', 1],
    [Agent::class, 'addRedirectToHook', 2],
    [Agent::class, 'listUsers', 0],
    [Agent::class, 'checkUserByEmail', 1],
    [Agent::class, 'updateKyc', 2],

    // --- Root: Accounts (1) ---
    [Accounts::class, 'getConsentDetails', 1],

    // --- Root: Payments (1) ---
    [Payments::class, 'statusByHook', 1],

    // --- Root: BulkPayments (2) ---
    [BulkPayments::class, 'status', 1],
    [BulkPayments::class, 'search', 1],

    // --- Root: Reports (4) ---
    [Reports::class, 'searchPayments', 1],
    [Reports::class, 'activeUsers', 2],
    [Reports::class, 'activeUsersDetails', 1],
    [Reports::class, 'bankMaintenance', 0],

    // --- Root: ConsentGate (2) ---
    [ConsentGate::class, 'createRequest', 1],
    [ConsentGate::class, 'getConsents', 0],

    // --- User: Accounts (12) ---
    [UserAccounts::class, 'listBanks', 0],
    [UserAccounts::class, 'getBank', 1],
    [UserAccounts::class, 'getBankSca', 1],
    [UserAccounts::class, 'listIbans', 0],
    [UserAccounts::class, 'deleteIban', 1],
    [UserAccounts::class, 'getBalance', 1],
    [UserAccounts::class, 'listTransactions', 1],
    [UserAccounts::class, 'listPagedTransactions', 1],
    [UserAccounts::class, 'getTransaction', 2],
    [UserAccounts::class, 'listTokens', 0],
    [UserAccounts::class, 'createConsent', 1],
    [UserAccounts::class, 'getConsents', 1],

    // --- User: Payments (11) ---
    [UserPayments::class, 'createDirect', 1],
    [UserPayments::class, 'createDirectInitiate', 1],
    [UserPayments::class, 'createIban', 1],
    [UserPayments::class, 'confirm', 2],
    [UserPayments::class, 'confirmWithResult', 2],
    [UserPayments::class, 'confirmSms', 3],
    [UserPayments::class, 'statusByCode', 1],
    [UserPayments::class, 'authorization', 1],
    [UserPayments::class, 'sca', 1],
    [UserPayments::class, 'createBudget', 1],
    [UserPayments::class, 'createBudgetDirect', 1],

    // --- User: BulkPayments (4) ---
    [UserBulkPayments::class, 'create', 1],
    [UserBulkPayments::class, 'createIban', 1],
    [UserBulkPayments::class, 'createBudget', 1],
    [UserBulkPayments::class, 'createBudgetIban', 1],

    // --- User: Agent (4) ---
    [UserAgent::class, 'createToken', 0],
    [UserAgent::class, 'delete', 0],
    [UserAgent::class, 'sendAisEmail', 3],
    [UserAgent::class, 'kycStatus', 0],

    // --- User: Reports (1) ---
    [UserReports::class, 'listPayments', 0],

    // --- User: ConsentGate (1) ---
    [UserConsentGate::class, 'uiConsentRequest', 0],
];
```

- [ ] **Step 3: Write the coverage test**

`tests/Resources/MethodCoverageTest.php`:

```php
<?php

declare(strict_types=1);

$manifest = require __DIR__ . '/method-manifest.php';

test('the manifest covers all 56 endpoint methods', function () use ($manifest) {
    expect($manifest)->toHaveCount(56);
});

test('every implemented resource exposes its manifest methods', function () use ($manifest) {
    $checked = 0;

    foreach ($manifest as [$class, $method, $requiredParams]) {
        if (! class_exists($class)) {
            continue;
        }

        expect(method_exists($class, $method))->toBeTrue(
            "{$class}::{$method}() is missing",
        );

        $reflection = new ReflectionMethod($class, $method);
        expect($reflection->isPublic())->toBeTrue("{$class}::{$method}() must be public");
        expect($reflection->getNumberOfRequiredParameters())->toBe(
            $requiredParams,
            "{$class}::{$method}() should take {$requiredParams} required parameters",
        );

        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/MethodCoverageTest.php`
Expected: PASS, 2 tests. No resource classes exist yet, so the second test checks nothing — that is intended; Task 13 tightens it.

- [ ] **Step 5: Commit**

```bash
git add src/Resources tests/Resources
git commit -m "feat(resources): add resource base classes and method manifest"
```

---

### Task 6: PayByLink resource

**Files:**
- Create: `src/Resources/PayByLink.php`
- Test: `tests/Resources/PayByLinkTest.php`
- Reference (do not modify yet): `src/PayByLink/PayByLinkClient.php:41-283`

**Interfaces:**
- Consumes: `Resource` (Task 5), `Credentials::publicHash()` (Task 4), `IrisTransport::get/getRaw/post/putVoid` (Task 3).
- Produces:

```php
final class Ux2Dev\Iris\Resources\PayByLink extends Resource
{
    /** @return Bank[] */
    public function getBanks(): array
    /** @param string[]|null $bankHashes */
    public function createLink(float $sum, string $description, string $toIban, string $hookUrl, string $redirectUrl, ?array $bankHashes = null, ?string $name = null, ?string $orderId = null, ?Currency $currency = null, ?Language $lang = null, bool $repayable = false): PaymentLinkResponse
    public function getQrCode(string $paymentHash): string
    public function getStatus(string $paymentHash): PaymentStatusResponse
    public function refund(string $paymentHash, RefundType $refundType, float $sum, string $remittanceDescription, string $webhookUrl, ?string $psuId = null): RefundResponse
    public function deactivate(string $paymentHash): void
}
```

This resource is constructed with the **PayByLink** transport, not the core one. Response DTOs stay where they are, in `Ux2Dev\Iris\PayByLink\Response\`.

Two behaviour changes, both deliberate: the bespoke error strings `"HTTP {n} from QR code endpoint"` and `"HTTP {n} from deactivate endpoint"` become the transport's uniform `"HTTP {n} from IRIS API"`, and the `currency`/`lang` defaults now come from the config passed into `Credentials`. To keep the config reachable, this resource also needs the `MerchantConfig`; give it a constructor of its own.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/PayByLinkTest.php`:

```php
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
        new Response(200, [], json_encode(['paymentHash' => 'ph-1', 'url' => 'https://pay.test/ph-1'])),
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
        new Response(200, [], json_encode(['refundHash' => 'rh-1'])),
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
```

Before running, confirm the `RefundType` case name: `grep -n 'case ' src/Enum/RefundType.php`. Use whichever case exists in place of `RefundType::Full`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/PayByLinkTest.php`
Expected: FAIL — `Class "Ux2Dev\Iris\Resources\PayByLink" not found`.

- [ ] **Step 3: Implement**

Create `src/Resources/PayByLink.php`. Copy the three validators (`validateSum`, `validateDescription`, `validateHttpsUrl`) verbatim from `src/PayByLink/PayByLinkClient.php:255-283`, and the `createLink`/`refund` body assembly from lines 51-99 and 134-160.

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

final class PayByLink extends Resource
{
    public function __construct(
        IrisTransport $transport,
        Credentials $credentials,
        private readonly MerchantConfig $config,
    ) {
        parent::__construct($transport, $credentials);
    }

    /** @return Bank[] */
    public function getBanks(): array
    {
        $data = $this->transport->get('/backend/payment/banks/' . $this->credentials->publicHash());

        return array_map(fn (array $bank) => Bank::fromArray($bank), $data);
    }

    /** @param string[]|null $bankHashes */
    public function createLink(
        float $sum,
        string $description,
        string $toIban,
        string $hookUrl,
        string $redirectUrl,
        ?array $bankHashes = null,
        ?string $name = null,
        ?string $orderId = null,
        ?Currency $currency = null,
        ?Language $lang = null,
        bool $repayable = false,
    ): PaymentLinkResponse {
        $this->validateSum($sum);
        $this->validateDescription($description);
        $this->validateHttpsUrl($hookUrl, 'hookUrl');
        $this->validateHttpsUrl($redirectUrl, 'redirectUrl');

        if ($name !== null && mb_strlen($name) > 34) {
            throw new ConfigurationException('name must not exceed 34 characters');
        }

        $body = [
            'currency' => ($currency ?? $this->config->currency)->value,
            'description' => $description,
            'hookUrl' => $hookUrl,
            'redirectUrl' => $redirectUrl,
            'sum' => $sum,
            'toIban' => $toIban,
            'repayable' => $repayable,
        ];

        if ($bankHashes !== null) {
            $body['bankHashes'] = $bankHashes;
        }
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($orderId !== null) {
            $body['orderId'] = $orderId;
        }
        if ($lang !== null) {
            $body['lang'] = $lang->value;
        }

        $data = $this->transport->post(
            '/backend/payment/external/' . $this->credentials->publicHash(),
            $body,
        );

        return PaymentLinkResponse::fromArray($data);
    }

    public function getQrCode(string $paymentHash): string
    {
        return $this->transport->getRaw("/backend/payment/qr/{$paymentHash}");
    }

    public function getStatus(string $paymentHash): PaymentStatusResponse
    {
        $data = $this->transport->get("/backend/payment/status/{$paymentHash}");

        return PaymentStatusResponse::fromArray($data);
    }

    public function refund(
        string $paymentHash,
        RefundType $refundType,
        float $sum,
        string $remittanceDescription,
        string $webhookUrl,
        ?string $psuId = null,
    ): RefundResponse {
        $this->validateSum($sum);
        $this->validateHttpsUrl($webhookUrl, 'webhookUrl');

        $body = [
            'paymentHash' => $paymentHash,
            'refundType' => $refundType->value,
            'remittanceDescription' => $remittanceDescription,
            'sum' => $sum,
            'webhookUrl' => $webhookUrl,
        ];

        if ($psuId !== null) {
            $body['psuId'] = $psuId;
        }

        $data = $this->transport->post(
            '/backend/payment/external/refund/' . $this->credentials->publicHash(),
            $body,
        );

        return RefundResponse::fromArray($data);
    }

    public function deactivate(string $paymentHash): void
    {
        $this->transport->putVoid(
            '/backend/payment/inactive/' . $this->credentials->publicHash(),
            ['paymentHash' => $paymentHash],
        );
    }

    private function validateSum(float $sum): void
    {
        if ($sum <= 0) {
            throw new ConfigurationException('sum must be greater than 0');
        }
    }

    private function validateDescription(string $description): void
    {
        if ($description === '') {
            throw new ConfigurationException('description must not be empty');
        }
        if (mb_strlen($description) > 240) {
            throw new ConfigurationException('description must not exceed 240 characters');
        }
    }

    private function validateHttpsUrl(string $url, string $field): void
    {
        if (!str_starts_with($url, 'https://')) {
            throw new ConfigurationException("{$field} must use https://");
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/PayByLinkTest.php tests/Resources/MethodCoverageTest.php`
Expected: PASS. The coverage test now checks PayByLink's six rows.

- [ ] **Step 5: Commit**

```bash
git add src/Resources/PayByLink.php tests/Resources/PayByLinkTest.php
git commit -m "feat(resources): add PayByLink resource"
```

---

### Task 7: Agent root resource

**Files:**
- Create: `src/Resources/Agent.php`
- Test: `tests/Resources/AgentTest.php`
- Reference: `src/Api/AgentClient.php:17-93`

**Interfaces:**
- Consumes: `Resource` (Task 5).
- Produces:

```php
final class Ux2Dev\Iris\Resources\Agent extends Resource
{
    public function signup(SignupData $data): IdentificationAccount
    public function signupAgent(SignupAgentData $data): IdentificationAccount
    public function createHook(CreateHookData $data): Hook
    public function addRedirectToHook(string $hookHash, string $redirectUrl): void
    public function listUsers(int $page = 0, int $size = 30, ?string $dateFrom = null, ?string $dateTo = null, ?string $text = null): UsersList
    public function checkUserByEmail(string $email): EmailAccount
    public function updateKyc(string $submissionId, string $status): void
}
```

Per-method specification. All paths are on the core transport.

| Method | Transport call | Old source |
|---|---|---|
| `signup` | `post('/api/8/signup', $data->toArray())` → `IdentificationAccount::fromArray` | `AgentClient.php:17-21` |
| `signupAgent` | `post('/api/8/signup/agent', $data->toArray())` → `IdentificationAccount::fromArray` | `AgentClient.php:23-27` |
| `createHook` | `post('/api/8/createhook', $data->toArray())` → `Hook::fromArray` | `AgentClient.php:29-33` |
| `addRedirectToHook` | `putVoid('/api/8/redirect', [], [], ['hookhash' => $hookHash, 'redirectUrl' => $redirectUrl])` | `AgentClient.php:76-79` |
| `listUsers` | `get('/api/8/agent/users', $this->credentials->agent(), $query)` → `UsersList::fromArray` | `AgentClient.php:45-59` |
| `checkUserByEmail` | `post('/api/8/agent/user/check', ['email' => $email], $this->credentials->agent())` → `EmailAccount::fromArray` | `AgentClient.php:61-65` |
| `updateKyc` | `postVoid('/api/8/kyc/update', ['submissionId' => $submissionId, 'status' => $status], $this->credentials->agent())` | `AgentClient.php:87-93` |

`listUsers` builds its query exactly as the original does: `['page' => $page, 'size' => $size]`, then `dateFrom`, `dateTo`, `text` each appended only when not null.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/AgentTest.php`. Use this helper and these seven tests:

```php
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
        new Response(200, [], json_encode(['content' => [], 'totalElements' => 0])),
    ], $history);

    $resource->listUsers(page: 2, size: 10, text: 'ivan');

    $request = $history[0]['request'];
    expect($request->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($request->getUri()->getQuery())->toBe('page=2&size=10&text=ivan');
});

test('checkUserByEmail posts the email with the agent header', function () {
    $history = [];
    $resource = agentResource([
        new Response(200, [], json_encode(['exists' => true])),
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
    $resource = agentResource([new Response(200, [], '{}')], $history);

    $resource->signup(new \Ux2Dev\Iris\Api\Request\SignupData(email: 'a@b.test'));

    expect($history[0]['request']->hasHeader('x-agent-hash'))->toBeFalse()
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/signup');
});

test('createHook posts to the createhook path', function () {
    $history = [];
    $resource = agentResource([new Response(200, [], '{}')], $history);

    $resource->createHook(new \Ux2Dev\Iris\Api\Request\CreateHookData(hookUrl: 'https://shop.test/hook'));

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/8/createhook');
});

test('signupAgent posts to the agent signup path', function () {
    $history = [];
    $resource = agentResource([new Response(200, [], '{}')], $history);

    $resource->signupAgent(new \Ux2Dev\Iris\Api\Request\SignupAgentData(email: 'a@b.test'));

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/8/signup/agent');
});
```

Before running, check the real constructor signatures with `grep -A12 'public function __construct' src/Api/Request/SignupData.php src/Api/Request/SignupAgentData.php src/Api/Request/CreateHookData.php` and supply whichever required arguments they declare. Mirror the argument values already used in `tests/Api/AgentClientTest.php`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/AgentTest.php`
Expected: FAIL — `Class "Ux2Dev\Iris\Resources\Agent" not found`.

- [ ] **Step 3: Implement**

Create `src/Resources/Agent.php` following the per-method table above. Import the request DTOs from `Ux2Dev\Iris\Api\Request\` and the response DTOs from `Ux2Dev\Iris\Api\Response\`, exactly as `src/Api/AgentClient.php:1-14` does.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/AgentTest.php tests/Resources/MethodCoverageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Resources/Agent.php tests/Resources/AgentTest.php
git commit -m "feat(resources): add Agent resource"
```

---

### Task 8: Thin root resources

Five resources holding ten methods between them. They land together because none is large enough to review on its own.

**Files:**
- Create: `src/Resources/Accounts.php`, `src/Resources/Payments.php`, `src/Resources/BulkPayments.php`, `src/Resources/Reports.php`, `src/Resources/ConsentGate.php`
- Test: `tests/Resources/RootResourcesTest.php`
- Reference: `src/Api/AccountClient.php:139-144`, `src/Api/PaymentClient.php:60-65`, `src/Api/BulkPaymentClient.php:46-58`, `src/Api/ReportClient.php:31-68`, `src/Api/ConsentGateClient.php:32-46`

**Interfaces:**
- Consumes: `Resource` (Task 5).
- Produces:

```php
final class Ux2Dev\Iris\Resources\Accounts extends Resource {
    public function getConsentDetails(string $iban): ConsentDetails
}
final class Ux2Dev\Iris\Resources\Payments extends Resource {
    public function statusByHook(string $hookHash): Payment
}
final class Ux2Dev\Iris\Resources\BulkPayments extends Resource {
    public function status(string $hookHash): BulkPayment
    public function search(PaymentSearchData $data): BulkPaymentsList
}
final class Ux2Dev\Iris\Resources\Reports extends Resource {
    public function searchPayments(PaymentSearchData $data): PaymentSearchResult
    public function activeUsers(string $fromDate, string $toDate, ?string $validUntil = null): ActiveUsers
    public function activeUsersDetails(ActiveUsersDetailsData $data): ActiveUsersDetails
    /** @return BankMaintenance[] */
    public function bankMaintenance(): array
}
final class Ux2Dev\Iris\Resources\ConsentGate extends Resource {
    public function createRequest(ConsentGateRequestData $data): ConsentGateResponse
    /** @return ConsentGateStatus[] */
    public function getConsents(bool $fulfilled = true): array
}
```

`ConsentGate::getConsents()` loses its `$userHash` parameter — the original at `ConsentGateClient.php:40` reads `/api/cgate/consents-request/{$userHash}` with the **admin** header. Because the path is keyed by user, this method moves to the user scope in Task 11 and the root version takes no user at all. Root `getConsents()` therefore calls `/api/cgate/consents-request` with `$this->credentials->admin()`; if the IRIS API rejects a path without a user segment, drop this method from the root resource, delete its manifest row, and change the manifest count assertion from 56 to 55.

Per-method specification:

| Method | Transport call | Old source |
|---|---|---|
| `Accounts::getConsentDetails` | `get("/api/8/consent/iban/{$iban}", $this->credentials->agent())` → `ConsentDetails::fromArray` | `AccountClient.php:139-144` |
| `Payments::statusByHook` | `get("/api/8/status/{$hookHash}", $this->credentials->agent())` → `Payment::fromArray` | `PaymentClient.php:60-65` |
| `BulkPayments::status` | `get("/api/8/bulk-payments/status/{$hookHash}", $this->credentials->agent())` → `BulkPayment::fromArray` | `BulkPaymentClient.php:46-51` |
| `BulkPayments::search` | `post('/api/8/bulk-payments/search', $data->toArray(), $this->credentials->agent())` → `BulkPaymentsList::fromArray` | `BulkPaymentClient.php:53-58` |
| `Reports::searchPayments` | `post('/api/8/payments/search-last-updated', $data->toArray())` → `PaymentSearchResult::fromArray` | `ReportClient.php:31-36` |
| `Reports::activeUsers` | `post('/api/8/reports/active-users', $body, $this->credentials->agent())` → `ActiveUsers::fromArray` | `ReportClient.php:38-53` |
| `Reports::activeUsersDetails` | `post('/api/8/reports/active-users-details', $data->toArray(), $this->credentials->agent())` → `ActiveUsersDetails::fromArray` | `ReportClient.php:55-61` |
| `Reports::bankMaintenance` | `get('/api/8/reports/bank-maintenance')` → `array_map(BankMaintenance::fromArray, ...)` | `ReportClient.php:63-68` |
| `ConsentGate::createRequest` | `post('/api/cgate/consents-request', $data->toArray(), $this->credentials->adminAgent())` → `ConsentGateResponse::fromArray` | `ConsentGateClient.php:32-38` |
| `ConsentGate::getConsents` | `get('/api/cgate/consents-request', $this->credentials->admin(), ['fulfilled' => $fulfilled ? 'true' : 'false'])` → `array_map(ConsentGateStatus::fromArray, ...)` | `ConsentGateClient.php:40-46` |

`Reports::activeUsers` builds its body exactly as the original: `['agentHash' => $this->credentials->agent()['x-agent-hash'], 'fromDate' => $fromDate, 'toDate' => $toDate]`, plus `validUntil` when not null.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/RootResourcesTest.php` with a shared helper parameterised by class name:

```php
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
    $resource = rootResource(Accounts::class, [new Response(200, [], '{}')], $history);

    expect($resource->getConsentDetails('BG18RZBB9155'))->toBeInstanceOf(ConsentDetails::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/consent/iban/BG18RZBB9155')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});

test('Payments::statusByHook uses the agent header', function () {
    $history = [];
    $resource = rootResource(Payments::class, [new Response(200, [], '{}')], $history);

    expect($resource->statusByHook('hook-1'))->toBeInstanceOf(Payment::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/status/hook-1');
});

test('BulkPayments::status uses the agent header', function () {
    $history = [];
    $resource = rootResource(BulkPayments::class, [new Response(200, [], '{}')], $history);

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
        new Response(200, [], json_encode([['bankHash' => 'b1']])),
    ]);

    expect($resource->bankMaintenance()[0])->toBeInstanceOf(BankMaintenance::class);
});

test('Reports::activeUsers includes agentHash in the body', function () {
    $history = [];
    $resource = rootResource(Reports::class, [new Response(200, [], '{}')], $history);

    $resource->activeUsers('2026-01-01', '2026-01-31');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body['agentHash'])->toBe('agent-1')
        ->and($body['fromDate'])->toBe('2026-01-01')
        ->and($body)->not->toHaveKey('validUntil');
});

test('Reports::activeUsers appends validUntil when given', function () {
    $history = [];
    $resource = rootResource(Reports::class, [new Response(200, [], '{}')], $history);

    $resource->activeUsers('2026-01-01', '2026-01-31', '2026-02-28');

    expect(json_decode((string) $history[0]['request']->getBody(), true)['validUntil'])
        ->toBe('2026-02-28');
});

test('ConsentGate::createRequest sends admin and agent headers', function () {
    $history = [];
    $resource = rootResource(ConsentGate::class, [new Response(200, [], '{}')], $history);

    $resource->createRequest(new \Ux2Dev\Iris\Api\Request\ConsentGateRequestData(userHash: 'u1'));

    expect($history[0]['request']->getHeaderLine('x-admin-hash'))->toBe('admin-1')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});

test('ConsentGate::getConsents sends the fulfilled query', function () {
    $history = [];
    $resource = rootResource(ConsentGate::class, [new Response(200, [], '[]')], $history);

    $resource->getConsents(fulfilled: false);

    expect($history[0]['request']->getUri()->getQuery())->toBe('fulfilled=false')
        ->and($history[0]['request']->getHeaderLine('x-admin-hash'))->toBe('admin-1');
});
```

Check `ConsentGateRequestData`'s required arguments with `grep -A10 '__construct' src/Api/Request/ConsentGateRequestData.php` and supply them; mirror `tests/Api/ConsentGateClientTest.php`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/RootResourcesTest.php`
Expected: FAIL — the five classes do not exist.

- [ ] **Step 3: Implement the five resources**

Create each file per the table above. Each is `final class X extends Resource` in namespace `Ux2Dev\Iris\Resources` with no constructor of its own.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Resources tests/Resources/RootResourcesTest.php
git commit -m "feat(resources): add thin root resources"
```

---

### Task 9: User\Accounts resource

**Files:**
- Create: `src/Resources/User/Accounts.php`
- Test: `tests/Resources/User/AccountsTest.php`
- Reference: `src/Api/AccountClient.php:21-137`

**Interfaces:**
- Consumes: `UserResource` (Task 5).
- Produces:

```php
final class Ux2Dev\Iris\Resources\User\Accounts extends UserResource
{
    /** @return BankInfo[] */ public function listBanks(?string $country = null): array
    public function getBank(string $bankHash, ?string $country = null): BankInfo
    public function getBankSca(string $bankHash): BankSca
    /** @return BankAccount[] */ public function listIbans(bool $consentDetails = false): array
    public function deleteIban(int $ibanId): void
    public function getBalance(int $ibanId): BalanceList
    public function listTransactions(int $ibanId, ?string $dateFrom = null, ?string $dateTo = null): TransactionList
    public function listPagedTransactions(int $ibanId, ?string $dateFrom = null, ?string $dateTo = null, ?string $nextPageUrl = null): TransactionList
    public function getTransaction(int $ibanId, string $transactionId): Transaction
    /** @return TokenInfo[] */ public function listTokens(?string $country = null): array
    public function createConsent(CreateConsentData $data): BankSca
    /** @return Consent[] */ public function getConsents(int $ibanId): array
}
```

Every method drops the leading `string $userHash` parameter and calls `$this->credentials->user($this->userHash)` in its place. Everything else is unchanged from `AccountClient`.

| Method | Transport call |
|---|---|
| `listBanks` | `get('/api/8/banks', $auth, $query)`, `$query['country']` when not null → `array_map(BankInfo::fromArray, ...)` |
| `getBank` | `get("/api/8/banks/{$bankHash}", $auth, $query)` → `BankInfo::fromArray` |
| `getBankSca` | `postEmpty("/api/8/bank/{$bankHash}", $auth)` → `BankSca::fromArray` |
| `listIbans` | `get('/api/8/ibans', $auth + ($consentDetails ? ['consent-details' => 'true'] : []))` → `array_map(BankAccount::fromArray, ...)` |
| `deleteIban` | `delete("/api/8/iban/{$ibanId}", $auth)` |
| `getBalance` | `get("/api/8/balance/{$ibanId}", $auth)` → `BalanceList::fromArray` |
| `listTransactions` | `get("/api/8/transactions/{$ibanId}", $auth, $query)` with optional `dateFrom`, `dateTo` → `TransactionList::fromArray` |
| `listPagedTransactions` | `get("/api/8/paged-transactions/{$ibanId}", $auth, $query)` with optional `dateFrom`, `dateTo`, `nextPageUrl` → `TransactionList::fromArray` |
| `getTransaction` | `get("/api/8/transactions/{$ibanId}/{$transactionId}", $auth)` → `Transaction::fromArray` |
| `listTokens` | `get('/api/8/tokens', $auth, $query)` → `array_map(TokenInfo::fromArray, ...)` |
| `createConsent` | `post('/api/8/consent', $data->toArray(), $auth)` → `BankSca::fromArray` |
| `getConsents` | `get("/api/8/consents/{$ibanId}", $auth)`, then `$data['consents'] ?? $data`, then `array_map(Consent::fromArray, is_array($consents) ? $consents : [])` |

where `$auth = $this->credentials->user($this->userHash)`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/User/AccountsTest.php`:

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\Consent;
use Ux2Dev\Iris\Api\Response\Transaction;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\User\Accounts;

function userAccounts(array $responses, ?array &$history = null): Accounts
{
    $stack = HandlerStack::create(new MockHandler($responses));
    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'agent-1');

    return new Accounts(
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

test('every call carries the scoped user header', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listBanks();

    expect($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('listBanks maps rows to BankInfo', function () {
    $resource = userAccounts([new Response(200, [], json_encode([['bankHash' => 'b1']]))]);

    expect($resource->listBanks()[0])->toBeInstanceOf(BankInfo::class);
});

test('listBanks appends the country query when given', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listBanks('BG');

    expect($history[0]['request']->getUri()->getQuery())->toBe('country=BG');
});

test('listIbans adds the consent-details header when asked', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listIbans(consentDetails: true);

    expect($history[0]['request']->getHeaderLine('consent-details'))->toBe('true');
});

test('listIbans maps rows to BankAccount', function () {
    $resource = userAccounts([new Response(200, [], json_encode([['id' => 1]]))]);

    expect($resource->listIbans()[0])->toBeInstanceOf(BankAccount::class);
});

test('deleteIban issues a DELETE to the iban path', function () {
    $history = [];
    $resource = userAccounts([new Response(204)], $history);

    $resource->deleteIban(42);

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/iban/42');
});

test('getBalance returns BalanceList', function () {
    $resource = userAccounts([new Response(200, [], '{}')]);

    expect($resource->getBalance(42))->toBeInstanceOf(BalanceList::class);
});

test('listPagedTransactions forwards nextPageUrl', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '{}')], $history);

    $resource->listPagedTransactions(42, nextPageUrl: 'https://bank.test/page/2');

    expect($history[0]['request']->getUri()->getQuery())
        ->toContain('nextPageUrl=https%3A%2F%2Fbank.test%2Fpage%2F2');
});

test('getTransaction hits the nested transaction path', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '{}')], $history);

    expect($resource->getTransaction(42, 'tx-9'))->toBeInstanceOf(Transaction::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/transactions/42/tx-9');
});

test('getConsents unwraps a consents envelope', function () {
    $resource = userAccounts([
        new Response(200, [], json_encode(['consents' => [['id' => 1]]])),
    ]);

    expect($resource->getConsents(42))->toHaveCount(1);
});

test('getConsents accepts a bare array', function () {
    $resource = userAccounts([new Response(200, [], json_encode([['id' => 1]]))]);

    expect($resource->getConsents(42)[0])->toBeInstanceOf(Consent::class);
});

test('getBankSca posts with no body', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '{}')], $history);

    $resource->getBankSca('bank-1');

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getBody())->toBe('');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/User/AccountsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

Create `src/Resources/User/Accounts.php` per the table. Response DTO imports come from `Ux2Dev\Iris\Api\Response\`, the request DTO from `Ux2Dev\Iris\Api\Request\CreateConsentData`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Resources/User/Accounts.php tests/Resources/User/AccountsTest.php
git commit -m "feat(resources): add user accounts resource"
```

---

### Task 10: User\Payments resource

**Files:**
- Create: `src/Resources/User/Payments.php`
- Test: `tests/Resources/User/PaymentsTest.php`
- Reference: `src/Api/PaymentClient.php:20-100`

**Interfaces:**
- Consumes: `UserResource` (Task 5).
- Produces:

```php
final class Ux2Dev\Iris\Resources\User\Payments extends UserResource
{
    public function createDirect(DirectPaymentData $data): PaymentResponse
    public function createDirectInitiate(DirectPaymentData $data): BankScaUrl
    public function createIban(IbanPaymentData $data): PaymentResponse
    public function confirm(string $code, int $ibanId): void
    public function confirmWithResult(string $code, int $ibanId): PaymentConfirmResult
    public function confirmSms(string $code, int $ibanId, string $smsCode): void
    public function statusByCode(string $code): PaymentStatusDetail
    public function authorization(int $ibanId): BankSca
    public function sca(int $ibanId): BankSca
    public function createBudget(BudgetPaymentData $data): PaymentResponse
    public function createBudgetDirect(BudgetDirectData $data): PaymentResponse
}
```

| Method | Transport call |
|---|---|
| `createDirect` | `post('/api/8/payment/direct', $data->toArray(), $auth)` → `PaymentResponse::fromArray` |
| `createDirectInitiate` | `post('/api/8/payment/direct-initiate', $data->toArray(), $auth)` → `BankScaUrl::fromArray` |
| `createIban` | `post('/api/8/payment/iban', $data->toArray(), $auth)` → `PaymentResponse::fromArray` |
| `confirm` | `put('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $auth)`, discard the result |
| `confirmWithResult` | `post('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $auth)` → `PaymentConfirmResult::fromArray` |
| `confirmSms` | `put('/api/8/payment/verify-sms', ['code' => $code, 'ibanId' => $ibanId, 'smsCode' => $smsCode], $auth)`, discard |
| `statusByCode` | `get('/api/8/payment/status', $auth, ['code' => $code])` → `PaymentStatusDetail::fromArray` |
| `authorization` | `get("/api/8/payment/ibans/{$ibanId}", $auth)` → `BankSca::fromArray` |
| `sca` | `postEmpty("/api/8/payment/sca/{$ibanId}", $auth)` → `BankSca::fromArray` |
| `createBudget` | `post('/api/8/budget-request', $data->toArray(), $auth)` → `PaymentResponse::fromArray` |
| `createBudgetDirect` | `post('/api/8/budget-request/no-iban', $data->toArray(), $auth)` → `PaymentResponse::fromArray` |

where `$auth = $this->credentials->user($this->userHash)`. `confirm` and `confirmSms` return `void`; the original used `putJson` and threw away the decoded body, so `put()` is called and its return value ignored.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/User/PaymentsTest.php`. Build the helper exactly as in Task 9 but returning `Ux2Dev\Iris\Resources\User\Payments`, then port every assertion from `tests/Api/PaymentClientTest.php`, dropping the `'user-hash'` first argument from each call. Add these three tests, which the old suite lacks:

```php
test('confirm issues a PUT and returns nothing', function () {
    $history = [];
    $resource = userPayments([new Response(200, [], '{}')], $history);

    $resource->confirm('code-1', 42);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['code' => 'code-1', 'ibanId' => 42]);
});

test('statusByCode passes the code as a query parameter', function () {
    $history = [];
    $resource = userPayments([new Response(200, [], '{}')], $history);

    $resource->statusByCode('code-1');

    expect($history[0]['request']->getUri()->getQuery())->toBe('code=code-1');
});

test('sca posts with no body to the sca path', function () {
    $history = [];
    $resource = userPayments([new Response(200, [], '{}')], $history);

    $resource->sca(42);

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/payment/sca/42');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/User/PaymentsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

Create `src/Resources/User/Payments.php` per the table.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Resources/User/Payments.php tests/Resources/User/PaymentsTest.php
git commit -m "feat(resources): add user payments resource"
```

---

### Task 11: Remaining user-scoped resources

**Files:**
- Create: `src/Resources/User/BulkPayments.php`, `src/Resources/User/Agent.php`, `src/Resources/User/Reports.php`, `src/Resources/User/ConsentGate.php`
- Test: `tests/Resources/User/RemainingResourcesTest.php`
- Reference: `src/Api/BulkPaymentClient.php:18-44`, `src/Api/AgentClient.php:35-43,67-86`, `src/Api/ReportClient.php:17-29`, `src/Api/ConsentGateClient.php:48-53`

**Interfaces:**
- Consumes: `UserResource` (Task 5).
- Produces:

```php
final class Ux2Dev\Iris\Resources\User\BulkPayments extends UserResource {
    public function create(BulkPaymentData $data): PaymentResponse
    public function createIban(BulkIbanPaymentData $data): PaymentResponse
    public function createBudget(BulkBudgetData $data): PaymentResponse
    public function createBudgetIban(BulkBudgetIbanData $data): PaymentResponse
}
final class Ux2Dev\Iris\Resources\User\Agent extends UserResource {
    public function createToken(): string
    public function delete(): void
    public function sendAisEmail(string $hookHash, string $bankHash, string $email): void
    public function kycStatus(): IdentificationAccount
}
final class Ux2Dev\Iris\Resources\User\Reports extends UserResource {
    public function listPayments(int $page = 0, int $size = 30, ?string $status = null): PaymentsList
}
final class Ux2Dev\Iris\Resources\User\ConsentGate extends UserResource {
    public function uiConsentRequest(): ConsentGateUi
}
```

| Method | Transport call |
|---|---|
| `BulkPayments::create` | `post('/api/8/bulk-payments/payment', $data->toArray(), $user)` → `PaymentResponse::fromArray` |
| `BulkPayments::createIban` | `post('/api/8/bulk-payments/request', $data->toArray(), $user)` → `PaymentResponse::fromArray` |
| `BulkPayments::createBudget` | `post('/api/8/bulk-payments/budget-payment', $data->toArray(), $user)` → `PaymentResponse::fromArray` |
| `BulkPayments::createBudgetIban` | `post('/api/8/bulk-payments/budget-request', $data->toArray(), $user)` → `PaymentResponse::fromArray` |
| `Agent::createToken` | `postForString('/api/8/usertoken', $user)` |
| `Agent::delete` | `delete('/api/8/agent/user', $this->credentials->both($this->userHash))` |
| `Agent::sendAisEmail` | `postVoid('/api/8/agent/ais/email', ['hookHash' => …, 'bankHash' => …, 'email' => …], $this->credentials->both($this->userHash))` |
| `Agent::kycStatus` | `get('/api/8/id/status', $user)` → `IdentificationAccount::fromArray` |
| `Reports::listPayments` | `post('/api/8/payments', ['page' => $page, 'size' => $size, 'status' => $status, 'userHash' => $this->userHash], $this->credentials->agent())` → `PaymentsList::fromArray` |
| `ConsentGate::uiConsentRequest` | `get('/api/cgate/ui/consents-request', $user)` → `ConsentGateUi::fromArray` |

where `$user = $this->credentials->user($this->userHash)`. Note `Reports::listPayments` sends the **agent** header and carries the user hash in the body — that is the original behaviour at `ReportClient.php:17-29`, preserved exactly.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resources/User/RemainingResourcesTest.php` with a class-parameterised helper like Task 8's, but passing `'user-1'` as the third constructor argument:

```php
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

test('Agent::createToken trims the quoted token', function () {
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [
        new Response(200, [], '"tok-1"'),
    ]);

    expect($resource->createToken())->toBe('tok-1');
});

test('Agent::delete sends both auth headers', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [new Response(204)], $history);

    $resource->delete();

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('Agent::sendAisEmail posts the three fields with both headers', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [new Response(204)], $history);

    $resource->sendAisEmail('hook-1', 'bank-1', 'a@b.test');

    expect(json_decode((string) $history[0]['request']->getBody(), true))
        ->toBe(['hookHash' => 'hook-1', 'bankHash' => 'bank-1', 'email' => 'a@b.test'])
        ->and($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1');
});

test('Agent::kycStatus returns IdentificationAccount', function () {
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Agent::class, [new Response(200, [], '{}')]);

    expect($resource->kycStatus())
        ->toBeInstanceOf(\Ux2Dev\Iris\Api\Response\IdentificationAccount::class);
});

test('Reports::listPayments sends the agent header and userHash in the body', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\Reports::class, [
        new Response(200, [], '{}'),
    ], $history);

    $resource->listPayments(page: 1, size: 5, status: 'CONFIRMED');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($history[0]['request']->getHeaderLine('x-agent-hash'))->toBe('agent-1')
        ->and($history[0]['request']->hasHeader('x-user-hash'))->toBeFalse()
        ->and($body)->toBe(['page' => 1, 'size' => 5, 'status' => 'CONFIRMED', 'userHash' => 'user-1']);
});

test('ConsentGate::uiConsentRequest sends the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\ConsentGate::class, [
        new Response(200, [], '{}'),
    ], $history);

    $resource->uiConsentRequest();

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/cgate/ui/consents-request')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});

test('BulkPayments::create posts to the bulk payment path with the user header', function () {
    $history = [];
    $resource = userResource(\Ux2Dev\Iris\Resources\User\BulkPayments::class, [
        new Response(200, [], '{}'),
    ], $history);

    $resource->create(new \Ux2Dev\Iris\Api\Request\BulkPaymentData(currency: 'BGN', payments: []));

    expect($history[0]['request']->getUri()->getPath())->toBe('/api/8/bulk-payments/payment')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1');
});
```

Check `BulkPaymentData`'s required arguments with `grep -A12 '__construct' src/Api/Request/BulkPaymentData.php` and mirror `tests/Api/BulkPaymentClientTest.php`. Add one path assertion per remaining bulk method the same way.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Resources/User/RemainingResourcesTest.php`
Expected: FAIL — the four classes do not exist.

- [ ] **Step 3: Implement the four resources**

Create each per the table.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Resources/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Resources/User tests/Resources/User/RemainingResourcesTest.php
git commit -m "feat(resources): add remaining user-scoped resources"
```

---

### Task 12: Iris root and UserScope

**Files:**
- Create: `src/Iris.php`
- Create: `src/UserScope.php`
- Test: `tests/IrisTest.php`

**Interfaces:**
- Consumes: every resource from Tasks 6-11, `IrisTransport` (Task 3), `Credentials` (Task 4), `MerchantConfig` (Task 2).
- Produces:

```php
final class Ux2Dev\Iris\Iris
{
    public function __construct(
        MerchantConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {}

    public function config(): MerchantConfig
    public function user(string $userHash): UserScope
    public function payByLink(): Resources\PayByLink
    public function agent(): Resources\Agent
    public function accounts(): Resources\Accounts
    public function payments(): Resources\Payments
    public function bulkPayments(): Resources\BulkPayments
    public function reports(): Resources\Reports
    public function consentGate(): Resources\ConsentGate
}

final class Ux2Dev\Iris\UserScope
{
    public function __construct(
        private readonly IrisTransport $transport,
        private readonly Credentials $credentials,
        private readonly string $userHash,
    ) {}

    public function userHash(): string
    public function accounts(): Resources\User\Accounts
    public function payments(): Resources\User\Payments
    public function bulkPayments(): Resources\User\BulkPayments
    public function agent(): Resources\User\Agent
    public function reports(): Resources\User\Reports
    public function consentGate(): Resources\User\ConsentGate
}
```

Both use the `??=` lazy-accessor idiom from `prim/src/Prim.php`. `Iris` builds two transports — core from `$config->environment->webSdkBaseUrl()` and PayByLink from `$config->environment->payByLinkBaseUrl()` — and passes the core one to `user()`, since no user-scoped endpoint lives on the PayByLink host. `user()` returns a **fresh** `UserScope` per hash and does not cache across different hashes.

- [ ] **Step 1: Write the failing tests**

Create `tests/IrisTest.php`:

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/IrisTest.php`
Expected: FAIL — `Class "Ux2Dev\Iris\Iris" not found`.

- [ ] **Step 3: Implement UserScope**

Create `src/UserScope.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\User\Accounts;
use Ux2Dev\Iris\Resources\User\Agent;
use Ux2Dev\Iris\Resources\User\BulkPayments;
use Ux2Dev\Iris\Resources\User\ConsentGate;
use Ux2Dev\Iris\Resources\User\Payments;
use Ux2Dev\Iris\Resources\User\Reports;

/**
 * Every IRIS endpoint that concerns one end user. Holding the hash here is
 * what keeps it out of 33 method signatures.
 */
final class UserScope
{
    private ?Accounts $accounts = null;
    private ?Payments $payments = null;
    private ?BulkPayments $bulkPayments = null;
    private ?Agent $agent = null;
    private ?Reports $reports = null;
    private ?ConsentGate $consentGate = null;

    public function __construct(
        private readonly IrisTransport $transport,
        private readonly Credentials $credentials,
        private readonly string $userHash,
    ) {
    }

    public function userHash(): string
    {
        return $this->userHash;
    }

    public function accounts(): Accounts
    {
        return $this->accounts ??= new Accounts($this->transport, $this->credentials, $this->userHash);
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments($this->transport, $this->credentials, $this->userHash);
    }

    public function bulkPayments(): BulkPayments
    {
        return $this->bulkPayments ??= new BulkPayments($this->transport, $this->credentials, $this->userHash);
    }

    public function agent(): Agent
    {
        return $this->agent ??= new Agent($this->transport, $this->credentials, $this->userHash);
    }

    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->transport, $this->credentials, $this->userHash);
    }

    public function consentGate(): ConsentGate
    {
        return $this->consentGate ??= new ConsentGate($this->transport, $this->credentials, $this->userHash);
    }
}
```

- [ ] **Step 4: Implement Iris**

Create `src/Iris.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\Accounts;
use Ux2Dev\Iris\Resources\Agent;
use Ux2Dev\Iris\Resources\BulkPayments;
use Ux2Dev\Iris\Resources\ConsentGate;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\Resources\Payments;
use Ux2Dev\Iris\Resources\Reports;

/**
 * Framework-agnostic entry point for the IRIS Solutions SDK. Instantiate
 * once per merchant with a PSR-18 client and PSR-17 factories, then reach
 * endpoints through the resource accessors.
 */
final class Iris
{
    private readonly IrisTransport $core;
    private readonly IrisTransport $payByLinkTransport;
    private readonly Credentials $credentials;

    private ?PayByLink $payByLink = null;
    private ?Agent $agent = null;
    private ?Accounts $accounts = null;
    private ?Payments $payments = null;
    private ?BulkPayments $bulkPayments = null;
    private ?Reports $reports = null;
    private ?ConsentGate $consentGate = null;

    public function __construct(
        private readonly MerchantConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $this->core = new IrisTransport(
            $config->environment->webSdkBaseUrl(),
            $httpClient,
            $requestFactory,
            $streamFactory,
        );

        $this->payByLinkTransport = new IrisTransport(
            $config->environment->payByLinkBaseUrl(),
            $httpClient,
            $requestFactory,
            $streamFactory,
        );

        $this->credentials = new Credentials($config);
    }

    public function config(): MerchantConfig
    {
        return $this->config;
    }

    public function user(string $userHash): UserScope
    {
        return new UserScope($this->core, $this->credentials, $userHash);
    }

    public function payByLink(): PayByLink
    {
        return $this->payByLink ??= new PayByLink($this->payByLinkTransport, $this->credentials, $this->config);
    }

    public function agent(): Agent
    {
        return $this->agent ??= new Agent($this->core, $this->credentials);
    }

    public function accounts(): Accounts
    {
        return $this->accounts ??= new Accounts($this->core, $this->credentials);
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments($this->core, $this->credentials);
    }

    public function bulkPayments(): BulkPayments
    {
        return $this->bulkPayments ??= new BulkPayments($this->core, $this->credentials);
    }

    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->core, $this->credentials);
    }

    public function consentGate(): ConsentGate
    {
        return $this->consentGate ??= new ConsentGate($this->core, $this->credentials);
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/IrisTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 6: Commit**

```bash
git add src/Iris.php src/UserScope.php tests/IrisTest.php
git commit -m "feat: add Iris root and UserScope"
```

---

### Task 13: Retire the old clients and close the coverage gate

**Files:**
- Delete: `src/Api/BaseClient.php`, `src/Api/AgentClient.php`, `src/Api/AccountClient.php`, `src/Api/PaymentClient.php`, `src/Api/BulkPaymentClient.php`, `src/Api/ReportClient.php`, `src/Api/ConsentGateClient.php`, `src/PayByLink/PayByLinkClient.php`
- Delete: `tests/Api/AgentClientTest.php`, `tests/Api/AccountClientTest.php`, `tests/Api/PaymentClientTest.php`, `tests/Api/BulkPaymentClientTest.php`, `tests/Api/ReportClientTest.php`, `tests/Api/ConsentGateClientTest.php`, `tests/PayByLink/PayByLinkClientTest.php`
- Modify: `tests/Resources/MethodCoverageTest.php`
- Modify: `tests/Security/HardeningTest.php`

**Interfaces:**
- Consumes: every resource from Tasks 6-11, `Iris` (Task 12).
- Produces: nothing new; removes the old surface.

Deleting `src/PayByLink/PayByLinkClient.php` removes the duplicated HTTP layer that motivated this refactor. Keep `src/PayByLink/Response/` and `src/Api/Request/`, `src/Api/Response/`, `src/Api/Enum/` — the DTOs are unchanged and still imported by the resources.

- [ ] **Step 1: Tighten the coverage test**

In `tests/Resources/MethodCoverageTest.php`, replace the second test with one that requires every class to exist:

```php
test('every manifest method exists on its resource', function () use ($manifest) {
    foreach ($manifest as [$class, $method, $requiredParams]) {
        expect(class_exists($class))->toBeTrue("{$class} is missing");
        expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is missing");

        $reflection = new ReflectionMethod($class, $method);
        expect($reflection->isPublic())->toBeTrue("{$class}::{$method}() must be public");
        expect($reflection->getNumberOfRequiredParameters())->toBe(
            $requiredParams,
            "{$class}::{$method}() should take {$requiredParams} required parameters",
        );
    }
});
```

- [ ] **Step 2: Run it to confirm the gate is green before deleting anything**

Run: `./vendor/bin/pest tests/Resources/MethodCoverageTest.php`
Expected: PASS. If it fails, a resource method is missing or misnamed — fix that before deleting the old clients, since they are the reference.

- [ ] **Step 3: Port the hardening test**

`tests/Security/HardeningTest.php` builds `AgentClient` and `PayByLinkClient` via two helpers at lines 23-49. Rewrite both helpers to build resources instead:

```php
function createSecurityAgentResource(array $responses): \Ux2Dev\Iris\Resources\Agent
{
    $mock = new MockHandler($responses);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent');

    return new \Ux2Dev\Iris\Resources\Agent(
        new \Ux2Dev\Iris\Http\IrisTransport(
            $config->environment->webSdkBaseUrl(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $factory,
            $factory,
        ),
        new \Ux2Dev\Iris\Http\Credentials($config),
    );
}

function createSecurityPayByLinkResource(array $responses): \Ux2Dev\Iris\Resources\PayByLink
{
    $mock = new MockHandler($responses);
    $factory = new HttpFactory();
    $config = new MerchantConfig(environment: Environment::Development, publicHash: 'test-public');

    return new \Ux2Dev\Iris\Resources\PayByLink(
        new \Ux2Dev\Iris\Http\IrisTransport(
            $config->environment->payByLinkBaseUrl(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $factory,
            $factory,
        ),
        new \Ux2Dev\Iris\Http\Credentials($config),
        $config,
    );
}
```

Then update every call site in the file to use the new helpers and the new method names (`listUsers`, `getBanks`, `createLink`, `getQrCode`). Two assertions need adjusting:

- The test asserting that constructing a client without `agentHash` throws now belongs to `Credentials`: change it to assert `credentialsFor(public: 'p')->agent()` throws `ConfigurationException`. If `tests/Http/CredentialsTest.php` already covers it, delete the duplicate here instead.
- Any assertion on the message `"HTTP {n} from QR code endpoint"` becomes `"HTTP {n} from IRIS API"`.

- [ ] **Step 4: Delete the old clients and their tests**

```bash
git rm src/Api/BaseClient.php src/Api/AgentClient.php src/Api/AccountClient.php \
       src/Api/PaymentClient.php src/Api/BulkPaymentClient.php src/Api/ReportClient.php \
       src/Api/ConsentGateClient.php src/PayByLink/PayByLinkClient.php
git rm tests/Api/AgentClientTest.php tests/Api/AccountClientTest.php \
       tests/Api/PaymentClientTest.php tests/Api/BulkPaymentClientTest.php \
       tests/Api/ReportClientTest.php tests/Api/ConsentGateClientTest.php \
       tests/PayByLink/PayByLinkClientTest.php
```

- [ ] **Step 5: Confirm nothing still references the deleted classes**

Run: `grep -rn 'BaseClient\|PayByLinkClient\|AgentClient\|AccountClient\|PaymentClient\|BulkPaymentClient\|ReportClient\|ConsentGateClient' src/ tests/`
Expected: matches only in `src/Laravel/` (fixed in Task 14). If anything else matches, fix it now.

- [ ] **Step 6: Run the suite**

Run: `./vendor/bin/pest`
Expected: everything except the Laravel tests passes. The Laravel manager still references the deleted clients; that is Task 14.

- [ ] **Step 7: Commit**

```bash
git add -A src tests
git commit -m "refactor: replace clients with resources and close coverage gate"
```

---

### Task 14: Rewire the Laravel layer

**Files:**
- Modify: `src/Laravel/IrisManager.php`
- Delete: `src/Laravel/IrisFacade.php`
- Create: `src/Laravel/Facades/Iris.php`
- Modify: `src/Laravel/Http/Controllers/IrisWebhookController.php`
- Modify: `src/Laravel/Console/StatusCheckCommand.php`
- Modify: `src/Laravel/config/iris.php`
- Modify: `composer.json` (the `extra.laravel.aliases` entry)
- Test: `tests/Laravel/IrisManagerTest.php`

**Interfaces:**
- Consumes: `Iris` (Task 12), `MerchantConfig` (Task 2).
- Produces:

```php
final class Ux2Dev\Iris\Laravel\IrisManager
{
    public function __construct(
        array $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {}

    public function merchant(string $name): self
    public function currentMerchant(): string
    public function client(): Iris
    public function getConfig(): MerchantConfig
    public function __call(string $method, array $arguments): mixed
}
```

`__call` forwards to `client()`, so `Iris::payByLink()` and `Iris::user($h)->payments()` both resolve through the facade. This mirrors `prim/src/Laravel/PrimManager.php:56-67`.

- [ ] **Step 1: Write the failing tests**

Rewrite `tests/Laravel/IrisManagerTest.php`:

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Iris;
use Ux2Dev\Iris\Laravel\IrisManager;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\UserScope;

function managerConfig(): array
{
    return [
        'default' => 'main',
        'merchants' => [
            'main' => [
                'public_hash' => 'hash-main',
                'agent_hash' => 'agent-main',
                'environment' => 'development',
                'currency' => 'EUR',
                'language' => 'bg',
                'timeout' => 15,
            ],
            'secondary' => [
                'public_hash' => 'hash-secondary',
                'environment' => 'production',
            ],
        ],
    ];
}

test('resolves an Iris instance for the default merchant', function () {
    expect((new IrisManager(managerConfig()))->client())->toBeInstanceOf(Iris::class);
});

test('caches the Iris instance per merchant', function () {
    $manager = new IrisManager(managerConfig());

    expect($manager->client())->toBe($manager->client());
});

test('switches merchant immutably', function () {
    $manager = new IrisManager(managerConfig());
    $secondary = $manager->merchant('secondary');

    expect($secondary->currentMerchant())->toBe('secondary')
        ->and($manager->currentMerchant())->toBe('main')
        ->and($secondary->getConfig()->publicHash)->toBe('hash-secondary');
});

test('reads the timeout from merchant config', function () {
    expect((new IrisManager(managerConfig()))->getConfig()->timeout)->toBe(15);
});

test('defaults the timeout to 30 when absent', function () {
    expect((new IrisManager(managerConfig()))->merchant('secondary')->getConfig()->timeout)->toBe(30);
});

test('throws on an unknown merchant', function () {
    expect(fn () => (new IrisManager(managerConfig()))->merchant('nope')->client())
        ->toThrow(ConfigurationException::class, 'Merchant "nope" is not configured');
});

test('forwards resource accessors to the Iris instance', function () {
    $manager = new IrisManager(managerConfig());

    expect($manager->payByLink())->toBeInstanceOf(PayByLink::class)
        ->and($manager->user('user-1'))->toBeInstanceOf(UserScope::class);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Laravel/IrisManagerTest.php`
Expected: FAIL — `IrisManager::client()` does not exist and the old `payByLink()` returns a deleted class.

- [ ] **Step 3: Rewrite IrisManager**

Replace `src/Laravel/IrisManager.php` entirely:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Iris;

/**
 * Laravel integration. Resolves merchant configuration from `config/iris.php`
 * and exposes a lazy, cached {@see Iris} instance per merchant.
 */
final class IrisManager
{
    /** @var array<string, Iris> */
    private array $instances = [];

    /** @var array<string, MerchantConfig> */
    private array $configs = [];

    private string $currentMerchant;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->currentMerchant = (string) ($config['default'] ?? 'main');
    }

    public function merchant(string $name): self
    {
        $clone = clone $this;
        $clone->currentMerchant = $name;

        return $clone;
    }

    public function currentMerchant(): string
    {
        return $this->currentMerchant;
    }

    public function client(): Iris
    {
        return $this->instances[$this->currentMerchant] ??= $this->build($this->currentMerchant);
    }

    public function getConfig(): MerchantConfig
    {
        return $this->resolveConfig($this->currentMerchant);
    }

    /**
     * Forward any Iris accessor — payByLink(), user(), agent() — straight through.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }

    private function build(string $merchant): Iris
    {
        $config = $this->resolveConfig($merchant);
        $factory = new HttpFactory();

        return new Iris(
            $config,
            $this->httpClient ?? new Client(['timeout' => $config->timeout]),
            $this->requestFactory ?? $factory,
            $this->streamFactory ?? $factory,
        );
    }

    private function resolveConfig(string $name): MerchantConfig
    {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        $merchants = (array) ($this->config['merchants'] ?? []);

        if (! isset($merchants[$name]) || ! is_array($merchants[$name])) {
            throw new ConfigurationException("Merchant \"{$name}\" is not configured");
        }

        $m = $merchants[$name];

        return $this->configs[$name] = new MerchantConfig(
            environment: Environment::from($m['environment'] ?? 'production'),
            publicHash: $m['public_hash'] ?? null,
            agentHash: $m['agent_hash'] ?? null,
            adminHash: $m['admin_hash'] ?? null,
            currency: Currency::from($m['currency'] ?? 'EUR'),
            language: Language::from($m['language'] ?? 'bg'),
            timeout: (int) ($m['timeout'] ?? 30),
        );
    }
}
```

Note the `clone` in `merchant()` copies `$instances` and `$configs`; that is intentional and matches the previous behaviour.

- [ ] **Step 4: Move the facade**

Delete `src/Laravel/IrisFacade.php` and create `src/Laravel/Facades/Iris.php`:

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Ux2Dev\Iris\Laravel\IrisManager;

/**
 * @method static IrisManager merchant(string $name)
 * @method static string currentMerchant()
 * @method static \Ux2Dev\Iris\Iris client()
 * @method static \Ux2Dev\Iris\Config\MerchantConfig getConfig()
 * @method static \Ux2Dev\Iris\UserScope user(string $userHash)
 * @method static \Ux2Dev\Iris\Resources\PayByLink payByLink()
 * @method static \Ux2Dev\Iris\Resources\Agent agent()
 * @method static \Ux2Dev\Iris\Resources\Accounts accounts()
 * @method static \Ux2Dev\Iris\Resources\Payments payments()
 * @method static \Ux2Dev\Iris\Resources\BulkPayments bulkPayments()
 * @method static \Ux2Dev\Iris\Resources\Reports reports()
 * @method static \Ux2Dev\Iris\Resources\ConsentGate consentGate()
 *
 * @see IrisManager
 */
final class Iris extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IrisManager::class;
    }
}
```

Update `composer.json`:

```json
            "aliases": {
                "Iris": "Ux2Dev\\Iris\\Laravel\\Facades\\Iris"
            }
```

- [ ] **Step 5: Update the controller, command and config**

In `src/Laravel/Http/Controllers/IrisWebhookController.php` and `src/Laravel/Console/StatusCheckCommand.php`, replace every client type-hint and call with the resource equivalent. Find them with:

Run: `grep -n 'Client\|payByLink()\|payment()\|getPaymentStatus\|getStatusByHookHash' src/Laravel/Http/Controllers/IrisWebhookController.php src/Laravel/Console/StatusCheckCommand.php`

Rename `getPaymentStatus(` to `getStatus(` on PayByLink calls and `getStatusByHookHash(` to `statusByHook(` on core payment calls, and resolve `IrisManager` rather than a client class.

Add `'timeout' => env('IRIS_TIMEOUT', 30),` to the `main` merchant block in `src/Laravel/config/iris.php`.

- [ ] **Step 6: Run the full suite**

Run: `./vendor/bin/pest`
Expected: PASS, every test.

- [ ] **Step 7: Confirm no reference to the old facade survives**

Run: `grep -rn 'IrisFacade' src/ tests/ composer.json`
Expected: no matches.

- [ ] **Step 8: Commit**

```bash
git add -A src tests composer.json
git commit -m "refactor(laravel): wire manager and facade to Iris root"
```

---

### Task 15: README

**Files:**
- Create: `README.md`

**Interfaces:**
- Consumes: the public API from Tasks 6-14.
- Produces: nothing consumed by code.

Model the structure on `~/Projects/prim/README.md`: title, one-paragraph description, Requirements, Installation, Quick Start (plain PHP, then Laravel), then a resource reference.

- [ ] **Step 1: Write the README**

Include, at minimum:

````markdown
# IRIS Pay PHP SDK

Framework-agnostic PHP SDK for the [IRIS Solutions](https://irispay.bg) open
banking platform. Covers the PayByLink/QR API, the Core API (agents, accounts,
payments, bulk payments, reports) and the Consent Gateway as resource methods
returning typed DTOs. Works with plain PHP or Laravel.

## Requirements

- PHP 8.2 or higher
- JSON extension
- A PSR-18 HTTP client and PSR-17 request/stream factories (Guzzle provides both)

## Installation

```bash
composer require ux2dev/iris-pay
```

## Quick Start

### Plain PHP

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Iris;

$config = new MerchantConfig(
    environment: Environment::Production,
    publicHash:  'your-public-hash',
    agentHash:   'your-agent-hash',
);

$factory = new HttpFactory();
$iris = new Iris($config, new Client(), $factory, $factory);

$link = $iris->payByLink()->createLink(
    sum:         49.90,
    description: 'Order #1024',
    toIban:      'BG18RZBB91550123456789',
    hookUrl:     'https://shop.example/iris/webhook',
    redirectUrl: 'https://shop.example/thank-you',
);

echo $link->url;
```

### User-scoped calls

Core API endpoints that concern one end user hang off `user()`:

```php
$user = $iris->user($userHash);

foreach ($user->accounts()->listIbans() as $iban) {
    echo $iban->iban . PHP_EOL;
}

$payment = $user->payments()->createDirect($data);
```

### Laravel

```php
use Ux2Dev\Iris\Laravel\Facades\Iris;

$link = Iris::payByLink()->createLink(...);
$ibans = Iris::user($userHash)->accounts()->listIbans();
$other = Iris::merchant('secondary')->payByLink()->getBanks();
```

Publish the config with:

```bash
php artisan vendor:publish --tag=iris-config
```

## Resources

| Accessor | Scope | Purpose |
|---|---|---|
| `payByLink()` | root | Payment links, QR codes, refunds |
| `agent()` | root | Signup, hooks, user administration |
| `accounts()` | root | Consent lookup by IBAN |
| `payments()` | root | Payment status by hook hash |
| `bulkPayments()` | root | Bulk status and search |
| `reports()` | root | Payment search, active users, bank maintenance |
| `consentGate()` | root | Consent requests (admin) |
| `user($h)->accounts()` | user | Banks, IBANs, balances, transactions, consents |
| `user($h)->payments()` | user | Direct, IBAN and budget payments, confirmation |
| `user($h)->bulkPayments()` | user | Bulk payment creation |
| `user($h)->agent()` | user | Tokens, deletion, AIS email, KYC status |
| `user($h)->reports()` | user | That user's payment list |
| `user($h)->consentGate()` | user | Consent request UI |
````

- [ ] **Step 2: Verify the plain-PHP example actually runs**

Save the Quick Start snippet to `/tmp/iris-readme-check.php` with `require 'vendor/autoload.php';` at the top and `Environment::Development`, then:

Run: `php /tmp/iris-readme-check.php`
Expected: a network error reaching the sandbox host, not a PHP fatal error. A `TypeError`, `Error: Class not found`, or `ArgumentCountError` means the README is wrong — fix it.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add README"
```

---

### Task 16: Final verification

- [ ] **Step 1: Run the whole suite**

Run: `./vendor/bin/pest`
Expected: PASS. Test count should be at least 102 — the pre-refactor baseline — plus the new transport, credentials, Iris and coverage tests.

- [ ] **Step 2: Confirm the coverage gate is real**

Temporarily rename one resource method, for example `Ux2Dev\Iris\Resources\Agent::listUsers` to `listUsersX`, then:

Run: `./vendor/bin/pest tests/Resources/MethodCoverageTest.php`
Expected: FAIL with `Ux2Dev\Iris\Resources\Agent::listUsers() is missing`. Rename it back and re-run to confirm PASS. A gate that cannot fail is not a gate.

- [ ] **Step 3: Confirm the duplicated HTTP layer is gone**

Run: `grep -rn 'parseJsonResponse\|throwForStatus' src/`
Expected: matches only in `src/Http/IrisTransport.php`.

- [ ] **Step 4: Confirm Guzzle is not a runtime requirement**

Run: `grep -rn 'GuzzleHttp' src/ | grep -v 'src/Laravel/IrisManager.php'`
Expected: no matches. Guzzle appears only in the Laravel manager's fallback.

- [ ] **Step 5: Confirm vendor is still untracked**

Run: `git status --short | grep -c '^?? vendor'`
Expected: `1` — present on disk, never staged.

---

## Self-Review Notes

Checked against the spec:

- Transport, Credentials, Iris root, UserScope, resource map, config changes, Laravel rewiring, testing strategy and hygiene each map to a task.
- **Spec addendum found during planning:** the spec's `IrisTransport` interface omitted `getRaw()`, which the PayByLink QR endpoint needs (it returns image bytes with `Accept: */*`, not JSON), and `postVoid()`/`putVoid()`-with-body, which `sendAisEmail`, `updateKyc` and `deactivate` need. Task 3 includes all of them.
- **Open question flagged in Task 8:** root `ConsentGate::getConsents()` drops the user segment from a path that was keyed by user. If the IRIS API rejects the unkeyed path, the task says to remove the method and drop the manifest count to 55.
- Method names are consistent between the manifest (Task 5), the per-method tables (Tasks 6-11), the accessors (Task 12), the facade docblock (Task 14) and the README table (Task 15).
