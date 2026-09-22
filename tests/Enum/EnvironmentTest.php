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
