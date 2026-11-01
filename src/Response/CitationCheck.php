<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of POST /citation-checks and GET /citation-checks/{run}: one finished citation check run.
 *
 * Run `status` values: pending, gating, extracting, resolving, checking_external, complete, rejected, failed.
 * Finding and citation `verdict` values: verified_corpus, verified_external, not_found, out_of_coverage, ambiguous,
 * unverifiable, discrepancy. A `not_found` verdict means the citation was not found in the corpus. It is not a claim
 * that the citation is fabricated. Read `evidence`, the advisories and `coverage` on each finding before you act on it.
 * Offsets are Unicode codepoint offsets into the text you sent.
 */
final readonly class CitationCheck extends Response
{
    protected const KEYS = [
        'id',
        'status',
        'source',
        'progress',
        'verdict_counts',
        'partial',
        'unverified_count',
        'reason_counts',
        'unique_citations',
        'extraction',
        'notes',
        'findings_unavailable_reason',
        'findings_purged_at',
        'findings',
        'submitted_at',
        'completed_at',
        'created_at',
        'payload_truncated',
    ];

    /**
     * Run id.
     */
    public ?string $id;

    /**
     * Run status; see the class description.
     */
    public ?string $status;

    /**
     * Where the run came from, `api_v1` for this API.
     */
    public ?string $source;

    /**
     * @var array{citations_total?: int, citations_checked?: int, unique_citations_total?: int}|null
     */
    public ?array $progress;

    /**
     * Count per verdict.
     *
     * @var array<string, int>|null
     */
    public ?array $verdict_counts;

    /**
     * @var bool|null
     */
    public ?bool $partial;

    /**
     * @var int|null
     */
    public ?int $unverified_count;

    /**
     * @var list<array{category?: string, count?: int}>|null
     */
    public ?array $reason_counts;

    /**
     * One row per distinct citation.
     *
     * @var list<array{key_basis?: string, target_artifact_id?: string|null, canonical?: string, spellings?: list<string>, occurrences?: int, verdict?: string, reference_type?: string}>|null
     */
    public ?array $unique_citations;

    /**
     * Counters of the citation extraction step.
     *
     * @var array<string, mixed>|null
     */
    public ?array $extraction;

    /**
     * @var list<mixed>|null
     */
    public ?array $notes;

    /**
     * @var string|null
     */
    public ?string $findings_unavailable_reason;

    /**
     * @var string|null
     */
    public ?string $findings_purged_at;

    /**
     * One row per place a citation occurs, with offsets.
     *
     * @var list<array{id?: string, raw_text?: string, canonical?: string|null, type?: string, role?: string|null, verdict?: string, external_status?: string, start_offset?: int, end_offset?: int, occurrence_index?: int, confidence?: string|null, matched_decision?: array{artifact_id?: string, ecli?: string|null, court?: string, date?: string}|null, subdivision_check?: string, subdivision_advisory?: string|null, check_scope?: array<string, mixed>|null, ambiguity_advisory?: string|null, coverage?: mixed, coverage_boundary?: mixed, coverage_advisory?: string|null, evidence?: array<string, mixed>|null, disputed_at?: string|null}>|null
     */
    public ?array $findings;

    /**
     * @var string|null
     */
    public ?string $submitted_at;

    /**
     * @var string|null
     */
    public ?string $completed_at;

    /**
     * @var string|null
     */
    public ?string $created_at;

    /**
     * @var bool|null
     */
    public ?bool $payload_truncated;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        $this->id = Fields::string($raw, 'id');
        $this->status = Fields::string($raw, 'status');
        $this->source = Fields::string($raw, 'source');
        /** @var array{citations_total?: int, citations_checked?: int, unique_citations_total?: int}|null $value */
        $value = Fields::array($raw, 'progress');
        $this->progress = $value;
        /** @var array<string, int>|null $value */
        $value = Fields::array($raw, 'verdict_counts');
        $this->verdict_counts = $value;
        $this->partial = Fields::bool($raw, 'partial');
        $this->unverified_count = Fields::int($raw, 'unverified_count');
        /** @var list<array{category?: string, count?: int}>|null $value */
        $value = Fields::array($raw, 'reason_counts');
        $this->reason_counts = $value;
        /** @var list<array{key_basis?: string, target_artifact_id?: string|null, canonical?: string, spellings?: list<string>, occurrences?: int, verdict?: string, reference_type?: string}>|null $value */
        $value = Fields::array($raw, 'unique_citations');
        $this->unique_citations = $value;
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'extraction');
        $this->extraction = $value;
        /** @var list<mixed>|null $value */
        $value = Fields::array($raw, 'notes');
        $this->notes = $value;
        $this->findings_unavailable_reason = Fields::string($raw, 'findings_unavailable_reason');
        $this->findings_purged_at = Fields::string($raw, 'findings_purged_at');
        /** @var list<array{id?: string, raw_text?: string, canonical?: string|null, type?: string, role?: string|null, verdict?: string, external_status?: string, start_offset?: int, end_offset?: int, occurrence_index?: int, confidence?: string|null, matched_decision?: array{artifact_id?: string, ecli?: string|null, court?: string, date?: string}|null, subdivision_check?: string, subdivision_advisory?: string|null, check_scope?: array<string, mixed>|null, ambiguity_advisory?: string|null, coverage?: mixed, coverage_boundary?: mixed, coverage_advisory?: string|null, evidence?: array<string, mixed>|null, disputed_at?: string|null}>|null $value */
        $value = Fields::array($raw, 'findings');
        $this->findings = $value;
        $this->submitted_at = Fields::string($raw, 'submitted_at');
        $this->completed_at = Fields::string($raw, 'completed_at');
        $this->created_at = Fields::string($raw, 'created_at');
        $this->payload_truncated = Fields::bool($raw, 'payload_truncated');
    }
}
