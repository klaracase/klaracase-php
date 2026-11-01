<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\Confidence;
use Klaracase\Enum\ReferenceType;
use Klaracase\Enum\Verdict;

/**
 * One citation found in the checked text. `start_offset` and `end_offset` count Unicode code points.
 */
final readonly class Finding implements Model
{
    use Wire;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['coverage', 'coverage_boundary'];
    }

    /**
     * @param array<string, mixed>|null $coverage
     * @param array<string, mixed>|null $coverage_boundary
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $raw_text = null,
        public ?string $canonical = null,
        public ?string $type = null,
        public ?string $role = null,
        public ?string $verdict = null,
        public ?string $external_status = null,
        public ?int $start_offset = null,
        public ?int $end_offset = null,
        public ?int $occurrence_index = null,
        public ?string $confidence = null,
        public ?MatchedDecision $matched_decision = null,
        public ?string $subdivision_check = null,
        public ?string $subdivision_advisory = null,
        public ?CheckScope $check_scope = null,
        public ?string $ambiguity_advisory = null,
        public ?array $coverage = null,
        public ?array $coverage_boundary = null,
        public ?string $coverage_advisory = null,
        public ?Evidence $evidence = null,
        public ?string $disputed_at = null,
        public array $extra = [],
        public array $nulls = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public static function fromArray(array $data): self
    {
        $r = new Reader($data);

        return new self(
            id: $r->string('id'),
            raw_text: $r->string('raw_text'),
            canonical: $r->string('canonical'),
            type: $r->string('type'),
            role: $r->string('role'),
            verdict: $r->string('verdict'),
            external_status: $r->string('external_status'),
            start_offset: $r->int('start_offset'),
            end_offset: $r->int('end_offset'),
            occurrence_index: $r->int('occurrence_index'),
            confidence: $r->string('confidence'),
            matched_decision: $r->object('matched_decision', MatchedDecision::class),
            subdivision_check: $r->string('subdivision_check'),
            subdivision_advisory: $r->string('subdivision_advisory'),
            check_scope: $r->object('check_scope', CheckScope::class),
            ambiguity_advisory: $r->string('ambiguity_advisory'),
            coverage: $r->map('coverage'),
            coverage_boundary: $r->map('coverage_boundary'),
            coverage_advisory: $r->string('coverage_advisory'),
            evidence: $r->object('evidence', Evidence::class),
            disputed_at: $r->string('disputed_at'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$type` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function typeEnum(): ?ReferenceType
    {
        return ReferenceType::fromWire($this->type);
    }

    /**
     * `$verdict` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function verdictEnum(): ?Verdict
    {
        return Verdict::fromWire($this->verdict);
    }

    /**
     * `$confidence` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function confidenceEnum(): ?Confidence
    {
        return Confidence::fromWire($this->confidence);
    }

    /**
     * The text this finding points at, cut out of the text you sent. `start_offset` and `end_offset` count Unicode
     * code points, not bytes, so the cut uses `mb_substr`. Falls back to `raw_text` when the offsets are missing.
     */
    public function excerpt(string $text): string
    {
        if ($this->start_offset === null || $this->end_offset === null || $this->end_offset < $this->start_offset) {
            return $this->raw_text ?? '';
        }

        return mb_substr($text, $this->start_offset, $this->end_offset - $this->start_offset, 'UTF-8');
    }
}
