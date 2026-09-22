# IRIS Core API Extension Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the iris-pay SDK to cover the full IRIS Solutions Core API (developer.irispay.bg) -- agents, accounts, payments, bulk payments, reports, and consent gateway -- adding ~45 v8 endpoints, 3 cgate endpoints, and 1 v9 endpoint.

**Architecture:** A shared `BaseClient` abstract class handles HTTP communication with `x-agent-hash` / `x-user-hash` header auth. Five domain clients extend it: `AgentClient`, `AccountClient`, `PaymentClient`, `BulkPaymentClient`, `ReportClient`. A separate `ConsentGateClient` handles the cgate endpoints (different auth). All DTOs are readonly classes with `fromArray()` factory methods. `IrisManager` is extended with `agent()`, `account()`, `payment()`, `bulkPayment()`, `report()`, `consentGate()` methods.

**Tech Stack:** Same as base SDK (PHP 8.2, PSR-18, Pest 4, Guzzle 7, Laravel 11/12).

**API source:** Swagger at `https://developer.sandbox.irispay.bg/api-docs` -- all schemas extracted.

---

## New File Structure

```
src/
├── Config/
│   └── MerchantConfig.php              # MODIFY: add agentHash, adminHash; make publicHash optional
├── Enum/
│   └── PaymentStatus.php               # MODIFY: add Rejected, ScaRedirected, Created, BulkProcessed
├── PayByLink/
│   └── PayByLinkClient.php             # MODIFY: validate publicHash is present
├── Api/
│   ├── BaseClient.php                  # Abstract HTTP client with header auth
│   ├── AgentClient.php                 # 11 endpoints
│   ├── AccountClient.php               # 13 endpoints
│   ├── PaymentClient.php               # 12 endpoints
│   ├── BulkPaymentClient.php           # 6 endpoints
│   ├── ReportClient.php                # 5 endpoints
│   ├── ConsentGateClient.php           # 3 cgate endpoints
│   ├── Enum/
│   │   ├── ScaType.php
│   │   ├── PsuIdType.php
│   │   ├── IdentityStatus.php
│   │   ├── IdentifierType.php
│   │   ├── PaymentType.php
│   │   ├── CreditDebitIndicator.php
│   │   ├── TokenType.php
│   │   ├── LegalEntityType.php
│   │   ├── ConsentErrorCode.php
│   │   └── OptionalHookEvent.php
│   ├── Request/
│   │   ├── SignupData.php
│   │   ├── SignupAgentData.php
│   │   ├── CreateHookData.php
│   │   ├── CreateConsentData.php
│   │   ├── IbanPaymentData.php
│   │   ├── DirectPaymentData.php
│   │   ├── BudgetPaymentData.php
│   │   ├── BudgetDirectData.php
│   │   ├── BulkPaymentData.php
│   │   ├── BulkIbanPaymentData.php
│   │   ├── BulkBudgetData.php
│   │   ├── BulkBudgetIbanData.php
│   │   ├── BulkEntry.php
│   │   ├── BulkBudgetEntry.php
│   │   ├── PaymentSearchData.php
│   │   ├── ActiveUsersDetailsData.php
│   │   └── ConsentGateRequestData.php
│   └── Response/
│       ├── BankSca.php
│       ├── BankScaUrl.php
│       ├── BankInfo.php
│       ├── BankReference.php
│       ├── Video.php
│       ├── BankAccount.php
│       ├── ConsentsStatus.php
│       ├── Consent.php
│       ├── ConsentDetails.php
│       ├── Balance.php
│       ├── Transaction.php
│       ├── TransactionAmount.php
│       ├── ExchangeRate.php
│       ├── Category.php
│       ├── CategorySum.php
│       ├── CategorySumByOtherSide.php
│       ├── TransactionList.php
│       ├── BalanceList.php
│       ├── TokenInfo.php
│       ├── IdentificationAccount.php
│       ├── Hook.php
│       ├── AgentUser.php
│       ├── UsersList.php
│       ├── EmailAccount.php
│       ├── Payment.php
│       ├── PaymentStatusDetail.php
│       ├── PaymentUrls.php
│       ├── PaymentConfirmResult.php
│       ├── PaymentResponse.php
│       ├── PaymentListEntry.php
│       ├── PaymentsList.php
│       ├── PaymentSearchEntry.php
│       ├── PaymentSearchResult.php
│       ├── BulkPayment.php
│       ├── BulkPaymentEntry.php
│       ├── BulkPaymentV2.php
│       ├── BulkPaymentsList.php
│       ├── ActiveUsers.php
│       ├── ActiveUsersDetails.php
│       ├── BankMaintenance.php
│       ├── ConsentGateResponse.php
│       ├── ConsentGateStatus.php
│       └── ConsentGateUi.php
├── Laravel/
│   ├── IrisManager.php                 # MODIFY: add agent(), account(), payment(), etc.
│   ├── IrisFacade.php                  # MODIFY: add method annotations
│   └── config/iris.php                 # MODIFY: add agent_hash, admin_hash
```

Tests:
```
tests/
├── Api/
│   ├── AgentClientTest.php
│   ├── AccountClientTest.php
│   ├── PaymentClientTest.php
│   ├── BulkPaymentClientTest.php
│   ├── ReportClientTest.php
│   └── ConsentGateClientTest.php
├── Config/
│   └── MerchantConfigTest.php          # MODIFY: update for new constructor
└── Laravel/
    └── IrisManagerTest.php             # MODIFY: test new client methods
```

---

## Auth Headers Reference

| Client | Auth Header(s) | Source |
|--------|----------------|--------|
| PayByLinkClient | publicHash in URL path | MerchantConfig.publicHash |
| AgentClient | x-agent-hash and/or x-user-hash | MerchantConfig.agentHash |
| AccountClient | x-user-hash (sometimes x-agent-hash) | Per-method userHash param |
| PaymentClient | x-user-hash (sometimes x-agent-hash) | Per-method userHash param |
| BulkPaymentClient | x-user-hash or x-agent-hash | Per-method |
| ReportClient | x-agent-hash | MerchantConfig.agentHash |
| ConsentGateClient | x-admin-hash + x-agent-hash | MerchantConfig.adminHash + agentHash |

---

### Task 14: Foundation Updates

**Files:**
- Modify: `src/Config/MerchantConfig.php`
- Modify: `src/Enum/PaymentStatus.php`
- Modify: `src/PayByLink/PayByLinkClient.php`
- Modify: `tests/Config/MerchantConfigTest.php`
- Modify: `tests/PayByLink/PayByLinkClientTest.php`
- Modify: `tests/Laravel/IrisManagerTest.php`

- [ ] **Step 1: Update PaymentStatus enum -- add 4 new values**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum PaymentStatus: string
{
    case Waiting = 'WAITING';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
    case ScaRedirected = 'SCA_REDIRECTED';
    case Created = 'CREATED';
    case BulkProcessed = 'BULK_PROCESSED';
}
```

- [ ] **Step 2: Update MerchantConfig -- make publicHash optional, add agentHash and adminHash**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Config;

use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;

final readonly class MerchantConfig
{
    public function __construct(
        public Environment $environment,
        public ?string $publicHash = null,
        public ?string $agentHash = null,
        public ?string $adminHash = null,
        public Currency $currency = Currency::EUR,
        public Language $language = Language::Bulgarian,
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
        if ($publicHash === null && $agentHash === null) {
            throw new ConfigurationException('At least one of publicHash or agentHash must be provided');
        }
    }

    public function __debugInfo(): array
    {
        return [
            'publicHash' => $this->publicHash !== null ? '[REDACTED]' : null,
            'agentHash' => $this->agentHash !== null ? '[REDACTED]' : null,
            'adminHash' => $this->adminHash !== null ? '[REDACTED]' : null,
            'environment' => $this->environment,
            'currency' => $this->currency,
            'language' => $this->language,
        ];
    }

    public function __serialize(): array
    {
        throw new \LogicException(
            'MerchantConfig must not be serialized as it contains key material'
        );
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException(
            'MerchantConfig must not be unserialized'
        );
    }
}
```

- [ ] **Step 3: Update PayByLinkClient -- validate publicHash in constructor**

Add this at the top of the constructor in `src/PayByLink/PayByLinkClient.php`:

```php
public function __construct(
    private readonly MerchantConfig $config,
    private readonly ClientInterface $httpClient,
    private readonly RequestFactoryInterface $requestFactory,
    private readonly StreamFactoryInterface $streamFactory,
) {
    if ($config->publicHash === null) {
        throw new ConfigurationException('publicHash is required for PayByLink API');
    }
}
```

- [ ] **Step 4: Update MerchantConfigTest**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;

test('creates config with publicHash', function () {
    $config = new MerchantConfig(
        environment: Environment::Development,
        publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
    );

    expect($config->publicHash)->toBe('f75fa38d-ca1b-4241-a01a-58965d444aba');
    expect($config->environment)->toBe(Environment::Development);
    expect($config->currency)->toBe(Currency::EUR);
    expect($config->language)->toBe(Language::Bulgarian);
    expect($config->agentHash)->toBeNull();
});

test('creates config with agentHash', function () {
    $config = new MerchantConfig(
        environment: Environment::Development,
        agentHash: 'agent-hash-123',
    );

    expect($config->agentHash)->toBe('agent-hash-123');
    expect($config->publicHash)->toBeNull();
});

test('creates config with both hashes', function () {
    $config = new MerchantConfig(
        environment: Environment::Production,
        publicHash: 'pub-hash',
        agentHash: 'agent-hash',
    );

    expect($config->publicHash)->toBe('pub-hash');
    expect($config->agentHash)->toBe('agent-hash');
});

test('throws when neither hash is provided', function () {
    new MerchantConfig(environment: Environment::Development);
})->throws(ConfigurationException::class, 'At least one of publicHash or agentHash must be provided');

test('throws on empty publicHash', function () {
    new MerchantConfig(environment: Environment::Development, publicHash: '');
})->throws(ConfigurationException::class, 'publicHash must not be empty when provided');

test('throws on empty agentHash', function () {
    new MerchantConfig(environment: Environment::Development, agentHash: '');
})->throws(ConfigurationException::class, 'agentHash must not be empty when provided');

