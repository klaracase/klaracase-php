<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Why a 402 `insufficient_credits` call could not be paid (the problem member `reason`, API 0.96.0).
 *
 * The vocabulary is open. A client must treat a value it does not know like `ApiBudgetNotAvailable`.
 */
enum InsufficientCreditsReason: string
{
    /** The organization may load an API budget, and what it holds does not cover this call. */
    case ApiBudgetEmpty = 'api_budget_empty';
    /** The organization has no API budget to draw on, or its budget is on hold, and its plan does not allow a top-up. */
    case ApiBudgetNotAvailable = 'api_budget_not_available';
    /** Reserved: a refusal where the API budget plays no part. The API does not serve it today. */
    case PlanCreditsExhausted = 'plan_credits_exhausted';
    /** A value this SDK version does not know. Never sent to the server. */
    case Unknown = '__unknown__';

    /**
     * Null when the server sent no value; Unknown for a value this SDK version does not know.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : (self::tryFrom($value) ?? self::Unknown);
    }
}
