<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use DateTimeImmutable;
use Exception;
use Throwable;

/**
 * HTTP 402 `token_limit_reached` (API 0.96.0): this API key used up the credit limit an organization admin set for it.
 *
 * It extends `InsufficientCreditsException` because the HTTP status is the same, but its body carries no `cost` and
 * no `balance`, so both stay null. The organization may still have credits; only this key is capped. The call did not
 * run and nothing was charged. It is never retried: stop, and report the refusal to your user. Waiting for
 * `resetsAt` or an admin who raises the limit are the only ways out.
 *
 * `unit` (always `credits`), `cap` (the key's limit for the period) and `resetsAt` (the raw timestamp) are read on
 * every `KlaracaseException`; this class adds the parsed time, `spent` and `settingsUrl`.
 */
final class TokenLimitReachedException extends InsufficientCreditsException
{
    /** The credits this API key has used in the period, plan credits and API budget together. Null when absent. */
    public readonly ?int $spent;

    /** When the credit period of the key ends and its count starts again, parsed from `resetsAt`. Null when absent or not a timestamp. */
    public readonly ?DateTimeImmutable $resetsAtDateTime;

    /**
     * The page where an organization admin changes the limit of the key. It is for a human admin: a program or an AI
     * agent must never open it, only show it to its user. Null when absent.
     */
    public readonly ?string $settingsUrl;

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

        $this->spent = is_int($extra['spent'] ?? null) ? $extra['spent'] : null;
        $this->settingsUrl = is_string($extra['settings_url'] ?? null) ? $extra['settings_url'] : null;
        $this->resetsAtDateTime = self::timestamp($this->resetsAt);
    }

    private static function timestamp(?string $raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }
    }
}
