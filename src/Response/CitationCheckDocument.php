<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Enum\DocumentErrorCode;
use Klaracase\Enum\DocumentStatus;
use Klaracase\Exception\ResponseShapeException;

/**
 * The receipt of an uploaded document. Statuses `uploaded`, `extracting` and `ocr_pending` are not final.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class CitationCheckDocument implements Model, ArrayAccess
{
    use Wire;
    use ReadsAsArray;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['ignored_parameters'];
    }

    /**
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public string $id,
        public ?string $filename = null,
        public ?string $format = null,
        public ?string $status = null,
        public ?int $char_count = null,
        public ?int $page_count = null,
        public ?int $pages_without_text_layer = null,
        public ?int $check_max_chars = null,
        public ?int $quoted_credits = null,
        public ?string $error_code = null,
        public ?string $expires_at = null,
        public ?string $created_at = null,
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
            filename: $r->string('filename'),
            format: $r->string('format'),
            status: $r->string('status'),
            char_count: $r->int('char_count'),
            page_count: $r->int('page_count'),
            pages_without_text_layer: $r->int('pages_without_text_layer'),
            check_max_chars: $r->int('check_max_chars'),
            quoted_credits: $r->int('quoted_credits'),
            error_code: $r->string('error_code'),
            expires_at: $r->string('expires_at'),
            created_at: $r->string('created_at'),
            api_version: $r->string('api_version'),
            changelog_url: $r->string('changelog_url'),
            credits: $r->object('credits', Credits::class),
            ignored_parameters: $r->map('ignored_parameters'),
            meta: $meta ?? new ResponseMeta(200, null, 0.0),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$status` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function statusEnum(): ?DocumentStatus
    {
        return DocumentStatus::fromWire($this->status);
    }

    /**
     * `$error_code` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function errorCodeEnum(): ?DocumentErrorCode
    {
        return DocumentErrorCode::fromWire($this->error_code);
    }
}
