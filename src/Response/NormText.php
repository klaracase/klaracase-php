<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The text of one norm.
 */
final readonly class NormText implements Model
{
    use Wire;

    /**
     * @param list<string>|null $absatz_identifiers
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $number = null,
        public ?string $title = null,
        public ?string $lifecycle = null,
        public ?string $text = null,
        public ?array $absatz_identifiers = null,
        public ?int $chars = null,
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
            number: $r->string('number'),
            title: $r->string('title'),
            lifecycle: $r->string('lifecycle'),
            text: $r->string('text'),
            absatz_identifiers: $r->strings('absatz_identifiers'),
            chars: $r->int('chars'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
