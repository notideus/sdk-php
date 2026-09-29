<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use PHPUnit\Framework\TestCase;

final class ContactsResourceTest extends TestCase
{
    private const CONTACT = '{"id":"3fa8","email":"jane@example.com","status":"subscribed","properties":{"plan":"pro"},'
        . '"subscribed_topic_ids":["7b1c"],"created_at":"2026-09-10T12:00:00Z","updated_at":"2026-09-10T12:00:00Z"}';

    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient('k', ['handler' => $stack]);
    }

    public function testListSerializesFiltersAndMapsPage(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[' . self::CONTACT . '],"next_cursor":"20"}'),
        ], $history);

        $page = $notideus->contacts()->list([
            'limit' => 20, 'status' => 'subscribed', 'email' => 'jane', 'cursor' => '0',
        ]);

        self::assertCount(1, $page['data']);
        self::assertSame('20', $page['next_cursor']);
        self::assertSame(
            'limit=20&status=subscribed&email=jane&cursor=0',
            $history[0]['request']->getUri()->getQuery()
        );
    }

    public function testCreate(): void
    {
        $history = [];
        $notideus = $this->client([new Response(201, [], self::CONTACT)], $history);

        $contact = $notideus->contacts()->create([
            'email' => 'jane@example.com',
            'properties' => ['plan' => 'pro'],
            'topic_ids' => ['7b1c'],
        ]);

        self::assertSame('subscribed', $contact['status']);
        self::assertSame('POST', $history[0]['request']->getMethod());
    }

    public function testGetUrlEncodesEmail(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], self::CONTACT)], $history);

        $notideus->contacts()->get('jane+news@example.com');

        self::assertSame('/v1/contacts/jane%2Bnews%40example.com', $history[0]['request']->getUri()->getPath());
        self::assertSame('GET', $history[0]['request']->getMethod());
    }

    public function testUpdate(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], self::CONTACT)], $history);

        $notideus->contacts()->update('jane@example.com', ['properties' => ['plan' => 'scale']]);

        self::assertSame('PATCH', $history[0]['request']->getMethod());
        self::assertSame('/v1/contacts/jane%40example.com', $history[0]['request']->getUri()->getPath());
        /** @var array{properties: array<string, string>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(['plan' => 'scale'], $body['properties']);
    }

    public function testDeleteResolvesNullOn204(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->contacts()->delete('jane@example.com');

        self::assertSame('DELETE', $history[0]['request']->getMethod());
    }

    public function testBulkReturnsPerItemResults(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[{"index":0,"id":"3fa8"},{"index":1,"error":'
                . '{"code":"bad_request","message":"invalid email address"}}]}'),
        ], $history);

        $results = $notideus->contacts()->bulk([
            ['email' => 'jane@example.com'],
            ['email' => 'not-an-email'],
        ]);

        self::assertSame('3fa8', $results[0]['id']);
        self::assertSame('bad_request', $results[1]['error']['code']);
        self::assertSame('/v1/contacts/bulk', $history[0]['request']->getUri()->getPath());
    }

    public function testUnsubscribeAndResubscribe(): void
    {
        $history = [];
        $unsubscribed = str_replace('"subscribed"', '"unsubscribed"', self::CONTACT);
        $notideus = $this->client([
            new Response(200, [], $unsubscribed),
            new Response(200, [], self::CONTACT),
        ], $history);

        $contact = $notideus->contacts()->unsubscribe('jane@example.com');
        self::assertSame('unsubscribed', $contact['status']);
        self::assertSame('/v1/contacts/jane%40example.com/unsubscribe', $history[0]['request']->getUri()->getPath());

        $notideus->contacts()->resubscribe('jane@example.com');
        self::assertSame('/v1/contacts/jane%40example.com/resubscribe', $history[1]['request']->getUri()->getPath());
    }
}
