# IRIS Pay PHP SDK Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a framework-agnostic PHP SDK for IRIS Solutions payment platform covering the PayByLink/QR API (payment links, QR codes, status checks, refunds) and Web SDK component configuration, with an optional Laravel integration layer.

**Architecture:** Two independent modules sharing common infrastructure (Config, Enums, Exceptions). `PayByLinkClient` is a PSR-18 HTTP client wrapper for the PayByLink/QR REST API (6 endpoints). `ComponentConfig` is a builder for the `irispay-component` web component attributes. Laravel layer follows the Manager pattern (multi-merchant) with ServiceProvider, Facade, Events, webhook controller, and Artisan commands. Mirrors the architecture of `ux2dev/epay-easypay` and `ux2dev/borica`.

**Tech Stack:** PHP 8.2, PSR-18 (HTTP client), PSR-17 (HTTP factories), Pest 4 (testing), Guzzle 7 (dev/test), Laravel 11/12 (optional), Orchestra Testbench 10 (Laravel tests).

**Reference implementations:** `~/Herd/epay` (ux2dev/epay-easypay), `~/Herd/borica` (ux2dev/borica) -- follow the same patterns exactly.

**API documentation:** `~/Herd/iris/PayByLink_QR_v.3.5.2.pdf` (PayByLink/QR API v3.5.2), IRIS Web SDK docs at irisbgsf.com/sdk-documentation.

---

## File Structure

```
iris-pay/
├── src/
│   ├── Config/
│   │   └── MerchantConfig.php                  # publicHash, environment, currency, language
│   ├── Enum/
│   │   ├── BankScaType.php                     # RedirectUrl, CodeRedirectUrl, Push
│   │   ├── ComponentType.php                   # 8 Web SDK component types
│   │   ├── Country.php                         # Bulgaria, Romania, Greece, Croatia, Cyprus
│   │   ├── Currency.php                        # BGN, EUR, RON
│   │   ├── Environment.php                     # Development, Production (with base URLs)
│   │   ├── Language.php                        # bg, en, ro, el, hr, cy
│   │   ├── PaymentStatus.php                   # Waiting, Confirmed, Failed
│   │   └── RefundType.php                      # Full, Partial
│   ├── Exception/
│   │   ├── IrisException.php                   # Base exception (extends RuntimeException)
│   │   ├── ConfigurationException.php          # Config validation errors
│   │   └── InvalidResponseException.php        # HTTP/API errors with response data
│   ├── PayByLink/
│   │   ├── PayByLinkClient.php                 # PSR-18 HTTP client for all 6 endpoints
│   │   └── Response/
│   │       ├── Bank.php                        # DTO: bankHash, name, fullName, bic, services, country
│   │       ├── PaymentLinkResponse.php         # DTO: accountId, paymentHash, paymentLink
│   │       ├── PaymentStatusResponse.php       # DTO: full payment status with payer info
│   │       └── RefundResponse.php              # DTO: bankScaType, url
│   ├── WebSdk/
│   │   └── ComponentConfig.php                 # Builder for irispay-component HTML attributes
│   ├── Webhook/
│   │   └── WebhookParser.php                   # Parses IRIS webhook query-string callbacks
│   └── Laravel/
│       ├── IrisServiceProvider.php             # Registers manager, publishes config/routes, commands
│       ├── IrisFacade.php                      # Static proxy to IrisManager
│       ├── IrisManager.php                     # Multi-merchant manager, lazy client creation
│       ├── Events/
│       │   ├── PaymentConfirmed.php            # Dispatched on status=CONFIRMED webhook
│       │   └── PaymentFailed.php               # Dispatched on status=FAILED webhook
│       ├── Http/
│       │   └── Controllers/
│       │       └── IrisWebhookController.php   # Receives hookUrl callbacks, dispatches events
│       ├── Console/
│       │   └── StatusCheckCommand.php          # artisan iris:status-check {paymentHash}
│       ├── config/
│       │   └── iris.php                        # Laravel config template
│       └── routes/
│           └── iris.php                        # Webhook route
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   ├── Config/
│   │   └── MerchantConfigTest.php
│   ├── Enum/
│   │   └── EnvironmentTest.php
│   ├── PayByLink/
│   │   ├── PayByLinkClientTest.php
│   │   └── Response/
│   │       ├── BankTest.php
│   │       ├── PaymentLinkResponseTest.php
│   │       ├── PaymentStatusResponseTest.php
│   │       └── RefundResponseTest.php
│   ├── WebSdk/
│   │   └── ComponentConfigTest.php
│   ├── Webhook/
│   │   └── WebhookParserTest.php
│   └── Laravel/
│       ├── IrisManagerTest.php
│       └── IrisServiceProviderTest.php
├── composer.json
├── phpunit.xml
└── .gitignore
```

---

## IRIS API Reference (from PDF v3.5.2)

**Base URLs:**
- Development: `https://dev.paybyclick.irispay.bg`
- Production: `https://paybyclick.irispay.bg`

**Endpoints:**
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/backend/payment/banks/{key}` | publicHash in URL | List available banks |
| POST | `/backend/payment/external/{key}` | publicHash in URL | Create payment link |
| GET | `/backend/payment/qr/{hash}` | paymentHash in URL | Get QR code image |
| GET | `/backend/payment/status/{hash}` | paymentHash in URL | Check payment status |
| POST | `/backend/payment/external/refund/{key}` | publicHash in URL | Refund payment |
| PUT | `/backend/payment/inactive/{key}` | publicHash in URL | Deactivate payment link |

**Webhook:** IRIS appends `?status=CONFIRMED` or `?status=FAILED` to the merchant's `hookUrl`.

**Web SDK assets:**
- Dev: `https://developer.sandbox.irispay.bg`
- Prod: `https://developer.irispay.bg`
- JS: `https://websdk.irispay.bg/assets/irispay-ui/elements.js`
- CSS: `https://websdk.irispay.bg/assets/irispay-ui/styles.css`

---

