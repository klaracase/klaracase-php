<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\Confidence;
use Klaracase\Enum\Mode;

/**
 * Answer of GET /decisions/search. Costs 1 credit.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class DecisionSearchResult implements Model, ArrayAccess
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
     * @param list<DecisionHit>|null $results
     * @param list<mixed>|null $frequently_cited_by_results
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?array $results = null,
        public ?bool $payload_truncated = null,
        public ?Count $total_available = null,
        public ?string $total_available_basis = null,
        public ?int $candidate_pool = null,
        public ?bool $pool_exhaustive = null,
        public ?bool $truncated = null,
        public ?int $page = null,
        public ?int $limit = null,
        public ?string $mode = null,
        public ?array $frequently_cited_by_results = null,
        public ?QueryExpansion $query_expansion = null,
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
            results: $r->listOf('results', DecisionHit::class),
            payload_truncated: $r->bool('payload_truncated'),
            total_available: $r->object('total_available', Count::class),
            total_available_basis: $r->string('total_available_basis'),
            candidate_pool: $r->int('candidate_pool'),
            pool_exhaustive: $r->bool('pool_exhaustive'),
            truncated: $r->bool('truncated'),
            page: $r->int('page'),
            limit: $r->int('limit'),
            mode: $r->string('mode'),
            frequently_cited_by_results: $r->list('frequently_cited_by_results'),
            query_expansion: $r->object('query_expansion', QueryExpansion::class),
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

    private const LAST_PAGE = 10;

    /**
     * True when another page can follow. Uses `page`, `limit` and `total_available`. A total that is only a floor
     * (`is_floor`) does not promise more when this page came back empty. The API serves pages 1 to 10.
     */
    public function hasMore(): bool
    {
        $total = $this->total_available;
        if ($this->page === null || $this->limit === null || $total === null || $total->value === null) {
            return false;
        }
        if ($this->page >= self::LAST_PAGE) {
            return false;
        }
        if ($total->is_floor === true && ($this->results ?? []) === []) {
            return false;
        }

        return $this->page * $this->limit < $total->value;
    }

    /**
     * The number to pass as `page` for the next call, or null when `hasMore()` is false.
     */
    public function nextPage(): ?int
    {
        return $this->hasMore() && $this->page !== null ? $this->page + 1 : null;
    }
}
