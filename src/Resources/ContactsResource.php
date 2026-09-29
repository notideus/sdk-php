<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class ContactsResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param array{cursor?: string, limit?: int, email?: string, status?: string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('GET', '/v1/contacts', ['query' => $params]);

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('POST', '/v1/contacts', ['body' => $params]);

        return $result;
    }

    /** @return array<string, mixed> */
    public function get(string $email): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('GET', '/v1/contacts/' . rawurlencode($email));

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $email, array $params): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('PATCH', '/v1/contacts/' . rawurlencode($email), ['body' => $params]);

        return $result;
    }

    public function delete(string $email): void
    {
        $this->api->request('DELETE', '/v1/contacts/' . rawurlencode($email));
    }

    /**
     * @param list<array<string, mixed>> $contacts
     * @return list<array{index: int, id?: string, error?: array{code: string, message: string}}>
     */
    public function bulk(array $contacts): array
    {
        /** @var array{data?: mixed} $result */
        $result = $this->api->request('POST', '/v1/contacts/bulk', ['body' => ['contacts' => $contacts]]);

        /** @var list<array{index: int, id?: string, error?: array{code: string, message: string}}> */
        return (array) ($result['data'] ?? []);
    }

    /** @return array<string, mixed> */
    public function unsubscribe(string $email): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('POST', '/v1/contacts/' . rawurlencode($email) . '/unsubscribe');

        return $result;
    }

    /** @return array<string, mixed> */
    public function resubscribe(string $email): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('POST', '/v1/contacts/' . rawurlencode($email) . '/resubscribe');

        return $result;
    }
}
