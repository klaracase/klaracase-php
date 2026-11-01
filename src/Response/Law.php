<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The statute a norm belongs to.
 */
final readonly class Law implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $abbreviation = null,
        public ?string $source_abbreviation = null,
        public ?string $official_name = null,
        public ?string $law_level = null,
        public ?string $jurisdiction = null,
        public ?string $source_url = null,
        public ?string $upstream_status = null,
        public ?string $upstream_last_seen_at = null,
        public ?string $upstream_missing_since = null,
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
            abbreviation: $r->string('abbreviation'),
            source_abbreviation: $r->string('source_abbreviation'),
            official_name: $r->string('official_name'),
            law_level: $r->string('law_level'),
            jurisdiction: $r->string('jurisdiction'),
            source_url: $r->string('source_url'),
            upstream_status: $r->string('upstream_status'),
            upstream_last_seen_at: $r->string('upstream_last_seen_at'),
            upstream_missing_since: $r->string('upstream_missing_since'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
