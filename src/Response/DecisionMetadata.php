<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\DocumentType;

/**
 * Metadata of one decision.
 */
final readonly class DecisionMetadata implements Model
{
    use Wire;

    /**
     * @param list<string>|null $file_numbers
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $document_type = null,
        public ?string $ecli = null,
        public ?array $file_numbers = null,
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
            document_type: $r->string('document_type'),
            ecli: $r->string('ecli'),
            file_numbers: $r->strings('file_numbers'),
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
