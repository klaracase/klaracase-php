<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Reads typed values out of a decoded body. A key that is missing, or holds a value of
 * another JSON type, reads as null. The raw value stays available through `toArray()`.
 *
 * @internal
 */
final class Fields
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public static function string(array $raw, string $key): ?string
    {
        $value = $raw[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public static function int(array $raw, string $key): ?int
    {
        $value = $raw[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public static function bool(array $raw, string $key): ?bool
    {
        $value = $raw[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * An object or a list. The caller narrows the shape with an `@var` tag.
     *
     * @param array<array-key, mixed> $raw
     * @return array<array-key, mixed>|null
     */
    public static function array(array $raw, string $key): ?array
    {
        $value = $raw[$key] ?? null;

        return is_array($value) ? $value : null;
    }
}
