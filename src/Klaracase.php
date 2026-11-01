<?php

declare(strict_types=1);

namespace Klaracase;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Exception\NotFoundException as DiscoveryNotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Klaracase\Exception\ConfigurationException;
use Klaracase\Http\DeprecationNotice;
use Klaracase\Http\DeprecationNotifier;
use Klaracase\Http\HttpTransport;
use Klaracase\Support\Clock;
use Klaracase\Support\NativeSleeper;
use Klaracase\Support\ProvidesResources;
use Klaracase\Support\ResourceSet;
use Klaracase\Support\Sleeper;
use Klaracase\Support\SystemClock;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client for the Klaracase REST API v1.
 *
 * Klaracase covers German law only. It is a research tool. It gives no legal advice and does not replace a
 * lawyer's review. A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the
 * citation is fabricated.
 *
 * ```php
 * $client = Klaracase\Klaracase::client('klc_your_token_here');
 * $result = $client->decisions->search('Eigenbedarf Kündigung', court: 'BGH');
 * ```
 *
 * For more settings use `Klaracase::factory()`.
 */
final class Klaracase implements Client
{
    use ProvidesResources;

    public const VERSION = '0.1.0';

    public const DEFAULT_BASE_URL = 'https://klaracase.de/api/v1';

    public const TOKEN_ENV_VAR = 'KLARACASE_API_TOKEN';

    /**
     * @param string|null $token A personal access token. Falls back to the `KLARACASE_API_TOKEN` environment variable.
     * @param string $baseUrl API root, without a trailing slash.
     * @param float $timeout Seconds. Applied to the default Guzzle client only; a client you inject keeps its own timeouts.
     * @param int $maxRetries Retries on HTTP 429 and 503 (and, for GET, on a transport error).
     * @param string|null $userAgentSuffix Appended to the User-Agent, after a space. Name your app here.
     * @param ClientInterface|null $httpClient Any PSR-18 client. Found through php-http/discovery when null.
     * @param RequestFactoryInterface|null $requestFactory Any PSR-17 request factory. Found through discovery when null.
     * @param StreamFactoryInterface|null $streamFactory Any PSR-17 stream factory. Found through discovery when null.
     * @param (callable(DeprecationNotice): void)|null $onDeprecation Called once per process when an answer carries a `Deprecation` or `Sunset` header. The default raises an E_USER_DEPRECATED notice.
     * @param Sleeper|null $sleeper Replaces the real sleep (tests).
     * @param Clock|null $clock Replaces the real clock (tests).
     * @param LoggerInterface|null $logger PSR-3 logger. Gets a debug line per retry and a warning per deprecation. Never gets the token, headers or bodies.
     * @param array<string, string> $headers Headers added to every request. `Authorization` cannot be set here.
     * @param list<callable(RequestInterface, callable(RequestInterface): ResponseInterface): ResponseInterface> $middleware Wrap every HTTP attempt, first entry outermost.
     *
     * @throws ConfigurationException When the token is missing or empty, the base URL is invalid, or no HTTP client is found.
     */
    public function __construct(
        ?string $token = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?string $userAgentSuffix = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $onDeprecation = null,
        ?Sleeper $sleeper = null,
        ?Clock $clock = null,
        ?LoggerInterface $logger = null,
        array $headers = [],
        array $middleware = [],
    ) {
        $token ??= self::tokenFromEnvironment();
        if ($token === null || trim($token) === '') {
            throw new ConfigurationException(
                'No Klaracase API token. Pass `token` or set the ' . self::TOKEN_ENV_VAR . ' environment variable. '
                . 'Create a token at https://klaracase.de/settings/api-tokens.',
            );
        }
        if ($maxRetries < 0) {
            throw new ConfigurationException('`maxRetries` must be 0 or more.');
        }
        if ($timeout <= 0.0) {
            throw new ConfigurationException('`timeout` must be greater than zero.');
        }

        $sleeper ??= new NativeSleeper();
        $clock ??= new SystemClock();
        $logger ??= new NullLogger();

        $transport = new HttpTransport(
            http: $httpClient ?? self::discoverHttpClient($timeout),
            requestFactory: $requestFactory ?? self::discover(static fn (): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory(), 'PSR-17 request factory'),
            streamFactory: $streamFactory ?? self::discover(static fn (): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory(), 'PSR-17 stream factory'),
            baseUrl: self::normaliseBaseUrl($baseUrl),
            token: trim($token),
            userAgent: self::userAgent($userAgentSuffix),
            maxRetries: $maxRetries,
            sleeper: $sleeper,
            clock: $clock,
            deprecations: new DeprecationNotifier($onDeprecation, $logger),
            logger: $logger,
            defaults: new RequestOptions(extraHeaders: $headers),
            middleware: array_values($middleware),
        );
        $resources = new ResourceSet($transport, $sleeper, $clock);
        $this->apiTransport = $resources->transport;
        $this->decisions = $resources->decisions;
        $this->norms = $resources->norms;
        $this->datapackages = $resources->datapackages;
        $this->citationChecks = $resources->citationChecks;
        $this->citationCheckDocuments = $resources->citationCheckDocuments;
        $this->corpus = $resources->corpus;
    }

