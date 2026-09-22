# IRIS Pay SDK — Resource-Based Refactor Design

**Date:** 2026-09-21
**Status:** Approved, ready for implementation planning

## Goal

Restructure `ux2dev/iris-pay` from eight independently constructed clients into
the resource-based architecture `ux2dev/prim` uses: one root object, one HTTP
transport composed into thin resource classes.

Today a consumer outside Laravel must instantiate each of eight clients
separately with four constructor arguments each. There is no root entry point.
`PayByLinkClient` does not extend `BaseClient` and therefore carries its own
283-line copy of the HTTP layer — `send()`, `parseJsonResponse()`,
`throwForStatus()` are duplicated verbatim.

Nothing consumes the package yet (only `LICENSE` is tracked in git; it is not
published). The public API changes without a backward-compatibility layer.

## Constraints

- PHP 8.2+, PSR-18 client and PSR-17 factories injected by the caller.
- The 102 existing tests (257 assertions) must stay green or be ported with
  their assertions intact.
- No endpoint may be lost: all 56 public methods must remain reachable.
- Guzzle must not be a hard runtime dependency.

## Why prim's transport cannot be copied verbatim

prim has one base URL and one auth regime (a token in the query string), which
is why `PrimTransport` is a single `final` class that handles auth itself.

IRIS has two base URLs and four auth regimes:

| Regime | Header(s) | Methods |
|---|---|---|
| none | — | 6 |
| agent | `x-agent-hash` | 10 |
| user | `x-user-hash` | 30 |
| both | `x-agent-hash` + `x-user-hash` | 2 |
| admin / admin+agent | `x-admin-hash` (+ agent) | 2 |

Plus PayByLink, which carries `publicHash` in the **path** against a different
host (`paybyclick.irispay.bg` vs `developer.irispay.bg`).

Two consequences for the design:

1. The transport must be parameterized by base URL and must not know about
   credentials.
2. Credential validation must happen at point of use, not in a constructor.
   The current `BaseClient::__construct()` throws when `agentHash` is null,
   which makes `signup()` — an endpoint that sends no auth header at all —
   impossible to call without supplying a credential it never uses.

## Architecture

### IrisTransport

`final class Ux2Dev\Iris\Http\IrisTransport`. Knows HTTP, JSON and error
mapping. Knows nothing about credentials.

```php
public function __construct(
    private string $baseUrl,
    private ClientInterface $httpClient,
    private RequestFactoryInterface $requestFactory,
    private StreamFactoryInterface $streamFactory,
) {}

public function get(string $path, array $headers = [], array $query = []): array
public function getRaw(string $path, array $headers = []): string
public function post(string $path, array $body, array $headers = []): array
public function postEmpty(string $path, array $headers = []): array
public function postVoid(string $path, array $body, array $headers = []): void
public function postForString(string $path, array $headers = []): string
public function put(string $path, array $body = [], array $headers = [], array $query = []): array
public function putVoid(string $path, array $body = [], array $headers = [], array $query = []): void
public function delete(string $path, array $headers = []): void
```

Error mapping is lifted unchanged from `BaseClient`: transport failures become
`NetworkException`, 4xx becomes `ApiClientException`, 5xx becomes
`ApiServerException`, unparseable bodies become `InvalidResponseException`.

### Credentials

`final class Ux2Dev\Iris\Http\Credentials`, built from `MerchantConfig`.
Produces header arrays and validates the credential it is asked for:

```php
public function agent(): array                  // x-agent-hash
public function user(string $userHash): array   // x-user-hash
public function both(string $userHash): array
public function admin(): array                  // x-admin-hash
public function adminAgent(): array
public function publicHash(): string            // for PayByLink paths
```

Each throws `ConfigurationException` when the underlying hash is null or
contains CR/LF. The CRLF check currently in `BaseClient::validateHeaderValue()`
moves here, so it covers every regime rather than only `agentHash`.

### Iris root

`final class Ux2Dev\Iris\Iris`, mirroring `Prim.php`: constructed with a
`MerchantConfig`, a PSR-18 client and PSR-17 factories; builds two transports
(core and PayByLink) plus one `Credentials`; exposes lazy resource accessors.

```php
$iris = new Iris($config, new Client(), $factory, $factory);
$iris->payByLink()->createLink(...);
$iris->user($userHash)->accounts()->listIbans();
```

### Resource map

Resources are thin: a constructor taking the transport and credentials, then
one method per endpoint. The vocabulary is identical at both levels, so the
same resource name means the same thing whether or not a user is in scope.

Split rule: **which user the call concerns**, not which header authorizes it.
`reports()->listPayments()` therefore sits under `user()` even though it sends
`x-agent-hash` and carries `userHash` in the body.

