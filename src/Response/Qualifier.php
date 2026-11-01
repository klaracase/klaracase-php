<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * A qualifier of the requested citation, such as an Absatz.
 */
final readonly class Qualifier implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $type = null,
        public ?string $value = null,
        public ?bool $checked = null,
        public ?bool $present = null,
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
            type: $r->string('type'),
            value: $r->string('value'),
            checked: $r->bool('checked'),
            present: $r->bool('present'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
