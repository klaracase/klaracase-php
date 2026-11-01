<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Exception\ResponseShapeException;

/**
 * Answer of GET /corpus/facets/{field}. Free.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class CorpusFacets implements Model, ArrayAccess
{
    use Wire;
    use ReadsAsArray;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['values', 'ignored_parameters'];
    }

    /**
     * @param array<string, Count>|null $values
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public string $field,
        public ?string $norm = null,
        public ?string $applies_to = null,
        public ?array $values = null,
        public ?int $total_values = null,
        public ?bool $truncated = null,
        public ?int $value_limit = null,
        public ?string $count_basis = null,
        public ?string $as_of = null,
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
        $field = $r->string('field');
        if ($field === null) {
            throw ResponseShapeException::missing(self::class, 'field', $meta);
        }

        return new self(
            field: $field,
            norm: $r->string('norm'),
            applies_to: $r->string('applies_to'),
            values: $r->mapOf('values', Count::class),
            total_values: $r->int('total_values'),
            truncated: $r->bool('truncated'),
            value_limit: $r->int('value_limit'),
            count_basis: $r->string('count_basis'),
            as_of: $r->string('as_of'),
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
