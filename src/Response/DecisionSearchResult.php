<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /decisions/search.
 *
 * `mode` values: keyword, semantic, hybrid. `top_confidence` is a plain string such as `high`.
 */
final readonly class DecisionSearchResult extends Response
{
    protected const KEYS = [
        'results',
        'payload_truncated',
        'total_available',
        'total_available_basis',
        'candidate_pool',
        'pool_exhaustive',
        'truncated',
        'page',
        'limit',
        'mode',
        'frequently_cited_by_results',
        'query_expansion',
        'top_confidence',
    ];

    /**
     * @var list<array{id?: string, aktenzeichen?: string, court?: string, date?: string, document_type?: string, title?: string, snippet?: string, snippets?: list<array<string, mixed>>, chunk_title?: string|null, rn_range?: string|null, fundstelle?: string|null, fortgeltung_checked?: bool, fortgeltung_note?: string, confidence?: string}>|null
     */
    public ?array $results;

    /**
     * @var bool|null
     */
    public ?bool $payload_truncated;

    /**
     * @var array{value?: int, is_floor?: bool}|null
     */
    public ?array $total_available;

    /**
     * @var string|null
     */
    public ?string $total_available_basis;

    /**
     * @var int|null
     */
    public ?int $candidate_pool;

    /**
     * @var bool|null
     */
    public ?bool $pool_exhaustive;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var int|null
     */
    public ?int $page;

    /**
     * @var int|null
     */
    public ?int $limit;

    /**
     * @var string|null
     */
    public ?string $mode;

    /**
     * @var list<mixed>|null
     */
    public ?array $frequently_cited_by_results;

    /**
     * What the server added to the query; `status` is for example `abstained`.
     *
     * @var array{status?: string, applied?: bool, reason?: string, note?: string}|null
     */
    public ?array $query_expansion;

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

        /** @var list<array{id?: string, aktenzeichen?: string, court?: string, date?: string, document_type?: string, title?: string, snippet?: string, snippets?: list<array<string, mixed>>, chunk_title?: string|null, rn_range?: string|null, fundstelle?: string|null, fortgeltung_checked?: bool, fortgeltung_note?: string, confidence?: string}>|null $value */
        $value = Fields::array($raw, 'results');
        $this->results = $value;
        $this->payload_truncated = Fields::bool($raw, 'payload_truncated');
        /** @var array{value?: int, is_floor?: bool}|null $value */
        $value = Fields::array($raw, 'total_available');
        $this->total_available = $value;
        $this->total_available_basis = Fields::string($raw, 'total_available_basis');
        $this->candidate_pool = Fields::int($raw, 'candidate_pool');
        $this->pool_exhaustive = Fields::bool($raw, 'pool_exhaustive');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->page = Fields::int($raw, 'page');
        $this->limit = Fields::int($raw, 'limit');
        $this->mode = Fields::string($raw, 'mode');
        /** @var list<mixed>|null $value */
        $value = Fields::array($raw, 'frequently_cited_by_results');
        $this->frequently_cited_by_results = $value;
        /** @var array{status?: string, applied?: bool, reason?: string, note?: string}|null $value */
        $value = Fields::array($raw, 'query_expansion');
        $this->query_expansion = $value;
        $this->top_confidence = Fields::string($raw, 'top_confidence');
    }
}
