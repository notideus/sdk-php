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

final class ApiClientTest extends TestCase
{
    /** @param list<Response> $responses */
    private function client(array $responses, array &$history = [], ?string $key = 'nt_live_test'): ApiClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        return new ApiClient($key, ['handler' => $stack]);
    }

    public function testPostsJsonWithBearerAuthAndUserAgent(): void
    {
        $history = [];
        $client = $this->client([new Response(201, [], '{"id":"abc"}')], $history);

        /** @var array{id: string} $result */
        $result = $client->request('POST', '/v1/emails', ['body' => ['subject' => 'Hi']]);

        self::assertSame('abc', $result['id']);
        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/emails', $request->getUri()->getPath());
        self::assertSame('Bearer nt_live_test', $request->getHeaderLine('Authorization'));
        self::assertStringStartsWith('notideus-php/', $request->getHeaderLine('User-Agent'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"subject":"Hi"}', (string) $request->getBody());
    }

    public function testOmitsAuthorizationWhenUnauthenticatedOrKeyless(): void
    {
        $history = [];
        $client = $this->client([new Response(200, [], '{}')], $history, null);
        $client->request('GET', '/v1/plans', ['authenticated' => false]);
        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    public function testDropsNullQueryParams(): void
    {
        $history = [];
        $client = $this->client(
            [new Response(200, [], '{"data":[]}')],
            $history
        );
        $client->request('GET', '/v1/contacts', [
            'query' => ['limit' => 50, 'status' => 'subscribed', 'cursor' => null],
        ]);
        self::assertSame('limit=50&status=subscribed', $history[0]['request']->getUri()->getQuery());
    }

    public function testThrowsNotideusExceptionFromEnvelope(): void
    {
        $client = $this->client([
            new Response(
                422,
                [],
                '{"error":{"code":"from_domain_not_verified","message":"no verified domain matches the from address"}}'
            ),
        ]);
        try {
            $client->request('POST', '/v1/emails', ['body' => []]);
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('from_domain_not_verified', $e->getErrorCode());
            self::assertSame(422, $e->getStatus());
            self::assertSame('no verified domain matches the from address', $e->getMessage());
        }
    }

    public function testParsesRetryAfterOn429(): void
    {
        $client = $this->client([
            new Response(429, ['Retry-After' => '7'], '{"error":{"code":"rate_limited","message":"slow down"}}'),
        ]);
        try {
            $client->request('GET', '/v1/contacts');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('rate_limited', $e->getErrorCode());
            self::assertSame(7, $e->getRetryAfter());
        }
    }

    public function testParsesReasonOnAccountSuspended(): void
    {
        $client = $this->client([
            new Response(
                403,
                [],
                '{"error":{"code":"account_suspended","message":"this account is suspended",'
                    . '"reason":"spam complaints"}}'
            ),
        ]);
        try {
            $client->request('GET', '/v1/contacts');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('account_suspended', $e->getErrorCode());
            self::assertSame('spam complaints', $e->getReason());
        }
    }

    public function testReasonIsNullWhenAbsent(): void
    {
        $client = $this->client([
            new Response(401, [], '{"error":{"code":"unauthorized","message":"bad key"}}'),
        ]);
        try {
            $client->request('GET', '/v1/contacts');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertNull($e->getReason());
        }
    }

    public function testReturnsNullFor204(): void
    {
        $client = $this->client([new Response(204)]);
        self::assertNull($client->request('DELETE', '/v1/contacts/a%40b.c'));
    }

    public function testWrapsNetworkFailures(): void
    {
        // Directly exercise the ConnectException branch via a refusing handler.
        $stack = HandlerStack::create(new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'https://api.notideus.io/v1/contacts')
            ),
        ]));
        $failing = new ApiClient('k', ['handler' => $stack]);
        try {
            $failing->request('GET', '/v1/contacts');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('network_error', $e->getErrorCode());
            self::assertSame(0, $e->getStatus());
        }
    }
}
