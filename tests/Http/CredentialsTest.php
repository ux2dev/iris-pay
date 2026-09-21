<?php

declare(strict_types=1);

use Ux2Dev\Iris\Config\MerchantConfig;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Exception\ConfigurationException;
use Ux2Dev\Iris\Http\Credentials;

function credentialsFor(?string $agent = null, ?string $admin = null, ?string $public = null): Credentials
{
    return new Credentials(new MerchantConfig(
        environment: Environment::Development,
        publicHash: $public,
        agentHash: $agent,
        adminHash: $admin,
    ));
}

test('agent returns the agent header', function () {
    expect(credentialsFor(agent: 'a1')->agent())->toBe(['x-agent-hash' => 'a1']);
});

test('user returns the user header', function () {
    expect(credentialsFor(agent: 'a1')->user('u1'))->toBe(['x-user-hash' => 'u1']);
});

test('both returns agent and user headers', function () {
    expect(credentialsFor(agent: 'a1')->both('u1'))
        ->toBe(['x-agent-hash' => 'a1', 'x-user-hash' => 'u1']);
});

test('admin returns the admin header', function () {
    expect(credentialsFor(admin: 'ad1')->admin())->toBe(['x-admin-hash' => 'ad1']);
});

test('adminAgent returns admin and agent headers', function () {
    expect(credentialsFor(agent: 'a1', admin: 'ad1')->adminAgent())
        ->toBe(['x-admin-hash' => 'ad1', 'x-agent-hash' => 'a1']);
});

test('publicHash returns the public hash', function () {
    expect(credentialsFor(public: 'p1')->publicHash())->toBe('p1');
});

test('agent throws when agentHash is missing', function () {
    expect(fn () => credentialsFor(public: 'p1')->agent())
        ->toThrow(ConfigurationException::class, 'agentHash is required');
});

test('admin throws when adminHash is missing', function () {
    expect(fn () => credentialsFor(agent: 'a1')->admin())
        ->toThrow(ConfigurationException::class, 'adminHash is required');
});

test('publicHash throws when publicHash is missing', function () {
    expect(fn () => credentialsFor(agent: 'a1')->publicHash())
        ->toThrow(ConfigurationException::class, 'publicHash is required');
});

test('rejects a userHash containing a newline', function () {
    expect(fn () => credentialsFor(agent: 'a1')->user("u1\r\nX-Evil: 1"))
        ->toThrow(ConfigurationException::class, 'userHash contains invalid characters');
});

test('rejects an agentHash containing a newline', function () {
    expect(fn () => credentialsFor(agent: "a1\nX-Evil: 1")->agent())
        ->toThrow(ConfigurationException::class, 'agentHash contains invalid characters');
});
