<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\ReferenceType;
use Klaracase\Enum\Verdict;

/**
 * One distinct citation of a run, with its spellings and its verdict.
 */
final readonly class UniqueCitation implements Model
{
    use Wire;

    /**
     * @param list<string>|null $spellings
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $key_basis = null,
        public ?string $target_artifact_id = null,
        public ?string $canonical = null,
        public ?array $spellings = null,
        public ?int $occurrences = null,
        public ?string $verdict = null,
        public ?string $reference_type = null,
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
            key_basis: $r->string('key_basis'),
            target_artifact_id: $r->string('target_artifact_id'),
            canonical: $r->string('canonical'),
            spellings: $r->strings('spellings'),
            occurrences: $r->int('occurrences'),
            verdict: $r->string('verdict'),
            reference_type: $r->string('reference_type'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$verdict` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function verdictEnum(): ?Verdict
    {
        return Verdict::fromWire($this->verdict);
    }

    /**
     * `$reference_type` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function referenceTypeEnum(): ?ReferenceType
    {
        return ReferenceType::fromWire($this->reference_type);
    }
}