test('accepts custom currency and language', function () {
    $config = new MerchantConfig(
        environment: Environment::Production,
        publicHash: 'test-key',
        currency: Currency::RON,
        language: Language::Romanian,
    );

    expect($config->currency)->toBe(Currency::RON);
    expect($config->language)->toBe(Language::Romanian);
});

test('redacts hashes in debug info', function () {
    $config = new MerchantConfig(
        environment: Environment::Development,
        publicHash: 'secret-hash',
        agentHash: 'secret-agent',
    );

    $debug = $config->__debugInfo();
    expect($debug['publicHash'])->toBe('[REDACTED]');
    expect($debug['agentHash'])->toBe('[REDACTED]');
});

test('prevents serialization', function () {
    $config = new MerchantConfig(
        environment: Environment::Development,
        publicHash: 'test-key',
    );

    serialize($config);
})->throws(\LogicException::class);
```

- [ ] **Step 5: Update PayByLinkClientTest helper function**

The `createClient()` helper in `tests/PayByLink/PayByLinkClientTest.php` must use named argument for publicHash:

```php
function createClient(array $responses): PayByLinkClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PayByLinkClient(
        config: new MerchantConfig(
            environment: Environment::Development,
            publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
        ),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}
```

- [ ] **Step 6: Update IrisManagerTest and IrisManager.resolveConfig()**

In `src/Laravel/IrisManager.php`, update `resolveConfig()`:

```php
private function resolveConfig(string $name): MerchantConfig
{
    if (isset($this->configs[$name])) {
        return $this->configs[$name];
    }

    $merchants = $this->config['merchants'] ?? [];

    if (!isset($merchants[$name])) {
        throw new ConfigurationException("Merchant \"{$name}\" is not configured");
    }

    $m = $merchants[$name];

    $this->configs[$name] = new MerchantConfig(
        environment: Environment::from($m['environment'] ?? 'production'),
        publicHash: $m['public_hash'] ?? null,
        agentHash: $m['agent_hash'] ?? null,
        adminHash: $m['admin_hash'] ?? null,
        currency: Currency::from($m['currency'] ?? 'EUR'),
        language: Language::from($m['language'] ?? 'bg'),
    );

    return $this->configs[$name];
}
```

Update `tests/Laravel/IrisManagerTest.php` -- all config arrays need at least one hash:

```php
test('resolves default merchant payByLink client', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => [
            'main' => [
                'public_hash' => 'f75fa38d-ca1b-4241-a01a-58965d444aba',
                'environment' => 'development',
                'currency' => 'EUR',
                'language' => 'bg',
            ],
        ],
    ]);

    expect($manager->payByLink())->toBeInstanceOf(PayByLinkClient::class);
});

test('resolves named merchant', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => [
            'main' => [
                'public_hash' => 'hash-main',
                'environment' => 'development',
            ],
            'secondary' => [
                'public_hash' => 'hash-secondary',
                'environment' => 'production',
            ],
        ],
    ]);

    $secondary = $manager->merchant('secondary');
    expect($secondary->getConfig()->publicHash)->toBe('hash-secondary');
});

test('throws on unknown merchant', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => [
            'main' => [
                'public_hash' => 'hash-main',
                'environment' => 'development',
            ],
        ],
    ]);

    $manager->merchant('nonexistent')->payByLink();
})->throws(ConfigurationException::class, 'Merchant "nonexistent" is not configured');

test('caches client instances', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => [
            'main' => [
                'public_hash' => 'hash-main',
                'environment' => 'development',
            ],
        ],
    ]);

    $first = $manager->payByLink();
    $second = $manager->payByLink();

    expect($first)->toBe($second);
});
```

- [ ] **Step 7: Run full test suite**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest`
Expected: All 44 tests PASS (some test assertions may change due to MerchantConfig update).

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "refactor: update MerchantConfig for multi-API support (publicHash optional, add agentHash/adminHash), extend PaymentStatus enum"
```

---

### Task 15: API Enums

**Files:**
- Create: `src/Api/Enum/ScaType.php`
- Create: `src/Api/Enum/PsuIdType.php`
- Create: `src/Api/Enum/IdentityStatus.php`
- Create: `src/Api/Enum/IdentifierType.php`
- Create: `src/Api/Enum/PaymentType.php`
- Create: `src/Api/Enum/CreditDebitIndicator.php`
- Create: `src/Api/Enum/TokenType.php`
- Create: `src/Api/Enum/LegalEntityType.php`
- Create: `src/Api/Enum/ConsentErrorCode.php`
- Create: `src/Api/Enum/OptionalHookEvent.php`

- [ ] **Step 1: Create all 10 enum files**

**src/Api/Enum/ScaType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum ScaType: string
{
    case RedirectUrl = 'REDIRECT_URL';
    case Push = 'PUSH';
    case CodeRedirectUrl = 'CODE_REDIRECT_URL';
    case OauthRedirectUrl = 'OAUTH_REDIRECT_URL';
}
```

**src/Api/Enum/PsuIdType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum PsuIdType: string
{
    case Username = 'USERNAME';
    case Msisdn = 'MSISDN';
    case UsernameAndClientNumber = 'USERNAME_AND_CLIENT_NUMBER';
    case ZbRetail = 'ZB_RETAIL';
    case OibHr = 'OIB_HR';
    case TokenSn = 'TOKEN_SN';
}
```

**src/Api/Enum/IdentityStatus.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum IdentityStatus: string
{
    case Success = 'SUCCESS';
    case Pending = 'PENDING';
    case Fail = 'FAIL';
    case NotStarted = 'NOT_STARTED';
}
```

**src/Api/Enum/IdentifierType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum IdentifierType: string
{
    case Egn = 'EGN';
    case Eik = 'EIK';
    case Pnf = 'PNF';
}
```

**src/Api/Enum/PaymentType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum PaymentType: string
{
    case DomesticCreditTransfer = 'DOMESTIC_CREDIT_TRANSFER';
    case DomesticBudgetTransfer = 'DOMESTIC_BUDGET_TRANSFER';
    case SepaCreditTransfer = 'SEPA_CREDIT_TRANSFER';
    case CrossBorderTransfer = 'CROSS_BORDER_TRANSFER';
    case BulkDomesticCreditTransfer = 'BULK_DOMESTIC_CREDIT_TRANSFER';
    case BulkDomesticBudgetTransfer = 'BULK_DOMESTIC_BUDGET_TRANSFER';
    case BulkSepaCreditTransfer = 'BULK_SEPA_CREDIT_TRANSFER';
    case BulkEntry = 'BULK_ENTRY';
    case SepaBudgetTransfer = 'SEPA_BUDGET_TRANSFER';
    case BulkSepaBudgetTransfer = 'BULK_SEPA_BUDGET_TRANSFER';
    case Domestic = 'DOMESTIC';
    case Budget = 'BUDGET';
    case Sepa = 'SEPA';
    case CrossBorder = 'CROSS_BORDER';
    case BulkDomestic = 'BULK_DOMESTIC';
    case BulkSepa = 'BULK_SEPA';
    case BulkBudget = 'BULK_BUDGET';
    case InstantDomestic = 'INSTANT_DOMESTIC';
}
```

**src/Api/Enum/CreditDebitIndicator.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum CreditDebitIndicator: string
{
    case Credit = 'CREDIT';
    case Debit = 'DEBIT';
}
```

**src/Api/Enum/TokenType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum TokenType: string
{
    case AuthAis = 'AUTH_AIS';
    case AuthPis = 'AUTH_PIS';
    case RefreshAis = 'REFRESH_AIS';
    case RefreshPis = 'REFRESH_PIS';
    case ConsentReceived = 'CONSENT_RECEIVED';
    case ConsentValid = 'CONSENT_VALID';
}
```

**src/Api/Enum/LegalEntityType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum LegalEntityType: string
{
    case SoleOwner = 'SOLEOWNER';
    case Ltd = 'LTD';
    case SolTd = 'SOLTD';
    case Jsc = 'JSC';
    case SoJsc = 'SOJSC';
    case Sd = 'SD';
    case Cd = 'CD';
    case Cda = 'CDA';
    case Dzzd = 'DZZD';
    case Npo = 'NPO';
    case Branch = 'BRANCH';
    case Not = 'NOT';
    case OtherProf = 'OTHERPROF';
    case Offshore = 'OFFSHORE';
    case OtherJrd = 'OTHER JRD';
    case Public = 'PUBLIC';
    case Vcc = 'VCC';
    case Other = 'OTHER';
}
```

**src/Api/Enum/ConsentErrorCode.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum ConsentErrorCode: string
{
    case ConsentExpired = 'CONSENT_EXPIRED';
    case BalanceFailed = 'BALANCE_FAILED';
    case TransactionsFailed = 'TRANSACTIONS_FAILED';
    case ConsentDetailsFailed = 'CONSENT_DETAILS_FAILED';
}
```

