<?php

declare(strict_types=1);

namespace Notideus\Resources;

final class WhatsAppResource
{
    private readonly WhatsAppMessagesResource $messages;

    public function __construct(\Notideus\Http\ApiClient $api)
    {
        $this->messages = new WhatsAppMessagesResource($api);
    }

    public function messages(): WhatsAppMessagesResource
    {
        return $this->messages;
    }
}
