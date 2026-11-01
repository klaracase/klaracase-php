<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use Klaracase\Exception\ResponseShapeException;

/**
 * Answer of GET /norms/{reference}: the current consolidated text of one norm. Costs 1 credit.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Norm implements Model, ArrayAccess
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
     * @param list<Qualifier>|null $requested_qualifiers
     * @param array<string, mixed>|null $ignored_parameters
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public string $reference,
        public ?string $requested_reference = null,
        public ?Law $law = null,
        public ?NormText $norm = null,
        public ?array $requested_qualifiers = null,
        public ?NormVersion $version = null,
        public ?Neighbours $neighbours = null,
        public ?NormLinks $links = null,
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
        $reference = $r->string('reference');
        if ($reference === null) {
            throw ResponseShapeException::missing(self::class, 'reference', $meta);
        }

        return new self(
            reference: $reference,
            requested_reference: $r->string('requested_reference'),
            law: $r->object('law', Law::class),
            norm: $r->object('norm', NormText::class),
            requested_qualifiers: $r->listOf('requested_qualifiers', Qualifier::class),
            version: $r->object('version', NormVersion::class),
            neighbours: $r->object('neighbours', Neighbours::class),
            links: $r->object('links', NormLinks::class),
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
