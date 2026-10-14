<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class WebhooksResource
{
    private readonly WebhookDeliveriesResource $deliveries;

    public function __construct(private readonly ApiClient $api)
    {
        $this->deliveries = new WebhookDeliveriesResource($api);
    }

    /**
     * @param array{cursor?: string, limit?: int} $params
     * @return array{data: array<int, array<string, mixed>>, next_cursor?: string}
     */
    public function list(array $params = []): array
    {
        /** @var array{data: array<int, array<string, mixed>>, next_cursor?: string} $result */
        $result = $this->api->request('GET', '/v1/webhooks', ['query' => $params]);

        return $result;
    }

    /**
     * @param array{name: string, url: string, events: list<string>} $params
     * @return array{endpoint: array<string, mixed>, secret: string}
     */
    public function create(array $params): array
    {
        /** @var array{endpoint: array<string, mixed>, secret: string} $result */
        $result = $this->api->request('POST', '/v1/webhooks', ['body' => $params]);

        return $result;
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        /** @var array{endpoint: array<string, mixed>} $result */
        $result = $this->api->request('GET', '/v1/webhooks/' . rawurlencode($id));

        return $result['endpoint'];
    }

    /**
     * @param array{name: string, url: string, events: list<string>, status: string} $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array{endpoint: array<string, mixed>} $result */
        $result = $this->api->request('PATCH', '/v1/webhooks/' . rawurlencode($id), ['body' => $params]);

        return $result['endpoint'];
    }

    public function delete(string $id): void
    {
        $this->api->request('DELETE', '/v1/webhooks/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function test(string $id, ?string $event = null): array
    {
        $options = [];
        if ($event !== null) {
            $options['body'] = ['event' => $event];
        }
        /** @var array{delivery: array<string, mixed>} $result */
        $result = $this->api->request('POST', '/v1/webhooks/' . rawurlencode($id) . '/test', $options);

        return $result['delivery'];
    }

    /** @return array<int, array<string, mixed>> */
    public function events(): array
    {
        /** @var array{data: array<int, array<string, mixed>>} $result */
        $result = $this->api->request('GET', '/v1/webhooks/meta/events');

        return $result['data'];
    }

    public function deliveries(): WebhookDeliveriesResource
    {
        return $this->deliveries;
    }
}
