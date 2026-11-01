<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * The HTTP client gave up waiting for an answer.
 */
class TimeoutException extends TransportException
{
    public static function wrap(Throwable $previous): self
    {
        return new self('Klaracase did not answer in time: ' . $previous->getMessage(), previous: $previous);
    }
}
