<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Klaracase\Enum\InsufficientCreditsReason;
use Throwable;

/**
 * HTTP 402: the call was not paid, so it did not run and nothing was charged. Never retried.
 *
 * `insufficient_credits` carries `cost` and `balance`, and since API 0.96.0 often `reason` and `topUpUrl`. Stop, and
 * report the refusal to your user. An organization admin can fix it; a program cannot.
 */
class InsufficientCreditsException extends KlaracaseException
{
    /** Credits this call costs. Null when the problem has no `cost`. */
    public readonly ?int $cost;

    /** Plan credits the organization has left. Null when the problem has no `balance`. */
    public readonly ?int $balance;

    /**
     * Why the call could not be paid, as the server wrote it: `api_budget_empty`, `api_budget_not_available`, and the
     * reserved `plan_credits_exhausted`. The vocabulary is open, so a client must tolerate a value it does not know
     * and treat it like `api_budget_not_available`. Null when the server sent no `reason`.
     */
    public readonly ?string $reason;

    /**
     * The page where an organization admin loads the API budget. It is for a human admin: a program or an AI agent
     * must never open it or follow it, only show it to its user. Null when the organization may not load a budget.
     */
    public readonly ?string $topUpUrl;

    /**
     * @param array<string, mixed> $extra
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $type = null,
        ?string $title = null,
        ?string $detail = null,
        ?string $errorCode = null,
        ?string $apiRelease = null,
        array $extra = [],
        ?Throwable $previous = null,
        array $headers = [],
        array $body = [],
    ) {
        parent::__construct($message, $status, $type, $title, $detail, $errorCode, $apiRelease, $extra, $previous, $headers, $body);

        $this->cost = is_int($extra['cost'] ?? null) ? $extra['cost'] : null;
        $this->balance = is_int($extra['balance'] ?? null) ? $extra['balance'] : null;
        $this->reason = is_string($extra['reason'] ?? null) ? $extra['reason'] : null;
        $this->topUpUrl = is_string($extra['top_up_url'] ?? null) ? $extra['top_up_url'] : null;
    }

    /**
     * `$reason` as an enum: null when absent, Unknown when the value is new to this SDK version. The raw string stays on the property.
     */
    public function reasonEnum(): ?InsufficientCreditsReason
    {
        return InsufficientCreditsReason::fromWire($this->reason);
    }
}
