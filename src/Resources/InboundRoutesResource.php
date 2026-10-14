<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class InboundRoutesResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{simulate_enabled: bool, retention_days: int},
     * }
     */
    public function list(): array
    {
        /**
         * @var array{
         *     data: array<int, array<string, mixed>>,
         *     meta: array{simulate_enabled: bool, retention_days: int},
         * } $result
         */
        $result = $this->api->request('GET', '/v1/inbound/routes');

        return $result;
    }

    /**
     * @param array{
     *     domain_id: string,
     *     recipient: string,
     *     name?: string,
     *     store: bool,
     *     webhook_endpoint_id?: string,
     * } $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array{route: array<string, mixed>} $result */
        $result = $this->api->request('POST', '/v1/inbound/routes', ['body' => $params]);

        return $result['route'];
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        /** @var array{route: array<string, mixed>} $result */
        $result = $this->api->request('GET', '/v1/inbound/routes/' . rawurlencode($id));

        return $result['route'];
    }

    /**
     * @param array{recipient: string, name?: string, store: bool, webhook_endpoint_id?: string} $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array{route: array<string, mixed>} $result */
        $result = $this->api->request('PATCH', '/v1/inbound/routes/' . rawurlencode($id), ['body' => $params]);

        return $result['route'];
    }

    public function delete(string $id): void
    {
        $this->api->request('DELETE', '/v1/inbound/routes/' . rawurlencode($id));
    }
}
