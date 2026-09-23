<?php

namespace NoriaLabs\Send\Exceptions;

use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

// A transport exception, so a Laravel failover mailer moves on to its next mailer.
class SendException extends RuntimeException implements TransportExceptionInterface
{
    private string $debug = '';

    /**
     * @param  array<array-key, mixed>|null  $details
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly ?array $details = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function network(string $message, ?Throwable $previous = null): self
    {
        return new self('network_error', 0, $message, null, null, $previous);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    public static function fromResponse(int $status, array $body): self
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];

        return new self(
            is_string($error['code'] ?? null) ? $error['code'] : 'internal_error',
            $status,
            is_string($error['message'] ?? null) ? $error['message'] : "Request failed with status {$status}",
            is_array($error['details'] ?? null) ? $error['details'] : null,
            is_string($error['request_id'] ?? null) ? $error['request_id'] : null,
        );
    }

    public function getDebug(): string
    {
        return $this->debug;
    }

    public function appendDebug(string $debug): void
    {
        $this->debug .= $debug;
    }

    public function isSuppressed(): bool
    {
        return $this->errorCode === 'suppressed_recipient';
    }

    public function isOverQuota(): bool
    {
        return $this->errorCode === 'quota_exceeded' || $this->errorCode === 'rate_limited';
    }

    public function isRetryable(): bool
    {
        if (in_array($this->errorCode, [
            'quota_exceeded',
            'suppressed_recipient',
            'domain_not_verified',
            'sender_not_approved',
            'message_too_long',
        ], true)) {
            return false;
        }

        return $this->status === 0 || in_array($this->status, [408, 429, 500, 502, 503, 504], true);
    }
}
