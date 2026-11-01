<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Totals of one section of a cross-type search.
 */
final readonly class SectionTotal implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?Count $total_available = null,
        public ?string $total_available_basis = null,
        public ?int $candidate_pool = null,
        public ?bool $pool_exhaustive = null,
        public ?bool $truncated = null,
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
            total_available: $r->object('total_available', Count::class),
            total_available_basis: $r->string('total_available_basis'),
            candidate_pool: $r->int('candidate_pool'),
            pool_exhaustive: $r->bool('pool_exhaustive'),
            truncated: $r->bool('truncated'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
