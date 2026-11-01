<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * The prepaid API budget of the organization after this call (`credits.api_budget`, API 0.96.0).
 *
 * The budget pays for API and MCP calls once the plan credits are used up. It does not expire, and the web app never
 * draws on it. The whole object is absent from `Credits` when the organization has no budget and may not load one.
 * It is never null when it is present.
 */
final readonly class ApiBudget implements Model
{
    use Wire;

    /**
     * @param int|null $cents The budget after this call, in euro cents. It can be negative after a payment was lost.
     * @param int|null $rate_cents_per_credit What one credit costs from the budget, in whole euro cents. This is the rate for
     *                                        the organization, which a contract can set apart from the list rate.
     * @param int|null $credits_at_rate How many further credits the spendable part of the budget pays for at that rate.
     *                                  0 when the budget is empty or on hold.
     * @param array<string, mixed> $extra Keys this class does not model; kept so they survive a round trip.
     * @param list<string> $nulls Modelled keys that arrived as an explicit JSON null.
     */
    public function __construct(
        public ?int $cents = null,
        public ?int $rate_cents_per_credit = null,
        public ?int $credits_at_rate = null,
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
            cents: $r->int('cents'),
            rate_cents_per_credit: $r->int('rate_cents_per_credit'),
            credits_at_rate: $r->int('credits_at_rate'),
            extra: $r->rest(),
            nulls: $r->nulls(),
        );
    }
}
