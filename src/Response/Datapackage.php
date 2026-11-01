<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\TextMode;

/**
 * Answer of POST /datapackages: up to 10 decisions with their citation edges. Costs 5 credits.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Datapackage implements Model, ArrayAccess
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
     * @param list<PackagedDecision>|null $decisions
     * @param list<CitationEdge>|null $citation_edges
     * @param list<EdgeAccounting>|null $edge_accounting
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?array $decisions = null,
        public ?array $citation_edges = null,
        public ?array $edge_accounting = null,
        public ?int $per_source_resolved_target_cap = null,
        public ?int $per_source_unresolved_edge_row_cap = null,
        public ?int $requested = null,
        public ?int $included = null,
        public ?int $deduped = null,
        public ?string $text_mode = null,
        public ?int $max_chars = null,
        public ?bool $truncated = null,
        public ?bool $payload_truncated = null,
        public ?string $assembled_at = null,
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
            decisions: $r->listOf('decisions', PackagedDecision::class),
            citation_edges: $r->listOf('citation_edges', CitationEdge::class),
            edge_accounting: $r->listOf('edge_accounting', EdgeAccounting::class),
            per_source_resolved_target_cap: $r->int('per_source_resolved_target_cap'),
            per_source_unresolved_edge_row_cap: $r->int('per_source_unresolved_edge_row_cap'),
            requested: $r->int('requested'),
            included: $r->int('included'),
            deduped: $r->int('deduped'),
            text_mode: $r->string('text_mode'),
            max_chars: $r->int('max_chars'),
            truncated: $r->bool('truncated'),
            payload_truncated: $r->bool('payload_truncated'),
            assembled_at: $r->string('assembled_at'),
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
     * `$text_mode` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function textModeEnum(): ?TextMode
    {
        return TextMode::fromWire($this->text_mode);
    }
}
