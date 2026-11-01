<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Totals of both sections of a cross-type search.
 */
final readonly class SectionTotals implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?SectionTotal $laws = null,
        public ?SectionTotal $decisions = null,
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
            laws: $r->object('laws', SectionTotal::class),
            decisions: $r->object('decisions', SectionTotal::class),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
