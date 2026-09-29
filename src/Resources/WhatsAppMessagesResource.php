<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class WhatsAppMessagesResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{id: string, to: string, status: string}>
     */
    public function send(array $params): array
    {
        /** @var array{data?: mixed} $result */
        $result = $this->api->request('POST', '/v1/whatsapp/messages', ['body' => $params]);

        /** @var list<array{id: string, to: string, status: string}> */
        return (array) ($result['data'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array{index: int, id?: string, error?: array{code: string, message: string}}>
     */
    public function sendBatch(array $messages): array
    {
        /** @var array{data?: mixed} $result */
        $result = $this->api->request('POST', '/v1/whatsapp/messages/batch', ['body' => ['messages' => $messages]]);

        /** @var list<array{index: int, id?: string, error?: array{code: string, message: string}}> */
        return (array) ($result['data'] ?? []);
    }
}
