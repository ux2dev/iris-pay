# IRIS Pay PHP SDK

Framework-agnostic PHP SDK for the [IRIS Solutions](https://irispay.bg) open
banking platform. Covers the PayByLink/QR API, the IRIS Core API (agents,
accounts, payments, bulk payments, reports) and the Consent Gateway as
resource methods returning typed DTOs. Works with plain PHP or Laravel.

## Requirements

- PHP 8.2 or higher
- JSON extension
- A PSR-18 HTTP client and PSR-17 request/stream factories (Guzzle satisfies
  both and is suggested, not required)

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

Core API endpoints that concern one end user hang off `$iris->user($hash)`;
everything else hangs off `$iris` directly:

```php
use Ux2Dev\Iris\Api\Request\DirectPaymentData;

$user = $iris->user($userHash);

foreach ($user->accounts()->listIbans() as $account) {
    echo $account->iban . PHP_EOL;
}

$payment = $user->payments()->createDirect(new DirectPaymentData(
    bankHash:               'bank-hash',
    receiverIban:           'BG18RZBB91550123456789',
    receiverName:           'Acme Ltd',
    remittanceDescription:  'Order #1024',
    sum:                    49.90,
    currency:               'EUR',
    hookHash:                'your-hook-hash',
));
```

### Laravel

```php
use Ux2Dev\Iris\Laravel\Facades\Iris;

$link = Iris::payByLink()->createLink(/* ... */);
$ibans = Iris::user($userHash)->accounts()->listIbans();
$other = Iris::merchant('secondary')->payByLink()->getBanks();
```

Publish the config and routes with:

```bash
php artisan vendor:publish --tag=iris-config
php artisan vendor:publish --tag=iris-routes
```

## Configuration

### MerchantConfig

Every `Iris` instance takes a `MerchantConfig`. It is a `final readonly`
value object: all inputs are validated at construction, and it blocks
`serialize()`/`unserialize()` since it carries key material.

```php
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Currency;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;

$config = new MerchantConfig(
    environment: Environment::Production, // required
    publicHash:  'your-public-hash',       // required for payByLink()
    agentHash:   'your-agent-hash',        // required for agent()/user()
    adminHash:   null,                     // required for admin-only endpoints
    currency:    Currency::EUR,            // default currency for createLink()
    language:    Language::Bulgarian,      // default UI language
    timeout:     30,                       // seconds, default 30 — last parameter
);
```

`publicHash`, `agentHash` and `adminHash` are independently optional (each
call site validates that the hash it needs is present), but if given they
must not be an empty string.

### Laravel configuration

The service provider registers a `IrisManager` singleton (bound to
`IrisManager::class` and the `iris` alias) resolved from `config/iris.php`,
plus a webhook route, controller and console command. Publishing the config
creates:

```php
return [
    'default' => env('IRIS_MERCHANT', 'main'),

    'merchants' => [
        'main' => [
            'public_hash' => env('IRIS_PUBLIC_HASH'),
            'agent_hash' => env('IRIS_AGENT_HASH'),
            'admin_hash' => env('IRIS_ADMIN_HASH'),
            'environment' => env('IRIS_ENVIRONMENT', 'production'),
            'currency' => env('IRIS_CURRENCY', 'EUR'),
            'language' => env('IRIS_LANGUAGE', 'bg'),
            'timeout' => env('IRIS_TIMEOUT', 30),
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

    'webhook' => [
        'secret' => env('IRIS_WEBHOOK_SECRET'),
    ],
];
```

Add more entries under `merchants` and switch at runtime with
`Iris::merchant('secondary')`, which returns an immutable clone of the
manager; the default merchant stays untouched.

## Webhooks

PayByLink notifies your `hookUrl` with a query string carrying the payment
status. `Ux2Dev\Iris\Webhook\WebhookParser::payload()` parses it into a typed
`WebhookPayload` (`status`, `hashedOrderId`, `paymentHash`, `orderId`,
`queryParams`); `WebhookParser::parse()` returns just the `PaymentStatus`.

In Laravel this is wired up for you: the package registers a signed
`GET|POST /{prefix}/webhook/{hashedOrderId}/{signature}` route (prefix and
middleware from `iris.routes`), verified with an HMAC-SHA256 signature over
`iris.webhook.secret`. `Ux2Dev\Iris\Laravel\IrisWebhookUrl::for($orderId)`
builds the signed URL to put in `hookUrl`. On a valid request the controller
fires `Ux2Dev\Iris\Laravel\Events\WebhookReceived`, then either
`PaymentConfirmed` or `PaymentFailed`, and redirects to
`iris.redirect.success` or `iris.redirect.failure`.

## Web SDK components

`Ux2Dev\Iris\WebSdk\ComponentConfig` builds the HTML attributes for IRIS's
embeddable JS payment components (`type`, `userhash`, `backend`, and the
option set for the chosen `ComponentType`), and
`ComponentConfig::assetTags($environment)` returns the `<script>`/`<link>`
tags to load them. Use it when embedding a payment widget directly in a page
rather than redirecting to a hosted link.

## Exceptions

All SDK exceptions extend `Ux2Dev\Iris\Exception\IrisException`:

| Exception | When it is thrown |
|-----------|-------------------|
| `ConfigurationException` | Invalid `MerchantConfig`/input, unknown merchant |
| `NetworkException` | PSR-18 client failure (network error, timeout) |
| `InvalidResponseException` | Malformed or unexpected response body |
| `ApiClientException` | HTTP 4xx response (not retryable) |
| `ApiServerException` | HTTP 5xx response (may be retryable) |

## Resources

`Iris` exposes the root resources directly; `$iris->user($hash)` returns a
`UserScope` exposing the user-scoped equivalents. 13 resource classes, 56
methods total.

| Accessor | Scope | Purpose |
|---|---|---|
| `payByLink()` | root | Payment links, QR codes, status, refunds |
| `agent()` | root | Signup, hooks, user listing/lookup, KYC |
| `accounts()` | root | Consent lookup by IBAN |
| `payments()` | root | Payment status by hook hash |
| `bulkPayments()` | root | Bulk status and search |
| `reports()` | root | Payment search, active users, bank maintenance |
| `consentGate()` | root | Create consent requests (admin) |
| `user($h)->accounts()` | user | Banks, IBANs, balances, transactions, tokens, consents |
| `user($h)->payments()` | user | Direct, IBAN and budget payments, confirmation |
| `user($h)->bulkPayments()` | user | Bulk payment creation |
| `user($h)->agent()` | user | Tokens, deletion, AIS email, KYC status |
| `user($h)->reports()` | user | That user's payment list |
| `user($h)->consentGate()` | user | Consent request UI, consent list |

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
