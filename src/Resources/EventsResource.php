<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class EventsResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return array{event: array<string, mixed>, created: bool}
     */
    public function send(array $params): array
    {
        /** @var array{event: array<string, mixed>, created: bool} $result */
        $result = $this->api->request('POST', '/v1/events/send', ['body' => $params]);

        return $result;
    }
}
