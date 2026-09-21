<?php

declare(strict_types=1);

use Ux2Dev\Iris\Laravel\IrisManager;
use Ux2Dev\Iris\Tests\TestCase;

uses(TestCase::class);

test('registers IrisManager as singleton', function () {
    $manager = $this->app->make(IrisManager::class);

    expect($manager)->toBeInstanceOf(IrisManager::class);
    expect($this->app->make(IrisManager::class))->toBe($manager);
});

test('resolves via iris alias', function () {
    $manager = $this->app->make('iris');

    expect($manager)->toBeInstanceOf(IrisManager::class);
});
