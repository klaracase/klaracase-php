<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use InvalidArgumentException;

/**
 * Narrowing helpers for decoded JSON in tests.
 */
final class Json
{
    /**
     * @return array<array-key, mixed>
     */
    public static function decodeFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException('Cannot read ' . $path);
        }

        return self::array(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected an array, got ' . get_debug_type($value));
        }

        return $value;
    }

    public static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Expected a string, got ' . get_debug_type($value));
        }

        return $value;
    }

    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Expected an int, got ' . get_debug_type($value));
    }

    public static function float(mixed $value): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Expected a number, got ' . get_debug_type($value));
        }

        return (float) $value;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }
}
