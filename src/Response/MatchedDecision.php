<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The corpus decision a case citation matched.
 */
final readonly class MatchedDecision implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $artifact_id = null,
        public ?string $ecli = null,
        public ?string $court = null,
        public ?string $date = null,
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
            artifact_id: $r->string('artifact_id'),
            ecli: $r->string('ecli'),
            court: $r->string('court'),
            date: $r->string('date'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
