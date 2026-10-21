<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class EmailsResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * @param bool $params['track_opens']  Opt into open tracking (default false)
     * @param bool $params['track_clicks'] Opt into click tracking (default false)
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('POST', '/v1/emails', ['body' => $params]);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $emails
     * @return list<array{index: int, id?: string, error?: array{code: string, message: string}}>
     */
    public function sendBatch(array $emails): array
    {
        /** @var array{data?: mixed} $result */
        $result = $this->api->request('POST', '/v1/emails/batch', ['body' => ['emails' => $emails]]);

        /** @var list<array{index: int, id?: string, error?: array{code: string, message: string}}> */
        return (array) ($result['data'] ?? []);
    }
}
