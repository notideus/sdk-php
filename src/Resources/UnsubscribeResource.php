<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class UnsubscribeResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /** @return array<string, mixed> */
    public function info(string $token): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('GET', '/v1/unsubscribe/info', [
            'query' => ['t' => $token],
            'authenticated' => false,
        ]);

        return $result;
    }

    /** @param list<string>|null $topicIds */
    public function unsubscribe(string $token, ?array $topicIds = null): void
    {
        $body = ['token' => $token];
        if ($topicIds !== null) {
            $body['topic_ids'] = $topicIds;
        }
        $this->api->request('POST', '/v1/unsubscribe', ['body' => $body, 'authenticated' => false]);
    }

    /** @param list<string> $topicIds */
    public function preferences(string $token, array $topicIds): void
    {
        $this->api->request('POST', '/v1/unsubscribe/preferences', [
            'body' => ['token' => $token, 'topic_ids' => $topicIds],
            'authenticated' => false,
        ]);
    }
}
