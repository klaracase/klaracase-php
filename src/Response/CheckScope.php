<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * What a statute check could read.
 */
final readonly class CheckScope implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $subdivision = null,
        public ?bool $article_text_available = null,
        public ?bool $article_text_read = null,
        public ?string $court = null,
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
            subdivision: $r->string('subdivision'),
            article_text_available: $r->bool('article_text_available'),
            article_text_read: $r->bool('article_text_read'),
            court: $r->string('court'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