### Task 1: Project Scaffolding

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/Pest.php`
- Create: `tests/TestCase.php`
- Create: `.gitignore`

- [ ] **Step 1: Create composer.json**

```json
{
    "name": "ux2dev/iris-pay",
    "description": "PHP SDK for IRIS Solutions open banking payment platform (PayByLink/QR API, Web SDK)",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "ext-json": "*",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0"
    },
    "require-dev": {
        "pestphp/pest": "^4.0",
        "guzzlehttp/guzzle": "^7.0",
        "orchestra/testbench": "^10.0"
    },
    "suggest": {
        "illuminate/support": "^11.0|^12.0"
    },
    "autoload": {
        "psr-4": {
            "Ux2Dev\\Iris\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Ux2Dev\\Iris\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Ux2Dev\\Iris\\Laravel\\IrisServiceProvider"
            ],
            "aliases": {
                "Iris": "Ux2Dev\\Iris\\Laravel\\IrisFacade"
            }
        }
    },
    "minimum-stability": "stable",
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
```

- [ ] **Step 2: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 3: Create tests/Pest.php**

```php
<?php

declare(strict_types=1);
```

- [ ] **Step 4: Create tests/TestCase.php**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Ux2Dev\Iris\Laravel\IrisServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [IrisServiceProvider::class];
    }
}
```

- [ ] **Step 5: Create .gitignore**

```
/vendor/
/node_modules/
.phpunit.result.cache
.phpunit.cache/
composer.lock
.idea/
.DS_Store
```

- [ ] **Step 6: Install dependencies**

Run: `cd /Users/hristolaskov/Herd/iris-pay && composer install`
Expected: Dependencies installed successfully, autoloader generated.

- [ ] **Step 7: Initialize git and commit**

```bash
cd /Users/hristolaskov/Herd/iris-pay
git init
git add composer.json phpunit.xml tests/Pest.php tests/TestCase.php .gitignore
git commit -m "chore: project scaffolding with composer, pest, and testbench"
```

---

### Task 2: Enums

**Files:**
- Create: `src/Enum/Currency.php`
- Create: `src/Enum/Environment.php`
- Create: `src/Enum/Language.php`
- Create: `src/Enum/PaymentStatus.php`
- Create: `src/Enum/RefundType.php`
- Create: `src/Enum/BankScaType.php`
- Create: `src/Enum/Country.php`
- Create: `src/Enum/ComponentType.php`
- Create: `tests/Enum/EnvironmentTest.php`

- [ ] **Step 1: Write Environment test**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\Environment;

test('development returns correct payByLink base URL', function () {
    expect(Environment::Development->payByLinkBaseUrl())
        ->toBe('https://dev.paybyclick.irispay.bg');
});

test('production returns correct payByLink base URL', function () {
    expect(Environment::Production->payByLinkBaseUrl())
        ->toBe('https://paybyclick.irispay.bg');
});

test('development returns correct webSdk base URL', function () {
    expect(Environment::Development->webSdkBaseUrl())
        ->toBe('https://developer.sandbox.irispay.bg');
});

test('production returns correct webSdk base URL', function () {
    expect(Environment::Production->webSdkBaseUrl())
        ->toBe('https://developer.irispay.bg');
});

test('development returns correct webSdk assets URL', function () {
    expect(Environment::Development->webSdkAssetsUrl())
        ->toBe('https://websdk.sandbox.irispay.bg');
});

