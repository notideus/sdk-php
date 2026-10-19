<?php

declare(strict_types=1);

namespace Notideus\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Notideus\NotideusException;

final class ApiClient
{
    private const DEFAULT_BASE_URL = 'https://api.notideus.io';
    private const VERSION = '1.0.0';

    private readonly Client $http;
    private readonly int $maxRetries;

    /**
     * @param array{
     *     base_url?: string,
     *     timeout?: float,
     *     max_retries?: int,
     *     handler?: callable|\GuzzleHttp\HandlerStack|null
     * } $options
     */
    public function __construct(
        private readonly ?string $apiKey = null,
        array $options = [],
    ) {
        $handler = $options['handler'] ?? null;
        $stack = $handler instanceof HandlerStack ? $handler : HandlerStack::create($handler);

        $this->maxRetries = (int) ($options['max_retries'] ?? 2);
        $this->http = new Client([
            'base_uri' => rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/') . '/',
            'timeout' => (float) ($options['timeout'] ?? 30.0),
            'handler' => $stack,
            'headers' => ['User-Agent' => 'notideus-php/' . self::VERSION],
            'http_errors' => true,
        ]);
    }

    /**
     * @param array{query?: array<string, mixed>, body?: array<string, mixed>, authenticated?: bool} $options
     */
    public function request(string $method, string $path, array $options = []): mixed
    {
        $opts = [];
        if (($options['authenticated'] ?? true) && $this->apiKey !== null) {
            $opts['headers']['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        if (isset($options['query'])) {
            $opts['query'] = array_filter($options['query'], static fn ($value) => $value !== null);
        }
        if (isset($options['body'])) {
            $opts['json'] = $options['body'];
        }

        $retries = 0;
        $lastFailure = null;

        while (true) {
            try {
                $response = $this->http->request($method, ltrim($path, '/'), $opts);

                if ($response->getStatusCode() === 204) {
                    return null;
                }
                $decoded = json_decode((string) $response->getBody(), true);

                return is_array($decoded) ? $decoded : null;
            } catch (ConnectException | RequestException $e) {
                $lastFailure = $e;
                if (!$this->shouldRetry($method, $options['body'] ?? null, $retries, $e)) {
                    throw self::toException($e);
                }
                $retries++;
                $delayMs = self::retryDelayMs($retries, $e);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            } catch (\Throwable $e) {
                // A retry attempt died before any HTTP outcome (e.g. the transport
                // blew up mid-retry); report the last definite failure instead.
                if ($lastFailure !== null) {
                    throw self::toException($lastFailure);
                }

                throw $e;
            }
        }
    }

    /**
     * Downloads a binary endpoint (raw MIME, attachments). Returns the body
     * contents plus filename (from Content-Disposition) and content type.
     * No retries — binary payloads are not idempotent-safe to re-fetch
     * blindly, and callers can re-invoke cheaply.
     *
     * @return array{contents: string, filename: string, content_type: string}
     */
    public function requestBinary(string $path): array
    {
        $opts = [];
        if ($this->apiKey !== null) {
            $opts['headers']['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            $response = $this->http->request('GET', ltrim($path, '/'), $opts);
        } catch (ConnectException | RequestException $e) {
            throw self::toException($e);
        }

        $disposition = $response->getHeaderLine('Content-Disposition');
        $filename = 'download';
        if (preg_match('/filename="?([^";]+)"?/', $disposition, $m) === 1) {
            $filename = $m[1];
        }

        return [
            'contents' => (string) $response->getBody(),
            'filename' => $filename,
            'content_type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function shouldRetry(
        string $method,
        ?array $body,
        int $retries,
        ConnectException | RequestException $e,
    ): bool {
        if ($retries >= $this->maxRetries) {
            return false;
        }
        if ($method === 'POST' && ($body['idempotency_key'] ?? null) === null) {
            return false;
        }
        if ($e instanceof ConnectException) {
            return true;
        }
        $response = $e->getResponse();

        return $response === null || $response->getStatusCode() === 429 || $response->getStatusCode() >= 500;
    }

    private static function retryDelayMs(int $retries, ConnectException | RequestException $e): int
    {
        $response = $e instanceof RequestException ? $e->getResponse() : null;
        if ($response !== null && $response->hasHeader('Retry-After')) {
            return (int) $response->getHeaderLine('Retry-After') * 1000;
        }

        return (int) min(1000 * 2 ** $retries, 8000) + random_int(0, 249);
    }

    private static function toException(ConnectException | RequestException $e): NotideusException
    {
        if ($e instanceof ConnectException) {
            return new NotideusException('network_error', $e->getMessage(), 0);
        }
        $response = $e->getResponse();
        if ($response === null) {
            return new NotideusException('network_error', $e->getMessage(), 0);
        }
        /** @var array{error?: array{code?: string, message?: string, reason?: string}}|null $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $code = is_array($payload) && isset($payload['error']['code'])
            ? (string) $payload['error']['code']
            : 'unknown_error';
        $message = is_array($payload) && isset($payload['error']['message'])
            ? (string) $payload['error']['message']
            : 'HTTP ' . $response->getStatusCode();
        $reason = is_array($payload) && isset($payload['error']['reason'])
            ? (string) $payload['error']['reason']
            : null;
        $retryAfter = $response->hasHeader('Retry-After')
            ? (int) $response->getHeaderLine('Retry-After')
            : null;

        return new NotideusException($code, $message, $response->getStatusCode(), $retryAfter, $reason);
    }
}