**src/Api/Enum/OptionalHookEvent.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum OptionalHookEvent: string
{
    case PaymentAuthorised = 'PAYMENT_AUTHORISED';
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Api/Enum/ && git commit -m "feat: add Core API enums (ScaType, PsuIdType, IdentityStatus, IdentifierType, PaymentType, CreditDebitIndicator, TokenType, LegalEntityType, ConsentErrorCode, OptionalHookEvent)"
```

---

### Task 16: BaseClient

**Files:**
- Create: `src/Api/BaseClient.php`

- [ ] **Step 1: Implement BaseClient**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;

abstract class BaseClient
{
    public function __construct(
        protected readonly MerchantConfig $config,
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
    ) {
        if ($config->agentHash === null) {
            throw new ConfigurationException('agentHash is required for IRIS Core API');
        }
    }

    protected function baseUrl(): string
    {
        return $this->config->environment->webSdkBaseUrl();
    }

    /** @return array<string, string> */
    protected function agentHeaders(): array
    {
        return ['x-agent-hash' => $this->config->agentHash];
    }

    /** @return array<string, string> */
    protected function userHeaders(string $userHash): array
    {
        return ['x-user-hash' => $userHash];
    }

    /** @return array<string, string> */
    protected function bothHeaders(string $userHash): array
    {
        return array_merge($this->agentHeaders(), $this->userHeaders($userHash));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function getJson(string $path, array $headers = [], array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postJson(string $path, array $body, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body)));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postEmpty(string $path, array $headers = []): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function postForString(string $path, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Accept', '*/*');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->httpClient->sendRequest($request);
        $this->assertSuccess($response);

        return trim((string) $response->getBody(), '"');
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function putJson(string $path, array $body = [], array $headers = [], array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('PUT', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        if ($body) {
            $request = $request->withBody($this->streamFactory->createStream(json_encode($body)));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->parseJsonResponse($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    protected function putVoid(string $path, array $headers = [], array $query = []): void
    {
        $url = $this->buildUrl($path, $query);

        $request = $this->requestFactory->createRequest('PUT', $url)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function deleteVoid(string $path, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->httpClient->sendRequest($request));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    protected function postVoid(string $path, array $body, array $headers = []): void
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body)));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertSuccess($this->httpClient->sendRequest($request));
    }

    /** @param array<string, mixed> $query */
    private function buildUrl(string $path, array $query = []): string
    {
        $url = $this->baseUrl() . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new InvalidResponseException(
                "HTTP {$statusCode} from IRIS API",
                ['status' => $statusCode, 'body' => $body],
            );
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidResponseException('Invalid JSON response from IRIS API', ['body' => $body]);
        }

        return $data;
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new InvalidResponseException(
                "HTTP {$statusCode} from IRIS API",
                ['status' => $statusCode, 'body' => $body],
            );
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Api/BaseClient.php && git commit -m "feat: add BaseClient abstract class with HTTP helpers and header auth"
```

---

### Task 17: Agent Response DTOs + Request DTOs

**Files:**
- Create: `src/Api/Response/IdentificationAccount.php`
- Create: `src/Api/Response/Hook.php`
- Create: `src/Api/Response/AgentUser.php`
- Create: `src/Api/Response/UsersList.php`
- Create: `src/Api/Response/EmailAccount.php`
- Create: `src/Api/Request/SignupData.php`
- Create: `src/Api/Request/SignupAgentData.php`
- Create: `src/Api/Request/CreateHookData.php`

- [ ] **Step 1: Create all agent DTOs**

**src/Api/Response/IdentificationAccount.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\IdentityStatus;

final readonly class IdentificationAccount
{
    public function __construct(
        public string $userHash,
        public ?string $idUrl,
        public ?string $identityStatusUrl,
        public ?string $identityToken,
        public IdentityStatus $identified,
        public ?string $publicHash = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userHash: $data['userHash'],
            idUrl: $data['idUrl'] ?? null,
            identityStatusUrl: $data['identityStatusUrl'] ?? null,
            identityToken: $data['identityToken'] ?? null,
            identified: IdentityStatus::from($data['identified']),
            publicHash: $data['publicHash'] ?? null,
        );
    }
}
```

**src/Api/Response/Hook.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Hook
{
    public function __construct(
        public string $hookHash,
        public int $closeSelf,
        public int $redirectTimer,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            hookHash: $data['hookHash'],
            closeSelf: (int) $data['closeSelf'],
            redirectTimer: (int) $data['redirectTimer'],
        );
    }
}
```

**src/Api/Response/AgentUser.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class AgentUser
{
    public function __construct(
        public ?string $name,
        public ?string $middleName,
        public ?string $family,
        public ?string $email,
        public ?string $uic,
        public string $dateCreated,
        public string $userHash,
        public int $accounts,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            middleName: $data['middleName'] ?? null,
            family: $data['family'] ?? null,
            email: $data['email'] ?? null,
            uic: $data['uic'] ?? null,
            dateCreated: $data['dateCreated'],
            userHash: $data['userHash'],
            accounts: (int) $data['accounts'],
        );
    }
}
```

**src/Api/Response/UsersList.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class UsersList
{
    /**
     * @param AgentUser[] $list
     */
    public function __construct(
        public int $pages,
        public int $size,
        public int $currPage,
        public array $list,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pages: (int) $data['pages'],
            size: (int) $data['size'],
            currPage: (int) $data['currPage'],
            list: array_map(fn (array $u) => AgentUser::fromArray($u), $data['list'] ?? []),
        );
    }
}
```

**src/Api/Response/EmailAccount.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class EmailAccount
{
    public function __construct(
        public string $userHash,
        public string $name,
        public string $lastname,
        public string $surname,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userHash: $data['userHash'],
            name: $data['name'],
            lastname: $data['lastname'],
            surname: $data['surname'],
        );
    }
}
```

**src/Api/Request/SignupData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class SignupData
{
    public function __construct(
        public string $agentHash,
        public string $companyName,
        public string $uic,
        public string $name,
        public string $middleName,
        public string $family,
        public string $identityHash,
        public string $email,
        public ?string $webhookUrl = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'agentHash' => $this->agentHash,
            'companyName' => $this->companyName,
            'uic' => $this->uic,
            'name' => $this->name,
            'middleName' => $this->middleName,
            'family' => $this->family,
            'identityHash' => $this->identityHash,
            'email' => $this->email,
            'webhookUrl' => $this->webhookUrl,
        ], fn ($v) => $v !== null);
    }
}
```

**src/Api/Request/SignupAgentData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\LegalEntityType;

final readonly class SignupAgentData
{
    public function __construct(
        public string $agentHash,
        public string $companyName,
        public string $uic,
        public string $name,
        public string $middleName,
        public string $family,
        public string $email,
        public bool $requiresPublicHash = false,
        public ?string $publicHash = null,
        public ?string $webhookUrl = null,
        public ?LegalEntityType $legalEntityType = null,
        public ?string $mobile = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'agentHash' => $this->agentHash,
            'companyName' => $this->companyName,
            'uic' => $this->uic,
            'name' => $this->name,
            'middleName' => $this->middleName,
            'family' => $this->family,
            'email' => $this->email,
            'requiresPublicHash' => $this->requiresPublicHash,
        ];

        if ($this->publicHash !== null) { $data['publicHash'] = $this->publicHash; }
        if ($this->webhookUrl !== null) { $data['webhookUrl'] = $this->webhookUrl; }
        if ($this->legalEntityType !== null) { $data['legalEntityType'] = $this->legalEntityType->value; }
        if ($this->mobile !== null) { $data['mobile'] = $this->mobile; }

        return $data;
    }
}
```

**src/Api/Request/CreateHookData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\OptionalHookEvent;

final readonly class CreateHookData
{
    /**
     * @param OptionalHookEvent[] $optionalEvents
     */
    public function __construct(
        public string $url,
        public string $agentHash,
        public ?string $state = null,
        public array $optionalEvents = [],
        public ?string $successUrl = null,
        public ?string $errorUrl = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'url' => $this->url,
            'agentHash' => $this->agentHash,
        ];

        if ($this->state !== null) { $data['state'] = $this->state; }
        if ($this->optionalEvents) {
            $data['optionalEvents'] = array_map(fn (OptionalHookEvent $e) => $e->value, $this->optionalEvents);
        }
        if ($this->successUrl !== null) { $data['successUrl'] = $this->successUrl; }
        if ($this->errorUrl !== null) { $data['errorUrl'] = $this->errorUrl; }

        return $data;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Api/Response/IdentificationAccount.php src/Api/Response/Hook.php src/Api/Response/AgentUser.php src/Api/Response/UsersList.php src/Api/Response/EmailAccount.php src/Api/Request/SignupData.php src/Api/Request/SignupAgentData.php src/Api/Request/CreateHookData.php && git commit -m "feat: add Agent DTOs (IdentificationAccount, Hook, AgentUser, UsersList, EmailAccount, SignupData, SignupAgentData, CreateHookData)"
```

---

### Task 18: AgentClient + Tests

**Files:**
- Create: `src/Api/AgentClient.php`
- Create: `tests/Api/AgentClientTest.php`

- [ ] **Step 1: Implement AgentClient**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\CreateHookData;
use Ux2Dev\Iris\Api\Request\SignupAgentData;
use Ux2Dev\Iris\Api\Request\SignupData;
use Ux2Dev\Iris\Api\Response\EmailAccount;
use Ux2Dev\Iris\Api\Response\Hook;
use Ux2Dev\Iris\Api\Response\IdentificationAccount;
use Ux2Dev\Iris\Api\Response\UsersList;

final class AgentClient extends BaseClient
{
    public function signup(SignupData $data): IdentificationAccount
    {
        $response = $this->postJson('/api/8/signup', $data->toArray());

        return IdentificationAccount::fromArray($response);
    }

    public function signupAgent(SignupAgentData $data): IdentificationAccount
    {
        $response = $this->postJson('/api/8/signup/agent', $data->toArray());

        return IdentificationAccount::fromArray($response);
    }

    public function createHook(CreateHookData $data): Hook
    {
        $response = $this->postJson('/api/8/createhook', $data->toArray());

        return Hook::fromArray($response);
    }

    public function createUserToken(string $userHash): string
    {
        return $this->postForString('/api/8/usertoken', $this->userHeaders($userHash));
    }

    public function deleteUser(string $userHash): void
    {
        $this->deleteVoid('/api/8/agent/user', $this->bothHeaders($userHash));
    }

    public function listUsers(
        int $page = 0,
        int $size = 30,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $text = null,
    ): UsersList {
        $query = ['page' => $page, 'size' => $size];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }
        if ($text !== null) { $query['text'] = $text; }

        $response = $this->getJson('/api/8/agent/users', $this->agentHeaders(), $query);

