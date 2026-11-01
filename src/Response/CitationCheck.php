<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\RunStatus;
use Klaracase\Enum\Verdict;
use Klaracase\Exception\ResponseShapeException;

/**
 * A citation check run. A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the citation is fabricated.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class CitationCheck implements Model, ArrayAccess
{
    use Wire;
    use ReadsAsArray;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['verdict_counts', 'ignored_parameters'];
    }

    /**
     * @param array<string, int>|null $verdict_counts
     * @param list<ReasonCount>|null $reason_counts
     * @param list<UniqueCitation>|null $unique_citations
     * @param list<mixed>|null $notes
     * @param list<Finding>|null $findings
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public string $id,
        public ?string $status = null,
        public ?string $source = null,
        public ?Progress $progress = null,
        public ?array $verdict_counts = null,
        public ?bool $partial = null,
        public ?int $unverified_count = null,
        public ?array $reason_counts = null,
        public ?array $unique_citations = null,
        public ?Extraction $extraction = null,
        public ?array $notes = null,
        public ?string $findings_unavailable_reason = null,
        public ?string $findings_purged_at = null,
        public ?array $findings = null,
        public ?string $submitted_at = null,
        public ?string $completed_at = null,
        public ?string $created_at = null,
        public ?bool $payload_truncated = null,
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
        $id = $r->string('id');
        if ($id === null) {
            throw ResponseShapeException::missing(self::class, 'id', $meta);
        }

        return new self(
            id: $id,
            status: $r->string('status'),
            source: $r->string('source'),
            progress: $r->object('progress', Progress::class),
            verdict_counts: $r->ints('verdict_counts'),
            partial: $r->bool('partial'),
            unverified_count: $r->int('unverified_count'),
            reason_counts: $r->listOf('reason_counts', ReasonCount::class),
            unique_citations: $r->listOf('unique_citations', UniqueCitation::class),
            extraction: $r->object('extraction', Extraction::class),
            notes: $r->list('notes'),
            findings_unavailable_reason: $r->string('findings_unavailable_reason'),
            findings_purged_at: $r->string('findings_purged_at'),
            findings: $r->listOf('findings', Finding::class),
            submitted_at: $r->string('submitted_at'),
            completed_at: $r->string('completed_at'),
            created_at: $r->string('created_at'),
            payload_truncated: $r->bool('payload_truncated'),
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
     * `$status` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function statusEnum(): ?RunStatus
    {
        return RunStatus::fromWire($this->status);
    }

    /**
     * True when the run has finished (`status` is `complete`).
     */
    public function isComplete(): bool
    {
        return $this->status === RunStatus::Complete->value;
    }

    /**
     * How many citations got this verdict, from `verdict_counts`. 0 when the verdict does not appear.
     */
    public function count(Verdict|string $verdict): int
    {
        $key = $verdict instanceof Verdict ? $verdict->value : $verdict;

        return $this->verdict_counts[$key] ?? 0;
    }

    /**
     * The findings grouped by their raw `verdict` string. A finding without a verdict is left out.
     *
     * @return array<string, list<Finding>>
     */
    public function findingsByVerdict(): array
    {
        $groups = [];
        foreach ($this->findings ?? [] as $finding) {
            if ($finding->verdict !== null) {
                $groups[$finding->verdict][] = $finding;
            }
        }

        return $groups;
    }
}
