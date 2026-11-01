<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /decisions/{id}/citations: the citation graph around one decision.
 *
 * `served_direction` values: outgoing, incoming, both.
 */
final readonly class DecisionCitations extends Response
{
    protected const KEYS = [
        'source',
        'references',
        'referenced_by',
        'served_direction',
        'served_references_total',
        'served_referenced_by_total',
        'references_total',
        'references_resolved_total',
        'referenced_by_total',
        'references_unresolved_total',
        'references_withheld_reasons',
        'totals_scope',
        'per_direction_cap',
        'unresolved_cap',
        'node_cap',
        'truncated',
        'nodes_omitted',
        'payload_truncated',
        'pagination',
        'fortgeltung_checked',
        'fortgeltung_note',
    ];

    /**
     * @var array{id?: string, aktenzeichen?: string, court?: string, date?: string}|null
     */
    public ?array $source;

    /**
     * Decisions this one cites.
     *
     * @var list<array{reference_canonical?: string, reference_role?: string|null, resolution_status?: string, in_corpus?: bool, corpus?: array<string, mixed>|null, from_artifact_id?: string, hop?: int}>|null
     */
    public ?array $references;

    /**
     * Decisions that cite this one.
     *
     * @var list<array{reference_canonical?: string, reference_role?: string|null, resolution_status?: string, in_corpus?: bool, corpus?: array<string, mixed>|null, from_artifact_id?: string, hop?: int}>|null
     */
    public ?array $referenced_by;

    /**
     * @var string|null
     */
    public ?string $served_direction;

    /**
     * @var int|null
     */
    public ?int $served_references_total;

    /**
     * @var int|null
     */
    public ?int $served_referenced_by_total;

    /**
     * @var int|null
     */
    public ?int $references_total;

    /**
     * @var int|null
     */
    public ?int $references_resolved_total;

    /**
     * @var int|null
     */
    public ?int $referenced_by_total;

    /**
     * @var int|null
     */
    public ?int $references_unresolved_total;

    /**
     * @var list<mixed>|null
     */
    public ?array $references_withheld_reasons;

    /**
     * @var string|null
     */
    public ?string $totals_scope;

    /**
     * @var int|null
     */
    public ?int $per_direction_cap;

    /**
     * @var int|null
     */
    public ?int $unresolved_cap;

    /**
     * @var int|null
     */
    public ?int $node_cap;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var int|null
     */
    public ?int $nodes_omitted;

    /**
     * @var bool|null
     */
    public ?bool $payload_truncated;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $pagination;

    /**
     * @var bool|null
     */
    public ?bool $fortgeltung_checked;

    /**
     * @var string|null
     */
    public ?string $fortgeltung_note;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        /** @var array{id?: string, aktenzeichen?: string, court?: string, date?: string}|null $value */
        $value = Fields::array($raw, 'source');
        $this->source = $value;
        /** @var list<array{reference_canonical?: string, reference_role?: string|null, resolution_status?: string, in_corpus?: bool, corpus?: array<string, mixed>|null, from_artifact_id?: string, hop?: int}>|null $value */
        $value = Fields::array($raw, 'references');
        $this->references = $value;
        /** @var list<array{reference_canonical?: string, reference_role?: string|null, resolution_status?: string, in_corpus?: bool, corpus?: array<string, mixed>|null, from_artifact_id?: string, hop?: int}>|null $value */
        $value = Fields::array($raw, 'referenced_by');
        $this->referenced_by = $value;
        $this->served_direction = Fields::string($raw, 'served_direction');
        $this->served_references_total = Fields::int($raw, 'served_references_total');
        $this->served_referenced_by_total = Fields::int($raw, 'served_referenced_by_total');
        $this->references_total = Fields::int($raw, 'references_total');
        $this->references_resolved_total = Fields::int($raw, 'references_resolved_total');
        $this->referenced_by_total = Fields::int($raw, 'referenced_by_total');
        $this->references_unresolved_total = Fields::int($raw, 'references_unresolved_total');
        /** @var list<mixed>|null $value */
        $value = Fields::array($raw, 'references_withheld_reasons');
        $this->references_withheld_reasons = $value;
        $this->totals_scope = Fields::string($raw, 'totals_scope');
        $this->per_direction_cap = Fields::int($raw, 'per_direction_cap');
        $this->unresolved_cap = Fields::int($raw, 'unresolved_cap');
        $this->node_cap = Fields::int($raw, 'node_cap');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->nodes_omitted = Fields::int($raw, 'nodes_omitted');
        $this->payload_truncated = Fields::bool($raw, 'payload_truncated');
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'pagination');
        $this->pagination = $value;
        $this->fortgeltung_checked = Fields::bool($raw, 'fortgeltung_checked');
        $this->fortgeltung_note = Fields::string($raw, 'fortgeltung_note');
    }
}
