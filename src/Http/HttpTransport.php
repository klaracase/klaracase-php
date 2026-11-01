<?php

declare(strict_types=1);

namespace Klaracase\Http;

use Closure;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Klaracase\Exception\ApiException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\TimeoutException;
use Klaracase\Exception\TransportException;
use Klaracase\Exception\ValidationException;
use Klaracase\RequestOptions;
use Klaracase\Response\ResponseMeta;
use Klaracase\Support\Clock;
use Klaracase\Support\Sleeper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Sends one API call over a PSR-18 client: builds the request, applies the retry policy, reads the answer.
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
 * The logger gets one debug line per retry (method, path, status, attempt, wait). It never gets the token, a
 * header value or a body.
 *
 * @internal
 */
final class HttpTransport implements Transport
{
    /** A server-stated wait above this many seconds is raised, not slept. */
    public const MAX_STATED_WAIT = 30.0;

    private const BACKOFF_BASE = 0.5;

    private const BACKOFF_CAP = 8.0;

    private const SYMFONY_TIMEOUT = 'Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface';

    private const CURL_TIMEOUT_ERRNO = 28;

    /**
     * @param list<callable(RequestInterface, callable(RequestInterface): ResponseInterface): ResponseInterface> $middleware
     *        Each wraps one HTTP attempt, first entry outermost.
     */
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
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly RequestOptions $defaults = new RequestOptions(),
        private readonly array $middleware = [],
    ) {
    }

    public function send(Request $request, RequestOptions $options): Response
    {
        $effective = $this->defaults->merge($options);
        $maxRetries = $effective->maxRetries ?? $this->maxRetries;
        $url = $this->baseUrl . $request->path . self::queryString([...$request->query, ...$effective->extraQuery]);
        $body = null;
        $contentType = null;
        if ($request->json !== null) {
            $body = self::encodeJson($request->json);
            $contentType = 'application/json';
        } elseif ($request->multipart !== null) {
            $body = $request->multipart->body;
            $contentType = $request->multipart->contentType;
        }

        $attempt = 0;
        $waited = 0.0;
        while (true) {
            $psr = $this->buildRequest($request->method, $url, $body, $contentType, $effective->extraHeaders);
            $started = $this->clock->now();

            try {
                $response = $this->dispatch($psr, $effective->timeout);
            } catch (ClientExceptionInterface|GuzzleException $e) {
                if ($request->method === 'GET' && $attempt < $maxRetries) {
                    $wait = self::backoff($attempt);
                    $this->logRetry($request, null, $attempt + 1, $wait);
                    $this->sleeper->sleep($wait);
                    $waited += $wait;
                    $attempt++;

                    continue;
                }

                throw self::isTimeout($e) ? TimeoutException::wrap($e) : TransportException::wrap($e);
            }

            $duration = max(0.0, $this->clock->now() - $started);
            $this->deprecations->inspect($response, $request->method, $request->path);
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();

            if ($status >= 200 && $status < 300) {
                $meta = new ResponseMeta($status, self::release($response), $duration, self::headers($response), $attempt + 1, $waited);

                return new Response(self::decodeSuccess($raw, $response), $meta);
            }

            $error = ErrorFactory::fromResponse($response, $raw);
            if (($status === 429 || $status === 503) && $attempt < $maxRetries) {
                $wait = self::statedWait($error, $response);
                if ($wait === null) {
                    $wait = self::backoff($attempt);
                } elseif ($wait > self::MAX_STATED_WAIT) {
                    throw $error;
                }
                $this->logRetry($request, $status, $attempt + 1, $wait);
                $this->sleeper->sleep($wait);
                $waited += $wait;
                $attempt++;

                continue;
            }

            throw $error;
        }
    }

    /**
     * Runs one HTTP attempt through the middleware. A per-call timeout is passed to a Guzzle client; any other
     * PSR-18 client has no hook for it and keeps its own setting.
     */
    private function dispatch(RequestInterface $request, ?float $timeout): ResponseInterface
    {
        $http = $this->http;
        /** @var Closure(RequestInterface): ResponseInterface $send */
        $send = $timeout !== null && $http instanceof GuzzleClientInterface
            ? static fn (RequestInterface $r): ResponseInterface => $http->send($r, [
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'allow_redirects' => false,
                'http_errors' => false,
            ])
            : static fn (RequestInterface $r): ResponseInterface => $http->sendRequest($r);

        foreach (array_reverse($this->middleware) as $layer) {
            $next = $send;
            $send = static fn (RequestInterface $r): ResponseInterface => $layer($r, $next);
        }

        return $send($request);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function buildRequest(string $method, string $url, ?string $body, ?string $contentType, array $extraHeaders): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($extraHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request = $request
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

    private function logRetry(Request $request, ?int $status, int $attempt, float $wait): void
    {
        $this->logger->debug('Klaracase request will be retried.', [
            'method' => $request->method,
            'path' => $request->path,
            'status' => $status,
            'attempt' => $attempt,
            'wait' => $wait,
        ]);
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
            headers: ErrorFactory::headers($response),
        );
    }

    private static function release(ResponseInterface $response): ?string
    {
        $release = $response->getHeaderLine('X-Klaracase-Api-Release');

        return $release === '' ? null : $release;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function headers(ResponseInterface $response): array
    {
        return ErrorFactory::headers($response);
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

    /**
     * Whether a failed attempt was a timeout. A request the client refused to build is never one. Then, along the
     * chain of previous exceptions: a Symfony timeout exception (matched by name, Symfony is not a dependency), a
     * cURL error 28 (Guzzle's handler context, or the text), and last the wording of the message.
     */
    private static function isTimeout(Throwable $e): bool
    {
        if ($e instanceof RequestExceptionInterface) {
            return false;
        }
        for ($link = $e; $link !== null; $link = $link->getPrevious()) {
            if ($link instanceof (self::SYMFONY_TIMEOUT)) {
                return true;
            }
            if (method_exists($link, 'getHandlerContext')) {
                $context = $link->getHandlerContext();
                if (is_array($context) && ($context['errno'] ?? null) === self::CURL_TIMEOUT_ERRNO) {
                    return true;
                }
            }
            if (str_contains($link->getMessage(), 'cURL error 28')) {
                return true;
            }
        }

        return preg_match('/timed? ?out|timeout/i', $e->getMessage()) === 1;
    }
}
