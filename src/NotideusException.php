<?php

declare(strict_types=1);

namespace Notideus;

use RuntimeException;

final class NotideusException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
        private readonly ?int $retryAfter = null,
        private readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Admin-provided note, present on account_suspended errors.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