    /**
     * A client with the defaults: the given token, the public API, two retries, a 30 second timeout.
     *
     * @throws ConfigurationException When the token is empty or no HTTP client is found.
     */
    public static function client(string $token): self
    {
        return self::factory()->withToken($token)->make();
    }

    /**
     * Start a client builder. Every `with...` call returns a new builder; `make()` builds the client.
     */
    public static function factory(): Factory
    {
        return new Factory();
    }

    /**
     * Build a client from the `KLARACASE_API_TOKEN` environment variable.
     *
     * @param (callable(DeprecationNotice): void)|null $onDeprecation
     *
     * @throws ConfigurationException When the variable is unset or empty.
     */
    public static function fromEnv(
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?string $userAgentSuffix = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $onDeprecation = null,
        ?Sleeper $sleeper = null,
        ?Clock $clock = null,
    ): self {
        $factory = self::factory()
            ->withBaseUrl($baseUrl)
            ->withTimeout($timeout)
            ->withMaxRetries($maxRetries)
            ->withUserAgentSuffix($userAgentSuffix);
        if ($httpClient !== null) {
            $factory = $factory->withHttpClient($httpClient);
        }
        if ($requestFactory !== null) {
            $factory = $factory->withRequestFactory($requestFactory);
        }
        if ($streamFactory !== null) {
            $factory = $factory->withStreamFactory($streamFactory);
        }
        if ($onDeprecation !== null) {
            $factory = $factory->withDeprecationHandler($onDeprecation);
        }
        if ($sleeper !== null) {
            $factory = $factory->withSleeper($sleeper);
        }
        if ($clock !== null) {
            $factory = $factory->withClock($clock);
        }

        return $factory->make();
    }

    /**
     * Forget that the deprecation hook ran. For tests: the hook fires once per process.
     */
    public static function resetDeprecationWarnings(): void
    {
        DeprecationNotifier::reset();
    }

    private static function tokenFromEnvironment(): ?string
    {
        $fromGetenv = getenv(self::TOKEN_ENV_VAR);
        if (is_string($fromGetenv) && $fromGetenv !== '') {
            return $fromGetenv;
        }
        foreach ([$_ENV, $_SERVER] as $source) {
            $value = $source[self::TOKEN_ENV_VAR] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function normaliseBaseUrl(string $baseUrl): string
    {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new ConfigurationException('`baseUrl` must be an absolute http or https URL, for example ' . self::DEFAULT_BASE_URL . '.');
        }

        return rtrim($baseUrl, '/');
    }

    private static function userAgent(?string $suffix): string
    {
        $agent = 'klaracase-php/' . self::VERSION . ' PHP/' . PHP_VERSION;
        $suffix = $suffix === null ? '' : trim(str_replace(["\r", "\n"], ' ', $suffix));

        return $suffix === '' ? $agent : $agent . ' ' . $suffix;
    }

    private static function discoverHttpClient(float $timeout): ClientInterface
    {
        if (class_exists(GuzzleClient::class)) {
            return new GuzzleClient([
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        }

        return self::discover(static fn (): ClientInterface => Psr18ClientDiscovery::find(), 'PSR-18 HTTP client');
    }

    /**
     * @template T of object
     *
     * @param callable(): T $find
     * @return T
     */
    private static function discover(callable $find, string $what): object
    {
        try {
            return $find();
        } catch (DiscoveryNotFoundException $e) {
            throw new ConfigurationException(
                sprintf('No %s found. Install one, for example `composer require guzzlehttp/guzzle nyholm/psr7`, or pass it to the constructor.', $what),
                0,
                $e,
            );
        }
    }
}
