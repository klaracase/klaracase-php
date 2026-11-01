<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The norms before and after this one.
 */
final readonly class Neighbours implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $previous = null,
        public ?string $next = null,
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
            previous: $r->string('previous'),
            next: $r->string('next'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
