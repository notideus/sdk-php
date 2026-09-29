<?php

declare(strict_types=1);

namespace Notideus;

use Notideus\Http\ApiClient;
use Notideus\Resources\EmailsResource;

final class NotideusClient
{
    private readonly ApiClient $api;
    private ?EmailsResource $emails = null;

    /** @param array<string, mixed> $options */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->api = new ApiClient($apiKey, $options);
    }

    public function emails(): EmailsResource
    {
        return $this->emails ??= new EmailsResource($this->api);
    }
}
