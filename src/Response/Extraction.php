<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * What the extraction step found and dropped.
 */
final readonly class Extraction implements Model
{
    use Wire;

    /**
     * @param array<array-key, mixed>|null $dropped_by_token
     * @param array<array-key, mixed>|null $dropped_unreadable_texts
     * @param array<array-key, mixed>|null $refused_contract_collision
     * @param array<array-key, mixed>|null $not_verifiable_by_type
     * @param array<array-key, mixed>|null $possible_citations_unparsed_texts
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $detected = null,
        public ?int $dropped = null,
        public ?int $dropped_unreadable = null,
        public ?int $dropped_truncation = null,
        public ?int $dropped_trailing_noun = null,
        public ?int $dropped_stacked_subdivision = null,
        public ?int $dropped_refused_collision = null,
        public ?array $dropped_by_token = null,
        public ?array $dropped_unreadable_texts = null,
        public ?array $refused_contract_collision = null,
        public ?int $not_verifiable = null,
        public ?array $not_verifiable_by_type = null,
        public ?int $possible_citations_unparsed = null,
        public ?array $possible_citations_unparsed_texts = null,
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
            detected: $r->int('detected'),
            dropped: $r->int('dropped'),
            dropped_unreadable: $r->int('dropped_unreadable'),
            dropped_truncation: $r->int('dropped_truncation'),
            dropped_trailing_noun: $r->int('dropped_trailing_noun'),
            dropped_stacked_subdivision: $r->int('dropped_stacked_subdivision'),
            dropped_refused_collision: $r->int('dropped_refused_collision'),
            dropped_by_token: $r->anyArray('dropped_by_token'),
            dropped_unreadable_texts: $r->anyArray('dropped_unreadable_texts'),
            refused_contract_collision: $r->anyArray('refused_contract_collision'),
            not_verifiable: $r->int('not_verifiable'),
            not_verifiable_by_type: $r->anyArray('not_verifiable_by_type'),
            possible_citations_unparsed: $r->int('possible_citations_unparsed'),
            possible_citations_unparsed_texts: $r->anyArray('possible_citations_unparsed_texts'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