        return UsersList::fromArray($response);
    }

    public function checkUserByEmail(string $email): EmailAccount
    {
        $response = $this->postJson('/api/8/agent/user/check', ['email' => $email], $this->agentHeaders());

        return EmailAccount::fromArray($response);
    }

    public function sendAisEmail(string $userHash, string $hookHash, string $bankHash, string $email): void
    {
        $this->postVoid('/api/8/agent/ais/email', [
            'hookHash' => $hookHash,
            'bankHash' => $bankHash,
            'email' => $email,
        ], $this->bothHeaders($userHash));
    }

    public function addRedirectToHook(string $hookHash, string $redirectUrl): void
    {
        $this->putVoid('/api/8/redirect', [], ['hookhash' => $hookHash, 'redirectUrl' => $redirectUrl]);
    }

    public function getKycStatus(string $userHash): IdentificationAccount
    {
        $response = $this->getJson('/api/8/id/status', $this->userHeaders($userHash));

        return IdentificationAccount::fromArray($response);
    }

    public function updateKyc(string $submissionId, string $status): void
    {
        $this->postVoid('/api/8/kyc/update', [
            'submissionId' => $submissionId,
            'status' => $status,
        ], $this->agentHeaders());
    }
}
```

- [ ] **Step 2: Write tests**

```php
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
            'userHash' => 'user-hash-123',
            'idUrl' => 'https://example.com/id',
            'identityStatusUrl' => null,
            'identityToken' => null,
            'identified' => 'NOT_STARTED',
        ])),
    ]);

    $result = $client->signup(new SignupData(
        agentHash: 'test-agent-hash',
        companyName: 'Test Co',
        uic: '123456789',
        name: 'John',
        middleName: 'M',
        family: 'Doe',
        identityHash: 'id-hash',
        email: 'john@example.com',
    ));

    expect($result)->toBeInstanceOf(IdentificationAccount::class);
    expect($result->userHash)->toBe('user-hash-123');
    expect($result->identified)->toBe(IdentityStatus::NotStarted);
});

test('createHook returns Hook', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode([
            'hookHash' => 'hook-hash-456',
            'closeSelf' => 0,
            'redirectTimer' => 5,
        ])),
    ]);

    $result = $client->createHook(new CreateHookData(
        url: 'https://example.com/webhook',
        agentHash: 'test-agent-hash',
    ));

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
            'pages' => 1,
            'size' => 30,
            'currPage' => 0,
            'list' => [
                [
                    'name' => 'John',
                    'middleName' => 'M',
                    'family' => 'Doe',
                    'email' => 'john@example.com',
                    'uic' => '123456789',
                    'dateCreated' => '2025-01-01T00:00:00Z',
                    'userHash' => 'user-hash-123',
                    'accounts' => 2,
                ],
            ],
        ])),
    ]);

    $result = $client->listUsers();

    expect($result)->toBeInstanceOf(UsersList::class);
    expect($result->list)->toHaveCount(1);
    expect($result->list[0]->name)->toBe('John');
});

test('checkUserByEmail returns EmailAccount', function () {
    $client = createAgentClient([
        new Response(200, [], json_encode([
            'userHash' => 'user-hash-123',
            'name' => 'John',
            'lastname' => 'Doe',
            'surname' => 'M',
        ])),
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
```

- [ ] **Step 3: Run tests**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Api/AgentClientTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 4: Commit**

```bash
git add src/Api/AgentClient.php tests/Api/AgentClientTest.php && git commit -m "feat: add AgentClient with 11 endpoints (signup, createHook, listUsers, KYC, etc.)"
```

---

### Task 19: Account Response DTOs

**Files:** Create all account-related response DTOs. This task creates the DTOs only; AccountClient comes in Task 20.

- Create: `src/Api/Response/BankSca.php`
- Create: `src/Api/Response/BankScaUrl.php`
- Create: `src/Api/Response/BankInfo.php`
- Create: `src/Api/Response/BankReference.php`
- Create: `src/Api/Response/Video.php`
- Create: `src/Api/Response/BankAccount.php`
- Create: `src/Api/Response/ConsentsStatus.php`
- Create: `src/Api/Response/Consent.php`
- Create: `src/Api/Response/ConsentDetails.php`
- Create: `src/Api/Response/Balance.php`
- Create: `src/Api/Response/BalanceList.php`
- Create: `src/Api/Response/TransactionAmount.php`
- Create: `src/Api/Response/ExchangeRate.php`
- Create: `src/Api/Response/Category.php`
- Create: `src/Api/Response/CategorySum.php`
- Create: `src/Api/Response/CategorySumByOtherSide.php`
- Create: `src/Api/Response/Transaction.php`
- Create: `src/Api/Response/TransactionList.php`
- Create: `src/Api/Response/TokenInfo.php`
- Create: `src/Api/Request/CreateConsentData.php`

- [ ] **Step 1: Create all DTOs**

Each follows the same readonly + fromArray pattern. Full code for every file:

**src/Api/Response/BankSca.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PsuIdType;
use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankSca
{
    public function __construct(
        public ?string $startUrl,
        public ?string $endUrl,
        public ?string $formText,
        public ?string $loadingText,
        public PsuIdType $psuIdType,
        public ScaType $sca,
        public bool $gatherPsu,
        public bool $hasAuthorization,
        public bool $externalApp,
        public ?string $authorizationId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            startUrl: $data['startUrl'] ?? null,
            endUrl: $data['endUrl'] ?? null,
            formText: $data['formText'] ?? null,
            loadingText: $data['loadingText'] ?? null,
            psuIdType: PsuIdType::from($data['psuIdType']),
            sca: ScaType::from($data['sca']),
            gatherPsu: (bool) $data['gatherPsu'],
            hasAuthorization: (bool) $data['hasAuthorization'],
            externalApp: (bool) $data['externalApp'],
            authorizationId: $data['authorizationId'] ?? null,
        );
    }
}
```

**src/Api/Response/BankScaUrl.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankScaUrl
{
    public function __construct(
        public string $url,
        public ScaType $bankSCA,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            bankSCA: ScaType::from($data['bankSCA']),
        );
    }
}
```

**src/Api/Response/Video.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Video
{
    public function __construct(public ?string $url) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(url: $data['url'] ?? null);
    }
}
```

**src/Api/Response/BankInfo.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PsuIdType;
use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankInfo
{
    /**
     * @param Video[] $videos
     */
    public function __construct(
        public string $bankHash,
        public string $name,
        public ?string $urlLogo,
        public ?string $urlDarkLogo,
        public ScaType $sca,
        public ?string $firstStepInstruction,
        public bool $directPayment,
        public bool $paymentRequiresIban,
        public ?string $fullName,
        public ?string $bic,
        public ?string $services,
        public ?string $country,
        public array $videos,
        public bool $consentRequiresIban,
        public bool $consentRequiresPsu,
        public bool $paymentRequiresAuthorization,
        public bool $consentRequiresAuthorization,
        public bool $paymentRequiresPsu,
        public PsuIdType $psuType,
        public bool $budgetPaymentsRequirePaymentCategory,
        public bool $aisAvailable,
        public bool $pisAvailable,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'],
            name: $data['name'],
            urlLogo: $data['urlLogo'] ?? null,
            urlDarkLogo: $data['urlDarkLogo'] ?? null,
            sca: ScaType::from($data['sca']),
            firstStepInstruction: $data['firstStepInstruction'] ?? null,
            directPayment: (bool) ($data['directPayment'] ?? false),
            paymentRequiresIban: (bool) ($data['paymentRequiresIban'] ?? false),
            fullName: $data['fullName'] ?? null,
            bic: $data['bic'] ?? null,
            services: $data['services'] ?? null,
            country: $data['country'] ?? null,
            videos: array_map(fn (array $v) => Video::fromArray($v), $data['videos'] ?? []),
            consentRequiresIban: (bool) ($data['consentRequiresIban'] ?? false),
            consentRequiresPsu: (bool) ($data['consentRequiresPsu'] ?? false),
            paymentRequiresAuthorization: (bool) ($data['paymentRequiresAuthorization'] ?? false),
            consentRequiresAuthorization: (bool) ($data['consentRequiresAuthorization'] ?? false),
            paymentRequiresPsu: (bool) ($data['paymentRequiresPsu'] ?? false),
            psuType: PsuIdType::from($data['psuType']),
            budgetPaymentsRequirePaymentCategory: (bool) ($data['budgetPaymentsRequirePaymentCategory'] ?? false),
            aisAvailable: (bool) ($data['aisAvailable'] ?? false),
            pisAvailable: (bool) ($data['pisAvailable'] ?? false),
        );
    }
}
```

**src/Api/Response/BankReference.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BankReference
{
    public function __construct(
        public ?string $bankHash,
        public ?string $name,
        public ?string $country,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'] ?? null,
            name: $data['name'] ?? null,
            country: $data['country'] ?? null,
        );
    }
}
```

**src/Api/Response/ConsentsStatus.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\ConsentErrorCode;

final readonly class ConsentsStatus
{
    /**
     * @param array<mixed> $consents
     * @param ConsentErrorCode[] $errorCodes
     */
    public function __construct(
        public array $consents,
        public array $errorCodes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            consents: $data['consents'] ?? [],
            errorCodes: array_map(fn (string $c) => ConsentErrorCode::from($c), $data['errorCodes'] ?? []),
        );
    }
}
```

**src/Api/Response/BankAccount.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BankAccount
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $iban,
        public ?string $product,
        public ?string $ownerName,
        public ?string $currency,
        public bool $hasAuthorization,
        public ?string $bankHash,
        public ?string $bankName,
        public ?string $liteLogoUrl,
        public ?string $darkLogoUrl,
        public ?string $country,
        public string $dateCreate,
        public ?ConsentsStatus $consents,
        public bool $fulfilled,
        public ?string $validUntil,
        public int $frequencyPerDay,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: $data['name'] ?? null,
            iban: $data['iban'] ?? null,
            product: $data['product'] ?? null,
            ownerName: $data['ownerName'] ?? null,
            currency: $data['currency'] ?? null,
            hasAuthorization: (bool) ($data['hasAuthorization'] ?? false),
            bankHash: $data['bankHash'] ?? null,
            bankName: $data['bankName'] ?? null,
            liteLogoUrl: $data['liteLogoUrl'] ?? null,
            darkLogoUrl: $data['darkLogoUrl'] ?? null,
            country: $data['country'] ?? null,
            dateCreate: $data['dateCreate'],
            consents: isset($data['consents']) ? ConsentsStatus::fromArray($data['consents']) : null,
            fulfilled: (bool) ($data['fulfilled'] ?? false),
            validUntil: $data['validUntil'] ?? null,
            frequencyPerDay: (int) ($data['frequencyPerDay'] ?? 0),
        );
    }
}
```

**src/Api/Response/Balance.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Balance
{
    public function __construct(
        public float $amount,
        public ?string $currency,
        public ?string $balanceType,
        public ?string $referenceDate,
        public bool $creditLimitIncluded,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? null,
            balanceType: $data['balanceType'] ?? null,
            referenceDate: $data['referenceDate'] ?? null,
            creditLimitIncluded: (bool) ($data['creditLimitIncluded'] ?? false),
        );
    }
}
```

**src/Api/Response/BalanceList.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BalanceList
{
    /** @param Balance[] $balances */
    public function __construct(public array $balances) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            balances: array_map(fn (array $b) => Balance::fromArray($b), $data['balances'] ?? []),
        );
    }
}
```

