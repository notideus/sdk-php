<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class InboundFoldersResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, counts: array<string, int>, route_unread: array<string, int>}
     */
    public function list(): array
    {
        /** @var array{data: list<array<string, mixed>>, counts: array<string, int>, route_unread: array<string, int>} $result */
        $result = $this->api->request('GET', '/v1/inbound/folders');

        return $result;
    }

    /** @return array<string, mixed> */
    public function create(string $name): array
    {
        /** @var array{folder: array<string, mixed>} $result */
        $result = $this->api->request('POST', '/v1/inbound/folders', ['body' => ['name' => $name]]);

        return $result['folder'];
    }

    /** @return array<string, mixed> */
    public function update(string $id, string $name): array
    {
        /** @var array{folder: array<string, mixed>} $result */
        $result = $this->api->request(
            'PATCH',
            '/v1/inbound/folders/' . rawurlencode($id),
            ['body' => ['name' => $name]]
        );

        return $result['folder'];
    }

    public function delete(string $id): void
    {
        $this->api->request('DELETE', '/v1/inbound/folders/' . rawurlencode($id));
    }
}
