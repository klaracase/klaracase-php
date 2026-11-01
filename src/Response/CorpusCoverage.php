<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /corpus/coverage: what the corpus holds. Free.
 */
final readonly class CorpusCoverage extends Response
{
    protected const KEYS = [
        'total_decisions',
        'branches',
        'courts',
        'courts_returned',
        'courts_total',
        'corpus_floors',
        'coverage_ranges',
        'as_of',
        'filter_coverage_caveats',
        'law_coverage',
    ];

    /**
     * @var array{value?: int, is_floor?: bool}|null
     */
    public ?array $total_decisions;

    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $branches;

    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $courts;

    /**
     * @var int|null
     */
    public ?int $courts_returned;

    /**
     * @var int|null
     */
    public ?int $courts_total;

    /**
     * @var string|null
     */
    public ?string $corpus_floors;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $coverage_ranges;

    /**
     * @var string|null
     */
    public ?string $as_of;

    /**
     * @var string|null
     */
    public ?string $filter_coverage_caveats;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $law_coverage;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        /** @var array{value?: int, is_floor?: bool}|null $value */
        $value = Fields::array($raw, 'total_decisions');
        $this->total_decisions = $value;
        /** @var list<array<string, mixed>>|null $value */
        $value = Fields::array($raw, 'branches');
        $this->branches = $value;
        /** @var list<array<string, mixed>>|null $value */
        $value = Fields::array($raw, 'courts');
        $this->courts = $value;
        $this->courts_returned = Fields::int($raw, 'courts_returned');
        $this->courts_total = Fields::int($raw, 'courts_total');
        $this->corpus_floors = Fields::string($raw, 'corpus_floors');
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'coverage_ranges');
        $this->coverage_ranges = $value;
        $this->as_of = Fields::string($raw, 'as_of');
        $this->filter_coverage_caveats = Fields::string($raw, 'filter_coverage_caveats');
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'law_coverage');
        $this->law_coverage = $value;
    }
}