**src/Api/Response/TransactionAmount.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class TransactionAmount
{
    public function __construct(
        public float $amount,
        public ?string $currency,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? null,
        );
    }
}
```

**src/Api/Response/ExchangeRate.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ExchangeRate
{
    public function __construct(
        public ?string $currencyFrom,
        public ?string $rateFrom,
        public ?string $currencyTo,
        public ?string $rateTo,
        public ?string $rateDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currencyFrom: $data['currencyFrom'] ?? null,
            rateFrom: $data['rateFrom'] ?? null,
            currencyTo: $data['currencyTo'] ?? null,
            rateTo: $data['rateTo'] ?? null,
            rateDate: $data['rateDate'] ?? null,
        );
    }
}
```

**src/Api/Response/Category.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Category
{
    public function __construct(
        public string $code,
        public string $name,
        public string $otherSide,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(code: $data['code'], name: $data['name'], otherSide: $data['otherSide']);
    }
}
```

**src/Api/Response/CategorySumByOtherSide.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class CategorySumByOtherSide
{
    public function __construct(
        public string $otherSide,
        public float $sum,
        public string $currency,
        public int $transactionCount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            otherSide: $data['otherSide'],
            sum: (float) $data['sum'],
            currency: $data['currency'],
            transactionCount: (int) $data['transactionCount'],
        );
    }
}
```

**src/Api/Response/CategorySum.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class CategorySum
{
    /** @param CategorySumByOtherSide[] $sumByOtherSide */
    public function __construct(
        public float $sum,
        public string $currency,
        public string $code,
        public string $categoryName,
        public int $transactionsCount,
        public array $sumByOtherSide,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sum: (float) $data['sum'],
            currency: $data['currency'],
            code: $data['code'],
            categoryName: $data['categoryName'],
            transactionsCount: (int) $data['transactionsCount'],
            sumByOtherSide: array_map(fn (array $s) => CategorySumByOtherSide::fromArray($s), $data['sumByOtherSide'] ?? []),
        );
    }
}
```

**src/Api/Response/Transaction.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\CreditDebitIndicator;

final readonly class Transaction
{
    public function __construct(
        public ?string $transactionId,
        public ?string $bookingDate,
        public ?string $creditorIban,
        public ?string $creditorName,
        public ?string $debtorIban,
        public ?string $debtorName,
        public ?string $entryReference,
        public ?string $remittanceInformationUnstructured,
        public TransactionAmount $transactionAmount,
        public ?ExchangeRate $exchangeRate,
        public ?string $valueDate,
        public ?CreditDebitIndicator $creditDebitIndicator,
        public ?Category $category,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: $data['transactionId'] ?? null,
            bookingDate: $data['bookingDate'] ?? null,
            creditorIban: $data['creditorAccount']['iban'] ?? null,
            creditorName: $data['creditorName'] ?? null,
            debtorIban: $data['debtorAccount']['iban'] ?? null,
            debtorName: $data['debtorName'] ?? null,
            entryReference: $data['entryReference'] ?? null,
            remittanceInformationUnstructured: $data['remittanceInformationUnstructured'] ?? null,
            transactionAmount: TransactionAmount::fromArray($data['transactionAmount']),
            exchangeRate: isset($data['exchangeRate']) ? ExchangeRate::fromArray($data['exchangeRate']) : null,
            valueDate: $data['valueDate'] ?? null,
            creditDebitIndicator: isset($data['creditDebitIndicator']) ? CreditDebitIndicator::from($data['creditDebitIndicator']) : null,
            category: isset($data['category']) ? Category::fromArray($data['category']) : null,
        );
    }
}
```

**src/Api/Response/TransactionList.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class TransactionList
{
    /**
     * @param Transaction[] $transactions
     * @param Balance[] $balances
     * @param CategorySum[] $categorySums
     */
    public function __construct(
        public array $transactions,
        public array $balances,
        public array $categorySums,
        public ?string $nextPageUrl,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactions: array_map(fn (array $t) => Transaction::fromArray($t), $data['transactions'] ?? []),
            balances: array_map(fn (array $b) => Balance::fromArray($b), $data['balances'] ?? []),
            categorySums: array_map(fn (array $c) => CategorySum::fromArray($c), $data['categorySums'] ?? []),
            nextPageUrl: $data['nextPageUrl'] ?? null,
        );
    }
}
```

**src/Api/Response/Consent.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class Consent
{
    /** @param string[] $consentPermissions */
    public function __construct(
        public int $ibanId,
        public ?string $consentId,
        public ?string $iban,
        public ?array $consentPermissions,
        public ?string $validUntil,
        public int $frequencyPerDay,
        public ?string $givenAt,
        public ?string $status,
        public ?string $country,
        public ?string $bankHash,
        public ?string $bankName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ibanId: (int) $data['ibanId'],
            consentId: $data['consentId'] ?? null,
            iban: $data['iban'] ?? null,
            consentPermissions: $data['consentPermissions'] ?? null,
            validUntil: $data['validUntil'] ?? null,
            frequencyPerDay: (int) ($data['frequencyPerDay'] ?? 0),
            givenAt: $data['givenAt'] ?? null,
            status: $data['status'] ?? null,
            country: $data['country'] ?? null,
            bankHash: $data['bankHash'] ?? null,
            bankName: $data['bankName'] ?? null,
        );
    }
}
```

**src/Api/Response/ConsentDetails.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ConsentDetails
{
    public function __construct(
        public string $dateCreated,
        public ?string $iban,
        public ?string $currency,
        public ?string $ownerName,
        public ?string $consentStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            dateCreated: $data['dateCreated'],
            iban: $data['iban'] ?? null,
            currency: $data['currency'] ?? null,
            ownerName: $data['ownerName'] ?? null,
            consentStatus: $data['consentStatus'] ?? null,
        );
    }
}
```

**src/Api/Response/TokenInfo.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\TokenType;

final readonly class TokenInfo
{
    public function __construct(
        public int $id,
        public int $accountId,
        public string $token,
        public TokenType $type,
        public int $bankId,
        public int $psuId,
        public string $psuIdentifier,
        public string $dateCreated,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            accountId: (int) $data['accountId'],
            token: $data['token'],
            type: TokenType::from($data['type']),
            bankId: (int) $data['bankId'],
            psuId: (int) $data['psuId'],
            psuIdentifier: $data['psuIdentifier'],
            dateCreated: $data['dateCreated'],
        );
    }
}
```

**src/Api/Request/CreateConsentData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class CreateConsentData
{
    public function __construct(
        public string $bankHash,
        public ?string $username = null,
        public ?string $iban = null,
        public ?string $hookHash = null,
        public ?string $sms = null,
        public ?string $authorizationUrl = null,
        public ?string $authorizationId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'bankHash' => $this->bankHash,
            'username' => $this->username,
            'iban' => $this->iban,
            'hookHash' => $this->hookHash,
            'sms' => $this->sms,
            'authorizationUrl' => $this->authorizationUrl,
            'authorizationId' => $this->authorizationId,
        ], fn ($v) => $v !== null);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Api/Response/ src/Api/Request/CreateConsentData.php && git commit -m "feat: add Account response DTOs (BankInfo, BankAccount, Transaction, Balance, Consent, TokenInfo, etc.) and CreateConsentData request"
```

---

### Task 20: AccountClient + Tests

**Files:**
- Create: `src/Api/AccountClient.php`
- Create: `tests/Api/AccountClientTest.php`

- [ ] **Step 1: Implement AccountClient**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\CreateConsentData;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\Consent;
use Ux2Dev\Iris\Api\Response\ConsentDetails;
use Ux2Dev\Iris\Api\Response\TokenInfo;
use Ux2Dev\Iris\Api\Response\Transaction;
use Ux2Dev\Iris\Api\Response\TransactionList;

final class AccountClient extends BaseClient
{
    /** @return BankInfo[] */
    public function listBanks(string $userHash, ?string $country = null): array
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson('/api/8/banks', $this->userHeaders($userHash), $query);

        return array_map(fn (array $b) => BankInfo::fromArray($b), $data);
    }

    public function getBank(string $userHash, string $bankHash, ?string $country = null): BankInfo
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson("/api/8/banks/{$bankHash}", $this->userHeaders($userHash), $query);

        return BankInfo::fromArray($data);
    }

    public function getBankSca(string $userHash, string $bankHash): BankSca
    {
        $data = $this->postEmpty("/api/8/bank/{$bankHash}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    /** @return BankAccount[] */
    public function listIbans(string $userHash, bool $consentDetails = false): array
    {
        $headers = $this->userHeaders($userHash);
        if ($consentDetails) {
            $headers['consent-details'] = 'true';
        }

        $data = $this->getJson('/api/8/ibans', $headers);

        return array_map(fn (array $a) => BankAccount::fromArray($a), $data);
    }

    public function deleteIban(string $userHash, int $ibanId): void
    {
        $this->deleteVoid("/api/8/iban/{$ibanId}", $this->userHeaders($userHash));
    }

    public function getBalance(string $userHash, int $ibanId): BalanceList
    {
        $data = $this->getJson("/api/8/balance/{$ibanId}", $this->userHeaders($userHash));

        return BalanceList::fromArray($data);
    }

    public function listTransactions(
        string $userHash,
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }

        $data = $this->getJson("/api/8/transactions/{$ibanId}", $this->userHeaders($userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function listPagedTransactions(
        string $userHash,
        int $ibanId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $nextPageUrl = null,
    ): TransactionList {
        $query = [];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }
        if ($nextPageUrl !== null) { $query['nextPageUrl'] = $nextPageUrl; }

        $data = $this->getJson("/api/8/paged-transactions/{$ibanId}", $this->userHeaders($userHash), $query);

        return TransactionList::fromArray($data);
    }

    public function getTransaction(string $userHash, int $ibanId, string $transactionId): Transaction
    {
        $data = $this->getJson("/api/8/transactions/{$ibanId}/{$transactionId}", $this->userHeaders($userHash));

        return Transaction::fromArray($data);
    }

    /** @return TokenInfo[] */
    public function listTokens(string $userHash, ?string $country = null): array
    {
        $query = [];
        if ($country !== null) { $query['country'] = $country; }

        $data = $this->getJson('/api/8/tokens', $this->userHeaders($userHash), $query);

        return array_map(fn (array $t) => TokenInfo::fromArray($t), $data);
    }

    public function createConsent(string $userHash, CreateConsentData $data): BankSca
    {
        $response = $this->postJson('/api/8/consent', $data->toArray(), $this->userHeaders($userHash));

        return BankSca::fromArray($response);
    }

    /** @return Consent[] */
    public function getConsents(string $userHash, int $ibanId): array
    {
        $data = $this->getJson("/api/8/consents/{$ibanId}", $this->userHeaders($userHash));
        $consents = $data['consents'] ?? $data;

        return array_map(fn (array $c) => Consent::fromArray($c), is_array($consents) ? $consents : []);
    }

    public function getConsentDetails(string $iban): ConsentDetails
    {
        $data = $this->getJson("/api/8/consent/iban/{$iban}", $this->agentHeaders());

        return ConsentDetails::fromArray($data);
    }
}
```

