<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\Http\ApiClient;
use Notideus\NotideusClient;
use Notideus\NotideusException;
use PHPUnit\Framework\TestCase;

final class WebhooksResourceTest extends TestCase
{
    private const ENDPOINT = '{"id":"8f2c","name":"Sync","url":"https://example.com/hook","events":["email.sent"],'
        . '"status":"active","created_at":"2026-10-01T12:00:00Z","updated_at":"2026-10-01T12:00:00Z"}';

    private const DELIVERY = '{"id":"d1","endpoint_id":"8f2c","event":"email.sent","status":"success",'
        . '"created_at":"2026-10-01T12:00:00Z"}';

    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient('k', ['handler' => $stack]);
    }

    public function testListPassesThroughPagination(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[' . self::ENDPOINT . '],"next_cursor":"20"}'),
        ], $history);

        $page = $notideus->webhooks()->list(['cursor' => '0', 'limit' => 20]);

        self::assertCount(1, $page['data']);
        self::assertSame('20', $page['next_cursor']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks', $history[0]['request']->getUri()->getPath());
        self::assertSame('cursor=0&limit=20', $history[0]['request']->getUri()->getQuery());
    }

    public function testCreateReturnsEndpointAndSecret(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(201, [], '{"endpoint":' . self::ENDPOINT . ',"secret":"whsec_abc"}'),
        ], $history);

        $created = $notideus->webhooks()->create([
            'name' => 'Sync',
            'url' => 'https://example.com/hook',
            'events' => ['email.sent'],
        ]);

        self::assertSame('whsec_abc', $created['secret']);
        self::assertSame('8f2c', $created['endpoint']['id']);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks', $history[0]['request']->getUri()->getPath());
        /** @var array{name: string, url: string, events: list<string>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('Sync', $body['name']);
        self::assertSame('https://example.com/hook', $body['url']);
        self::assertSame(['email.sent'], $body['events']);
    }

    public function testGetUnwrapsEndpointAndUrlEncodesId(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], '{"endpoint":' . self::ENDPOINT . '}')], $history);

        $endpoint = $notideus->webhooks()->get('8f2c/x');

        self::assertSame('Sync', $endpoint['name']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/8f2c%2Fx', $history[0]['request']->getUri()->getPath());
    }

    public function testUpdateSendsAllFourFields(): void
    {
        $history = [];
        $notideus = $this->client([new Response(200, [], '{"endpoint":' . self::ENDPOINT . '}')], $history);

        $endpoint = $notideus->webhooks()->update('8f2c', [
            'name' => 'Sync',
            'url' => 'https://example.com/hook',
            'events' => ['email.sent', 'email.bounced'],
            'status' => 'disabled',
        ]);

        self::assertSame('Sync', $endpoint['name']);
        self::assertSame('PATCH', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/8f2c', $history[0]['request']->getUri()->getPath());
        /** @var array{name: string, url: string, events: list<string>, status: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('Sync', $body['name']);
        self::assertSame('https://example.com/hook', $body['url']);
        self::assertSame(['email.sent', 'email.bounced'], $body['events']);
        self::assertSame('disabled', $body['status']);
    }

    public function testDeleteResolvesNullOn204(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->webhooks()->delete('8f2c');

        self::assertSame('DELETE', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/8f2c', $history[0]['request']->getUri()->getPath());
    }

    public function testTestWithEventSendsBody(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"delivery":' . self::DELIVERY . '}'),
        ], $history);

        $delivery = $notideus->webhooks()->test('8f2c', 'email.sent');

        self::assertSame('d1', $delivery['id']);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/8f2c/test', $history[0]['request']->getUri()->getPath());
        /** @var array{event: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame('email.sent', $body['event']);
    }

    public function testTestWithoutEventOmitsBody(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"delivery":' . self::DELIVERY . '}'),
        ], $history);

        $notideus->webhooks()->test('8f2c');

        self::assertSame('', (string) $history[0]['request']->getBody());
    }

    public function testEventsUnwrapsData(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[{"type":"email.sent","description":"Sent","sample":{}}]}'),
        ], $history);

        $events = $notideus->webhooks()->events();

        self::assertSame('email.sent', $events[0]['type']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/meta/events', $history[0]['request']->getUri()->getPath());
    }

    public function testRotateSecretReturnsEndpointAndSecret(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"endpoint":' . self::ENDPOINT . ',"secret":"whsec_new"}'),
        ], $history);

        $rotated = $notideus->webhooks()->rotateSecret('8f2c');

        self::assertSame('whsec_new', $rotated['secret']);
        self::assertSame('8f2c', $rotated['endpoint']['id']);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhooks/8f2c/rotate-secret', $history[0]['request']->getUri()->getPath());
        self::assertSame('', (string) $history[0]['request']->getBody());
    }

    public function testDeliveriesListPassesThroughFilters(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"data":[' . self::DELIVERY . '],"next_cursor":"10"}'),
        ], $history);

        $page = $notideus->webhooks()->deliveries()->list([
            'endpoint_id' => '8f2c',
            'status' => 'failed',
            'limit' => 5,
        ]);

        self::assertCount(1, $page['data']);
        self::assertSame('10', $page['next_cursor']);
        self::assertSame('/v1/webhook-deliveries', $history[0]['request']->getUri()->getPath());
        self::assertSame('endpoint_id=8f2c&status=failed&limit=5', $history[0]['request']->getUri()->getQuery());
    }

    public function testDeliveryGetReturnsDeliveryAndAttempts(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"delivery":' . self::DELIVERY . ',"attempts":[{"status_code":200}]}'),
        ], $history);

        $result = $notideus->webhooks()->deliveries()->get('d1');

        self::assertSame('d1', $result['delivery']['id']);
        self::assertSame(200, $result['attempts'][0]['status_code']);
        self::assertSame('/v1/webhook-deliveries/d1', $history[0]['request']->getUri()->getPath());
    }

    public function testRedeliverUnwrapsDelivery(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(200, [], '{"delivery":' . self::DELIVERY . '}'),
        ], $history);

        $delivery = $notideus->webhooks()->deliveries()->redeliver('d1');

        self::assertSame('d1', $delivery['id']);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v1/webhook-deliveries/d1/redeliver', $history[0]['request']->getUri()->getPath());
    }

    public function testEnvelopeErrorOnMissingEndpoint(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(404, [], '{"error":{"code":"not_found","message":"webhook endpoint not found"}}'),
        ], $history);

        try {
            $notideus->webhooks()->get('nope');
            self::fail('expected NotideusException');
        } catch (NotideusException $e) {
            self::assertSame('not_found', $e->getErrorCode());
            self::assertSame(404, $e->getStatus());
        }
    }

    public function testRequestBinaryParsesHeaders(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoice.pdf"',
            ], 'PDFDATA'),
        ]));
        $stack->push(Middleware::history($history));
        $api = new ApiClient('k', ['handler' => $stack]);

        $result = $api->requestBinary('/v1/inbound/messages/abc/attachments/att_1/download');

        self::assertSame('PDFDATA', $result['contents']);
        self::assertSame('invoice.pdf', $result['filename']);
        self::assertSame('application/pdf', $result['content_type']);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame(
            '/v1/inbound/messages/abc/attachments/att_1/download',
            $history[0]['request']->getUri()->getPath()
        );
        self::assertSame('Bearer k', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testRequestBinaryFallsBackToDownloadFilename(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'message/rfc822'], 'RAW'),
        ]));
        $api = new ApiClient('k', ['handler' => $stack]);

        $result = $api->requestBinary('/v1/inbound/messages/abc/raw');

        self::assertSame('RAW', $result['contents']);
        self::assertSame('download', $result['filename']);
        self::assertSame('message/rfc822', $result['content_type']);
    }

    public function testRequestBinaryMapsEnvelopeError(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(404, [], '{"error":{"code":"not_found","message":"attachment not found"}}'),
        ]));
        $api = new ApiClient('k', ['handler' => $stack]);

        $this->expectException(NotideusException::class);
        $this->expectExceptionMessage('attachment not found');

        $api->requestBinary('/v1/inbound/messages/abc/attachments/nope/download');
    }
}
