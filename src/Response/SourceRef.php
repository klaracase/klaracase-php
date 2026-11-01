<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The decision whose citations were requested.
 */
final readonly class SourceRef implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $aktenzeichen = null,
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
            id: $r->string('id'),
            aktenzeichen: $r->string('aktenzeichen'),
            court: $r->string('court'),
            date: $r->string('date'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
