<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class of every error that comes from talking to the API.
 *
 * It carries the RFC 9457 problem members. `$errorCode` is the problem `code`
 * (a PHP exception already owns `$code` as an int). `$extra` holds every
 * problem member that is not `type`, `title`, `status`, `detail` or `code`,
 * typed members included. `$body` is the whole problem as received and `$headers`
 * the response headers (lower-case names, never the token).
 *
 * The API token never appears in a message.
 */
class KlaracaseException extends RuntimeException implements KlaracaseThrowable
{
    /** On `ambiguous_aktenzeichen`: the decisions that carry the file number. Each has `id`, `court`, `date`, `aktenzeichen`. */
    /** @var list<array<string, mixed>>|null */
    public readonly ?array $candidates;

    /** On `statute_superseded`: the abbreviation of the statute that replaced the cited one. */
    public readonly ?string $successor;

    /** On `volume_cap`: the capped unit, `rows` or `documents`. On `token_limit_reached`: always `credits`. */
    public readonly ?string $unit;

    /** On `volume_cap`: the organization's daily cap for that unit. On `token_limit_reached`: the credit limit of this API key. */
    public readonly ?int $cap;

    /** On `volume_cap`: when the daily cap resets (midnight Europe/Berlin). On `token_limit_reached`: when the key's credit period ends. As the server wrote it. */
    public readonly ?string $resetsAt;

    /**
     * @param array<string, mixed> $extra
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $type = null,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $apiRelease = null,
        public readonly array $extra = [],
        ?Throwable $previous = null,
        public readonly array $headers = [],
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status ?? 0, $previous);

        $candidates = $extra['candidates'] ?? null;
        $this->candidates = is_array($candidates) && array_is_list($candidates) ? self::candidateList($candidates) : null;
        $this->successor = is_string($extra['successor'] ?? null) ? $extra['successor'] : null;
        $this->unit = is_string($extra['unit'] ?? null) ? $extra['unit'] : null;
        $this->cap = is_int($extra['cap'] ?? null) ? $extra['cap'] : null;
        $this->resetsAt = is_string($extra['resets_at'] ?? null) ? $extra['resets_at'] : null;
    }

    /**
     * Whether trying the same call again later can succeed: a rate limit that is not the daily volume cap,
     * a 503, and a failure to reach the API. Everything else (402, 4xx, document errors) repeats the same answer.
     */
    public function isRetryable(): bool
    {
        if ($this->status === 429) {
            return $this->errorCode !== 'volume_cap';
        }

        return $this->status === 503;
    }

    /**
     * A small, loggable summary. It holds no token and no request or response body.
     *
     * @return array{code: string|null, status: int|null, detail: string|null, retry_after_seconds: int|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'status' => $this->status,
            'detail' => $this->detail,
            'retry_after_seconds' => $this instanceof RateLimitException ? $this->retryAfterSeconds : null,
        ];
    }

    /**
     * @param list<mixed> $candidates
     * @return list<array<string, mixed>>
     */
    private static function candidateList(array $candidates): array
    {
        $out = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                return [];
            }
            $row = [];
            foreach ($candidate as $key => $value) {
                $row[(string) $key] = $value;
            }
            $out[] = $row;
        }

        return $out;
    }
}
