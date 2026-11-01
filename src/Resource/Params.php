<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use BackedEnum;
use DateTimeInterface;
use Klaracase\Exception\ValidationException;

/**
 * Turns the typed arguments of the resource methods into query and body values, and runs the checks that need
 * no server: a date format, an order of two bounds, a count. It does not check enum membership or server-owned
 * ranges; the server answers those with a 422.
 *
 * @internal
 */
final class Params
{
    public static function enum(BackedEnum|string|null $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    /**
     * @param list<BackedEnum|string>|null $values
     * @return list<string>|null
     */
    public static function enums(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_map(static fn (BackedEnum|string $v): string => (string) self::enum($v), $values);
    }

    /**
     * A date as `YYYY-MM-DD`. A `DateTimeInterface` is formatted in its own time zone.
     *
     * @throws ValidationException With code `client_validation` when a string is not a real calendar date.
     */
    public static function date(DateTimeInterface|string|null $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw ValidationException::client(sprintf('`%s` must be a date written YYYY-MM-DD, got "%s".', $name, $value));
        }

        return $value;
    }

    /**
     * @throws ValidationException When `$from` is later than `$to`.
     */
    public static function dateOrder(?string $from, ?string $to, string $fromName = 'dateFrom', string $toName = 'dateTo'): void
    {
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::client(sprintf('`%s` (%s) must not be later than `%s` (%s).', $fromName, $from, $toName, $to));
        }
    }

    /**
     * @throws ValidationException When `$from` is greater than `$to`.
     */
    public static function rnOrder(?int $from, ?int $to): void
    {
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::client(sprintf('`rnFrom` (%d) must not be greater than `rnTo` (%d).', $from, $to));
        }
    }
}
