<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * One of the busiest courts of a branch.
 */
final readonly class TopCourt implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $court = null,
        public ?Count $count = null,
        public ?string $earliest = null,
        public ?string $latest = null,
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
            court: $r->string('court'),
            count: $r->object('count', Count::class),
            earliest: $r->string('earliest'),
            latest: $r->string('latest'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
