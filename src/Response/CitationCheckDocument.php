<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Receipt of an uploaded document (POST and GET /citation-check-documents). It never holds the text.
 *
 * `status` values: uploaded, extracting, ocr_pending, ready, failed. Only `ready` and `failed` are final.
 * `error_code` values: document_too_long, unsupported_format_pending, corrupted_document, zip_bomb_rejected,
 * encrypted_pdf, scanned_pdf, pdf_text_garbled, extraction_failed.
 */
final readonly class CitationCheckDocument extends Response
{
    protected const KEYS = [
        'id',
        'filename',
        'format',
        'status',
        'char_count',
        'page_count',
        'pages_without_text_layer',
        'check_max_chars',
        'quoted_credits',
        'error_code',
        'expires_at',
        'created_at',
    ];

    /**
     * Document id; pass it to `citationChecks->create()` once `status` is `ready`.
     */
    public ?string $id;

    /**
     * @var string|null
     */
    public ?string $filename;

    /**
     * pdf, docx, md or txt.
     */
    public ?string $format;

    /**
     * See the class description.
     */
    public ?string $status;

    /**
     * @var int|null
     */
    public ?int $char_count;

    /**
     * @var int|null
     */
    public ?int $page_count;

    /**
     * @var int|null
     */
    public ?int $pages_without_text_layer;

    /**
     * @var int|null
     */
    public ?int $check_max_chars;

    /**
     * Price of checking this document; null above the character cap.
     */
    public ?int $quoted_credits;

    /**
     * Why extraction failed; see the class description.
     */
    public ?string $error_code;

    /**
     * @var string|null
     */
    public ?string $expires_at;

    /**
     * @var string|null
     */
    public ?string $created_at;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        $this->id = Fields::string($raw, 'id');
        $this->filename = Fields::string($raw, 'filename');
        $this->format = Fields::string($raw, 'format');
        $this->status = Fields::string($raw, 'status');
        $this->char_count = Fields::int($raw, 'char_count');
        $this->page_count = Fields::int($raw, 'page_count');
        $this->pages_without_text_layer = Fields::int($raw, 'pages_without_text_layer');
        $this->check_max_chars = Fields::int($raw, 'check_max_chars');
        $this->quoted_credits = Fields::int($raw, 'quoted_credits');
        $this->error_code = Fields::string($raw, 'error_code');
        $this->expires_at = Fields::string($raw, 'expires_at');
        $this->created_at = Fields::string($raw, 'created_at');
    }
}
