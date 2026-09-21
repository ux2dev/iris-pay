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
