<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Api\ConsentGateClient;
use Ux2Dev\Iris\Api\Request\ConsentGateRequestData;
use Ux2Dev\Iris\Api\Response\ConsentGateResponse;
use Ux2Dev\Iris\Api\Response\ConsentGateStatus;
use Ux2Dev\Iris\Api\Response\ConsentGateUi;
use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ConfigurationException;

function createConsentGateClient(array $responses): ConsentGateClient
{
    $mock = new MockHandler($responses);
    $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();

    return new ConsentGateClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'agent-hash', adminHash: 'admin-hash'),
        httpClient: $httpClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

test('requires adminHash and agentHash', function () {
    $factory = new HttpFactory();

    expect(fn () => new ConsentGateClient(
        config: new MerchantConfig(environment: Environment::Development, agentHash: 'agent-hash'),
        httpClient: new Client(),
        requestFactory: $factory,
        streamFactory: $factory,
    ))->toThrow(ConfigurationException::class, 'adminHash is required');

    expect(fn () => new ConsentGateClient(
        config: new MerchantConfig(environment: Environment::Development, publicHash: 'public-hash', adminHash: 'admin-hash'),
        httpClient: new Client(),
        requestFactory: $factory,
        streamFactory: $factory,
    ))->toThrow(ConfigurationException::class, 'agentHash is required');
});

test('createConsentRequest returns ConsentGateResponse', function () {
    $client = createConsentGateClient([
        new Response(200, [], json_encode([
            'userHash' => 'user-123',
            'url' => 'https://example.com/consent',
            'ibansIds' => ['BG12TEST' => 1, 'BG34TEST' => 2],
        ])),
    ]);

    $requestData = new ConsentGateRequestData(
        userInfo: [
            'firstName' => 'John',
            'middleName' => 'M',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+359888123456',
            'uic' => '1234567890',
        ],
        ibans: ['BG12TEST', 'BG34TEST'],
    );

    $result = $client->createConsentRequest($requestData);

    expect($result)->toBeInstanceOf(ConsentGateResponse::class);
    expect($result->userHash)->toBe('user-123');
    expect($result->url)->toBe('https://example.com/consent');
    expect($result->ibansIds)->toBe(['BG12TEST' => 1, 'BG34TEST' => 2]);
});

test('getConsents returns ConsentGateStatus array', function () {
    $client = createConsentGateClient([
        new Response(200, [], json_encode([
            [
                'ibanId' => 1,
                'iban' => 'BG12TEST',
                'fulfilled' => true,
                'validUntil' => '2026-12-31',
                'frequencyPerDay' => 4,
            ],
            [
                'ibanId' => 2,
                'iban' => 'BG34TEST',
                'fulfilled' => false,
                'validUntil' => null,
                'frequencyPerDay' => 0,
            ],
        ])),
    ]);

    $result = $client->getConsents('user-123');

    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(ConsentGateStatus::class);
    expect($result[0]->ibanId)->toBe(1);
    expect($result[0]->iban)->toBe('BG12TEST');
    expect($result[0]->fulfilled)->toBeTrue();
    expect($result[0]->validUntil)->toBe('2026-12-31');
    expect($result[0]->frequencyPerDay)->toBe(4);
    expect($result[1]->fulfilled)->toBeFalse();
    expect($result[1]->validUntil)->toBeNull();
});

test('getUiConsentRequest returns ConsentGateUi', function () {
    $client = createConsentGateClient([
        new Response(200, [], json_encode([
            'id' => 10,
            'accountId' => 5,
            'expirationDate' => '2026-12-31',
            'timeout' => 300,
            'showValidUntil' => true,
            'showFrequencyPerDay' => false,
            'expirationMessage' => 'Consent expired',
            'successMessage' => null,
            'deleted' => false,
        ])),
    ]);

    $result = $client->getUiConsentRequest('user-123');

    expect($result)->toBeInstanceOf(ConsentGateUi::class);
    expect($result->id)->toBe(10);
    expect($result->accountId)->toBe(5);
    expect($result->expirationDate)->toBe('2026-12-31');
    expect($result->timeout)->toBe(300);
    expect($result->showValidUntil)->toBeTrue();
    expect($result->showFrequencyPerDay)->toBeFalse();
    expect($result->expirationMessage)->toBe('Consent expired');
    expect($result->successMessage)->toBeNull();
    expect($result->deleted)->toBeFalse();
});
