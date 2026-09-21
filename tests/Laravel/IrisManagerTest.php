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

test('resolves agent client', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => ['main' => ['agent_hash' => 'agent-123', 'environment' => 'development']],
    ]);

    expect($manager->agent())->toBeInstanceOf(\Ux2Dev\Iris\Api\AgentClient::class);
});

test('resolves account client', function () {
    $manager = new IrisManager([
        'default' => 'main',
        'merchants' => ['main' => ['agent_hash' => 'agent-123', 'environment' => 'development']],
    ]);

    expect($manager->account())->toBeInstanceOf(\Ux2Dev\Iris\Api\AccountClient::class);
});
