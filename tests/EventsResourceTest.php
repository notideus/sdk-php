<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use PHPUnit\Framework\TestCase;

final class EventsResourceTest extends TestCase
{
    public function testSendCustomEvent(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(202, [], '{"created":true,"event":'
                . '{"id":"evt-1","event":"user.created","email":"ada@example.com"}}'),
        ]));
        $stack->push(Middleware::history($history));
        $notideus = new NotideusClient('nt_live_k', ['handler' => $stack]);

        $result = $notideus->events()->send([
            'event' => 'user.created',
            'email' => 'ada@example.com',
            'payload' => ['first_name' => 'Ada', 'plan' => 'Pro'],
            'idempotency_key' => 'signup-42',
        ]);

        self::assertTrue($result['created']);
        self::assertSame('evt-1', $result['event']['id']);
        self::assertSame('/v1/events/send', $history[0]['request']->getUri()->getPath());
        self::assertSame('POST', $history[0]['request']->getMethod());
        /** @var array{event: string, email: string, payload: array{first_name: string, plan: string}, idempotency_key: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('user.created', $body['event']);
        self::assertSame('ada@example.com', $body['email']);
        self::assertSame(['first_name' => 'Ada', 'plan' => 'Pro'], $body['payload']);
        self::assertSame('signup-42', $body['idempotency_key']);
    }

    public function testSendToContactAndReplay(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(202, [], '{"created":false,"event":{"id":"evt-1","contact_id":"contact-1"}}'),
        ]));
        $stack->push(Middleware::history($history));
        $notideus = new NotideusClient('nt_live_k', ['handler' => $stack]);

        $result = $notideus->events()->send(['event' => 'user.created', 'contact_id' => 'contact-1']);

        self::assertFalse($result['created']);
        self::assertSame('contact-1', $result['event']['contact_id']);
        /** @var array{contact_id: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('contact-1', $body['contact_id']);
    }
}
