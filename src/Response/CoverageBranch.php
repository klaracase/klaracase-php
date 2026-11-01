<?php

declare(strict_types=1);

namespace Klaracase\Response;

use Klaracase\Enum\Branch;

/**
 * Coverage of one court branch.
 */
final readonly class CoverageBranch implements Model
{
    use Wire;

    /**
     * @param list<TopCourt>|null $top_courts
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?string $branch = null,
        public ?Count $count = null,
        public ?string $earliest = null,
        public ?string $latest = null,
        public ?int $court_founding_year = null,
        public ?string $court_founding_basis = null,
        public ?bool $has_dates_below_floor = null,
        public ?InstanceMix $instance_mix = null,
        public ?array $top_courts = null,
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
            branch: $r->string('branch'),
            count: $r->object('count', Count::class),
            earliest: $r->string('earliest'),
            latest: $r->string('latest'),
            court_founding_year: $r->int('court_founding_year'),
            court_founding_basis: $r->string('court_founding_basis'),
            has_dates_below_floor: $r->bool('has_dates_below_floor'),
            instance_mix: $r->object('instance_mix', InstanceMix::class),
            top_courts: $r->listOf('top_courts', TopCourt::class),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }

    /**
     * `$branch` as an enum: null when absent, null when the value is new to this SDK version. The raw string stays on the property.
     */
    public function branchEnum(): ?Branch
    {
        return Branch::fromWire($this->branch);
    }
}
