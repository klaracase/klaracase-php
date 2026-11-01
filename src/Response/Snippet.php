<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * A passage of a hit and its Randnummer.
 */
final readonly class Snippet implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $text = null,
        public ?int $rn = null,
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
            text: $r->string('text'),
            rn: $r->int('rn'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
