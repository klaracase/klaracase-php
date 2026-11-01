<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * How many citation edges of one source made it into a data package.
 */
final readonly class EdgeAccounting implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $from_artifact_id = null,
        public ?string $unit = null,
        public ?int $edges_total = null,
        public ?int $edges_included = null,
        public ?int $edges_resolved_included = null,
        public ?int $edges_unresolved_included = null,
        public ?int $edges_resolved_total = null,
        public ?int $edges_unresolved_total = null,
        public ?int $targets_resolved_included = null,
        public ?int $targets_resolved_total = null,
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
            from_artifact_id: $r->string('from_artifact_id'),
            unit: $r->string('unit'),
            edges_total: $r->int('edges_total'),
            edges_included: $r->int('edges_included'),
            edges_resolved_included: $r->int('edges_resolved_included'),
            edges_unresolved_included: $r->int('edges_unresolved_included'),
            edges_resolved_total: $r->int('edges_resolved_total'),
            edges_unresolved_total: $r->int('edges_unresolved_total'),
            targets_resolved_included: $r->int('targets_resolved_included'),
            targets_resolved_total: $r->int('targets_resolved_total'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
