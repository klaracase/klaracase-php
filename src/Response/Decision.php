<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Exception\ResponseShapeException;

/**
 * Answer of GET /decisions/{id}: the text of one decision, or a slice of it. `fortgeltung_checked` is false: the API does not check whether a decision is still good law.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Decision implements Model, ArrayAccess
{
    use Wire;
    use ReadsAsArray;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['returned_range', 'ignored_parameters'];
    }

    /**
     * @param list<string>|null $available_sections
     * @param array<string, mixed>|null $returned_range
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public string $id,
        public ?string $aktenzeichen = null,
        public ?string $court = null,
        public ?string $date = null,
        public ?string $jurisdiction = null,
        public ?DecisionMetadata $metadata = null,
        public ?bool $fortgeltung_checked = null,
        public ?string $fortgeltung_note = null,
        public ?string $full_text = null,
        public ?int $text_length = null,
        public ?bool $truncated = null,
        public ?string $rn_source = null,
        public ?string $rn_source_note = null,
        public ?array $available_sections = null,
        public ?AvailableRange $available_range = null,
        public ?array $returned_range = null,
        public ?string $available_range_scope = null,
        public ?bool $has_rn_anchors = null,
        public ?string $api_version = null,
        public ?string $changelog_url = null,
        public ?Credits $credits = null,
        public ?array $ignored_parameters = null,
        public ResponseMeta $meta = new ResponseMeta(200, null, 0.0),
        public array $extra = [],
        public array $nulls = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public static function fromArray(array $data, ?ResponseMeta $meta = null): self
    {
        $r = new Reader($data);
        $id = $r->string('id');
        if ($id === null) {
            throw ResponseShapeException::missing(self::class, 'id', $meta);
        }

        return new self(
            id: $id,
            aktenzeichen: $r->string('aktenzeichen'),
            court: $r->string('court'),
            date: $r->string('date'),
            jurisdiction: $r->string('jurisdiction'),
            metadata: $r->object('metadata', DecisionMetadata::class),
            fortgeltung_checked: $r->bool('fortgeltung_checked'),
            fortgeltung_note: $r->string('fortgeltung_note'),
            full_text: $r->string('full_text'),
            text_length: $r->int('text_length'),
            truncated: $r->bool('truncated'),
            rn_source: $r->string('rn_source'),
            rn_source_note: $r->string('rn_source_note'),
            available_sections: $r->strings('available_sections'),
            available_range: $r->object('available_range', AvailableRange::class),
            returned_range: $r->map('returned_range'),
            available_range_scope: $r->string('available_range_scope'),
            has_rn_anchors: $r->bool('has_rn_anchors'),
            api_version: $r->string('api_version'),
            changelog_url: $r->string('changelog_url'),
            credits: $r->object('credits', Credits::class),
            ignored_parameters: $r->map('ignored_parameters'),
            meta: $meta ?? new ResponseMeta(200, null, 0.0),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
