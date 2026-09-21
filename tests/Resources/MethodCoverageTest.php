<?php

declare(strict_types=1);

$manifest = require __DIR__ . '/method-manifest.php';

test('the manifest covers all 56 endpoint methods', function () use ($manifest) {
    expect($manifest)->toHaveCount(56);
});

test('every manifest method exists on its resource', function () use ($manifest) {
    foreach ($manifest as [$class, $method, $requiredParams]) {
        expect(class_exists($class))->toBeTrue("{$class} is missing");
        expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is missing");

        $reflection = new ReflectionMethod($class, $method);
        expect($reflection->isPublic())->toBeTrue("{$class}::{$method}() must be public");
        expect($reflection->getNumberOfRequiredParameters())->toBe(
            $requiredParams,
            "{$class}::{$method}() should take {$requiredParams} required parameters",
        );
    }
});
