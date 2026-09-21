<?php

declare(strict_types=1);

use Ux2Dev\Iris\Enum\ComponentType;
use Ux2Dev\Iris\Enum\Country;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;
use Ux2Dev\Iris\WebSdk\ComponentConfig;

test('builds minimal payment component attributes', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('payment');
    expect($attrs['userhash'])->toBe('user-hash-123');
    expect($attrs['backend'])->toBe('https://developer.sandbox.irispay.bg');
    expect($attrs['hookhash'])->toBe('hook-hash-456');
    expect($attrs)->not->toHaveKey('lang');
    expect($attrs)->not->toHaveKey('country');
});

test('includes optional attributes when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Production,
        hookHash: 'hook-hash-456',
        lang: Language::English,
        country: Country::Bulgaria,
        showBankSelector: true,
        redirectUrl: 'https://example.com/done',
        redirectTimeout: 5,
    );

    $attrs = $config->toAttributes();

    expect($attrs['lang'])->toBe('en');
    expect($attrs['country'])->toBe('bulgaria');
    expect($attrs['show_bank_selector'])->toBe('true');
    expect($attrs['redirect_url'])->toBe('https://example.com/done');
    expect($attrs['redirect_timeout'])->toBe('5');
});

test('builds payment-data component with payment data', function () {
    $config = new ComponentConfig(
        type: ComponentType::PaymentData,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        paymentData: [
            'sum' => 9.99,
            'description' => 'Test payment',
            'currency' => 'EUR',
            'merchant' => 'Test Merchant',
            'toIban' => 'BG31UNCR70001526254645',
            'publicHash' => 'pub-hash-789',
        ],
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('payment-data');
    expect($attrs['show_bank_selector'])->toBe('false');
    $paymentData = json_decode($attrs['payment_data'], true);
    expect($paymentData['sum'])->toBe(9.99);
    expect($paymentData['currency'])->toBe('EUR');
});

test('builds add-iban-with-bank component with bank hash', function () {
    $config = new ComponentConfig(
        type: ComponentType::AddIbanWithBank,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        bankHash: 'bank-hash-789',
    );

    $attrs = $config->toAttributes();

    expect($attrs['type'])->toBe('add-iban-with-bank');
    expect($attrs['bankhash'])->toBe('bank-hash-789');
    expect($attrs)->not->toHaveKey('hookhash');
});

test('generates script and stylesheet tags', function () {
    $tags = ComponentConfig::assetTags(Environment::Production);

    expect($tags)->toContain('https://websdk.irispay.bg/assets/irispay-ui/elements.js');
    expect($tags)->toContain('https://websdk.irispay.bg/assets/irispay-ui/styles.css');
});

test('includes pagination options when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        paginationOptions: ['start_page_items' => 9, 'increase_per_click' => 3],
    );

    $attrs = $config->toAttributes();
    $pagination = json_decode($attrs['pagination_options'], true);

    expect($pagination['start_page_items'])->toBe(9);
});

test('includes header options when set', function () {
    $config = new ComponentConfig(
        type: ComponentType::Payment,
        userHash: 'user-hash-123',
        backend: Environment::Development,
        hookHash: 'hook-hash-456',
        headerOptions: ['show_header' => true, 'show_language_selector' => true],
    );

    $attrs = $config->toAttributes();
    $header = json_decode($attrs['header_options'], true);

    expect($header['show_header'])->toBeTrue();
});
