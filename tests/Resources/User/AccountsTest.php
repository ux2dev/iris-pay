<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\Request\CreateConsentData;
use Ux2Dev\Iris\Api\Response\BalanceList;
use Ux2Dev\Iris\Api\Response\BankAccount;
use Ux2Dev\Iris\Api\Response\BankInfo;
use Ux2Dev\Iris\Api\Response\BankSca;
use Ux2Dev\Iris\Api\Response\Consent;
use Ux2Dev\Iris\Api\Response\TokenInfo;
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
    $resource = userAccounts([new Response(200, [], json_encode([[
        'bankHash' => 'bank-1', 'name' => 'Test Bank', 'urlLogo' => null, 'urlDarkLogo' => null,
        'sca' => 'REDIRECT_URL', 'firstStepInstruction' => null, 'directPayment' => true,
        'paymentRequiresIban' => false, 'fullName' => 'Test Bank Full', 'bic' => 'TESTBG',
        'services' => 'AIS,PIS', 'country' => 'bulgaria', 'videos' => [],
        'consentRequiresIban' => false, 'consentRequiresPsu' => true,
        'paymentRequiresAuthorization' => true, 'consentRequiresAuthorization' => false,
        'paymentRequiresPsu' => true, 'psuType' => 'USERNAME',
        'budgetPaymentsRequirePaymentCategory' => false, 'aisAvailable' => true, 'pisAvailable' => true,
    ]]))]);

    expect($resource->listBanks()[0])->toBeInstanceOf(BankInfo::class);
});

test('listBanks appends the country query when given', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listBanks('BG');

    expect($history[0]['request']->getUri()->getQuery())->toBe('country=BG');
});

test('getBank hits the nested bank path', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], json_encode([
        'bankHash' => 'bank-1', 'name' => 'Test Bank', 'urlLogo' => null, 'urlDarkLogo' => null,
        'sca' => 'REDIRECT_URL', 'firstStepInstruction' => null, 'directPayment' => true,
        'paymentRequiresIban' => false, 'fullName' => 'Test Bank Full', 'bic' => 'TESTBG',
        'services' => 'AIS,PIS', 'country' => 'bulgaria', 'videos' => [],
        'consentRequiresIban' => false, 'consentRequiresPsu' => true,
        'paymentRequiresAuthorization' => true, 'consentRequiresAuthorization' => false,
        'paymentRequiresPsu' => true, 'psuType' => 'USERNAME',
        'budgetPaymentsRequirePaymentCategory' => false, 'aisAvailable' => true, 'pisAvailable' => true,
    ]))], $history);

    expect($resource->getBank('bank-1'))->toBeInstanceOf(BankInfo::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/banks/bank-1')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('');
});

test('getBank appends the country query when given and omits it otherwise', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], json_encode([
        'bankHash' => 'bank-1', 'name' => 'Test Bank', 'urlLogo' => null, 'urlDarkLogo' => null,
        'sca' => 'REDIRECT_URL', 'firstStepInstruction' => null, 'directPayment' => true,
        'paymentRequiresIban' => false, 'fullName' => 'Test Bank Full', 'bic' => 'TESTBG',
        'services' => 'AIS,PIS', 'country' => 'bulgaria', 'videos' => [],
        'consentRequiresIban' => false, 'consentRequiresPsu' => true,
        'paymentRequiresAuthorization' => true, 'consentRequiresAuthorization' => false,
        'paymentRequiresPsu' => true, 'psuType' => 'USERNAME',
        'budgetPaymentsRequirePaymentCategory' => false, 'aisAvailable' => true, 'pisAvailable' => true,
    ]))], $history);

    $resource->getBank('bank-1', 'BG');

    expect($history[0]['request']->getUri()->getQuery())->toBe('country=BG');
});

test('listIbans adds the consent-details header when asked', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listIbans(consentDetails: true);

    expect($history[0]['request']->getHeaderLine('consent-details'))->toBe('true');
});

