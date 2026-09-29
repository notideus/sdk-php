<?php

declare(strict_types=1);

namespace Notideus\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notideus\NotideusClient;
use PHPUnit\Framework\TestCase;

final class UnsubscribeAndPlansTest extends TestCase
{
    /** @param list<Response> $responses */
    private function client(array $responses, array &$history): NotideusClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new NotideusClient(null, ['handler' => $stack]);
    }

    public function testInfoUsesQueryParamWithoutAuthHeader(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(
                200,
                [],
                '{"email":"jane@example.com","status":"subscribed","team_name":"Acme Inc","topics":[]}',
            ),
        ], $history);

        $info = $notideus->unsubscribe()->info('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('Acme Inc', $info['team_name']);
        self::assertSame('t=550e8400-e29b-41d4-a716-446655440000', $history[0]['request']->getUri()->getQuery());
        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    public function testUnsubscribeOmitsTopicIdsWhenNull(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->unsubscribe()->unsubscribe('tok');

        self::assertSame('/v1/unsubscribe', $history[0]['request']->getUri()->getPath());
        /** @var array{token: string} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(['token' => 'tok'], $body);
    }

    public function testUnsubscribeSendsTopicIdsWhenGiven(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->unsubscribe()->unsubscribe('tok', ['7b1c']);

        /** @var array{token: string, topic_ids: list<string>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(['token' => 'tok', 'topic_ids' => ['7b1c']], $body);
    }

    public function testPreferencesAlwaysSendsTopicIds(): void
    {
        $history = [];
        $notideus = $this->client([new Response(204)], $history);

        $notideus->unsubscribe()->preferences('tok', []);

        self::assertSame('/v1/unsubscribe/preferences', $history[0]['request']->getUri()->getPath());
        /** @var array{token: string, topic_ids: list<string>} $body */
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(['token' => 'tok', 'topic_ids' => []], $body);
    }

    public function testPlansListWithoutKeyAndWithLocale(): void
    {
        $history = [];
        $notideus = $this->client([
            new Response(
                200,
                [],
                '{"free_allowances":{"emails":5000,"contacts":1000},'
                . '"overage_per_1000_cents":75,"plans":[],"contacts_tiers":[]}',
            ),
        ], $history);

        $catalog = $notideus->plans()->list('fr');

        self::assertSame(5000, $catalog['free_allowances']['emails']);
        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
        self::assertSame('locale=fr', $history[0]['request']->getUri()->getQuery());
    }
}
