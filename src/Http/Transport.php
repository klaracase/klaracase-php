<?php

declare(strict_types=1);

namespace Klaracase\Http;

use JsonException;
use Klaracase\Exception\ApiException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\TimeoutException;
use Klaracase\Exception\TransportException;
use Klaracase\Exception\ValidationException;
use Klaracase\Response\ResponseMeta;
use Klaracase\Support\Clock;
use Klaracase\Support\Sleeper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends one API call: builds the request, applies the retry policy, reads the answer.
 *
 * Retry policy:
 * - HTTP 429 and 503 are retried up to `$maxRetries` times, for GET and POST alike: both statuses
 *   guarantee that nothing ran and nothing was charged.
 * - The wait is `retry_after_seconds` from the body, else the `Retry-After` header, else exponential
 *   backoff with full jitter (0.5 s, 1 s, 2 s, ... capped at 8 s). A stated wait above 30 seconds is not
 *   slept: the error is raised at once.
 * - A transport error (no answer) is retried for GET only.
 * - Nothing else is retried: not 402, not other 4xx, not 500 or 502.
 *
 * @internal
 */
final class Transport
{
    /** A server-stated wait above this many seconds is raised, not slept. */
    public const MAX_STATED_WAIT = 30.0;

    private const BACKOFF_BASE = 0.5;

    private const BACKOFF_CAP = 8.0;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $userAgent,
        private readonly int $maxRetries,
        private readonly Sleeper $sleeper,
        private readonly Clock $clock,
        private readonly DeprecationNotifier $deprecations,
    ) {
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query Null values and empty lists are not sent.
     * @param array<string, mixed>|null $json
     * @return array{0: array<array-key, mixed>, 1: ResponseMeta} The decoded body and the answer's meta.
     */
    public function send(string $method, string $path, array $query = [], ?array $json = null, ?Multipart $multipart = null): array
    {
        $url = $this->baseUrl . $path . self::queryString($query);
        $body = null;
        $contentType = null;
        if ($json !== null) {
            $body = self::encodeJson($json);
            $contentType = 'application/json';
        } elseif ($multipart !== null) {
            $body = $multipart->body;
            $contentType = $multipart->contentType;
        }

        $attempt = 0;
        while (true) {
            $request = $this->buildRequest($method, $url, $body, $contentType);
            $started = $this->clock->now();

            try {
                $response = $this->http->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($method === 'GET' && $attempt < $this->maxRetries) {
                    $this->sleeper->sleep(self::backoff($attempt));
                    $attempt++;

                    continue;
                }

                throw self::isTimeout($e) ? TimeoutException::wrap($e) : TransportException::wrap($e);
            }

            $duration = max(0.0, $this->clock->now() - $started);
            $this->deprecations->inspect($response, $method, $path);
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();

            if ($status >= 200 && $status < 300) {
                return [self::decodeSuccess($raw, $response), new ResponseMeta($status, self::release($response), $duration)];
            }

            $error = ErrorFactory::fromResponse($response, $raw);
            if (($status === 429 || $status === 503) && $attempt < $this->maxRetries) {
                $wait = self::statedWait($error, $response);
                if ($wait === null) {
                    $wait = self::backoff($attempt);
                } elseif ($wait > self::MAX_STATED_WAIT) {
                    throw $error;
                }
                $this->sleeper->sleep($wait);
                $attempt++;

                continue;
            }

            throw $error;
        }
    }

    private function buildRequest(string $method, string $url, ?string $body, ?string $contentType): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($body !== null && $contentType !== null) {
            $request = $request
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    private static function queryString(array $query): string
    {
        $pairs = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode($name . '[]') . '=' . rawurlencode(self::scalar($item));
                }

                continue;
            }
            $pairs[] = rawurlencode($name) . '=' . rawurlencode(self::scalar($value));
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }

    private static function scalar(bool|int|float|string $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }

    /**
     * @param array<string, mixed> $json
     */
    private static function encodeJson(array $json): string
    {
        try {
            return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new ValidationException(
                'The request body cannot be encoded as JSON (is every string valid UTF-8?): ' . $e->getMessage(),
                errorCode: 'client_validation',
                previous: $e,
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeSuccess(string $raw, ResponseInterface $response): array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && ($decoded === [] || ! array_is_list($decoded))) {
            return $decoded;
        }

        throw new ApiException(
            'Klaracase answered with a success status but the body is not a JSON object.',
            status: $response->getStatusCode(),
            detail: mb_substr($raw, 0, 1000, 'UTF-8'),
            errorCode: 'invalid_response',
            apiRelease: self::release($response),
        );
    }

    private static function release(ResponseInterface $response): ?string
    {
        $release = $response->getHeaderLine('X-Klaracase-Api-Release');

        return $release === '' ? null : $release;
    }

    /**
     * Seconds the server asks us to wait: the body member first, then the header.
     */
    private static function statedWait(KlaracaseException $error, ResponseInterface $response): ?float
    {
        $fromBody = $error->extra['retry_after_seconds'] ?? null;
        if ((is_int($fromBody) || is_float($fromBody)) && $fromBody >= 0) {
            return (float) $fromBody;
        }
        $fromHeader = ErrorFactory::retryAfterHeader($response);

        return $fromHeader === null ? null : (float) $fromHeader;
    }

    /**
     * Full jitter: a random wait between 0 and min(8, 0.5 * 2^attempt) seconds.
     */
    private static function backoff(int $attempt): float
    {
        $ceiling = min(self::BACKOFF_CAP, self::BACKOFF_BASE * (2 ** $attempt));

        return $ceiling * (random_int(0, 1_000_000) / 1_000_000);
    }

    private static function isTimeout(ClientExceptionInterface $e): bool
    {
        if (method_exists($e, 'getHandlerContext')) {
            $context = $e->getHandlerContext();
            if (is_array($context) && ($context['errno'] ?? null) === 28) {
                return true;
            }
        }

        return preg_match('/timed? ?out|timeout/i', $e->getMessage()) === 1;
    }
}
