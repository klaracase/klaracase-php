<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /corpus/facets/{field}: the values of one facet with counts. Free.
 */
final readonly class CorpusFacets extends Response
{
    protected const KEYS = [
        'field',
        'norm',
        'applies_to',
        'values',
        'total_values',
        'truncated',
        'value_limit',
        'count_basis',
        'as_of',
    ];

    /**
     * @var string|null
     */
    public ?string $field;

    /**
     * Echoed back for `cited_norms`.
     */
    public ?string $norm;

    /**
     * @var string|null
     */
    public ?string $applies_to;

    /**
     * Facet value to count.
     *
     * @var array<string, array{value?: int, is_floor?: bool}>|null
     */
    public ?array $values;

    /**
     * @var int|null
     */
    public ?int $total_values;

    /**
     * @var bool|null
     */
    public ?bool $truncated;

    /**
     * @var int|null
     */
    public ?int $value_limit;

    /**
     * @var string|null
     */
    public ?string $count_basis;

    /**
     * @var string|null
     */
    public ?string $as_of;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        $this->field = Fields::string($raw, 'field');
        $this->norm = Fields::string($raw, 'norm');
        $this->applies_to = Fields::string($raw, 'applies_to');
        /** @var array<string, array{value?: int, is_floor?: bool}>|null $value */
        $value = Fields::array($raw, 'values');
        $this->values = $value;
        $this->total_values = Fields::int($raw, 'total_values');
        $this->truncated = Fields::bool($raw, 'truncated');
        $this->value_limit = Fields::int($raw, 'value_limit');
        $this->count_basis = Fields::string($raw, 'count_basis');
        $this->as_of = Fields::string($raw, 'as_of');
    }
}
