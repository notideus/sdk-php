<?php

declare(strict_types=1);

namespace Notideus;

use Notideus\Http\ApiClient;
use Notideus\Resources\ContactsResource;
use Notideus\Resources\EmailsResource;
use Notideus\Resources\InboundResource;
use Notideus\Resources\PlansResource;
use Notideus\Resources\UnsubscribeResource;
use Notideus\Resources\WebhooksResource;
use Notideus\Resources\WhatsAppResource;

final class NotideusClient
{
    private readonly ApiClient $api;
    private ?EmailsResource $emails = null;
    private ?ContactsResource $contacts = null;
    private ?WhatsAppResource $whatsapp = null;
    private ?UnsubscribeResource $unsubscribe = null;
    private ?PlansResource $plans = null;
    private ?WebhooksResource $webhooks = null;
    private ?InboundResource $inbound = null;

    /** @param array<string, mixed> $options */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->api = new ApiClient($apiKey, $options);
    }

    public function emails(): EmailsResource
    {
        return $this->emails ??= new EmailsResource($this->api);
    }

    public function contacts(): ContactsResource
    {
        return $this->contacts ??= new ContactsResource($this->api);
    }

    public function whatsapp(): WhatsAppResource
    {
        return $this->whatsapp ??= new WhatsAppResource($this->api);
    }

    public function unsubscribe(): UnsubscribeResource
    {
        return $this->unsubscribe ??= new UnsubscribeResource($this->api);
    }

    public function plans(): PlansResource
    {
        return $this->plans ??= new PlansResource($this->api);
    }

    public function webhooks(): WebhooksResource
    {
        return $this->webhooks ??= new WebhooksResource($this->api);
    }

    public function inbound(): InboundResource
    {
        return $this->inbound ??= new InboundResource($this->api);
    }
}
