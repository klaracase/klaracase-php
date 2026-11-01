<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Links related to a norm.
 */
final readonly class NormLinks implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?DecisionsCitingLink $decisions_citing = null,
        public ?string $decisions_citing_url = null,
        public ?string $self_url = null,
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
            decisions_citing: $r->object('decisions_citing', DecisionsCitingLink::class),
            decisions_citing_url: $r->string('decisions_citing_url'),
            self_url: $r->string('self_url'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