test('production returns correct webSdk assets URL', function () {
    expect(Environment::Production->webSdkAssetsUrl())
        ->toBe('https://websdk.irispay.bg');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Enum/EnvironmentTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Create all enum files**

**src/Enum/Currency.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum Currency: string
{
    case BGN = 'BGN';
    case EUR = 'EUR';
    case RON = 'RON';
}
```

**src/Enum/Environment.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum Environment: string
{
    case Development = 'development';
    case Production = 'production';

    public function payByLinkBaseUrl(): string
    {
        return match ($this) {
            self::Development => 'https://dev.paybyclick.irispay.bg',
            self::Production => 'https://paybyclick.irispay.bg',
        };
    }

    public function webSdkBaseUrl(): string
    {
        return match ($this) {
            self::Development => 'https://developer.sandbox.irispay.bg',
            self::Production => 'https://developer.irispay.bg',
        };
    }

    public function webSdkAssetsUrl(): string
    {
        return match ($this) {
            self::Development => 'https://websdk.sandbox.irispay.bg',
            self::Production => 'https://websdk.irispay.bg',
        };
    }
}
```

**src/Enum/Language.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum Language: string
{
    case Bulgarian = 'bg';
    case English = 'en';
    case Romanian = 'ro';
    case Greek = 'el';
    case Croatian = 'hr';
    case Cypriot = 'cy';
}
```

**src/Enum/PaymentStatus.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum PaymentStatus: string
{
    case Waiting = 'WAITING';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
}
```

**src/Enum/RefundType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum RefundType: string
{
    case Full = 'FULL';
    case Partial = 'PARTIAL';
}
```

**src/Enum/BankScaType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum BankScaType: string
{
    case RedirectUrl = 'REDIRECT_URL';
    case CodeRedirectUrl = 'CODE_REDIRECT_URL';
    case Push = 'PUSH';
}
```

**src/Enum/Country.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum Country: string
{
    case Bulgaria = 'bulgaria';
    case Romania = 'romania';
    case Greece = 'greece';
    case Croatia = 'croatia';
    case Cyprus = 'cyprus';
}
```

**src/Enum/ComponentType.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum ComponentType: string
{
    case Payment = 'payment';
    case PayWithIbanSelection = 'pay-with-iban-selection';
    case BudgetPayment = 'budget-payment';
    case PaymentData = 'payment-data';
    case PaymentDataWithAccountId = 'payment-data-with-accountid';
    case PayWithCode = 'pay-with-code';
    case AddIban = 'add-iban';
    case AddIbanWithBank = 'add-iban-with-bank';

    public function requiresHookHash(): bool
    {
        return match ($this) {
            self::Payment,
            self::PayWithIbanSelection,
            self::BudgetPayment,
            self::PaymentData,
            self::PaymentDataWithAccountId,
            self::PayWithCode => true,
            self::AddIban,
            self::AddIbanWithBank => false,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Enum/EnvironmentTest.php`
Expected: All 6 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Enum/ tests/Enum/
git commit -m "feat: add all enums (Currency, Environment, Language, PaymentStatus, RefundType, BankScaType, Country, ComponentType)"
```

---

### Task 3: Exception Hierarchy

**Files:**
- Create: `src/Exception/IrisException.php`
- Create: `src/Exception/ConfigurationException.php`
- Create: `src/Exception/InvalidResponseException.php`

- [ ] **Step 1: Create exception classes**

**src/Exception/IrisException.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

use RuntimeException;

class IrisException extends RuntimeException
{
}
```

**src/Exception/ConfigurationException.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

class ConfigurationException extends IrisException
{
}
```

**src/Exception/InvalidResponseException.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

class InvalidResponseException extends IrisException
{
    /** @var array<string, mixed> */
    private readonly array $responseData;

    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        string $message,
        array $responseData = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->responseData = $responseData;
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, mixed> */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Exception/
git commit -m "feat: add exception hierarchy (IrisException, ConfigurationException, InvalidResponseException)"
```

---

### Task 4: MerchantConfig

**Files:**
- Create: `src/Config/MerchantConfig.php`
- Create: `tests/Config/MerchantConfigTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;

test('creates config with valid publicHash', function () {
    $config = new MerchantConfig(
        publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
        environment: Environment::Development,
    );

    expect($config->publicHash)->toBe('f75fa38d-ca1b-4241-a01a-58965d444aba');
    expect($config->environment)->toBe(Environment::Development);
    expect($config->currency)->toBe(Currency::EUR);
    expect($config->language)->toBe(Language::Bulgarian);
});

test('throws on empty publicHash', function () {
    new MerchantConfig(
        publicHash: '',
        environment: Environment::Development,
    );
})->throws(ConfigurationException::class, 'publicHash must not be empty');

test('accepts custom currency and language', function () {
    $config = new MerchantConfig(
        publicHash: 'test-key',
        environment: Environment::Production,
        currency: Currency::RON,
        language: Language::Romanian,
    );

    expect($config->currency)->toBe(Currency::RON);
    expect($config->language)->toBe(Language::Romanian);
});

test('redacts publicHash in debug info', function () {
    $config = new MerchantConfig(
        publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
        environment: Environment::Development,
    );

    $debug = $config->__debugInfo();
    expect($debug['publicHash'])->toBe('[REDACTED]');
});

test('prevents serialization', function () {
    $config = new MerchantConfig(
        publicHash: 'test-key',
        environment: Environment::Development,
    );

    serialize($config);
})->throws(\LogicException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Config/MerchantConfigTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Implement MerchantConfig**

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
        public string $publicHash,
        public Environment $environment,
        public Currency $currency = Currency::EUR,
        public Language $language = Language::Bulgarian,
    ) {
        if ($publicHash === '') {
            throw new ConfigurationException('publicHash must not be empty');
        }
    }

    public function __debugInfo(): array
    {
        return [
            'publicHash' => '[REDACTED]',
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

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Config/MerchantConfigTest.php`
Expected: All 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Config/ tests/Config/
git commit -m "feat: add MerchantConfig with validation and serialization protection"
```

---

### Task 5: PayByLink Response DTOs

**Files:**
- Create: `src/PayByLink/Response/Bank.php`
- Create: `src/PayByLink/Response/PaymentLinkResponse.php`
- Create: `src/PayByLink/Response/PaymentStatusResponse.php`
- Create: `src/PayByLink/Response/RefundResponse.php`
- Create: `tests/PayByLink/Response/BankTest.php`
- Create: `tests/PayByLink/Response/PaymentLinkResponseTest.php`
- Create: `tests/PayByLink/Response/PaymentStatusResponseTest.php`
- Create: `tests/PayByLink/Response/RefundResponseTest.php`

- [ ] **Step 1: Write all response DTO tests**

**tests/PayByLink/Response/BankTest.php:**
```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\PayByLink\Response\Bank;

test('creates bank from API response array', function () {
    $bank = Bank::fromArray([
        'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
        'name' => 'Gringotts',
        'fullName' => 'Gringotts Bank',
        'bic' => 'example',
        'services' => 'Account Information Services and Payment Initiation Services',
        'country' => 'bulgaria',
    ]);

    expect($bank->bankHash)->toBe('bf935ea4814061d70902683c1565fa2c');
    expect($bank->name)->toBe('Gringotts');
    expect($bank->fullName)->toBe('Gringotts Bank');
    expect($bank->bic)->toBe('example');
    expect($bank->services)->toBe('Account Information Services and Payment Initiation Services');
    expect($bank->country)->toBe('bulgaria');
});
```

**tests/PayByLink/Response/PaymentLinkResponseTest.php:**
```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;

test('creates payment link response from API response array', function () {
    $response = PaymentLinkResponse::fromArray([
        'accountId' => 'acc-123',
        'paymentHash' => 'hash-456',
        'paymentLink' => 'https://paybyclick.irispay.bg/payment/hash-456',
    ]);

    expect($response->accountId)->toBe('acc-123');
    expect($response->paymentHash)->toBe('hash-456');
    expect($response->paymentLink)->toBe('https://paybyclick.irispay.bg/payment/hash-456');
});
```

**tests/PayByLink/Response/PaymentStatusResponseTest.php:**
```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;

test('creates payment status response from API response array', function () {
    $response = PaymentStatusResponse::fromArray([
        'currency' => 'EUR',
        'date' => '2025-10-08T13:02:20.901Z',
        'description' => 'Test payment',
        'orderId' => 'ORD-001',
        'payerBank' => 'Gringotts Bank',
        'payerIban' => 'BG28TEST91556789845726',
        'payerName' => 'John Doe',
        'receiverIban' => 'BG31UNCR70001526254645',
        'status' => 'CONFIRMED',
        'sum' => 9.99,
    ]);

    expect($response->status)->toBe(PaymentStatus::Confirmed);
    expect($response->sum)->toBe(9.99);
    expect($response->payerName)->toBe('John Doe');
    expect($response->currency)->toBe('EUR');
    expect($response->orderId)->toBe('ORD-001');
});

test('handles null optional fields', function () {
    $response = PaymentStatusResponse::fromArray([
        'currency' => 'EUR',
        'date' => '2025-10-08T13:02:20.901Z',
        'description' => 'Test',
        'payerBank' => '',
        'payerIban' => '',
        'payerName' => '',
        'receiverIban' => 'BG31UNCR70001526254645',
        'status' => 'WAITING',
        'sum' => 0,
    ]);

    expect($response->status)->toBe(PaymentStatus::Waiting);
    expect($response->orderId)->toBeNull();
});
```

**tests/PayByLink/Response/RefundResponseTest.php:**
```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\BankScaType;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

test('creates refund response from API response array', function () {
    $response = RefundResponse::fromArray([
        'bankScaType' => 'REDIRECT_URL',
        'url' => 'https://bank.example.com/authorize/123',
    ]);

    expect($response->bankScaType)->toBe(BankScaType::RedirectUrl);
    expect($response->url)->toBe('https://bank.example.com/authorize/123');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/PayByLink/Response/`
Expected: FAIL -- classes not found.

- [ ] **Step 3: Implement all response DTOs**

**src/PayByLink/Response/Bank.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

final readonly class Bank
{
    public function __construct(
        public string $bankHash,
        public string $name,
        public string $fullName,
        public string $bic,
        public string $services,
        public string $country,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'],
            name: $data['name'],
            fullName: $data['fullName'],
            bic: $data['bic'],
            services: $data['services'],
            country: $data['country'],
        );
    }
}
```

**src/PayByLink/Response/PaymentLinkResponse.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

final readonly class PaymentLinkResponse
{
    public function __construct(
        public string $accountId,
        public string $paymentHash,
        public string $paymentLink,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: $data['accountId'],
            paymentHash: $data['paymentHash'],
            paymentLink: $data['paymentLink'],
        );
    }
}
```

**src/PayByLink/Response/PaymentStatusResponse.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentStatusResponse
{
    public function __construct(
        public string $currency,
        public string $date,
        public string $description,
        public ?string $orderId,
        public string $payerBank,
        public string $payerIban,
        public string $payerName,
        public string $receiverIban,
        public PaymentStatus $status,
        public float $sum,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->status === PaymentStatus::Confirmed;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isWaiting(): bool
    {
        return $this->status === PaymentStatus::Waiting;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'],
            date: $data['date'],
            description: $data['description'],
            orderId: $data['orderId'] ?? null,
            payerBank: $data['payerBank'],
            payerIban: $data['payerIban'],
            payerName: $data['payerName'],
            receiverIban: $data['receiverIban'],
            status: PaymentStatus::from($data['status']),
            sum: (float) $data['sum'],
        );
    }
}
```

**src/PayByLink/Response/RefundResponse.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink\Response;

use Ux2Dev\Iris\Enum\BankScaType;

final readonly class RefundResponse
{
    public function __construct(
        public BankScaType $bankScaType,
        public string $url,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankScaType: BankScaType::from($data['bankScaType']),
            url: $data['url'],
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/PayByLink/Response/`
Expected: All 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/PayByLink/Response/ tests/PayByLink/Response/
git commit -m "feat: add PayByLink response DTOs (Bank, PaymentLinkResponse, PaymentStatusResponse, RefundResponse)"
```

---

### Task 6: PayByLinkClient

**Files:**
- Create: `src/PayByLink/PayByLinkClient.php`
- Create: `tests/PayByLink/PayByLinkClientTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

function createClient(array $responses): PayByLinkClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new PayByLinkClient(
        config: new MerchantConfig(
            publicHash: 'f75fa38d-ca1b-4241-a01a-58965d444aba',
            environment: Environment::Development,
        ),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('getBanks returns array of Bank objects', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
                'name' => 'Gringotts',
                'fullName' => 'Gringotts Bank',
                'bic' => 'example',
                'services' => 'Account Information Services and Payment Initiation Services',
                'country' => 'bulgaria',
            ],
        ])),
    ]);

    $banks = $client->getBanks();

    expect($banks)->toHaveCount(1);
    expect($banks[0])->toBeInstanceOf(Bank::class);
    expect($banks[0]->name)->toBe('Gringotts');
});

test('createPaymentLink returns PaymentLinkResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'accountId' => 'acc-123',
            'paymentHash' => 'hash-456',
            'paymentLink' => 'https://dev.paybyclick.irispay.bg/payment/hash-456',
        ])),
    ]);

    $response = $client->createPaymentLink(
        sum: 9.99,
        description: 'Test payment',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );

    expect($response)->toBeInstanceOf(PaymentLinkResponse::class);
    expect($response->paymentHash)->toBe('hash-456');
});

test('createPaymentLink validates sum is positive', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 0,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'sum must be greater than 0');

test('createPaymentLink validates description length', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: str_repeat('a', 241),
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'description must not exceed 240 characters');

test('createPaymentLink validates hookUrl is https', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'http://example.com/webhook',
        redirectUrl: 'https://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'hookUrl must use https://');

test('createPaymentLink validates redirectUrl is https', function () {
    $client = createClient([]);

    $client->createPaymentLink(
        sum: 1.00,
        description: 'Test',
        toIban: 'BG31UNCR70001526254645',
        hookUrl: 'https://example.com/webhook',
        redirectUrl: 'http://example.com/redirect',
    );
})->throws(ConfigurationException::class, 'redirectUrl must use https://');

test('getQrCode returns image bytes', function () {
    $imageBytes = 'fake-jpeg-bytes';
    $client = createClient([
        new Response(200, ['Content-Type' => 'image/jpeg'], $imageBytes),
    ]);

    $result = $client->getQrCode('hash-456');

    expect($result)->toBe($imageBytes);
});

test('getPaymentStatus returns PaymentStatusResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'currency' => 'EUR',
            'date' => '2025-10-08T13:02:20.901Z',
            'description' => 'Test payment',
            'orderId' => 'ORD-001',
            'payerBank' => 'Gringotts Bank',
            'payerIban' => 'BG28TEST91556789845726',
            'payerName' => 'John Doe',
            'receiverIban' => 'BG31UNCR70001526254645',
            'status' => 'CONFIRMED',
            'sum' => 9.99,
        ])),
    ]);

    $response = $client->getPaymentStatus('hash-456');

    expect($response)->toBeInstanceOf(PaymentStatusResponse::class);
    expect($response->isConfirmed())->toBeTrue();
});

test('refund returns RefundResponse', function () {
    $client = createClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'bankScaType' => 'REDIRECT_URL',
            'url' => 'https://bank.example.com/authorize/123',
        ])),
    ]);

    $response = $client->refund(
        paymentHash: 'hash-456',
        refundType: RefundType::Full,
        sum: 9.99,
        remittanceDescription: 'Full refund',
        webhookUrl: 'https://example.com/refund-webhook',
    );

    expect($response)->toBeInstanceOf(RefundResponse::class);
    expect($response->url)->toBe('https://bank.example.com/authorize/123');
});

test('deactivate sends PUT request without error', function () {
    $client = createClient([
        new Response(200),
    ]);

    $client->deactivate('hash-456');

    // No exception means success
    expect(true)->toBeTrue();
});

test('throws InvalidResponseException on non-2xx response', function () {
    $client = createClient([
        new Response(400, [], json_encode(['error' => 'Bad Request'])),
    ]);

    $client->getBanks();
})->throws(InvalidResponseException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/PayByLink/PayByLinkClientTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Implement PayByLinkClient**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\PayByLink;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Enum\RefundType;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\PayByLink\Response\Bank;
use Ux2Dev\Iris\PayByLink\Response\PaymentLinkResponse;
use Ux2Dev\Iris\PayByLink\Response\PaymentStatusResponse;
use Ux2Dev\Iris\PayByLink\Response\RefundResponse;

final class PayByLinkClient
{
    public function __construct(
        private readonly MerchantConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    /** @return Bank[] */
    public function getBanks(): array
    {
        $data = $this->sendGet("/backend/payment/banks/{$this->config->publicHash}");

        return array_map(fn (array $bank) => Bank::fromArray($bank), $data);
    }

    /**
     * @param string[] $bankHashes
     */
    public function createPaymentLink(
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

        $data = $this->sendPost("/backend/payment/external/{$this->config->publicHash}", $body);

        return PaymentLinkResponse::fromArray($data);
    }

    public function getQrCode(string $paymentHash): string
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUrl() . "/backend/payment/qr/{$paymentHash}",
        )->withHeader('Accept', '*/*');

        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new InvalidResponseException(
                "HTTP {$statusCode} from QR code endpoint",
                ['status' => $statusCode],
            );
        }

        return (string) $response->getBody();
    }

    public function getPaymentStatus(string $paymentHash): PaymentStatusResponse
    {
        $data = $this->sendGet("/backend/payment/status/{$paymentHash}");

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

        $data = $this->sendPost("/backend/payment/external/refund/{$this->config->publicHash}", $body);

        return RefundResponse::fromArray($data);
    }

    public function deactivate(string $paymentHash): void
    {
        $body = ['paymentHash' => $paymentHash];

        $request = $this->requestFactory->createRequest(
            'PUT',
            $this->baseUrl() . "/backend/payment/inactive/{$this->config->publicHash}",
        )
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body)));

        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new InvalidResponseException(
                "HTTP {$statusCode} from deactivate endpoint",
                ['status' => $statusCode, 'paymentHash' => $paymentHash],
            );
        }
    }

    private function baseUrl(): string
    {
        return $this->config->environment->payByLinkBaseUrl();
    }

    /** @return array<string, mixed> */
    private function sendGet(string $path): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl() . $path)
            ->withHeader('Accept', 'application/json');

        $response = $this->httpClient->sendRequest($request);

        return $this->parseJsonResponse($response);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sendPost(string $path, array $body): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl() . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body)));

        $response = $this->httpClient->sendRequest($request);

        return $this->parseJsonResponse($response);
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(\Psr\Http\Message\ResponseInterface $response): array
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

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/PayByLink/PayByLinkClientTest.php`
Expected: All 11 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/PayByLink/PayByLinkClient.php tests/PayByLink/PayByLinkClientTest.php
git commit -m "feat: add PayByLinkClient with all 6 API operations (banks, payment link, QR, status, refund, deactivate)"
```

---

### Task 7: WebhookParser

**Files:**
- Create: `src/Webhook/WebhookParser.php`
- Create: `tests/Webhook/WebhookParserTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Webhook\WebhookParser;

test('parses CONFIRMED status from query params', function () {
    $result = WebhookParser::parse(['status' => 'CONFIRMED']);

    expect($result)->toBe(PaymentStatus::Confirmed);
});

test('parses FAILED status from query params', function () {
    $result = WebhookParser::parse(['status' => 'FAILED']);

    expect($result)->toBe(PaymentStatus::Failed);
});

test('throws on missing status parameter', function () {
    WebhookParser::parse([]);
})->throws(\InvalidArgumentException::class, 'Missing status parameter');

test('throws on invalid status value', function () {
    WebhookParser::parse(['status' => 'INVALID']);
})->throws(\ValueError::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Webhook/WebhookParserTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Implement WebhookParser**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Webhook;

use Ux2Dev\Iris\Enum\PaymentStatus;

final class WebhookParser
{
    /**
     * Parse the payment status from IRIS webhook query parameters.
     *
     * IRIS appends ?status=CONFIRMED or ?status=FAILED to the hookUrl.
     *
     * @param array<string, string> $queryParams
     */
    public static function parse(array $queryParams): PaymentStatus
    {
        if (!isset($queryParams['status'])) {
            throw new \InvalidArgumentException('Missing status parameter in webhook query string');
        }

        return PaymentStatus::from($queryParams['status']);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Webhook/WebhookParserTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Webhook/ tests/Webhook/
git commit -m "feat: add WebhookParser for IRIS payment status webhook callbacks"
```

---

### Task 8: WebSdk ComponentConfig

**Files:**
- Create: `src/WebSdk/ComponentConfig.php`
- Create: `tests/WebSdk/ComponentConfigTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\ComponentType;
use Ux2Dev\Iris\Enum\Country;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\WebSdk\ComponentConfig;

test('builds minimal payment component attributes', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('payment');
    expect($attrs['userhash'])->toBe('user-hash-123');
    expect($attrs['backend'])->toBe('https://developer.sandbox.irispay.bg');
    expect($attrs['hookhash'])->toBe('hook-hash-456');
    expect($attrs)->not->toHaveKey('lang');
    expect($attrs)->not->toHaveKey('country');
});

test('includes optional attributes when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Production,
        hookHash: 'hook-hash-456',
        lang: Language::English,
        country: Country::Bulgaria,
        showBankSelector: true,
        redirectUrl: 'https://example.com/done',
        redirectTimeout: 5,
    );

    $attrs = $config->toAttributes();

    expect($attrs['lang'])->toBe('en');
    expect($attrs['country'])->toBe('bulgaria');
    expect($attrs['show_bank_selector'])->toBe('true');
    expect($attrs['redirect_url'])->toBe('https://example.com/done');
    expect($attrs['redirect_timeout'])->toBe('5');
});

test('builds payment-data component with payment data', function () {
    $config = new ComponentConfig(
        type: ComponentType::PaymentData,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        paymentData: [
            'sum' => 9.99,
            'description' => 'Test payment',
            'currency' => 'EUR',
            'merchant' => 'Test Merchant',
            'toIban' => 'BG31UNCR70001526254645',
            'publicHash' => 'pub-hash-789',
        ],
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('payment-data');
    expect($attrs['show_bank_selector'])->toBe('false');
    $paymentData = json_decode($attrs['payment_data'], true);
    expect($paymentData['sum'])->toBe(9.99);
    expect($paymentData['currency'])->toBe('EUR');
});

test('builds add-iban-with-bank component with bank hash', function () {
    $config = new ComponentConfig(
        type: ComponentType::AddIbanWithBank,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        bankHash: 'bank-hash-789',
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('add-iban-with-bank');
    expect($attrs['bankhash'])->toBe('bank-hash-789');
    expect($attrs)->not->toHaveKey('hookhash');
});

test('generates script and stylesheet tags', function () {
    $tags = ComponentConfig::assetTags(Environment::Production);

    expect($tags)->toContain('https://websdk.irispay.bg/assets/irispay-ui/elements.js');
    expect($tags)->toContain('https://websdk.irispay.bg/assets/irispay-ui/styles.css');
});

test('includes pagination options when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        paginationOptions: ['start_page_items' => 9, 'increase_per_click' => 3],
    );

    $attrs = $config->toAttributes();
    $pagination = json_decode($attrs['pagination_options'], true);

    expect($pagination['start_page_items'])->toBe(9);
});

test('includes header options when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        headerOptions: ['show_header' => true, 'show_language_selector' => true],
    );

    $attrs = $config->toAttributes();
    $header = json_decode($attrs['header_options'], true);

    expect($header['show_header'])->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/WebSdk/ComponentConfigTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Implement ComponentConfig**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\WebSdk;

use Ux2Dev\Iris\Enum\ComponentType;
use Ux2Dev\Iris\Enum\Country;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;

final readonly class ComponentConfig
{
    /**
     * @param array<string, mixed>|null $paymentData       For payment-data type
     * @param array<string, mixed>|null $paginationOptions Pagination config
     * @param array<string, mixed>|null $headerOptions     Header config
     */
    public function __construct(
        public ComponentType $type,
        public string $userHash,
        public Environment $backend,
        public ?string $hookHash = null,
        public ?string $ibanHookHash = null,
        public ?Language $lang = null,
        public ?Country $country = null,
        public ?bool $showBankSelector = null,
        public ?string $bankHash = null,
        public ?string $useOnlySelectedBankHashes = null,
        public ?string $redirectUrl = null,
        public ?int $redirectTimeout = null,
        public ?string $code = null,
        public ?array $paymentData = null,
        public ?array $paymentDataWithAccountId = null,
        public ?array $paginationOptions = null,
        public ?array $headerOptions = null,
    ) {}

    /** @return array<string, string> */
    public function toAttributes(): array
    {
        $attrs = [
            'type' => $this->type->value,
            'userhash' => $this->userHash,
            'backend' => $this->backend->webSdkBaseUrl(),
        ];

        if ($this->hookHash !== null) {
            $attrs['hookhash'] = $this->hookHash;
        }
        if ($this->ibanHookHash !== null) {
            $attrs['ibanhookhash'] = $this->ibanHookHash;
        }
        if ($this->lang !== null) {
            $attrs['lang'] = $this->lang->value;
        }
        if ($this->country !== null) {
            $attrs['country'] = $this->country->value;
        }
        if ($this->showBankSelector !== null) {
            $attrs['show_bank_selector'] = $this->showBankSelector ? 'true' : 'false';
        }
        if ($this->bankHash !== null) {
            $attrs['bankhash'] = $this->bankHash;
        }
        if ($this->useOnlySelectedBankHashes !== null) {
            $attrs['useOnlySelectedBankHashes'] = $this->useOnlySelectedBankHashes;
        }
        if ($this->redirectUrl !== null) {
            $attrs['redirect_url'] = $this->redirectUrl;
        }
        if ($this->redirectTimeout !== null) {
            $attrs['redirect_timeout'] = (string) $this->redirectTimeout;
        }
        if ($this->code !== null) {
            $attrs['code'] = $this->code;
        }
        if ($this->paymentData !== null) {
            $attrs['payment_data'] = json_encode($this->paymentData);
            $attrs['show_bank_selector'] = $attrs['show_bank_selector'] ?? 'false';
        }
        if ($this->paymentDataWithAccountId !== null) {
            $attrs['payment_data_with_account_id'] = json_encode($this->paymentDataWithAccountId);
            $attrs['show_bank_selector'] = $attrs['show_bank_selector'] ?? 'false';
        }
        if ($this->paginationOptions !== null) {
            $attrs['pagination_options'] = json_encode($this->paginationOptions);
        }
        if ($this->headerOptions !== null) {
            $attrs['header_options'] = json_encode($this->headerOptions);
        }

        return $attrs;
    }

    public static function assetTags(Environment $environment): string
    {
        $base = $environment->webSdkAssetsUrl();

        return <<<HTML
        <script src="{$base}/assets/irispay-ui/elements.js"></script>
        <link rel="stylesheet" href="{$base}/assets/irispay-ui/styles.css">
        HTML;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/WebSdk/ComponentConfigTest.php`
Expected: All 7 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/WebSdk/ tests/WebSdk/
git commit -m "feat: add WebSdk ComponentConfig builder for irispay-component attributes"
```

---

### Task 9: Laravel Config + IrisManager

**Files:**
- Create: `src/Laravel/config/iris.php`
- Create: `src/Laravel/IrisManager.php`
- Create: `tests/Laravel/IrisManagerTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Laravel\IrisManager;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;

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

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Laravel/IrisManagerTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Create config file**

**src/Laravel/config/iris.php:**
```php
<?php

return [
    'default' => env('IRIS_MERCHANT', 'main'),

    'merchants' => [
        'main' => [
            'public_hash' => env('IRIS_PUBLIC_HASH'),
            'environment' => env('IRIS_ENVIRONMENT', 'production'),
            'currency' => env('IRIS_CURRENCY', 'EUR'),
            'language' => env('IRIS_LANGUAGE', 'bg'),
        ],
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'iris',
        'middleware' => ['web'],
    ],

    'redirect' => [
        'success' => env('IRIS_REDIRECT_SUCCESS', '/payment/success'),
        'failure' => env('IRIS_REDIRECT_FAILURE', '/payment/failure'),
    ],
];
```

- [ ] **Step 4: Implement IrisManager**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\PayByLink\PayByLinkClient;

final class IrisManager
{
    private string $currentMerchant;

    /** @var array<string, MerchantConfig> */
    private array $configs = [];

    /** @var array<string, PayByLinkClient> */
    private array $payByLinkClients = [];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $this->currentMerchant = $config['default'] ?? 'main';
    }

    public function merchant(string $name): self
    {
        $clone = clone $this;
        $clone->currentMerchant = $name;

        return $clone;
    }

    public function getConfig(): MerchantConfig
    {
        return $this->resolveConfig($this->currentMerchant);
    }

    public function payByLink(): PayByLinkClient
    {
        $name = $this->currentMerchant;

        if (!isset($this->payByLinkClients[$name])) {
            $config = $this->resolveConfig($name);
            $httpClient = new Client();
            $factory = new HttpFactory();

            $this->payByLinkClients[$name] = new PayByLinkClient(
                config: $config,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        }

        return $this->payByLinkClients[$name];
    }

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
            publicHash: $m['public_hash'],
            environment: Environment::from($m['environment'] ?? 'production'),
            currency: Currency::from($m['currency'] ?? 'EUR'),
            language: Language::from($m['language'] ?? 'bg'),
        );

        return $this->configs[$name];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Laravel/IrisManagerTest.php`
Expected: All 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Laravel/config/iris.php src/Laravel/IrisManager.php tests/Laravel/IrisManagerTest.php
git commit -m "feat: add Laravel config and IrisManager with multi-merchant support"
```

---

### Task 10: Laravel ServiceProvider + Facade

**Files:**
- Create: `src/Laravel/IrisServiceProvider.php`
- Create: `src/Laravel/IrisFacade.php`
- Create: `tests/Laravel/IrisServiceProviderTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use Ux2Dev\Iris\Laravel\IrisManager;
use Ux2Dev\Iris\Tests\TestCase;

uses(TestCase::class);

test('registers IrisManager as singleton', function () {
    $manager = $this->app->make(IrisManager::class);

    expect($manager)->toBeInstanceOf(IrisManager::class);
    expect($this->app->make(IrisManager::class))->toBe($manager);
});

test('resolves via iris alias', function () {
    $manager = $this->app->make('iris');

    expect($manager)->toBeInstanceOf(IrisManager::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Laravel/IrisServiceProviderTest.php`
Expected: FAIL -- class not found.

- [ ] **Step 3: Implement ServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use Illuminate\Support\ServiceProvider;

class IrisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/iris.php', 'iris');

        $this->app->singleton(IrisManager::class, function ($app) {
            return new IrisManager($app['config']->get('iris'));
        });

        $this->app->alias(IrisManager::class, 'iris');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/iris.php' => config_path('iris.php'),
            ], 'iris-config');

            $this->publishes([
                __DIR__ . '/routes/iris.php' => base_path('routes/iris.php'),
            ], 'iris-routes');

            $this->commands([
                Console\StatusCheckCommand::class,
            ]);
        }

        if ($this->app['config']->get('iris.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/iris.php');
        }
    }
}
```

- [ ] **Step 4: Implement Facade**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static IrisManager merchant(string $name)
 * @method static \Ux2Dev\Iris\PayByLink\PayByLinkClient payByLink()
 * @method static \Ux2Dev\Iris\Config\MerchantConfig getConfig()
 *
 * @see \Ux2Dev\Iris\Laravel\IrisManager
 */
class IrisFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IrisManager::class;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest tests/Laravel/IrisServiceProviderTest.php`
Expected: All 2 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Laravel/IrisServiceProvider.php src/Laravel/IrisFacade.php tests/Laravel/IrisServiceProviderTest.php
git commit -m "feat: add Laravel ServiceProvider and Facade"
```

---

### Task 11: Laravel Events + Webhook Controller + Routes

**Files:**
- Create: `src/Laravel/Events/PaymentConfirmed.php`
- Create: `src/Laravel/Events/PaymentFailed.php`
- Create: `src/Laravel/Http/Controllers/IrisWebhookController.php`
- Create: `src/Laravel/routes/iris.php`

- [ ] **Step 1: Create event classes**

**src/Laravel/Events/PaymentConfirmed.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(
        public PaymentStatus $status,
        public ?string $merchant = null,
    ) {}
}
```

**src/Laravel/Events/PaymentFailed.php:**
```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentFailed
{
    use Dispatchable;

    public function __construct(
        public PaymentStatus $status,
        public ?string $merchant = null,
    ) {}
}
```

- [ ] **Step 2: Create webhook controller**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Laravel\Events\PaymentConfirmed;
use Ux2Dev\Iris\Laravel\Events\PaymentFailed;
use Ux2Dev\Iris\Webhook\WebhookParser;

class IrisWebhookController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $status = WebhookParser::parse($request->query());

        match ($status) {
            PaymentStatus::Confirmed => PaymentConfirmed::dispatch($status),
            PaymentStatus::Failed => PaymentFailed::dispatch($status),
            PaymentStatus::Waiting => null,
        };

        $redirectPath = match ($status) {
            PaymentStatus::Confirmed => config('iris.redirect.success', '/payment/success'),
            default => config('iris.redirect.failure', '/payment/failure'),
        };

        return redirect($redirectPath);
    }
}
```

- [ ] **Step 3: Create routes file**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ux2Dev\Iris\Laravel\Http\Controllers\IrisWebhookController;

Route::group([
    'prefix' => config('iris.routes.prefix', 'iris'),
    'middleware' => config('iris.routes.middleware', ['web']),
], function () {
    Route::get('/webhook', IrisWebhookController::class)->name('iris.webhook');
});
```

- [ ] **Step 4: Commit**

```bash
git add src/Laravel/Events/ src/Laravel/Http/ src/Laravel/routes/
git commit -m "feat: add Laravel events, webhook controller, and routes"
```

---

### Task 12: Laravel StatusCheckCommand

**Files:**
- Create: `src/Laravel/Console/StatusCheckCommand.php`

- [ ] **Step 1: Implement the command**

```php
<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Console;

use Illuminate\Console\Command;
use Ux2Dev\Iris\Laravel\IrisManager;

class StatusCheckCommand extends Command
{
    protected $signature = 'iris:status-check
                            {paymentHash : The payment hash to check}
                            {--merchant= : Merchant name (uses default if omitted)}';

    protected $description = 'Check the status of an IRIS payment by its hash';

    public function handle(IrisManager $manager): int
    {
        $paymentHash = $this->argument('paymentHash');
        $merchantName = $this->option('merchant');

        $client = $merchantName
            ? $manager->merchant($merchantName)->payByLink()
            : $manager->payByLink();

        try {
            $response = $client->getPaymentStatus($paymentHash);
        } catch (\Throwable $e) {
            $this->error("Failed to check status: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Status', $response->status->value],
                ['Sum', (string) $response->sum],
                ['Currency', $response->currency],
                ['Date', $response->date],
                ['Description', $response->description],
                ['Order ID', $response->orderId ?? '-'],
                ['Payer Name', $response->payerName],
                ['Payer Bank', $response->payerBank],
                ['Payer IBAN', $response->payerIban],
                ['Receiver IBAN', $response->receiverIban],
            ],
        );

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Laravel/Console/
git commit -m "feat: add iris:status-check Artisan command"
```

---

### Task 13: Run Full Test Suite + Final Verification

- [ ] **Step 1: Run complete test suite**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest`
Expected: All tests PASS. Verify count matches expected total (~38 tests).

- [ ] **Step 2: Run static analysis (optional)**

Run: `cd /Users/hristolaskov/Herd/iris-pay && vendor/bin/pest --type-coverage` (if available)

- [ ] **Step 3: Verify file structure matches plan**

Run: `cd /Users/hristolaskov/Herd/iris-pay && find src -name '*.php' | sort`

Expected output:
```
src/Config/MerchantConfig.php
src/Enum/BankScaType.php
src/Enum/ComponentType.php
src/Enum/Country.php
src/Enum/Currency.php
src/Enum/Environment.php
src/Enum/Language.php
src/Enum/PaymentStatus.php
src/Enum/RefundType.php
src/Exception/ConfigurationException.php
src/Exception/InvalidResponseException.php
src/Exception/IrisException.php
src/Laravel/Console/StatusCheckCommand.php
src/Laravel/Events/PaymentConfirmed.php
src/Laravel/Events/PaymentFailed.php
src/Laravel/Http/Controllers/IrisWebhookController.php
src/Laravel/IrisFacade.php
src/Laravel/IrisManager.php
src/Laravel/IrisServiceProvider.php
src/PayByLink/PayByLinkClient.php
src/PayByLink/Response/Bank.php
src/PayByLink/Response/PaymentLinkResponse.php
src/PayByLink/Response/PaymentStatusResponse.php
src/PayByLink/Response/RefundResponse.php
src/WebSdk/ComponentConfig.php
src/Webhook/WebhookParser.php
```

- [ ] **Step 4: Final commit with all tests passing**

```bash
git add -A
git status
```

If any unstaged files remain, add them. Confirm working tree is clean.

---

## Deferred: Web SDK API Client

The following IRIS Web SDK API endpoints need full request/response documentation before implementation:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `api/8/signup` | Create userHash for end-user |
| POST | `api/8/createhook` | Create hookHash for webhook per payment |
| GET | `api/8/ibans` | List added bank accounts for user |
| GET | `api/8/balances` | Account balance (needs ibanId) |
| GET | `api/8/transactions` | Transaction history (needs ibanId) |

When docs are available, add `src/WebSdk/WebSdkClient.php` following the same PSR-18 pattern as `PayByLinkClient`. The `IrisManager` already has the config structure to support it -- add a `webSdk()` method mirroring the `payByLink()` pattern.

---

## Summary

| Task | What | Files | Tests |
|------|------|-------|-------|
| 1 | Project scaffolding | 5 | 0 |
| 2 | Enums | 8+1 | 6 |
| 3 | Exceptions | 3 | 0 |
| 4 | MerchantConfig | 1+1 | 5 |
| 5 | PayByLink Response DTOs | 4+4 | 5 |
| 6 | PayByLinkClient | 1+1 | 11 |
| 7 | WebhookParser | 1+1 | 4 |
| 8 | ComponentConfig | 1+1 | 7 |
| 9 | Laravel config + Manager | 2+1 | 4 |
| 10 | Laravel ServiceProvider + Facade | 2+1 | 2 |
| 11 | Laravel Events + Controller + Routes | 4 | 0 |
| 12 | StatusCheck Command | 1 | 0 |
| 13 | Final verification | 0 | all |
| **Total** | | **~42 files** | **~44 tests** |
