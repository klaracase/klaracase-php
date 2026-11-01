<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\ResolutionStatus;

/**
 * One citation between two decisions or between a decision and a norm.
 */
final readonly class CitationEdge implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $reference_canonical = null,
        public ?string $reference_role = null,
        public ?string $reference_type = null,
        public ?string $resolution_status = null,
        public ?bool $in_corpus = null,
        public ?CorpusRef $corpus = null,
        public ?string $from_artifact_id = null,
        public ?int $hop = null,
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
            reference_canonical: $r->string('reference_canonical'),
            reference_role: $r->string('reference_role'),
            reference_type: $r->string('reference_type'),
            resolution_status: $r->string('resolution_status'),
            in_corpus: $r->bool('in_corpus'),
            corpus: $r->object('corpus', CorpusRef::class),
            from_artifact_id: $r->string('from_artifact_id'),
            hop: $r->int('hop'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$resolution_status` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function resolutionStatusEnum(): ?ResolutionStatus
    {
        return ResolutionStatus::fromWire($this->resolution_status);
    }
}
