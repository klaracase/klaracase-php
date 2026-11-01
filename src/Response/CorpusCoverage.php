<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;

/**
 * Answer of GET /corpus/coverage. Free.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class CorpusCoverage implements Model, ArrayAccess
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
     * @param list<CoverageBranch>|null $branches
     * @param list<CoverageCourt>|null $courts
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?Count $total_decisions = null,
        public ?array $branches = null,
        public ?array $courts = null,
        public ?int $courts_returned = null,
        public ?int $courts_total = null,
        public ?string $corpus_floors = null,
        public ?CoverageRanges $coverage_ranges = null,
        public ?string $as_of = null,
        public ?string $filter_coverage_caveats = null,
        public ?LawCoverage $law_coverage = null,
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
            total_decisions: $r->object('total_decisions', Count::class),
            branches: $r->listOf('branches', CoverageBranch::class),
            courts: $r->listOf('courts', CoverageCourt::class),
            courts_returned: $r->int('courts_returned'),
            courts_total: $r->int('courts_total'),
            corpus_floors: $r->string('corpus_floors'),
            coverage_ranges: $r->object('coverage_ranges', CoverageRanges::class),
            as_of: $r->string('as_of'),
            filter_coverage_caveats: $r->string('filter_coverage_caveats'),
            law_coverage: $r->object('law_coverage', LawCoverage::class),
            api_version: $r->string('api_version'),
            changelog_url: $r->string('changelog_url'),
            credits: $r->object('credits', Credits::class),
            ignored_parameters: $r->map('ignored_parameters'),
            meta: $meta ?? new ResponseMeta(200, null, 0.0),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
