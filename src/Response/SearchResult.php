<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\Confidence;
use Klaracase\Enum\Mode;

/**
 * Answer of GET /search: statutes and decisions together. Costs 1 credit.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class SearchResult implements Model, ArrayAccess
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
     * @param list<mixed>|null $frequently_cited_by_results
     * @param list<mixed>|null $norms_cited_by_decision_results
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?SearchSections $sections = null,
        public ?SectionTotals $section_totals = null,
        public ?int $limit = null,
        public ?Count $total_available = null,
        public ?string $total_available_basis = null,
        public ?bool $pool_exhaustive = null,
        public ?bool $truncated = null,
        public ?string $mode = null,
        public ?QueryExpansion $query_expansion = null,
        public ?array $frequently_cited_by_results = null,
        public ?array $norms_cited_by_decision_results = null,
        public ?string $top_confidence = null,
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
            sections: $r->object('sections', SearchSections::class),
            section_totals: $r->object('section_totals', SectionTotals::class),
            limit: $r->int('limit'),
            total_available: $r->object('total_available', Count::class),
            total_available_basis: $r->string('total_available_basis'),
            pool_exhaustive: $r->bool('pool_exhaustive'),
            truncated: $r->bool('truncated'),
            mode: $r->string('mode'),
            query_expansion: $r->object('query_expansion', QueryExpansion::class),
            frequently_cited_by_results: $r->list('frequently_cited_by_results'),
            norms_cited_by_decision_results: $r->list('norms_cited_by_decision_results'),
            top_confidence: $r->string('top_confidence'),
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
     * `$mode` as an enum: null when absent, null when the value is new to this SDK version. The raw string stays on the property.
     */
    public function modeEnum(): ?Mode
    {
        return Mode::fromWire($this->mode);
    }

    /**
     * `$top_confidence` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function topConfidenceEnum(): ?Confidence
    {
        return Confidence::fromWire($this->top_confidence);
    }
}
