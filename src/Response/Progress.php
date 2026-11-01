<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Progress of a citation check run.
 */
final readonly class Progress implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $citations_total = null,
        public ?int $citations_checked = null,
        public ?int $unique_citations_total = null,
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
            citations_total: $r->int('citations_total'),
            citations_checked: $r->int('citations_checked'),
            unique_citations_total: $r->int('unique_citations_total'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
