<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * HTTP 402: the organization has too few credits. Never retried.
 */
class InsufficientCreditsException extends KlaracaseException
{
    /** Credits this call costs. */
    public readonly ?int $cost;

    /** Credits the organization has left. */
    public readonly ?int $balance;

    /**
     * @param array<string, mixed> $extra
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
    ) {
        parent::__construct($message, $status, $type, $title, $detail, $errorCode, $apiRelease, $extra, $previous);

        $this->cost = is_int($extra['cost'] ?? null) ? $extra['cost'] : null;
        $this->balance = is_int($extra['balance'] ?? null) ? $extra['balance'] : null;
    }
}