- [ ] **Step 2: Write tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\AccountClient;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\ConsentDetails;
use Ux2Dev\Iris\Api\Response\TransactionList;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;

function createAccountClient(array $responses): AccountClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new AccountClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('listBanks returns array of BankInfo', function () {
    $client = createAccountClient([
        new Response(200, [], json_encode([[
            'bankHash' => 'bank-1', 'name' => 'Test Bank', 'urlLogo' => null, 'urlDarkLogo' => null,
            'sca' => 'REDIRECT_URL', 'firstStepInstruction' => null, 'directPayment' => true,
            'paymentRequiresIban' => false, 'fullName' => 'Test Bank Full', 'bic' => 'TESTBG',
            'services' => 'AIS,PIS', 'country' => 'bulgaria', 'videos' => [],
            'consentRequiresIban' => false, 'consentRequiresPsu' => true,
            'paymentRequiresAuthorization' => true, 'consentRequiresAuthorization' => false,
            'paymentRequiresPsu' => true, 'psuType' => 'USERNAME',
            'budgetPaymentsRequirePaymentCategory' => false, 'aisAvailable' => true, 'pisAvailable' => true,
        ]])),
    ]);

    $banks = $client->listBanks('user-hash');

    expect($banks)->toHaveCount(1);
    expect($banks[0])->toBeInstanceOf(BankInfo::class);
    expect($banks[0]->name)->toBe('Test Bank');
    expect($banks[0]->directPayment)->toBeTrue();
});

test('listIbans returns array of BankAccount', function () {
    $client = createAccountClient([
        new Response(200, [], json_encode([[
            'id' => 1, 'name' => 'Main Account', 'iban' => 'BG12TEST', 'product' => null,
            'ownerName' => 'John', 'currency' => 'BGN', 'hasAuthorization' => true,
            'bankHash' => 'bank-1', 'bankName' => 'Test Bank', 'liteLogoUrl' => null,
            'darkLogoUrl' => null, 'country' => 'bulgaria', 'dateCreate' => '2025-01-01T00:00:00Z',
            'consents' => null, 'fulfilled' => true, 'validUntil' => '2026-01-01', 'frequencyPerDay' => 4,
        ]])),
    ]);

    $accounts = $client->listIbans('user-hash');

    expect($accounts)->toHaveCount(1);
    expect($accounts[0])->toBeInstanceOf(BankAccount::class);
    expect($accounts[0]->iban)->toBe('BG12TEST');
});

test('getBalance returns BalanceList', function () {
    $client = createAccountClient([
        new Response(200, [], json_encode([
            'balances' => [['amount' => 1500.50, 'currency' => 'BGN', 'balanceType' => 'closingBooked', 'referenceDate' => '2025-01-01', 'creditLimitIncluded' => false]],
        ])),
    ]);

    $result = $client->getBalance('user-hash', 1);

    expect($result)->toBeInstanceOf(BalanceList::class);
    expect($result->balances)->toHaveCount(1);
    expect($result->balances[0]->amount)->toBe(1500.50);
});

test('listTransactions returns TransactionList', function () {
    $client = createAccountClient([
        new Response(200, [], json_encode([
            'transactions' => [[
                'transactionId' => 'tx-1', 'bookingDate' => '2025-01-15',
                'creditorAccount' => ['iban' => 'BG12RECV'], 'creditorName' => 'Receiver',
                'debtorAccount' => ['iban' => 'BG12SEND'], 'debtorName' => 'Sender',
                'entryReference' => null, 'remittanceInformationUnstructured' => 'Payment',
                'transactionAmount' => ['amount' => 100.00, 'currency' => 'BGN'],
                'exchangeRate' => null, 'valueDate' => '2025-01-15',
                'creditDebitIndicator' => 'DEBIT', 'category' => null,
            ]],
            'balances' => [],
            'categorySums' => [],
            'nextPageUrl' => null,
        ])),
    ]);

    $result = $client->listTransactions('user-hash', 1);

    expect($result)->toBeInstanceOf(TransactionList::class);
    expect($result->transactions)->toHaveCount(1);
    expect($result->transactions[0]->transactionId)->toBe('tx-1');
});

test('deleteIban sends DELETE without error', function () {
    $client = createAccountClient([new Response(200)]);

    $client->deleteIban('user-hash', 1);

    expect(true)->toBeTrue();
});

test('getConsentDetails returns ConsentDetails', function () {
    $client = createAccountClient([
        new Response(200, [], json_encode([
            'dateCreated' => '2025-01-01T00:00:00Z', 'iban' => 'BG12TEST',
            'currency' => 'BGN', 'ownerName' => 'John', 'consentStatus' => 'valid',
        ])),
    ]);

    $result = $client->getConsentDetails('BG12TEST');

    expect($result)->toBeInstanceOf(ConsentDetails::class);
    expect($result->iban)->toBe('BG12TEST');
});
```

- [ ] **Step 3: Run tests**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Api/AccountClientTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 4: Commit**

```bash
git add src/Api/AccountClient.php tests/Api/AccountClientTest.php && git commit -m "feat: add AccountClient with 13 endpoints (banks, ibans, balances, transactions, consents, tokens)"
```

---

### Task 21: Payment DTOs + PaymentClient + Tests

**Files:**
- Create: `src/Api/Response/Payment.php`
- Create: `src/Api/Response/PaymentStatusDetail.php`
- Create: `src/Api/Response/PaymentUrls.php`
- Create: `src/Api/Response/PaymentConfirmResult.php`
- Create: `src/Api/Response/PaymentResponse.php`
- Create: `src/Api/Request/DirectPaymentData.php`
- Create: `src/Api/Request/IbanPaymentData.php`
- Create: `src/Api/Request/BudgetPaymentData.php`
- Create: `src/Api/Request/BudgetDirectData.php`
- Create: `src/Api/PaymentClient.php`
- Create: `tests/Api/PaymentClientTest.php`

- [ ] **Step 1: Create payment response DTOs**

**src/Api/Response/Payment.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class Payment
{
    public function __construct(
        public string $date,
        public string $payeeName,
        public ?string $payerName,
        public ?BankReference $payerBank,
        public ?BankReference $payeeBank,
        public string $description,
        public string $sum,
        public ?string $payerIban,
        public string $payeeIban,
        public ?string $id,
        public string $currency,
        public PaymentStatus $status,
        public ?string $reasonForFail,
        public bool $authorised,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            payeeName: $data['payeeName'],
            payerName: $data['payerName'] ?? null,
            payerBank: isset($data['payerBank']) ? BankReference::fromArray($data['payerBank']) : null,
            payeeBank: isset($data['payeeBank']) ? BankReference::fromArray($data['payeeBank']) : null,
            description: $data['description'],
            sum: (string) $data['sum'],
            payerIban: $data['payerIban'] ?? null,
            payeeIban: $data['payeeIban'],
            id: $data['id'] ?? null,
            currency: $data['currency'],
            status: PaymentStatus::from($data['status']),
            reasonForFail: $data['reasonForFail'] ?? null,
            authorised: (bool) ($data['authorised'] ?? false),
        );
    }
}
```

**src/Api/Response/PaymentUrls.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentUrls
{
    public function __construct(
        public ?string $startUrl,
        public ?string $endUrl,
        public bool $externalApp,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            startUrl: $data['startUrl'] ?? null,
            endUrl: $data['endUrl'] ?? null,
            externalApp: (bool) ($data['externalApp'] ?? false),
        );
    }
}
```

**src/Api/Response/PaymentStatusDetail.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentStatusDetail
{
    public function __construct(
        public Payment $payment,
        public ?PaymentUrls $urlSca,
        public bool $ready,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            payment: Payment::fromArray($data['payment']),
            urlSca: isset($data['urlSca']) ? PaymentUrls::fromArray($data['urlSca']) : null,
            ready: (bool) ($data['ready'] ?? false),
        );
    }
}
```

**src/Api/Response/PaymentConfirmResult.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentConfirmResult
{
    public function __construct(public string $result) {}

    public function isSuccess(): bool { return $this->result === 'SUCCESS'; }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(result: $data['result']);
    }
}
```

**src/Api/Response/PaymentResponse.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentResponse
{
    public function __construct(
        public string $code,
        public string $expiryDate,
        public ?string $confirmUrl,
        public ?int $ibanId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            expiryDate: $data['expiryDate'],
            confirmUrl: $data['confirmUrl'] ?? null,
            ibanId: isset($data['ibanId']) ? (int) $data['ibanId'] : null,
        );
    }
}
```

- [ ] **Step 2: Create payment request DTOs**

