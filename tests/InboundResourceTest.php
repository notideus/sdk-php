<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use PHPUnit\Framework\TestCase;

final class InboundResourceTest extends TestCase
{
    private const ROUTE = '{"id":"r1","domain_id":"d1","recipient":"support","name":"Support","store":true,'
        . '"webhook_endpoint_id":"8f2c","created_at":"2026-10-01T12:00:00Z"}';

    private const MESSAGE = '{"id":"m1","route_id":"r1","from_addr":"jane@example.com","from_name":"Jane",'
        . '"to_addr":"support@example.com","subject":"Hi","text":"hello","html":"","headers":{},'
        . '"attachments":[],"spam_verdict":null,"size_kb":1,"status":"received","error":null,'
        . '"received_at":"2026-10-01T12:00:00Z"}';

    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient('k', ['handler' => $stack]);
    }

    public function testRoutesListKeepsMeta(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[' . self::ROUTE . '],"meta":{"simulate_enabled":false,'
                . '"retention_days":30}}'),
        ], $history);

        $page = $notideus->inbound()->routes()->list();

        self::assertCount(1, $page['data']);
        self::assertFalse($page['meta']['simulate_enabled']);
        self::assertSame(30, $page['meta']['retention_days']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/routes', $history[0]['request']->getUri()->getPath());
    }

    public function testRouteCreateSendsBody(): void
    {
        $history = [];
        $notideus = $this->client([new Response(201, [], '{"route":' . self::ROUTE . '}')], $history);

        $route = $notideus->inbound()->routes()->create([
            'domain_id' => 'd1',
            'recipient' => 'support',
            'name' => 'Support',
            'store' => true,
            'webhook_endpoint_id' => '8f2c',
        ]);

        self::assertSame('r1', $route['id']);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/routes', $history[0]['request']->getUri()->getPath());
        /** @var array{domain_id: string, recipient: string, name: string, store: bool, webhook_endpoint_id: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('d1', $body['domain_id']);
        self::assertSame('support', $body['recipient']);
        self::assertSame('Support', $body['name']);
        self::assertTrue($body['store']);
        self::assertSame('8f2c', $body['webhook_endpoint_id']);
    }

    public function testRouteGetUnwrapsRoute(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], '{"route":' . self::ROUTE . '}')], $history);

        $route = $notideus->inbound()->routes()->get('r1/x');

        self::assertSame('support', $route['recipient']);
        self::assertSame('/v1/inbound/routes/r1%2Fx', $history[0]['request']->getUri()->getPath());
    }

    public function testRouteUpdateOmitsDomainId(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], '{"route":' . self::ROUTE . '}')], $history);

        $route = $notideus->inbound()->routes()->update('r1', [
            'recipient' => 'help',
            'store' => false,
            'webhook_endpoint_id' => '8f2c',
        ]);

        self::assertSame('r1', $route['id']);
        self::assertSame('PATCH', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/routes/r1', $history[0]['request']->getUri()->getPath());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('help', $body['recipient']);
        self::assertFalse($body['store']);
        self::assertArrayNotHasKey('domain_id', $body);
        self::assertArrayNotHasKey('status', $body);
    }

    public function testRouteDeleteResolvesNullOn204(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->inbound()->routes()->delete('r1');

        self::assertSame('DELETE', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/routes/r1', $history[0]['request']->getUri()->getPath());
    }

    public function testMessagesListPassesThroughQuery(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[' . self::MESSAGE . '],"next_cursor":"5"}'),
        ], $history);

        $page = $notideus->inbound()->messages()->list([
            'q' => 'invoice',
            'route_id' => 'r1',
            'cursor' => '0',
            'limit' => 10,
        ]);

        self::assertCount(1, $page['data']);
        self::assertSame('5', $page['next_cursor']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/messages', $history[0]['request']->getUri()->getPath());
        self::assertSame('q=invoice&route_id=r1&cursor=0&limit=10', $history[0]['request']->getUri()->getQuery());
    }

    public function testMessageGetUnwrapsMessage(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], '{"message":' . self::MESSAGE . '}')], $history);

        $message = $notideus->inbound()->messages()->get('m1');

        self::assertSame('Hi', $message['subject']);
        self::assertSame('received', $message['status']);
        self::assertSame('/v1/inbound/messages/m1', $history[0]['request']->getUri()->getPath());
    }

    public function testRawDownloadsMimeWithFallbackFilename(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, ['Content-Type' => 'message/rfc822'], 'RAW MIME'),
        ], $history);

        $result = $notideus->inbound()->messages()->raw('m1');

        self::assertSame('RAW MIME', $result['contents']);
        self::assertSame('download', $result['filename']);
        self::assertSame('message/rfc822', $result['content_type']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/inbound/messages/m1/raw', $history[0]['request']->getUri()->getPath());
    }

    public function testAttachmentDownloadsByIndex(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="report.csv"',
            ], 'a,b'),
        ], $history);

        $result = $notideus->inbound()->messages()->attachment('m1', 0);

        self::assertSame('a,b', $result['contents']);
        self::assertSame('report.csv', $result['filename']);
        self::assertSame('text/csv', $result['content_type']);
        self::assertSame('/v1/inbound/messages/m1/attachments/0', $history[0]['request']->getUri()->getPath());
    }
}
