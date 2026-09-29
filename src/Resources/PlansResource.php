<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class PlansResource
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(?string $locale = null): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->api->request('GET', '/v1/plans', [
            'query' => ['locale' => $locale],
            'authenticated' => false,
        ]);

        return $result;
    }
}
