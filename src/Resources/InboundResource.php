<?php

declare(strict_types=1);

namespace Notideus\Resources;

use Notideus\Http\ApiClient;

final class InboundResource
{
    private readonly InboundRoutesResource $routes;
    private readonly InboundMessagesResource $messages;

    public function __construct(ApiClient $api)
    {
        $this->routes = new InboundRoutesResource($api);
        $this->messages = new InboundMessagesResource($api);
    }

    public function routes(): InboundRoutesResource
    {
        return $this->routes;
    }

    public function messages(): InboundMessagesResource
    {
        return $this->messages;
    }
}
