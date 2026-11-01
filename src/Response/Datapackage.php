<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of POST /datapackages: up to 10 decisions bundled with their citation edges.
 *
 * `text_mode` values: excerpt, full_text.
 */
final readonly class Datapackage extends Response
{
    protected const KEYS = [
        'decisions',
        'citation_edges',
        'edge_accounting',
        'per_source_resolved_target_cap',
        'per_source_unresolved_edge_row_cap',
        'requested',
        'included',
        'deduped',
        'text_mode',
        'max_chars',
        'truncated',
        'payload_truncated',
        'assembled_at',
    ];

    /**
     * @var list<array{id?: string, aktenzeichen?: string, court?: string, date?: string, document_type?: string, ecli?: string|null, jurisdiction?: string, excerpt?: string, excerpt_span?: string, text_length?: int, truncated?: bool, fortgeltung_checked?: bool, fortgeltung_note?: string}>|null
     */
    public ?array $decisions;

    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $citation_edges;

    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $edge_accounting;

    /**
     * @var int|null
     */
    public ?int $per_source_resolved_target_cap;

    /**
     * @var int|null
     */
    public ?int $per_source_unresolved_edge_row_cap;

    /**
     * @var int|null
     */
    public ?int $requested;

    /**
     * @var int|null
     */
    public ?int $included;

    /**
     * @var int|null
     */
    public ?int $deduped;

    /**
     * @var string|null
     */
    public ?string $text_mode;

    /**
     * @var int|null
     */
    public ?int $max_chars;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var bool|null
     */
    public ?bool $payload_truncated;

    /**
     * @var string|null
     */
    public ?string $assembled_at;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        /** @var list<array{id?: string, aktenzeichen?: string, court?: string, date?: string, document_type?: string, ecli?: string|null, jurisdiction?: string, excerpt?: string, excerpt_span?: string, text_length?: int, truncated?: bool, fortgeltung_checked?: bool, fortgeltung_note?: string}>|null $value */
        $value = Fields::array($raw, 'decisions');
        $this->decisions = $value;
        /** @var list<array<string, mixed>>|null $value */
        $value = Fields::array($raw, 'citation_edges');
        $this->citation_edges = $value;
        /** @var list<array<string, mixed>>|null $value */
        $value = Fields::array($raw, 'edge_accounting');
        $this->edge_accounting = $value;
        $this->per_source_resolved_target_cap = Fields::int($raw, 'per_source_resolved_target_cap');
        $this->per_source_unresolved_edge_row_cap = Fields::int($raw, 'per_source_unresolved_edge_row_cap');
        $this->requested = Fields::int($raw, 'requested');
        $this->included = Fields::int($raw, 'included');
        $this->deduped = Fields::int($raw, 'deduped');
        $this->text_mode = Fields::string($raw, 'text_mode');
        $this->max_chars = Fields::int($raw, 'max_chars');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->payload_truncated = Fields::bool($raw, 'payload_truncated');
        $this->assembled_at = Fields::string($raw, 'assembled_at');
    }
}
