<?php

declare(strict_types=1);

namespace Klaracase\Http;

/**
 * Builds request paths. Each path parameter is encoded as ONE segment: every byte outside
 * A-Z a-z 0-9 - . _ ~ becomes %XX with upper-case hex. A space is %20, a slash is %2F, § is %C2%A7.
 *
 * @internal
 */
final class Path
{
    public static function segment(string $value): string
    {
        return rawurlencode($value);
    }
}
