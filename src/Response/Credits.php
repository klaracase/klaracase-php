<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The `credits` member of a served body.
 *
 * `metered` is false for an unmetered account; `cost` and `credits_remaining` are then null.
 */
final readonly class Credits
{
    public function __construct(
        public ?bool $metered,
        public ?int $cost,
        public ?int $credits_remaining,
    ) {
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            Fields::bool($raw, 'metered'),
            Fields::int($raw, 'cost'),
            Fields::int($raw, 'credits_remaining'),
        );
    }
}
