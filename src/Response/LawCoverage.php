<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Which statutes the corpus holds.
 */
final readonly class LawCoverage implements Model
{
    use Wire;

    /**
     * @param list<string>|null $eu
     * @param list<string>|null $international
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $note = null,
        public ?array $eu = null,
        public ?array $international = null,
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
            note: $r->string('note'),
            eu: $r->strings('eu'),
            international: $r->strings('international'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
