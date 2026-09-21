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
