<?php

declare(strict_types=1);

namespace Klaracase\Response;

use stdClass;

/**
 * Turns a value object back into the body it came from. No per-class code.
 *
 * @internal
 */
trait Wire
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->wire(false);
    }

    /**
     * @return array<string, mixed>|stdClass
     */
    public function jsonSerialize(): array|stdClass
    {
        return $this->wire(true) ?: new stdClass();
    }

    /**
     * @return array<string, mixed>
     */
    private function wire(bool $forJson): array
    {
        /** @var array<string, mixed> $out */
        $out = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value === null || $key === 'extra' || $key === 'nulls' || $key === 'meta') {
                continue;
            }
            $encoded = self::encode($value, $forJson);
            $out[(string) $key] = $forJson && is_array($encoded) && in_array($key, self::mapKeys(), true) ? (object) $encoded : $encoded;
        }
        foreach ($this->nulls as $key) {
            $out[(string) $key] = null;
        }

        $extra = $this->extra;

        return $out + $extra;
    }

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return [];
    }

    private static function encode(mixed $value, bool $forJson): mixed
    {
        if ($value instanceof Model) {
            return $forJson ? $value : $value->toArray();
        }
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::encode($item, $forJson), $value);
        }

        return $value;
    }
}
