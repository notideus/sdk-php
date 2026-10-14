<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class WebhookDeliveriesResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param array{endpoint_id?: string, event_type?: string, status?: string, cursor?: string, limit?: int} $params
     * @return array{data: array<int, array<string, mixed>>, next_cursor?: string}
     */
    public function list(array $params = []): array
    {
        /** @var array{data: array<int, array<string, mixed>>, next_cursor?: string} $result */
        $result = $this->api->request('GET', '/v1/webhook-deliveries', ['query' => $params]);

        return $result;
    }

    /** @return array{delivery: array<string, mixed>, attempts: array<int, array<string, mixed>>} */
    public function get(string $id): array
    {
        /** @var array{delivery: array<string, mixed>, attempts: array<int, array<string, mixed>>} $result */
        $result = $this->api->request('GET', '/v1/webhook-deliveries/' . rawurlencode($id));

        return $result;
    }

    /** @return array<string, mixed> */
    public function redeliver(string $id): array
    {
        /** @var array{delivery: array<string, mixed>} $result */
        $result = $this->api->request('POST', '/v1/webhook-deliveries/' . rawurlencode($id) . '/redeliver');

        return $result['delivery'];
    }
}