**src/Api/Request/DirectPaymentData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class DirectPaymentData
{
    public function __construct(
        public string $bankHash,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public bool $emailNotification = false,
        public ?string $description = null,
        public ?string $senderIban = null,
        public ?string $psuId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'bankHash' => $this->bankHash,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }
        if ($this->senderIban !== null) { $data['senderIban'] = $this->senderIban; }
        if ($this->psuId !== null) { $data['psuId'] = $this->psuId; }

        return $data;
    }
}
```

**src/Api/Request/IbanPaymentData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class IbanPaymentData
{
    public function __construct(
        public int $fromIbanId,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public bool $emailNotification = false,
        public ?string $description = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'fromIbanId' => $this->fromIbanId,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }

        return $data;
    }
}
```

**src/Api/Request/BudgetPaymentData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\IdentifierType;

final readonly class BudgetPaymentData
{
    public function __construct(
        public int $fromIbanId,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public string $identifier,
        public IdentifierType $identifierType,
        public bool $emailNotification = false,
        public ?string $description = null,
        public ?string $paymentCategory = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'fromIbanId' => $this->fromIbanId,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
            'identifier' => $this->identifier,
            'identifierType' => $this->identifierType->value,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }
        if ($this->paymentCategory !== null) { $data['paymentCategory'] = $this->paymentCategory; }

        return $data;
    }
}
```

**src/Api/Request/BudgetDirectData.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\IdentifierType;

final readonly class BudgetDirectData
{
    public function __construct(
        public string $bankHash,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public string $identifier,
        public IdentifierType $identifierType,
        public bool $emailNotification = false,
        public ?string $description = null,
        public ?string $senderIban = null,
        public ?string $psuId = null,
        public ?string $paymentCategory = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'bankHash' => $this->bankHash,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
            'identifier' => $this->identifier,
            'identifierType' => $this->identifierType->value,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }
        if ($this->senderIban !== null) { $data['senderIban'] = $this->senderIban; }
        if ($this->psuId !== null) { $data['psuId'] = $this->psuId; }
        if ($this->paymentCategory !== null) { $data['paymentCategory'] = $this->paymentCategory; }

        return $data;
    }
}
```

- [ ] **Step 3: Implement PaymentClient**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\BudgetDirectData;
use Ux2Dev\Iris\Api\Request\BudgetPaymentData;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\Payment;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;

