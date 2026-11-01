<?php

declare(strict_types=1);

namespace Klaracase;

use Klaracase\Exception\ConfigurationException;
use Klaracase\Http\DeprecationNotice;
use Klaracase\Support\Clock;
use Klaracase\Support\Sleeper;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds a `Klaracase` client one setting at a time. Every `with...` method returns a changed copy, so a
 * half-built factory can be shared and extended.
 *
 * ```php
 * $client = Klaracase::factory()
 *     ->withToken(getenv('KLARACASE_API_TOKEN') ?: '')
 *     ->withTimeout(10.0)
 *     ->withMaxRetries(1)
 *     ->withUserAgentSuffix('my-app/2.1')
 *     ->withLogger($logger)
 *     ->make();
 * ```
 */
final class Factory
{
    private ?string $token = null;

    private string $baseUrl = Klaracase::DEFAULT_BASE_URL;

    private float $timeout = 30.0;

    private int $maxRetries = 2;

    private ?string $userAgentSuffix = null;

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    /** @var (callable(DeprecationNotice): void)|null */
    private $onDeprecation = null;

    private ?Sleeper $sleeper = null;

    private ?Clock $clock = null;

    private ?LoggerInterface $logger = null;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<callable(RequestInterface, callable(RequestInterface): ResponseInterface): ResponseInterface> */
    private array $middleware = [];

    /**
     * The personal access token. Without it the `KLARACASE_API_TOKEN` environment variable is read.
     */
    public function withToken(string $token): self
    {
        return $this->with(static function (self $f) use ($token): void {
            $f->token = $token;
        });
    }

    /**
     * The API root. The default is the public API.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        return $this->with(static function (self $f) use ($baseUrl): void {
            $f->baseUrl = $baseUrl;
        });
    }

    /**
     * Any PSR-18 client. Without one, Guzzle is used when installed, else the client php-http/discovery finds.
     */
    public function withHttpClient(ClientInterface $client): self
    {
        return $this->with(static function (self $f) use ($client): void {
            $f->httpClient = $client;
        });
    }

    public function withRequestFactory(RequestFactoryInterface $factory): self
    {
        return $this->with(static function (self $f) use ($factory): void {
            $f->requestFactory = $factory;
        });
    }

    public function withStreamFactory(StreamFactoryInterface $factory): self
    {
        return $this->with(static function (self $f) use ($factory): void {
            $f->streamFactory = $factory;
        });
    }

    /**
     * Seconds before a request times out. It applies to the Guzzle client the SDK builds itself; a client you pass
     * with `withHttpClient()` keeps its own timeouts. Per-call: `RequestOptions::with(timeout: ...)`.
     */
    public function withTimeout(float $seconds): self
    {
        return $this->with(static function (self $f) use ($seconds): void {
            $f->timeout = $seconds;
        });
    }

    /**
     * Retries on HTTP 429 and 503 (and, for GET, on a network failure). 0 turns retries off.
     */
    public function withMaxRetries(int $retries): self
    {
        return $this->with(static function (self $f) use ($retries): void {
            $f->maxRetries = $retries;
        });
    }

    /**
     * A header sent with every request. `Authorization` cannot be set; it comes from the token.
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->with(static function (self $f) use ($name, $value): void {
            $f->headers[$name] = $value;
        });
    }

    /**
     * A PSR-3 logger. It gets a debug line for each retry and a warning for a deprecation notice. It never gets
     * the token, a header value or a body.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(static function (self $f) use ($logger): void {
            $f->logger = $logger;
        });
    }

    /**
     * Replaces the real sleep between retries and polls (tests).
     */
    public function withSleeper(Sleeper $sleeper): self
    {
        return $this->with(static function (self $f) use ($sleeper): void {
            $f->sleeper = $sleeper;
        });
    }

    /**
     * Replaces the real clock used for durations and the document wait (tests).
     */
    public function withClock(Clock $clock): self
    {
        return $this->with(static function (self $f) use ($clock): void {
            $f->clock = $clock;
        });
    }

    /**
     * Text appended to the User-Agent, after a space. Name your application here.
     */
    public function withUserAgentSuffix(?string $suffix): self
    {
        return $this->with(static function (self $f) use ($suffix): void {
            $f->userAgentSuffix = $suffix;
        });
    }

    /**
     * Called once per process when an answer carries a `Deprecation` or `Sunset` header.
     * The default raises an E_USER_DEPRECATED notice.
     *
     * @param callable(DeprecationNotice): void $handler
     */
    public function withDeprecationHandler(callable $handler): self
    {
        return $this->with(static function (self $f) use ($handler): void {
            $f->onDeprecation = $handler;
        });
    }

    /**
     * Wraps every HTTP attempt: `fn (RequestInterface $request, callable $next): ResponseInterface`. The first
     * middleware added is the outermost. Use it for tracing or a custom header per attempt.
     *
     * @param callable(RequestInterface, callable(RequestInterface): ResponseInterface): ResponseInterface $middleware
     */
    public function withMiddleware(callable $middleware): self
    {
        return $this->with(static function (self $f) use ($middleware): void {
            $f->middleware[] = $middleware;
        });
    }

    /**
     * @throws ConfigurationException When the token is missing or empty, the base URL is invalid, or no HTTP client is found.
     */
    public function make(): Klaracase
    {
        return new Klaracase(
            token: $this->token,
            baseUrl: $this->baseUrl,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            userAgentSuffix: $this->userAgentSuffix,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            onDeprecation: $this->onDeprecation,
            sleeper: $this->sleeper,
            clock: $this->clock,
            logger: $this->logger,
            headers: $this->headers,
            middleware: $this->middleware,
        );
    }

    /**
     * @param callable(self): void $change
     */
    private function with(callable $change): self
    {
        $copy = clone $this;
        $change($copy);

        return $copy;
    }
}
