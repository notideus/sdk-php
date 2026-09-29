<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use PHPUnit\Framework\TestCase;

final class WhatsAppResourceTest extends TestCase
{
    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient('k', ['handler' => $stack]);
    }

    public function testSendReturnsOneEntryPerRecipient(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(201, [], '{"data":[{"id":"9c2e","to":"+33612345678","status":"queued"},'
                . '{"id":"9c2f","to":"+1555123456","status":"queued"}]}'),
        ], $history);

        $messages = $notideus->whatsapp()->messages()->send([
            'template_id' => '8f1c',
            'to' => ['+33612345678', '+1555123456'],
            'parameters' => ['body:1' => 'Jane'],
            'idempotency_key' => 'req-123',
        ]);

        self::assertCount(2, $messages);
        self::assertSame(['id' => '9c2e', 'to' => '+33612345678', 'status' => 'queued'], $messages[0]);
        self::assertSame('/v1/whatsapp/messages', $history[0]['request']->getUri()->getPath());
        /** @var array{parameters: array<string, string>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(['body:1' => 'Jane'], $body['parameters']);
    }

    public function testSendBatchReturnsPerItemResults(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[{"index":0,"id":"9c2e"},{"index":1,"error":{"code":"invalid_phone",'
                . '"message":"to[0]: invalid_phone: +abc is not a valid E.164 phone number"}}]}'),
        ], $history);

        $results = $notideus->whatsapp()->messages()->sendBatch([
            ['template_id' => '8f1c', 'to' => ['+33612345678'], 'parameters' => ['body:1' => 'Jane']],
            ['template_id' => '8f1c', 'to' => ['+abc'], 'parameters' => []],
        ]);

        self::assertSame('9c2e', $results[0]['id']);
        self::assertSame('invalid_phone', $results[1]['error']['code']);
        self::assertSame('/v1/whatsapp/messages/batch', $history[0]['request']->getUri()->getPath());
        /** @var array{messages: list<array<string, mixed>>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertCount(2, $body['messages']);
    }
}
