<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * A ready-made call that lists the decisions citing a norm.
 */
final readonly class DecisionsCitingLink implements Model
{
    use Wire;

    /**
     * @return list<string>
     */
    private static function mapKeys(): array
    {
        return ['arguments'];
    }

    /**
     * @param array<string, mixed>|null $arguments
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $tool = null,
        public ?array $arguments = null,
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
            tool: $r->string('tool'),
            arguments: $r->map('arguments'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
