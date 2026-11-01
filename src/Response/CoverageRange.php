<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * One verified date range of the corpus.
 */
final readonly class CoverageRange implements Model
{
    use Wire;

    /**
     * @param list<string>|null $sources
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $bucket = null,
        public ?string $court = null,
        public ?string $label = null,
        public ?string $from = null,
        public ?string $until = null,
        public ?array $sources = null,
        public ?bool $exhaustive = null,
        public ?bool $ocr = null,
        public ?string $note = null,
        public ?string $holdings_earliest = null,
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
            bucket: $r->string('bucket'),
            court: $r->string('court'),
            label: $r->string('label'),
            from: $r->string('from'),
            until: $r->string('until'),
            sources: $r->strings('sources'),
            exhaustive: $r->bool('exhaustive'),
            ocr: $r->bool('ocr'),
            note: $r->string('note'),
            holdings_earliest: $r->string('holdings_earliest'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
