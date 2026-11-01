<?php

declare(strict_types=1);

namespace Klaracase\Response;

use JsonSerializable;
use stdClass;

/**
 * A response object or a nested value object. Every key the SDK does not model survives in `$extra`.
 */
interface Model extends JsonSerializable
{
    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public static function fromArray(array $data): static;

    /**
     * The wire shape: modelled keys that hold a value, explicit nulls, then the unmodelled keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @return array<string, mixed>|stdClass
     */
    public function jsonSerialize(): array|stdClass;
}
