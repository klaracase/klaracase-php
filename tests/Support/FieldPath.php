<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use ArrayAccess;
use InvalidArgumentException;
use Throwable;

/**
 * Reads a value out of an SDK result or error with the conformance path grammar:
 * names joined by `.`, an index `[0]`, and a quoted key `["§ 543 Abs. 1 BGB"]`.
 *
 * On an object, a name is tried as a property (as written, then camelCase), then in `extra`.
 */
final class FieldPath
{
    public static function get(mixed $root, string $path): mixed
    {
        $value = $root;
        foreach (self::tokens($path) as $token) {
            $value = self::step($value, $token, $path);
        }

        return $value;
    }

    /**
     * @return list<string|int>
     */
    private static function tokens(string $path): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($path);
        while ($offset < $length) {
            if (preg_match('/\G\.?([A-Za-z_][A-Za-z0-9_]*)/', $path, $m, 0, $offset) === 1) {
                $tokens[] = $m[1];
            } elseif (preg_match('/\G\[(\d+)\]/', $path, $m, 0, $offset) === 1) {
                $tokens[] = (int) $m[1];
            } elseif (preg_match('/\G\["((?:[^"\\\\]|\\\\.)*)"\]/', $path, $m, 0, $offset) === 1) {
                $tokens[] = stripslashes($m[1]);
            } else {
                throw new InvalidArgumentException(sprintf('Bad path "%s" at offset %d', $path, $offset));
            }
            $offset += strlen($m[0]);
        }

        return $tokens;
    }

    private static function step(mixed $value, string|int $token, string $path): mixed
    {
        if (is_array($value)) {
            return $value[$token] ?? null;
        }
        if (! is_object($value) || is_int($token)) {
            throw new InvalidArgumentException(sprintf('Cannot read "%s" of %s (path %s)', (string) $token, get_debug_type($value), $path));
        }
        if ($token === 'message' && $value instanceof Throwable) {
            return $value->getMessage();
        }
        $public = get_object_vars($value);
        foreach ([$token, self::camel($token)] as $name) {
            if (array_key_exists($name, $public)) {
                return $public[$name];
            }
        }
        $extra = $public['extra'] ?? null;
        if (is_array($extra) && array_key_exists($token, $extra)) {
            return $extra[$token];
        }
        if ($value instanceof ArrayAccess && $value->offsetExists($token)) {
            return $value->offsetGet($token);
        }
        if (is_array($extra)) {
            return null;
        }

        throw new InvalidArgumentException(sprintf('No field "%s" on %s (path %s)', $token, $value::class, $path));
    }

    public static function camel(string $snake): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));
    }
}
