<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Which version of the statute text was served.
 */
final readonly class NormVersion implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $last_amendment_date = null,
        public ?string $last_amendment_date_basis = null,
        public ?string $source_fetched_at = null,
        public ?string $source_last_seen_at = null,
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
            id: $r->string('id'),
            last_amendment_date: $r->string('last_amendment_date'),
            last_amendment_date_basis: $r->string('last_amendment_date_basis'),
            source_fetched_at: $r->string('source_fetched_at'),
            source_last_seen_at: $r->string('source_last_seen_at'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
