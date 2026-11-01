<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The Randnummer span a decision offers.
 */
final readonly class AvailableRange implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $rn_from = null,
        public ?int $rn_to = null,
        public ?string $rn_source = null,
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
            rn_from: $r->int('rn_from'),
            rn_to: $r->int('rn_to'),
            rn_source: $r->string('rn_source'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
