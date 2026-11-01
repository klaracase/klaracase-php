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
}
