<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /search: statutes and decisions together.
 *
 * `sections` has the keys `laws` and `decisions`; each holds a list of hits.
 */
final readonly class SearchResult extends Response
{
    protected const KEYS = [
        'sections',
        'section_totals',
        'limit',
        'total_available',
        'total_available_basis',
        'pool_exhaustive',
        'truncated',
        'mode',
        'query_expansion',
        'frequently_cited_by_results',
        'norms_cited_by_decision_results',
        'top_confidence',
    ];

    /**
     * @var array{laws?: list<array<string, mixed>>, decisions?: list<array<string, mixed>>}|null
     */
    public ?array $sections;

    /**
     * @var array<string, array{total_available?: array{value?: int, is_floor?: bool}, total_available_basis?: string, candidate_pool?: int, pool_exhaustive?: bool, truncated?: bool}>|null
     */
    public ?array $section_totals;

    /**
     * @var int|null
     */
    public ?int $limit;

    /**
     * @var array{value?: int, is_floor?: bool}|null
     */
    public ?array $total_available;

    /**
     * @var string|null
     */
    public ?string $total_available_basis;

    /**
     * @var bool|null
     */
    public ?bool $pool_exhaustive;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var string|null
     */
    public ?string $mode;

    /**
     * @var array{status?: string, applied?: bool, reason?: string, note?: string}|null
     */
    public ?array $query_expansion;

    /**
     * @var list<mixed>|null
     */
    public ?array $frequently_cited_by_results;

    /**
     * @var list<mixed>|null
     */
    public ?array $norms_cited_by_decision_results;

    /**
     * @var string|null
     */
    public ?string $top_confidence;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        /** @var array{laws?: list<array<string, mixed>>, decisions?: list<array<string, mixed>>}|null $value */
        $value = Fields::array($raw, 'sections');
        $this->sections = $value;
        /** @var array<string, array{total_available?: array{value?: int, is_floor?: bool}, total_available_basis?: string, candidate_pool?: int, pool_exhaustive?: bool, truncated?: bool}>|null $value */
        $value = Fields::array($raw, 'section_totals');
        $this->section_totals = $value;
        $this->limit = Fields::int($raw, 'limit');
        /** @var array{value?: int, is_floor?: bool}|null $value */
        $value = Fields::array($raw, 'total_available');
        $this->total_available = $value;
        $this->total_available_basis = Fields::string($raw, 'total_available_basis');
        $this->pool_exhaustive = Fields::bool($raw, 'pool_exhaustive');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->mode = Fields::string($raw, 'mode');
        /** @var array{status?: string, applied?: bool, reason?: string, note?: string}|null $value */
        $value = Fields::array($raw, 'query_expansion');
        $this->query_expansion = $value;
        /** @var list<mixed>|null $value */
        $value = Fields::array($raw, 'frequently_cited_by_results');
        $this->frequently_cited_by_results = $value;
        /** @var list<mixed>|null $value */
        $value = Fields::array($raw, 'norms_cited_by_decision_results');
        $this->norms_cited_by_decision_results = $value;
        $this->top_confidence = Fields::string($raw, 'top_confidence');
    }
}
