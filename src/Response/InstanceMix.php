<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Decisions of a branch by instance level.
 */
final readonly class InstanceMix implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?Count $federal = null,
        public ?Count $appellate = null,
        public ?Count $first_instance_and_regional = null,
        public ?Count $other = null,
        public ?string $note = null,
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
            federal: $r->object('federal', Count::class),
            appellate: $r->object('appellate', Count::class),
            first_instance_and_regional: $r->object('first_instance_and_regional', Count::class),
            other: $r->object('other', Count::class),
            note: $r->string('note'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
