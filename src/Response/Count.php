<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * A count. When `is_floor` is true the real number is at least `value`.
 */
final readonly class Count implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $value = null,
        public ?bool $is_floor = null,
        public array $extra = [],
        public array $nulls = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public static function fromArray(array $data): self
    {
        $r = new Reader($data);

        return new self(
            value: $r->int('value'),
            is_floor: $r->bool('is_floor'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
