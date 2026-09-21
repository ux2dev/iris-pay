<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Ux2Dev\Iris\Iris;
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

test('passes bound PSR-18/17 implementations to IrisManager instead of falling back to Guzzle', function () {
    $client = new class implements ClientInterface {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            throw new RuntimeException('sendRequest should not be called in this test');
        }
    };

    $requestFactory = new class implements RequestFactoryInterface {
        public function createRequest(string $method, $uri): RequestInterface
        {
            throw new RuntimeException('createRequest should not be called in this test');
        }
    };

    $streamFactory = new class implements StreamFactoryInterface {
        public function createStream(string $content = ''): StreamInterface
        {
            throw new RuntimeException('createStream should not be called in this test');
        }

        public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
        {
            throw new RuntimeException('createStreamFromFile should not be called in this test');
        }

        public function createStreamFromResource($resource): StreamInterface
        {
            throw new RuntimeException('createStreamFromResource should not be called in this test');
        }
    };

    $this->app->instance(ClientInterface::class, $client);
    $this->app->instance(RequestFactoryInterface::class, $requestFactory);
    $this->app->instance(StreamFactoryInterface::class, $streamFactory);

    $manager = $this->app->make(IrisManager::class);

    $reflection = new ReflectionClass($manager);

    expect($reflection->getProperty('httpClient')->getValue($manager))->toBe($client)
        ->and($reflection->getProperty('requestFactory')->getValue($manager))->toBe($requestFactory)
        ->and($reflection->getProperty('streamFactory')->getValue($manager))->toBe($streamFactory);
});

test('falls back to the manager\'s own Guzzle defaults when nothing is bound in the container', function () {
    $manager = $this->app->make(IrisManager::class);

    $reflection = new ReflectionClass($manager);

    expect($reflection->getProperty('httpClient')->getValue($manager))->toBeNull()
        ->and($reflection->getProperty('requestFactory')->getValue($manager))->toBeNull()
        ->and($reflection->getProperty('streamFactory')->getValue($manager))->toBeNull();

    expect($manager->client())->toBeInstanceOf(Iris::class);
});
