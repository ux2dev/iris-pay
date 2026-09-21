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
