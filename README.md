# notideus-php

Official PHP SDK for the [Notideus](https://notideus.io) email API. MIT licensed.

Requires PHP 8.2+. Built on Guzzle — responses are plain associative
arrays, so payloads from the [API reference](https://notideus.io/docs) work
verbatim.

## Install

```bash
composer require notideus/notideus-php
```

## Quickstart

```php
<?php

use Notideus\NotideusClient;

$notideus = new NotideusClient(getenv('NOTIDEUS_API_KEY'));

$email = $notideus->emails()->send([
    'from' => 'Acme Inc <noreply@acme.com>',
    'to' => ['jane@example.com'],
    'subject' => 'Welcome',
    'html' => '<p>Hi {{name}}</p>',
    'variables' => ['name' => 'Jane'],
    'tags' => ['welcome'],
    'idempotency_key' => 'req-123',
    // Opt into platform open/click tracking for this send:
    'track_opens' => true,
    'track_clicks' => true,
]);
```

## Configuration

```php
new NotideusClient(?string $apiKey = null, array $options = []);
```

| Option        | Type                           | Default                      | Notes                              |
| ------------- | ------------------------------ | ---------------------------- | ---------------------------------- |
| `apiKey`      | `?string` (1st constructor arg) | `null`                      | omit for unauthenticated resources |
| `base_url`    | `string`                       | `https://api.notideus.io`    | override for self-hosting          |
| `timeout`     | `float` (seconds)              | `30.0`                       | request timeout                    |
| `max_retries` | `int`                          | `2`                          | see Retry behavior below           |
| `handler`     | `callable`\|`HandlerStack`\|`null` | Guzzle default stack     | inject a custom Guzzle handler     |

The key is optional: `plans()` and `unsubscribe()` work without one. Calling
an authenticated resource without a key surfaces the API's 401 as a
`NotideusException`.

## Resources

Params and responses use snake_case exactly like the API.

### Emails

```php
$notideus->emails()->send(['from', 'to', 'subject', 'html?', 'text?', 'template_id?', 'variables?', 'tags?', 'headers?', 'reply_to?', 'idempotency_key?', 'track_opens?', 'track_clicks?']);
$notideus->emails()->sendBatch([['from' => …, 'to' => …, …]]); // up to 100
```

### WhatsApp messages

```php
$notideus->whatsapp()->messages()->send([
    'template_id' => 'tpl-1',
    'to' => ['+33612345678'],
    'parameters' => ['body:1' => 'Jane'],
]);
$notideus->whatsapp()->messages()->sendBatch([…]);
```

`send` returns one entry per recipient: `[['id' => …, 'to' => …, 'status' => …]]`.

### Contacts

```php
$notideus->contacts()->create(['email', 'properties?', 'topic_ids?']);
$notideus->contacts()->get('jane@example.com');
$notideus->contacts()->update('jane@example.com', ['properties' => ['plan' => 'pro']]);
$notideus->contacts()->delete('jane@example.com');
$notideus->contacts()->unsubscribe('jane@example.com');
$notideus->contacts()->resubscribe('jane@example.com');
$notideus->contacts()->bulk([['email', 'properties?'], …]); // up to 1000

$page = $notideus->contacts()->list(['limit' => 100, 'status' => 'subscribed']);
if (isset($page['next_cursor'])) {
    $next = $notideus->contacts()->list(['cursor' => $page['next_cursor']]);
}
```

### Webhooks

```php
$created = $notideus->webhooks()->create([
    'name' => 'Production API',
    'url' => 'https://example.com/hook',
    'events' => ['email.delivered'],
]);
// $created['secret'] — whsec_…, shown once
$notideus->webhooks()->update($created['endpoint']['id'], [
    'name' => 'Production API',
    'url' => 'https://example.com/hook',
    'events' => ['email.delivered'],
    'status' => 'disabled',
]);
$log = $notideus->webhooks()->deliveries()->list(['endpoint_id' => $created['endpoint']['id']]);
$notideus->webhooks()->deliveries()->redeliver($log['data'][0]['id']);
$events = $notideus->webhooks()->events();
```

### Inbound email

```php
$routes = $notideus->inbound()->routes()->list(); // { data, meta }
$notideus->inbound()->routes()->create(['domain_id' => $domainId, 'recipient' => 'support', 'store' => true]);
$inbox = $notideus->inbound()->messages()->list(['q' => 'billing']);
$raw = $notideus->inbound()->messages()->raw($inbox['data'][0]['id']); // ['contents', 'filename', 'content_type']
```

### Hosted unsubscribe (token-based, unauthenticated)

```php
$info = $notideus->unsubscribe()->info($token);
$notideus->unsubscribe()->unsubscribe($token);            // whole contact
$notideus->unsubscribe()->unsubscribe($token, [$topicId]); // specific topics
$notideus->unsubscribe()->preferences($token, [$topicId]); // desired subscribed set
```

### Plans (unauthenticated)

```php
$catalog = $notideus->plans()->list('fr'); // locale optional
```

## Error handling

Request-level failures throw `Notideus\NotideusException`:

```php
use Notideus\NotideusException;

try {
    $notideus->emails()->send([…]);
} catch (NotideusException $e) {
    if ($e->getErrorCode() === 'from_domain_not_verified') { … }
    if ($e->getErrorCode() === 'rate_limited') {
        $e->getRetryAfter(); // seconds to wait
    }
    $e->getStatus(); // HTTP status
    $e->getReason(); // optional admin note on account_suspended
}
```

Batch sends never throw per item — inspect the results instead:

```php
$results = $notideus->emails()->sendBatch([…]);
foreach ($results as $item) {
    if (isset($item['error'])) {
        error_log($item['index'] . ' ' . $item['error']['code'] . ' ' . $item['error']['message']);
    } else {
        error_log($item['index'] . ' ' . $item['id']);
    }
}
```

A whole-batch failure (401, 402 `quota_exceeded`, 400 `batch_too_large`)
still throws.

## Retry behavior

The SDK retries network errors, 5xx and 429 (honoring `Retry-After`) with
exponential backoff — but only idempotent requests: reads/writes other than
POST, and sends that include an `idempotency_key` (matching the API's replay
semantics, so a retried send never double-sends). Set `max_retries: 0` to
disable. Binary downloads (raw MIME / attachments) are never retried.

## Self-hosting / local development

```php
$notideus = new NotideusClient(getenv('NOTIDEUS_API_KEY'), [
    'base_url' => 'http://localhost:8080',
]);
```

## Development

```bash
composer install
composer test    # phpunit — mocked Guzzle handler, no infra needed
composer lint    # phpcs
composer stan    # phpstan analyse
```

## Releasing

1. Update `version` in `composer.json` and merge to `main`.
2. Create a GitHub Release with tag `v<version>` — it must match
   `composer.json`, the `publish` workflow verifies this and fails otherwise.
3. The `publish` workflow runs the full gate (`composer install`, `lint`,
   `stan`, `test`). There is no publish step and **no secrets at all**:
   Packagist auto-updates the package from the release tag.
4. **One-time setup**: submit the repository on
   [packagist.org](https://packagist.org) (GitHub hook or manual "Update"
   enables auto-sync from tags). Unlike npm, Packagist has no
   registry-existence prerequisite — the very first release can go straight
   through the workflow.
