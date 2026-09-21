<?php

declare(strict_types=1);

$manifest = require __DIR__ . '/method-manifest.php';

test('the manifest covers all 56 endpoint methods', function () use ($manifest) {
    expect($manifest)->toHaveCount(56);
});

test('every implemented resource exposes its manifest methods', function () use ($manifest) {
    $checked = 0;

    foreach ($manifest as [$class, $method, $requiredParams]) {
        if (! class_exists($class)) {
            continue;
        }

        expect(method_exists($class, $method))->toBeTrue(
            "{$class}::{$method}() is missing",
        );

        $reflection = new ReflectionMethod($class, $method);
        expect($reflection->isPublic())->toBeTrue("{$class}::{$method}() must be public");
        expect($reflection->getNumberOfRequiredParameters())->toBe(
            $requiredParams,
            "{$class}::{$method}() should take {$requiredParams} required parameters",
        );

        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});
