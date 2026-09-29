<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\Http\ApiClient;
use Notideus\NotideusException;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    /** @param list<Response> $responses */
    private function countingClient(array $responses, array &$history, int $maxRetries = 2): ApiClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new ApiClient('k', ['handler' => $stack, 'max_retries' => $maxRetries]);
    }

    public function testRetries429HonoringRetryAfterZero(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(429, ['Retry-After' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
            new Response(200, [], '{"ok":true}'),
        ], $history);

        /** @var array{ok: bool} $result */
        $result = $client->request('GET', '/v1/contacts');
        self::assertTrue($result['ok']);
        self::assertCount(2, $history);
    }

    public function testRetries5xxWithBackoff(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(500, [], '{"error":{"code":"internal","message":"boom"}}'),
            new Response(500, [], '{"error":{"code":"internal","message":"boom"}}'),
            new Response(200, [], '{"ok":true}'),
        ], $history);

        $client->request('GET', '/v1/contacts');
        self::assertCount(3, $history);
    }

    public function testDoesNotRetryKeylessPost(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(429, ['Retry-After' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
        ], $history);

        try {
            $client->request('POST', '/v1/emails', ['body' => ['subject' => 'x']]);
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('rate_limited', $e->getErrorCode());
        }
        self::assertCount(1, $history);
    }

    public function testRetriesPostWithIdempotencyKey(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(500, [], '{"error":{"code":"internal","message":"boom"}}'),
            new Response(201, [], '{"id":"e1"}'),
        ], $history);

        /** @var array{id: string} $result */
        $result = $client->request('POST', '/v1/emails', [
            'body' => ['subject' => 'x', 'idempotency_key' => 'req-1'],
        ]);
        self::assertSame('e1', $result['id']);
        self::assertCount(2, $history);
    }

    public function testDoesNotRetryContactWrites(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(500, [], '{"error":{"code":"internal","message":"boom"}}'),
        ], $history);

        try {
            $client->request('POST', '/v1/contacts', ['body' => ['email' => 'j@x.co']]);
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('internal', $e->getErrorCode());
        }
        self::assertCount(1, $history);
    }

    public function testStopsAfterMaxRetries(): void
    {
        $history = [];
        $client = $this->countingClient([
            new Response(429, ['Retry-After' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
            new Response(429, ['Retry-After' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
            new Response(429, ['Retry-After' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
        ], $history, 1);

        try {
            $client->request('GET', '/v1/contacts');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('rate_limited', $e->getErrorCode());
        }
        self::assertCount(2, $history); // initial + 1 retry
    }
}
