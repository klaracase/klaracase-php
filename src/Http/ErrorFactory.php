<?php

declare(strict_types=1);

namespace Klaracase\Http;

use Klaracase\Exception\ApiException;
use Klaracase\Exception\AuthenticationException;
use Klaracase\Exception\ConflictException;
use Klaracase\Exception\ForbiddenException;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\NotFoundException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\ServiceUnavailableException;
use Klaracase\Exception\TokenLimitReachedException;
use Klaracase\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns an error answer into the exception for its HTTP status.
 *
 * A body is a problem when it is a JSON object with a string `code`. Any other body becomes the class for its
 * status with `code` `unknown` and the raw text, cut to 1,000 characters, as `detail`. The HTTP status decides
 * the class, the body is not consulted for it.
 *
 * @internal
 */
final class ErrorFactory
{
    private const PROBLEM_MEMBERS = ['type', 'title', 'status', 'detail', 'code'];

    private const DETAIL_LIMIT = 1000;

    public static function fromResponse(ResponseInterface $response, string $body): KlaracaseException
    {
        $status = $response->getStatusCode();
        $release = $response->getHeaderLine('X-Klaracase-Api-Release');
        $apiRelease = $release === '' ? null : $release;
        $decoded = json_decode($body, true);

        if (is_array($decoded) && ! array_is_list($decoded) && is_string($decoded['code'] ?? null)) {
            $extra = [];
            foreach ($decoded as $key => $value) {
                if (! in_array($key, self::PROBLEM_MEMBERS, true)) {
                    $extra[(string) $key] = $value;
                }
            }
            $type = is_string($decoded['type'] ?? null) ? $decoded['type'] : null;
            $title = is_string($decoded['title'] ?? null) ? $decoded['title'] : null;
            $detail = is_string($decoded['detail'] ?? null) ? $decoded['detail'] : null;
            $code = $decoded['code'];
            $problem = [];
            foreach ($decoded as $key => $value) {
                $problem[(string) $key] = $value;
            }
        } else {
            $problem = [];
            $extra = [];
            $type = null;
            $title = null;
            $detail = mb_substr($body, 0, self::DETAIL_LIMIT, 'UTF-8');
            $code = 'unknown';
        }

        $message = $detail !== null && $detail !== '' ? $detail : ($title ?? sprintf('Klaracase API error (HTTP %d).', $status));

        return self::build($status, $message, $type, $title, $detail, $code, $apiRelease, $extra, self::retryAfterHeader($response), self::headers($response), $problem);
    }

    /**
     * @param array<string, mixed> $extra
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $problem
     */
    private static function build(int $status, string $message, ?string $type, ?string $title, ?string $detail, string $code, ?string $apiRelease, array $extra, ?int $retryAfterHeader, array $headers, array $problem): KlaracaseException
    {
        $args = [
            'message' => $message,
            'status' => $status,
            'type' => $type,
            'title' => $title,
            'detail' => $detail,
            'errorCode' => $code,
            'apiRelease' => $apiRelease,
            'extra' => $extra,
            'headers' => $headers,
            'body' => $problem,
        ];

        return match ($status) {
            401 => new AuthenticationException(...$args),
            402 => $code === 'token_limit_reached' ? new TokenLimitReachedException(...$args) : new InsufficientCreditsException(...$args),
            403 => new ForbiddenException(...$args),
            404 => new NotFoundException(...$args),
            409 => new ConflictException(...$args),
            422 => new ValidationException(...$args),
            429 => new RateLimitException(...$args, retryAfterHeader: $retryAfterHeader),
            503 => new ServiceUnavailableException(...$args),
            default => new ApiException(...$args),
        };
    }

    /**
     * The exception for a problem body that is already decoded. Used by the test double.
     *
     * @param array<string, mixed> $problem
     * @param array<string, list<string>> $headers
     */
    public static function fromProblem(int $status, array $problem, array $headers = []): KlaracaseException
    {
        $extra = array_diff_key($problem, array_flip(self::PROBLEM_MEMBERS));
        $type = is_string($problem['type'] ?? null) ? $problem['type'] : null;
        $title = is_string($problem['title'] ?? null) ? $problem['title'] : null;
        $detail = is_string($problem['detail'] ?? null) ? $problem['detail'] : null;
        $code = is_string($problem['code'] ?? null) ? $problem['code'] : 'unknown';
        $message = $detail !== null && $detail !== '' ? $detail : ($title ?? sprintf('Klaracase API error (HTTP %d).', $status));
        $header = $headers['retry-after'][0] ?? null;
        $retryAfter = is_string($header) && ctype_digit($header) ? (int) $header : null;
        $release = $headers['x-klaracase-api-release'][0] ?? null;

        return self::build($status, $message, $type, $title, $detail, $code, $release, $extra, $retryAfter, $headers, $problem);
    }

    /**
     * Every response header with a lower-case name. The `authorization` header is never copied.
     *
     * @return array<string, list<string>>
     */
    public static function headers(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $lower = strtolower((string) $name);
            if ($lower !== 'authorization') {
                $headers[$lower] = array_values($values);
            }
        }

        return $headers;
    }

    /**
     * The `Retry-After` header as whole seconds. An HTTP date is not read.
     */
    public static function retryAfterHeader(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }
}
