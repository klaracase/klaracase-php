<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\DocumentType;

/**
 * The corpus decision a citation resolved to.
 */
final readonly class CorpusRef implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $artifact_id = null,
        public ?string $court = null,
        public ?string $date = null,
        public ?string $az = null,
        public ?string $document_type = null,
        public ?string $ecli = null,
        public ?string $corpus_url = null,
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
            artifact_id: $r->string('artifact_id'),
            court: $r->string('court'),
            date: $r->string('date'),
            az: $r->string('az'),
            document_type: $r->string('document_type'),
            ecli: $r->string('ecli'),
            corpus_url: $r->string('corpus_url'),
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
