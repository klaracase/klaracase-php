<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * How many citations ended unverified for one reason.
 */
final readonly class ReasonCount implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $category = null,
        public ?int $count = null,
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
            category: $r->string('category'),
            count: $r->int('count'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
