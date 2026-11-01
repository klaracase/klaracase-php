<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The `credits` member of a served body. `metered` is false for an unmetered account; `cost` and `credits_remaining` are then null.
 */
final readonly class Credits implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?bool $metered = null,
        public ?int $cost = null,
        public ?int $credits_remaining = null,
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
            metered: $r->bool('metered'),
            cost: $r->int('cost'),
            credits_remaining: $r->int('credits_remaining'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
