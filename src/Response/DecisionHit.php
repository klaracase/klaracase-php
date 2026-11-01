<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\ArtifactType;
use Klaracase\Enum\Confidence;
use Klaracase\Enum\DocumentType;

/**
 * One decision in a search result.
 */
final readonly class DecisionHit implements Model
{
    use Wire;

    /**
     * @param list<Snippet>|null $snippets
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $artifact_type = null,
        public ?string $aktenzeichen = null,
        public ?string $court = null,
        public ?string $date = null,
        public ?string $document_type = null,
        public ?string $title = null,
        public ?string $snippet = null,
        public ?array $snippets = null,
        public ?string $chunk_title = null,
        public ?string $rn_range = null,
        public ?string $fundstelle = null,
        public ?bool $fortgeltung_checked = null,
        public ?string $fortgeltung_note = null,
        public ?string $confidence = null,
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
            artifact_type: $r->string('artifact_type'),
            aktenzeichen: $r->string('aktenzeichen'),
            court: $r->string('court'),
            date: $r->string('date'),
            document_type: $r->string('document_type'),
            title: $r->string('title'),
            snippet: $r->string('snippet'),
            snippets: $r->listOf('snippets', Snippet::class),
            chunk_title: $r->string('chunk_title'),
            rn_range: $r->string('rn_range'),
            fundstelle: $r->string('fundstelle'),
            fortgeltung_checked: $r->bool('fortgeltung_checked'),
            fortgeltung_note: $r->string('fortgeltung_note'),
            confidence: $r->string('confidence'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$artifact_type` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function artifactTypeEnum(): ?ArtifactType
    {
        return ArtifactType::fromWire($this->artifact_type);
    }

    /**
     * `$document_type` as an enum: null when absent, null when the value is new to this SDK version. The raw string stays on the property.
     */
    public function documentTypeEnum(): ?DocumentType
    {
        return DocumentType::fromWire($this->document_type);
    }

    /**
     * `$confidence` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function confidenceEnum(): ?Confidence
    {
        return Confidence::fromWire($this->confidence);
    }
}
