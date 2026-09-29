<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use Notideus\NotideusException;
use PHPUnit\Framework\TestCase;

final class EmailsResourceTest extends TestCase
{
    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient('nt_live_k', ['handler' => $stack]);
    }

    public function testSend(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(201, [], '{"id":"9c2e","from_address":"noreply@acme.com","subject":"Welcome"}'),
        ], $history);

        /** @var array{id: string} $email */
        $email = $notideus->emails()->send([
            'from' => 'Acme Inc <noreply@acme.com>',
            'to' => ['jane@example.com'],
            'subject' => 'Welcome',
            'html' => '<p>Hi {{name}}</p>',
            'variables' => ['name' => 'Jane'],
            'tags' => ['welcome'],
            'idempotency_key' => 'req-123',
        ]);

        self::assertSame('9c2e', $email['id']);
        self::assertSame('/v1/emails', $history[0]['request']->getUri()->getPath());
        self::assertSame('POST', $history[0]['request']->getMethod());
        /** @var array{from: string, to: list<string>, idempotency_key: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('Acme Inc <noreply@acme.com>', $body['from']);
        self::assertSame(['jane@example.com'], $body['to']);
        self::assertSame('req-123', $body['idempotency_key']);
    }

    public function testSendBatchReturnsPerItemResults(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[{"index":0,"id":"9c2e"},{"index":1,"error":{"code":"from_domain_not_verified",'
                . '"message":"no verified domain matches the from address"}}]}'),
        ], $history);

        $results = $notideus->emails()->sendBatch([
            ['from' => 'a@b.co', 'to' => ['j@x.co'], 'subject' => 'one', 'html' => '<p>1</p>'],
            ['from' => 'a@b.co', 'to' => ['k@x.co'], 'subject' => 'two', 'html' => '<p>2</p>'],
        ]);

        self::assertSame([
            ['index' => 0, 'id' => '9c2e'],
            ['index' => 1, 'error' => [
                'code' => 'from_domain_not_verified',
                'message' => 'no verified domain matches the from address',
            ]],
        ], $results);
        self::assertSame('/v1/emails/batch', $history[0]['request']->getUri()->getPath());
        /** @var array{emails: list<array<string, mixed>>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertCount(2, $body['emails']);
    }

    public function testWholeBatchFailureThrows(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(402, [], '{"error":{"code":"quota_exceeded","message":"allowance reached"}}'),
        ], $history);

        $this->expectException(NotideusException::class);
        $notideus->emails()->sendBatch([
            ['from' => 'a@b.co', 'to' => ['j@x.co'], 'subject' => 's', 'html' => '<p>h</p>'],
        ]);
    }
}
