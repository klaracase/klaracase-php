<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /decisions/{id}: the text of one decision, or a slice of it.
 *
 * `rn_source` says whether the Randnummern are the court's printed numbers. `fortgeltung_checked` is false:
 * the API does not check whether a decision is still good law.
 */
final readonly class Decision extends Response
{
    protected const KEYS = [
        'id',
        'aktenzeichen',
        'court',
        'date',
        'jurisdiction',
        'metadata',
        'fortgeltung_checked',
        'fortgeltung_note',
        'full_text',
        'text_length',
        'truncated',
        'rn_source',
        'rn_source_note',
        'available_sections',
        'available_range',
        'available_range_scope',
        'has_rn_anchors',
    ];

    /**
     * @var string|null
     */
    public ?string $id;

    /**
     * @var string|null
     */
    public ?string $aktenzeichen;

    /**
     * @var string|null
     */
    public ?string $court;

    /**
     * @var string|null
     */
    public ?string $date;

    /**
     * @var string|null
     */
    public ?string $jurisdiction;

    /**
     * @var array{document_type?: string, ecli?: string|null, file_numbers?: list<string>}|null
     */
    public ?array $metadata;

    /**
     * @var bool|null
     */
    public ?bool $fortgeltung_checked;

    /**
     * @var string|null
     */
    public ?string $fortgeltung_note;

    /**
     * @var string|null
     */
    public ?string $full_text;

    /**
     * @var int|null
     */
    public ?int $text_length;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var string|null
     */
    public ?string $rn_source;

    /**
     * @var string|null
     */
    public ?string $rn_source_note;

    /**
     * @var list<string>|null
     */
    public ?array $available_sections;

    /**
     * @var array{rn_from?: int, rn_to?: int, rn_source?: string}|null
     */
    public ?array $available_range;

    /**
     * @var string|null
     */
    public ?string $available_range_scope;

    /**
     * @var bool|null
     */
    public ?bool $has_rn_anchors;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        $this->id = Fields::string($raw, 'id');
        $this->aktenzeichen = Fields::string($raw, 'aktenzeichen');
        $this->court = Fields::string($raw, 'court');
        $this->date = Fields::string($raw, 'date');
        $this->jurisdiction = Fields::string($raw, 'jurisdiction');
        /** @var array{document_type?: string, ecli?: string|null, file_numbers?: list<string>}|null $value */
        $value = Fields::array($raw, 'metadata');
        $this->metadata = $value;
        $this->fortgeltung_checked = Fields::bool($raw, 'fortgeltung_checked');
        $this->fortgeltung_note = Fields::string($raw, 'fortgeltung_note');
        $this->full_text = Fields::string($raw, 'full_text');
        $this->text_length = Fields::int($raw, 'text_length');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->rn_source = Fields::string($raw, 'rn_source');
        $this->rn_source_note = Fields::string($raw, 'rn_source_note');
        /** @var list<string>|null $value */
        $value = Fields::array($raw, 'available_sections');
        $this->available_sections = $value;
        /** @var array{rn_from?: int, rn_to?: int, rn_source?: string}|null $value */
        $value = Fields::array($raw, 'available_range');
        $this->available_range = $value;
        $this->available_range_scope = Fields::string($raw, 'available_range_scope');
        $this->has_rn_anchors = Fields::bool($raw, 'has_rn_anchors');
    }
}