Root — `Ux2Dev\Iris\Resources\` (22 methods):

| Resource | n | Methods |
|---|---|---|
| `payByLink()` | 6 | getBanks, createLink, getQrCode, getStatus, refund, deactivate |
| `agent()` | 7 | signup, signupAgent, createHook, addRedirectToHook, listUsers, checkUserByEmail, updateKyc |
| `accounts()` | 1 | getConsentDetails |
| `payments()` | 1 | statusByHook |
| `bulkPayments()` | 2 | status, search |
| `reports()` | 4 | searchPayments, activeUsers, activeUsersDetails, bankMaintenance |
| `consentGate()` | 1 | createRequest |

User scope — `Ux2Dev\Iris\Resources\User\` (34 methods), reached via
`$iris->user($hash)`:

| Resource | n | Methods |
|---|---|---|
| `accounts()` | 12 | listBanks, getBank, getBankSca, listIbans, deleteIban, getBalance, listTransactions, listPagedTransactions, getTransaction, listTokens, createConsent, getConsents |
| `payments()` | 11 | createDirect, createDirectInitiate, createIban, confirm, confirmWithResult, confirmSms, statusByCode, authorization, sca, createBudget, createBudgetDirect |
| `bulkPayments()` | 4 | create, createIban, createBudget, createBudgetIban |
| `agent()` | 4 | createToken, delete, sendAisEmail, kycStatus |
| `reports()` | 1 | listPayments |
| `consentGate()` | 2 | uiConsentRequest, getConsents |

22 + 34 = 56, matching the current method count exactly. `getConsents` sits
under user scope rather than root: it hits `/api/cgate/consents-request/{userHash}`,
which is keyed by user, so dropping the `{userHash}` segment to fit the
original root/user split would have named a different endpoint, not the same
one at a different scope.

Four resources hold only one or two methods. They exist for symmetry: keeping
the same names at both levels means a new endpoint has one obvious home. For a
hand-written SDK with no generator, that predictability is worth more than the
four files it costs.

Method names drop redundant suffixes, as prim's do
(`$prim->items()->get()`): `createDirectPayment` becomes `createDirect`,
`getStatusByHookHash` becomes `statusByHook`, `createPaymentLink` becomes
`createLink`.

### UserScope

`final class Ux2Dev\Iris\UserScope` holds the transports, credentials and the
user hash, and exposes the six user-scoped accessors, each lazily constructed
and each passed the hash. It is the only place the hash is stored, so a
resource method can never be called with the wrong one.

## Config changes

`MerchantConfig` gains `timeout: int = 30`, validated at 1 or greater and
passed through to the Guzzle fallback in the Laravel manager.

The constructor cross-check requiring at least one of `publicHash` or
`agentHash` is removed. It is redundant once credentials validate at point of
use, and it blocks the endpoints that need no credential at all.

`__debugInfo()` redaction and the serialization ban are unchanged.

## Laravel layer

Kept in full — webhook handling is real value for a payment SDK — but rewired.

- `IrisManager` caches one lazy `Iris` per merchant instead of seven client
  caches, and accepts optional PSR client and factories with a Guzzle fallback,
  matching `PrimManager`. This removes the hard `new Client()` dependency on a
  package listed only under `require-dev`.
- `__call` forwards to the `Iris` instance, so `Iris::user($h)->payments()`
  works through the facade.
- `merchant(string)` immutable clone switching is unchanged.
- `IrisFacade` is renamed to `Ux2Dev\Iris\Laravel\Facades\Iris`, matching
  prim's layout and the rename prim made in commit `9ddfaa4`. The
  `composer.json` alias is updated.
- Routes, `IrisWebhookController`, the three events and `StatusCheckCommand`
  keep their behavior; the controller resolves `Iris` rather than a client.

## Testing

Test-driven, with one safeguard written first.

1. **Coverage test first.** Following prim's `FullCoverageTest`, a test asserts
   every one of the 56 methods exists on its expected resource with its
   expected signature. It fails at the start and is the green criterion that
   the migration dropped nothing.
2. **New unit tests** for `IrisTransport` (each verb, each error mapping) and
   `Credentials` (each regime, missing-hash and CRLF rejection).
3. **Ported tests.** The existing client tests move to the resource classes
   with their assertions intact, as prim did when porting
   `ResourcesIntegrationTest`.
4. **Untouched:** `HardeningTest`, `WebhookParserTest`, `ComponentConfigTest`,
   `MerchantConfigTest` and the enum tests.

## Repository hygiene

Folded into this work because the package cannot ship without it:

- `.gitignore` for `/vendor/`, `composer.lock`, `.phpunit.cache/`,
  `.phpunit.result.cache`, `.DS_Store`. Currently absent, so `vendor/` and a
  356 KB lock file would enter the first real commit.
- `README.md` modeled on prim's: requirements, installation, plain-PHP quick
  start, Laravel quick start, resource reference.
- `guzzlehttp/guzzle` added to `suggest`, which iris-pay dropped and prim has.

## Out of scope

- A code generator. prim generates from `endpoints.json`, which costs it typed
  results — its DTOs are `public readonly mixed` throughout. iris-pay's
  hand-written DTOs have real types, and IRIS publishes no machine-readable
  spec worth generating from.
- A migration guide. There are no consumers to migrate.
- Retry and logging decorators. The transport makes them possible later;
  nothing asks for them now.

## Risks

The one real risk is silently losing an endpoint across 13 new files. The
coverage test in step 1 exists specifically to make that failure loud, and it
is written before any resource class.
