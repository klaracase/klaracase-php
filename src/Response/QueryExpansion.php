<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\QueryExpansionStatus;

/**
 * What the server did to widen the query.
 */
final readonly class QueryExpansion implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $status = null,
        public ?bool $applied = null,
        public ?string $reason = null,
        public ?string $note = null,
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
            status: $r->string('status'),
            applied: $r->bool('applied'),
            reason: $r->string('reason'),
            note: $r->string('note'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$status` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function statusEnum(): ?QueryExpansionStatus
    {
        return QueryExpansionStatus::fromWire($this->status);
    }
}
