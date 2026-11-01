<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Why a finding got its verdict.
 */
final readonly class Evidence implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $reason = null,
        public ?string $match_strength = null,
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
            reason: $r->string('reason'),
            match_strength: $r->string('match_strength'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
