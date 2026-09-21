<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Iris\Exception\ApiClientException;
use Ux2Dev\Iris\Exception\ApiServerException;
use Ux2Dev\Iris\Exception\InvalidResponseException;
use Ux2Dev\Iris\Exception\NetworkException;
use Ux2Dev\Iris\Http\IrisTransport;

function makeTransport(array $responses, ?array &$container = null): IrisTransport
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    if ($container !== null) {
        $stack->push(\GuzzleHttp\Middleware::history($container));
    }
    $factory = new HttpFactory();

    return new IrisTransport(
        'https://api.example.test',
        new Client(['handler' => $stack]),
        $factory,
        $factory,
    );
}

test('get decodes a JSON object', function () {
    $transport = makeTransport([new Response(200, [], json_encode(['a' => 1]))]);

    expect($transport->get('/thing'))->toBe(['a' => 1]);
});

test('get appends the query string and sends headers', function () {
    $history = [];
    $transport = makeTransport([new Response(200, [], '{}')], $history);

    $transport->get('/thing', ['x-agent-hash' => 'agent'], ['page' => 2]);

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toBe('https://api.example.test/thing?page=2')
        ->and($request->getHeaderLine('x-agent-hash'))->toBe('agent');
});

test('getRaw returns the body untouched', function () {
    $transport = makeTransport([new Response(200, [], "\x89PNG\r\n")]);

    expect($transport->getRaw('/qr/abc'))->toBe("\x89PNG\r\n");
});

test('post sends a JSON body', function () {
    $history = [];
    $transport = makeTransport([new Response(200, [], '{"ok":true}')], $history);

    $result = $transport->post('/thing', ['sum' => 10]);

    expect($result)->toBe(['ok' => true])
        ->and((string) $history[0]['request']->getBody())->toBe('{"sum":10}')
        ->and($history[0]['request']->getHeaderLine('Content-Type'))->toBe('application/json');
});

test('postForString trims surrounding quotes', function () {
    $transport = makeTransport([new Response(200, [], '"token-value"')]);

    expect($transport->postForString('/usertoken'))->toBe('token-value');
});

test('postForString rejects an empty body', function () {
    $transport = makeTransport([new Response(200, [], '""')]);

    expect(fn () => $transport->postForString('/usertoken'))
        ->toThrow(InvalidResponseException::class, 'Empty response from IRIS API');
});

test('putVoid sends a body and returns nothing', function () {
    $history = [];
    $transport = makeTransport([new Response(204)], $history);

    $transport->putVoid('/inactive', ['paymentHash' => 'h']);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getBody())->toBe('{"paymentHash":"h"}');
});

test('delete issues a DELETE', function () {
    $history = [];
    $transport = makeTransport([new Response(204)], $history);

    $transport->delete('/iban/1');

    expect($history[0]['request']->getMethod())->toBe('DELETE');
});

test('maps 4xx to ApiClientException', function () {
    $transport = makeTransport([new Response(422, [], '{}')]);

    expect(fn () => $transport->get('/thing'))
        ->toThrow(ApiClientException::class, 'HTTP 422 from IRIS API');
});

test('maps 5xx to ApiServerException', function () {
    $transport = makeTransport([new Response(503, [], '{}')]);

    expect(fn () => $transport->get('/thing'))
        ->toThrow(ApiServerException::class, 'HTTP 503 from IRIS API');
});

test('maps transport failures to NetworkException', function () {
    $transport = makeTransport([
        new ConnectException('refused', new GuzzleRequest('GET', 'https://api.example.test')),
    ]);

    expect(fn () => $transport->get('/thing'))->toThrow(NetworkException::class);
});

test('rejects a non-JSON body', function () {
    $transport = makeTransport([new Response(200, [], 'not json')]);

    expect(fn () => $transport->get('/thing'))->toThrow(InvalidResponseException::class);
});
