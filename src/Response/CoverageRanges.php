<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The verified date ranges of the corpus.
 */
final readonly class CoverageRanges implements Model
{
    use Wire;

    /**
     * @param list<CoverageRange>|null $ranges
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $note = null,
        public ?string $scope = null,
        public ?string $verified_at = null,
        public ?array $ranges = null,
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
            note: $r->string('note'),
            scope: $r->string('scope'),
            verified_at: $r->string('verified_at'),
            ranges: $r->listOf('ranges', CoverageRange::class),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
