<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use stdClass;

/**
 * Brings a decoded JSON value into one canonical shape: object keys sorted, list order kept.
 * JSON objects are unordered, so two bodies are equal when their canonical forms are.
 */
final class Canonical
{
    /**
     * Sorts the keys of every object in a value that was decoded with `assoc = true`.
     */
    public static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $sorted = array_map(self::sortKeys(...), $value);
        if (! array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }

    /**
     * The canonical JSON text of a body. Decoded as objects, so `{}` and `[]` stay different.
     */
    public static function json(string $json): string
    {
        return json_encode(self::sortObjects(json_decode($json, false, 512, JSON_THROW_ON_ERROR)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sortObjects(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);
            ksort($vars);

            return (object) array_map(self::sortObjects(...), $vars);
        }
        if (is_array($value)) {
            return array_map(self::sortObjects(...), $value);
        }

        return $value;
    }
}
