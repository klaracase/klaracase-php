<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Klaracase\Response\ResponseMeta;

/**
 * Klaracase answered with a success status, but the body lacks the key that identifies the object
 * (`id`, `reference` or `field`). Every other missing key reads as null.
 */
final class ResponseShapeException extends KlaracaseException
{
    public static function missing(string $class, string $key, ?ResponseMeta $meta = null): self
    {
        return new self(
            sprintf('Klaracase answered, but the body has no usable `%s` for %s.', $key, $class),
            status: $meta?->status,
            errorCode: 'invalid_response',
            apiRelease: $meta?->apiRelease,
        );
    }
}