final class PaymentClient extends BaseClient
{
    public function createDirectPayment(string $userHash, DirectPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/payment/direct', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createDirectInitiate(string $userHash, DirectPaymentData $data): BankScaUrl
    {
        $response = $this->postJson('/api/8/payment/direct-initiate', $data->toArray(), $this->userHeaders($userHash));

        return BankScaUrl::fromArray($response);
    }

    public function createIbanPayment(string $userHash, IbanPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/payment/iban', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function confirmPayment(string $userHash, string $code, int $ibanId): void
    {
        $this->putJson('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->userHeaders($userHash));
    }

    public function confirmPaymentWithResult(string $userHash, string $code, int $ibanId): PaymentConfirmResult
    {
        $response = $this->postJson('/api/8/payment', ['code' => $code, 'ibanId' => $ibanId], $this->userHeaders($userHash));

        return PaymentConfirmResult::fromArray($response);
    }

    public function confirmPaymentSms(string $userHash, string $code, int $ibanId, string $smsCode): void
    {
        $this->putJson('/api/8/payment/verify-sms', [
            'code' => $code, 'ibanId' => $ibanId, 'smsCode' => $smsCode,
        ], $this->userHeaders($userHash));
    }

    public function getStatusByHookHash(string $hookHash): Payment
    {
        $data = $this->getJson("/api/8/status/{$hookHash}", $this->agentHeaders());

        return Payment::fromArray($data);
    }

    public function getStatusByCode(string $userHash, string $code): PaymentStatusDetail
    {
        $data = $this->getJson('/api/8/payment/status', $this->userHeaders($userHash), ['code' => $code]);

        return PaymentStatusDetail::fromArray($data);
    }

    public function getPaymentAuthorization(string $userHash, int $ibanId): BankSca
    {
        $data = $this->getJson("/api/8/payment/ibans/{$ibanId}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    public function getPaymentSca(string $userHash, int $ibanId): BankSca
    {
        $data = $this->postEmpty("/api/8/payment/sca/{$ibanId}", $this->userHeaders($userHash));

        return BankSca::fromArray($data);
    }

    public function createBudgetPayment(string $userHash, BudgetPaymentData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/budget-request', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }

    public function createBudgetDirectPayment(string $userHash, BudgetDirectData $data): PaymentResponse
    {
        $response = $this->postJson('/api/8/budget-request/no-iban', $data->toArray(), $this->userHeaders($userHash));

        return PaymentResponse::fromArray($response);
    }
}
```

- [ ] **Step 4: Write tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\PaymentClient;
use Ux2Dev\Iris\Api\Request\DirectPaymentData;
use Ux2Dev\Iris\Api\Request\IbanPaymentData;
use Ux2Dev\Iris\Api\Response\BankScaUrl;
use Ux2Dev\Iris\Api\Response\Payment;
use Ux2Dev\Iris\Api\Response\PaymentConfirmResult;
use Ux2Dev\Iris\Api\Response\PaymentResponse;
use Ux2Dev\Iris\Api\Response\PaymentStatusDetail;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;

function createPaymentClient(array $responses): PaymentClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PaymentClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'test-agent-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('createDirectPayment returns PaymentResponse', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['code' => 'PAY-001', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ]);

    $result = $client->createDirectPayment('user-hash', new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(PaymentResponse::class);
    expect($result->code)->toBe('PAY-001');
});

test('createDirectInitiate returns BankScaUrl', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['url' => 'https://bank.example.com/sca', 'bankSCA' => 'REDIRECT_URL'])),
    ]);

    $result = $client->createDirectInitiate('user-hash', new DirectPaymentData(
        bankHash: 'bank-1', receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 100.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(BankScaUrl::class);
    expect($result->url)->toBe('https://bank.example.com/sca');
});

test('createIbanPayment returns PaymentResponse', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['code' => 'PAY-002', 'expiryDate' => '2025-12-31T23:59:59Z', 'confirmUrl' => null, 'ibanId' => 1])),
    ]);

    $result = $client->createIbanPayment('user-hash', new IbanPaymentData(
        fromIbanId: 1, receiverIban: 'BG12RECV', receiverName: 'Receiver',
        remittanceDescription: 'Test', sum: 50.00, currency: 'BGN', hookHash: 'hook-1',
    ));

    expect($result)->toBeInstanceOf(PaymentResponse::class);
    expect($result->code)->toBe('PAY-002');
});

test('confirmPaymentWithResult returns PaymentConfirmResult', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode(['result' => 'SUCCESS'])),
    ]);

    $result = $client->confirmPaymentWithResult('user-hash', 'PAY-001', 1);

    expect($result)->toBeInstanceOf(PaymentConfirmResult::class);
    expect($result->isSuccess())->toBeTrue();
});

test('getStatusByHookHash returns Payment', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode([
            'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver', 'payerName' => 'Sender',
            'payerBank' => ['bankHash' => 'b1', 'name' => 'Bank', 'country' => 'bulgaria'],
            'payeeBank' => null, 'description' => 'Test', 'sum' => '100.00',
            'payerIban' => 'BG12SEND', 'payeeIban' => 'BG12RECV', 'id' => null,
            'currency' => 'BGN', 'status' => 'CONFIRMED', 'reasonForFail' => null, 'authorised' => true,
        ])),
    ]);

    $result = $client->getStatusByHookHash('hook-1');

    expect($result)->toBeInstanceOf(Payment::class);
    expect($result->status)->toBe(\Ux2Dev\Iris\Enum\PaymentStatus::Confirmed);
});

test('getStatusByCode returns PaymentStatusDetail', function () {
    $client = createPaymentClient([
        new Response(200, [], json_encode([
            'payment' => [
                'date' => '2025-01-15T10:00:00Z', 'payeeName' => 'Receiver', 'payerName' => null,
                'payerBank' => null, 'payeeBank' => null, 'description' => 'Test', 'sum' => '100',
                'payerIban' => null, 'payeeIban' => 'BG12RECV', 'id' => null,
                'currency' => 'BGN', 'status' => 'WAITING', 'reasonForFail' => null, 'authorised' => false,
            ],
            'urlSca' => ['startUrl' => 'https://bank.example.com', 'endUrl' => null, 'externalApp' => false],
            'ready' => false,
        ])),
    ]);

    $result = $client->getStatusByCode('user-hash', 'PAY-001');

    expect($result)->toBeInstanceOf(PaymentStatusDetail::class);
    expect($result->ready)->toBeFalse();
});
```

- [ ] **Step 5: Run tests and commit**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Api/PaymentClientTest.php`
Expected: All 6 tests PASS.

```bash
git add src/Api/Response/Payment.php src/Api/Response/PaymentUrls.php src/Api/Response/PaymentStatusDetail.php src/Api/Response/PaymentConfirmResult.php src/Api/Response/PaymentResponse.php src/Api/Request/DirectPaymentData.php src/Api/Request/IbanPaymentData.php src/Api/Request/BudgetPaymentData.php src/Api/Request/BudgetDirectData.php src/Api/PaymentClient.php tests/Api/PaymentClientTest.php && git commit -m "feat: add PaymentClient with 12 endpoints (direct, iban, budget, confirm, status, SCA)"
```

---

### Task 22: BulkPayment DTOs + BulkPaymentClient + Tests

**Files:**
- Create: `src/Api/Response/BulkPaymentEntry.php`
- Create: `src/Api/Response/BulkPayment.php`
- Create: `src/Api/Response/BulkPaymentV2.php`
- Create: `src/Api/Response/BulkPaymentsList.php`
- Create: `src/Api/Request/BulkEntry.php`
- Create: `src/Api/Request/BulkBudgetEntry.php`
- Create: `src/Api/Request/BulkPaymentData.php`
- Create: `src/Api/Request/BulkIbanPaymentData.php`
- Create: `src/Api/Request/BulkBudgetData.php`
- Create: `src/Api/Request/BulkBudgetIbanData.php`
- Create: `src/Api/BulkPaymentClient.php`
- Create: `tests/Api/BulkPaymentClientTest.php`

- [ ] **Step 1: Create bulk payment DTOs**

Response DTOs follow the same readonly + fromArray pattern. Request DTOs follow the same toArray pattern. BulkEntry and BulkBudgetEntry are simple nested value objects used inside the request DTOs.

Key structures:

**BulkEntry:** `receiverIban`, `receiverName`, `remittanceDescription`, `sum` (float)

**BulkBudgetEntry:** extends BulkEntry with `identifier`, `identifierType` (IdentifierType enum), `paymentCategory`

**BulkPaymentData:** `bankHash`, `currency`, `hookHash`, `emailNotification`, `senderIban`, `psuId`, `requestedExecutionDate`, `payments` (BulkEntry[])

**BulkIbanPaymentData:** `currency`, `hookHash`, `emailNotification`, `fromIbanId`, `requestedExecutionDate`, `payments` (BulkEntry[])

**BulkBudgetData / BulkBudgetIbanData:** same structure but with BulkBudgetEntry[] for payments

**BulkPaymentEntry (response):** `payeeName`, `description`, `sum`, `payeeIban`, `currency`, `status` (PaymentStatus)

**BulkPayment (response):** `date`, `status`, `reasonForFail`, `payerBank` (BankReference), `payments` (BulkPaymentEntry[])

**BulkPaymentV2 (response):** extends BulkPayment with `paymentType` (PaymentType)

**BulkPaymentsList (response):** `pages`, `page`, `payments` (BulkPaymentV2[])

**BulkPaymentClient methods:**
- `createBulkPayment(userHash, BulkPaymentData): PaymentResponse`
- `createBulkIbanPayment(userHash, BulkIbanPaymentData): PaymentResponse`
- `createBulkBudgetPayment(userHash, BulkBudgetData): PaymentResponse`
- `createBulkBudgetIbanPayment(userHash, BulkBudgetIbanData): PaymentResponse`
- `getBulkStatus(hookHash): BulkPayment` (uses agentHeaders)
- `searchBulkPayments(PaymentSearchData): BulkPaymentsList`

Implement all files following the exact same patterns as Tasks 17-21. Each DTO is a readonly class with fromArray() or toArray(). The client extends BaseClient.

- [ ] **Step 2: Write tests, run, commit**

Test at least: createBulkPayment, getBulkStatus, searchBulkPayments.

```bash
git commit -m "feat: add BulkPaymentClient with 6 endpoints (bulk payment, bulk budget, status, search)"
```

---

### Task 23: Report DTOs + ReportClient + Tests

**Files:**
- Create: `src/Api/Response/PaymentListEntry.php`
- Create: `src/Api/Response/PaymentsList.php`
- Create: `src/Api/Response/PaymentSearchEntry.php`
- Create: `src/Api/Response/PaymentSearchResult.php`
- Create: `src/Api/Response/ActiveUsers.php`
- Create: `src/Api/Response/ActiveUsersDetails.php`
- Create: `src/Api/Response/BankMaintenance.php`
- Create: `src/Api/Request/PaymentSearchData.php`
- Create: `src/Api/Request/ActiveUsersDetailsData.php`
- Create: `src/Api/ReportClient.php`
- Create: `tests/Api/ReportClientTest.php`

- [ ] **Step 1: Create DTOs and client**

**Key structures:**

**PaymentListEntry:** `date`, `description`, `payerName`, `payerIban`, `sum`, `status` (PaymentStatus)

**PaymentsList:** `size`, `page`, `payments` (PaymentListEntry[])

**PaymentSearchEntry:** extends PaymentListEntry with `payerBankName`, `payerBankCountry`, `payeeName`, `payeeIban`, `currency`, `remittanceDescription`, `paymentType` (PaymentType)

**PaymentSearchResult:** `pages`, `page`, `elementsSize`, `payments` (PaymentSearchEntry[])

**ActiveUsers:** `bankAccounts` (int), `users` (int)

**ActiveUsersDetails:** `bankAccounts` (int[]), `users` (string[]), `pages`, `page`, `elementsSize`

**BankMaintenance:** `bankName`, `maintenanceMessage`, `fromDate`, `toDate`

**PaymentSearchData:** `page`, `size`, `statuses` (PaymentStatus[]), `publicHash`, `userHash`, `agentHash`, `fromDate`, `toDate`

**ReportClient methods:**
- `listPayments(string $userHash, int $page, int $size, ?string $status): PaymentsList` (uses agentHeaders)
- `searchPayments(PaymentSearchData $data): PaymentSearchResult`
- `getActiveUsers(string $fromDate, string $toDate, ?string $validUntil): ActiveUsers` (uses agentHeaders)
- `getActiveUsersDetails(ActiveUsersDetailsData $data): ActiveUsersDetails` (uses agentHeaders)
- `getBankMaintenance(): BankMaintenance[]`

- [ ] **Step 2: Write tests, run, commit**

```bash
git commit -m "feat: add ReportClient with 5 endpoints (payments list, search, active users, bank maintenance)"
```

---

### Task 24: ConsentGate DTOs + ConsentGateClient + Tests

**Files:**
- Create: `src/Api/Response/ConsentGateResponse.php`
- Create: `src/Api/Response/ConsentGateStatus.php`
- Create: `src/Api/Response/ConsentGateUi.php`
- Create: `src/Api/Request/ConsentGateRequestData.php`
- Create: `src/Api/ConsentGateClient.php`
- Create: `tests/Api/ConsentGateClientTest.php`

- [ ] **Step 1: Create DTOs**

**ConsentGateResponse:** `userHash`, `url`, `ibansIds` (array<string, int>)

**ConsentGateStatus:** `ibanId`, `iban`, `fulfilled`, `validUntil`, `frequencyPerDay`

**ConsentGateUi:** `id`, `accountId`, `expirationDate`, `timeout`, `showValidUntil`, `showFrequencyPerDay`, `expirationMessage`, `successMessage`, `deleted`

**ConsentGateRequestData:** `userInfo` (array with firstName, middleName, lastName, email, phone, uic), `ibans` (string[])

- [ ] **Step 2: Implement ConsentGateClient**

This client uses different auth -- `x-admin-hash` + `x-agent-hash`. It does NOT extend BaseClient (different auth model). It has its own constructor that validates adminHash and agentHash.

```php
final class ConsentGateClient
{
    public function __construct(
        private readonly MerchantConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        if ($config->adminHash === null) {
            throw new ConfigurationException('adminHash is required for Consent Gate API');
        }
        if ($config->agentHash === null) {
            throw new ConfigurationException('agentHash is required for Consent Gate API');
        }
    }

    public function createConsentRequest(ConsentGateRequestData $data): ConsentGateResponse { ... }
    public function getConsents(string $userHash, bool $fulfilled = true): array { ... } // returns ConsentGateStatus[]
    public function getUiConsentRequest(string $userHash): ConsentGateUi { ... }
}
```

- [ ] **Step 3: Tests, run, commit**

```bash
git commit -m "feat: add ConsentGateClient with 3 cgate endpoints"
```

---

### Task 25: Update IrisManager + Laravel Config

**Files:**
- Modify: `src/Laravel/IrisManager.php`
- Modify: `src/Laravel/IrisFacade.php`
- Modify: `src/Laravel/config/iris.php`
- Modify: `tests/Laravel/IrisManagerTest.php`

- [ ] **Step 1: Update config/iris.php -- add agent_hash and admin_hash**

```php
'merchants' => [
    'main' => [
        'public_hash' => env('IRIS_PUBLIC_HASH'),
        'agent_hash' => env('IRIS_AGENT_HASH'),
        'admin_hash' => env('IRIS_ADMIN_HASH'),
        'environment' => env('IRIS_ENVIRONMENT', 'production'),
        'currency' => env('IRIS_CURRENCY', 'EUR'),
        'language' => env('IRIS_LANGUAGE', 'bg'),
    ],
],
```

- [ ] **Step 2: Add client methods to IrisManager**

Add methods following the same pattern as `payByLink()`:

```php
public function agent(): AgentClient { ... }
public function account(): AccountClient { ... }
public function payment(): PaymentClient { ... }
public function bulkPayment(): BulkPaymentClient { ... }
public function report(): ReportClient { ... }
public function consentGate(): ConsentGateClient { ... }
```

Each method caches the client instance (same as payByLink pattern).

- [ ] **Step 3: Update IrisFacade PHPDoc**

Add method annotations for all new client methods.

- [ ] **Step 4: Add tests for new client methods**

```php
test('resolves agent client', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => ['main' => ['agent_hash' => 'agent-123', 'environment' => 'development']],
    ]);

    expect($manager->agent())->toBeInstanceOf(AgentClient::class);
});
```

- [ ] **Step 5: Run full suite, commit**

```bash
git commit -m "feat: extend IrisManager with agent(), account(), payment(), bulkPayment(), report(), consentGate() methods"
```

---

### Task 26: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 2: Verify file structure**

Run: `cd /Users/hristolaskov/Herd/iris-pay && find src -name '*.php' | sort | wc -l`
Expected: ~90+ PHP files.

- [ ] **Step 3: Verify all API endpoints are covered**

Cross-reference every v8 endpoint from the Swagger spec against the implemented client methods.

---

## Summary

| Task | What | New Files | Tests |
|------|------|-----------|-------|
| 14 | Foundation updates | 0 (modify 6) | ~9 |
| 15 | API Enums | 10 | 0 |
| 16 | BaseClient | 1 | 0 |
| 17 | Agent DTOs | 8 | 0 |
| 18 | AgentClient + tests | 1+1 | 7 |
| 19 | Account DTOs | 20 | 0 |
| 20 | AccountClient + tests | 1+1 | 6 |
| 21 | Payment DTOs + client | 10+1+1 | 6 |
| 22 | BulkPayment DTOs + client | 11+1+1 | ~4 |
| 23 | Report DTOs + client | 9+1+1 | ~4 |
| 24 | ConsentGate DTOs + client | 4+1+1 | ~3 |
| 25 | IrisManager update | 0 (modify 4) | ~3 |
| 26 | Final verification | 0 | all |
| **Total** | | **~85 new files** | **~42 new tests** |

Combined with base SDK: **~130 files, ~86 tests** covering the entire IRIS Solutions platform.
