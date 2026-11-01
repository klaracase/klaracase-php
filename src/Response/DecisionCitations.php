<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\Direction;

/**
 * Answer of GET /decisions/{id}/citations. Costs 2 credits.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class DecisionCitations implements Model, ArrayAccess
{
    use Wire;
    use ReadsAsArray;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['ignored_parameters'];
    }

    /**
     * @param list<CitationEdge>|null $references
     * @param list<CitationEdge>|null $referenced_by
     * @param array<array-key, mixed>|null $references_withheld_reasons
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?SourceRef $source = null,
        public ?array $references = null,
        public ?array $referenced_by = null,
        public ?string $served_direction = null,
        public ?int $served_references_total = null,
        public ?int $served_referenced_by_total = null,
        public ?int $references_total = null,
        public ?int $references_resolved_total = null,
        public ?int $referenced_by_total = null,
        public ?int $references_unresolved_total = null,
        public ?array $references_withheld_reasons = null,
        public ?string $totals_scope = null,
        public ?int $per_direction_cap = null,
        public ?int $unresolved_cap = null,
        public ?int $node_cap = null,
        public ?bool $truncated = null,
        public ?int $nodes_omitted = null,
        public ?bool $payload_truncated = null,
        public ?Pagination $pagination = null,
        public ?bool $fortgeltung_checked = null,
        public ?string $fortgeltung_note = null,
        public ?string $api_version = null,
        public ?string $changelog_url = null,
        public ?Credits $credits = null,
        public ?array $ignored_parameters = null,
        public ResponseMeta $meta = new ResponseMeta(200, null, 0.0),
        public array $extra = [],
        public array $nulls = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public static function fromArray(array $data, ?ResponseMeta $meta = null): self
    {
        $r = new Reader($data);

        return new self(
            source: $r->object('source', SourceRef::class),
            references: $r->listOf('references', CitationEdge::class),
            referenced_by: $r->listOf('referenced_by', CitationEdge::class),
            served_direction: $r->string('served_direction'),
            served_references_total: $r->int('served_references_total'),
            served_referenced_by_total: $r->int('served_referenced_by_total'),
            references_total: $r->int('references_total'),
            references_resolved_total: $r->int('references_resolved_total'),
            referenced_by_total: $r->int('referenced_by_total'),
            references_unresolved_total: $r->int('references_unresolved_total'),
            references_withheld_reasons: $r->anyArray('references_withheld_reasons'),
            totals_scope: $r->string('totals_scope'),
            per_direction_cap: $r->int('per_direction_cap'),
            unresolved_cap: $r->int('unresolved_cap'),
            node_cap: $r->int('node_cap'),
            truncated: $r->bool('truncated'),
            nodes_omitted: $r->int('nodes_omitted'),
            payload_truncated: $r->bool('payload_truncated'),
            pagination: $r->object('pagination', Pagination::class),
            fortgeltung_checked: $r->bool('fortgeltung_checked'),
            fortgeltung_note: $r->string('fortgeltung_note'),
            api_version: $r->string('api_version'),
            changelog_url: $r->string('changelog_url'),
            credits: $r->object('credits', Credits::class),
            ignored_parameters: $r->map('ignored_parameters'),
            meta: $meta ?? new ResponseMeta(200, null, 0.0),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$served_direction` as an enum: null when absent, null when the value is new to this SDK version. The raw string stays on the property.
     */
    public function servedDirectionEnum(): ?Direction
    {
        return Direction::fromWire($this->served_direction);
    }
}
