<?php

declare(strict_types=1);

use Ux2Dev\Iris\PayByLink\Response\Bank;

test('creates bank from API response array', function () {
    $bank = Bank::fromArray([
        'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
        'name' => 'Gringotts',
        'fullName' => 'Gringotts Bank',
        'bic' => 'example',
        'services' => 'Account Information Services and Payment Initiation Services',
        'country' => 'bulgaria',
    ]);

    expect($bank->bankHash)->toBe('bf935ea4814061d70902683c1565fa2c');
    expect($bank->name)->toBe('Gringotts');
    expect($bank->fullName)->toBe('Gringotts Bank');
    expect($bank->bic)->toBe('example');
    expect($bank->services)->toBe('Account Information Services and Payment Initiation Services');
    expect($bank->country)->toBe('bulgaria');
});

test('tolerates nullable optional fields from API response', function () {
    $bank = Bank::fromArray([
        'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
        'name' => 'Gringotts',
        'fullName' => null,
        'bic' => null,
        'services' => null,
        'country' => null,
    ]);

    expect($bank->bankHash)->toBe('bf935ea4814061d70902683c1565fa2c');
    expect($bank->name)->toBe('Gringotts');
    expect($bank->fullName)->toBeNull();
    expect($bank->bic)->toBeNull();
    expect($bank->services)->toBeNull();
    expect($bank->country)->toBeNull();
});

test('tolerates missing optional keys from API response', function () {
    $bank = Bank::fromArray([
        'bankHash' => 'bf935ea4814061d70902683c1565fa2c',
        'name' => 'Gringotts',
    ]);

    expect($bank->fullName)->toBeNull();
    expect($bank->bic)->toBeNull();
    expect($bank->services)->toBeNull();
    expect($bank->country)->toBeNull();
});
