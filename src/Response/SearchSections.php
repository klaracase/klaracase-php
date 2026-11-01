<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The two sections of a cross-type search result.
 */
final readonly class SearchSections implements Model
{
    use Wire;

    /**
     * @param list<LawHit>|null $laws
     * @param list<DecisionHit>|null $decisions
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?array $laws = null,
        public ?array $decisions = null,
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
            laws: $r->listOf('laws', LawHit::class),
            decisions: $r->listOf('decisions', DecisionHit::class),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