test('listIbans maps rows to BankAccount', function () {
    $resource = userAccounts([new Response(200, [], json_encode([[
        'id' => 1, 'name' => 'Main Account', 'iban' => 'BG12TEST', 'product' => null,
        'ownerName' => 'John', 'currency' => 'BGN', 'hasAuthorization' => true,
        'bankHash' => 'bank-1', 'bankName' => 'Test Bank', 'liteLogoUrl' => null,
        'darkLogoUrl' => null, 'country' => 'bulgaria', 'dateCreate' => '2025-01-01T00:00:00Z',
        'consents' => null, 'fulfilled' => true, 'validUntil' => '2026-01-01', 'frequencyPerDay' => 4,
    ]]))]);

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

test('listTransactions hits the flat transactions path with the given dates', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '{}')], $history);

    $resource->listTransactions(42, dateFrom: '2025-01-01', dateTo: '2025-02-01');

    expect($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/transactions/42')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('dateFrom=2025-01-01&dateTo=2025-02-01');
});

test('listTransactions omits dateFrom and dateTo when not given', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '{}')], $history);

    $resource->listTransactions(42);

    expect($history[0]['request']->getUri()->getQuery())->toBe('');
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
    $resource = userAccounts([new Response(200, [], json_encode([
        'transactionId' => 'tx-9', 'transactionAmount' => ['amount' => 100.00, 'currency' => 'BGN'],
    ]))], $history);

    expect($resource->getTransaction(42, 'tx-9'))->toBeInstanceOf(Transaction::class)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/transactions/42/tx-9');
});

test('listTokens hits the tokens path', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], json_encode([[
        'id' => 1, 'accountId' => 2, 'token' => 'tok-1', 'type' => 'AUTH_AIS',
        'bankId' => 3, 'psuId' => 4, 'psuIdentifier' => 'psu-1', 'dateCreated' => '2025-01-01T00:00:00Z',
    ]]))], $history);

    expect($resource->listTokens()[0])->toBeInstanceOf(TokenInfo::class)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/tokens')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('');
});

test('listTokens appends the country query when given and omits it otherwise', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], '[]')], $history);

    $resource->listTokens('BG');

    expect($history[0]['request']->getUri()->getQuery())->toBe('country=BG');
});

test('createConsent posts the encoded data to the consent path', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], json_encode([
        'startUrl' => 'https://bank.test/start', 'endUrl' => 'https://bank.test/end',
        'formText' => null, 'loadingText' => null, 'psuIdType' => 'USERNAME',
        'sca' => 'REDIRECT_URL', 'gatherPsu' => false, 'hasAuthorization' => false,
        'externalApp' => false, 'authorizationId' => null,
    ]))], $history);

    $data = new CreateConsentData(bankHash: 'bank-1', iban: 'BG12TEST');

    expect($resource->createConsent($data))->toBeInstanceOf(BankSca::class)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/8/consent')
        ->and($history[0]['request']->getHeaderLine('x-user-hash'))->toBe('user-1')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($data->toArray()));
});

test('getConsents unwraps a consents envelope', function () {
    $resource = userAccounts([
        new Response(200, [], json_encode(['consents' => [['ibanId' => 42]]])),
    ]);

    expect($resource->getConsents(42))->toHaveCount(1);
});

test('getConsents accepts a bare array', function () {
    $resource = userAccounts([new Response(200, [], json_encode([['ibanId' => 42]]))]);

    expect($resource->getConsents(42)[0])->toBeInstanceOf(Consent::class);
});

test('getBankSca posts with no body', function () {
    $history = [];
    $resource = userAccounts([new Response(200, [], json_encode([
        'startUrl' => 'https://bank.test/start', 'endUrl' => 'https://bank.test/end',
        'formText' => null, 'loadingText' => null, 'psuIdType' => 'USERNAME',
        'sca' => 'REDIRECT_URL', 'gatherPsu' => false, 'hasAuthorization' => false,
        'externalApp' => false, 'authorizationId' => null,
    ]))], $history);

    $resource->getBankSca('bank-1');

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getBody())->toBe('');
});
