<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * No answer arrived: connection refused, DNS, TLS, dropped connection or timeout.
 * `$previous` is the exception of the HTTP client.
 */
class TransportException extends KlaracaseException
{
    public static function wrap(Throwable $previous): self
    {
        return new self('Could not reach Klaracase: ' . $previous->getMessage(), previous: $previous);
    }

    /**
     * A failure to reach the API can pass. Note for POST: the request may have reached the server before the
     * connection dropped, so check `citationChecks->get()` before you repeat a charged call by hand.
     */
    public function isRetryable(): bool
    {
        return true;
    }
}
