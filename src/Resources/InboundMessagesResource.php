<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class InboundMessagesResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param array{
     *   q?: string, route_id?: string, folder?: string, folder_id?: string,
     *   unread?: bool, attachments?: bool, sort?: string, cursor?: string, limit?: int
     * } $params
     * @return array{data: array<int, array<string, mixed>>, next_cursor?: string}
     */
    public function list(array $params = []): array
    {
        /** @var array{data: array<int, array<string, mixed>>, next_cursor?: string} $result */
        $result = $this->api->request('GET', '/v1/inbound/messages', ['query' => $params]);

        return $result;
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        /** @var array{message: array<string, mixed>} $result */
        $result = $this->api->request('GET', '/v1/inbound/messages/' . rawurlencode($id));

        return $result['message'];
    }

    /**
     * @param array{read?: bool, starred?: bool, folder?: string, folder_id?: string} $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array{message: array<string, mixed>} $result */
        $result = $this->api->request('PATCH', '/v1/inbound/messages/' . rawurlencode($id), ['body' => $params]);

        return $result['message'];
    }

    public function delete(string $id): void
    {
        $this->api->request('DELETE', '/v1/inbound/messages/' . rawurlencode($id));
    }

    /** @return array{contents: string, filename: string, content_type: string} */
    public function raw(string $id): array
    {
        return $this->api->requestBinary('/v1/inbound/messages/' . rawurlencode($id) . '/raw');
    }

    /** @return array{contents: string, filename: string, content_type: string} */
    public function attachment(string $messageId, int $index): array
    {
        return $this->api->requestBinary(
            '/v1/inbound/messages/' . rawurlencode($messageId) . '/attachments/' . $index
        );
    }
}
