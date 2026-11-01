<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\DocumentType;

/**
 * One decision inside a data package.
 */
final readonly class PackagedDecision implements Model
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
        public ?string $document_type = null,
        public ?string $ecli = null,
        public ?string $jurisdiction = null,
        public ?string $excerpt = null,
        public ?string $excerpt_span = null,
        public ?int $text_length = null,
        public ?bool $truncated = null,
        public ?bool $fortgeltung_checked = null,
        public ?string $fortgeltung_note = null,
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
            document_type: $r->string('document_type'),
            ecli: $r->string('ecli'),
            jurisdiction: $r->string('jurisdiction'),
            excerpt: $r->string('excerpt'),
            excerpt_span: $r->string('excerpt_span'),
            text_length: $r->int('text_length'),
            truncated: $r->bool('truncated'),
            fortgeltung_checked: $r->bool('fortgeltung_checked'),
            fortgeltung_note: $r->string('fortgeltung_note'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$document_type` as an enum: null when absent, null when the value is new to this SDK version. The raw string stays on the property.
     */
    public function documentTypeEnum(): ?DocumentType
    {
        return DocumentType::fromWire($this->document_type);
    }
}
