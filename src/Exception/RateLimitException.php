<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * HTTP 429. `rate_limited` is retried by the client; a wait above 30 seconds and `volume_cap` are raised at once.
 * For `volume_cap` read `$extra['unit']`, `$extra['cap']` and `$extra['resets_at']`.
 */
class RateLimitException extends KlaracaseException
{
    /** Seconds to wait, from the body member, else from the Retry-After header. */
    public readonly ?int $retryAfterSeconds;

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $type = null,
        ?string $title = null,
        ?string $detail = null,
        ?string $errorCode = null,
        ?string $apiRelease = null,
        array $extra = [],
        ?Throwable $previous = null,
        ?int $retryAfterHeader = null,
    ) {
        parent::__construct($message, $status, $type, $title, $detail, $errorCode, $apiRelease, $extra, $previous);

        $seconds = $extra['retry_after_seconds'] ?? null;
        $this->retryAfterSeconds = is_int($seconds) ? $seconds : (is_float($seconds) ? (int) ceil($seconds) : $retryAfterHeader);
    }
}
