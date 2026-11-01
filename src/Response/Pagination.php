<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Paging state of a citation graph.
 */
final readonly class Pagination implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $page = null,
        public ?int $page_size = null,
        public ?int $max_page = null,
        public ?int $reachable_distinct_max = null,
        public ?int $references_pages = null,
        public ?int $referenced_by_pages = null,
        public ?bool $has_more_references = null,
        public ?bool $has_more_referenced_by = null,
        public ?int $applies_at_depth = null,
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
            page: $r->int('page'),
            page_size: $r->int('page_size'),
            max_page: $r->int('max_page'),
            reachable_distinct_max: $r->int('reachable_distinct_max'),
            references_pages: $r->int('references_pages'),
            referenced_by_pages: $r->int('referenced_by_pages'),
            has_more_references: $r->bool('has_more_references'),
            has_more_referenced_by: $r->bool('has_more_referenced_by'),
            applies_at_depth: $r->int('applies_at_depth'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
