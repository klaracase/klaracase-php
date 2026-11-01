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
use Klaracase\Http\Transport;
use Klaracase\Resource\CitationCheckDocuments;
use Klaracase\Resource\CitationChecks;
use Klaracase\Resource\Corpus;
use Klaracase\Resource\Datapackages;
use Klaracase\Resource\Decisions;
use Klaracase\Resource\Norms;
use Klaracase\Response\SearchResult;
use Klaracase\Support\Clock;
use Klaracase\Support\NativeSleeper;
use Klaracase\Support\Sleeper;
use Klaracase\Support\SystemClock;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Client for the Klaracase REST API v1.
 *
 * Klaracase covers German law only. It is a research tool. It gives no legal advice and does not replace a
 * lawyer's review. A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the
 * citation is fabricated.
 *
 * ```php
 * $client = new Klaracase\Klaracase(token: 'klc_your_token_here');
 * $result = $client->decisions->search('Eigenbedarf Kündigung', court: 'BGH');
 * ```
 */
final class Klaracase
{
    public const VERSION = '0.1.0';

    public const DEFAULT_BASE_URL = 'https://klaracase.de/api/v1';

    public const TOKEN_ENV_VAR = 'KLARACASE_API_TOKEN';

    public readonly Decisions $decisions;

    public readonly Norms $norms;

    public readonly Datapackages $datapackages;

    public readonly CitationChecks $citationChecks;

    public readonly CitationCheckDocuments $citationCheckDocuments;

    public readonly Corpus $corpus;

    private readonly Transport $transport;

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

        $this->transport = new Transport(
            http: $httpClient ?? self::discoverHttpClient($timeout),
            requestFactory: $requestFactory ?? self::discover(static fn (): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory(), 'PSR-17 request factory'),
            streamFactory: $streamFactory ?? self::discover(static fn (): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory(), 'PSR-17 stream factory'),
            baseUrl: self::normaliseBaseUrl($baseUrl),
            token: trim($token),
            userAgent: self::userAgent($userAgentSuffix),
            maxRetries: $maxRetries,
            sleeper: $sleeper ?? new NativeSleeper(),
            clock: $clock ?? new SystemClock(),
            deprecations: new DeprecationNotifier($onDeprecation),
        );

        $sleeper ??= new NativeSleeper();
        $clock ??= new SystemClock();

        $this->decisions = new Decisions($this->transport);
        $this->norms = new Norms($this->transport);
        $this->datapackages = new Datapackages($this->transport);
        $this->citationChecks = new CitationChecks($this->transport);
        $this->citationCheckDocuments = new CitationCheckDocuments($this->transport, $sleeper, $clock);
        $this->corpus = new Corpus($this->transport);
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
        return new self(
            token: null,
            baseUrl: $baseUrl,
            timeout: $timeout,
            maxRetries: $maxRetries,
            userAgentSuffix: $userAgentSuffix,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            onDeprecation: $onDeprecation,
            sleeper: $sleeper,
            clock: $clock,
        );
    }

    /**
     * Search statutes and decisions together. Costs 1 credit.
     *
     * @param string $query 2 to 500 characters, German legal-concept phrasing.
     * @param string|null $jurisdiction DE, EU or XI. Narrows the laws section only.
     * @param string|null $court Court name filter on the decisions section.
     * @param string|null $branch ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt.
     * @param string|null $dateFrom Earliest decision date, `YYYY-MM-DD`.
     * @param string|null $dateTo Latest decision date, `YYYY-MM-DD`.
     * @param string|null $mode keyword, semantic or hybrid (the server default).
     * @param string|null $documentType Urteil, Beschluss, Verfügung, Sonstige, Gerichtsbescheid or Schlussanträge.
     * @param string|null $citedNorm One canonical statute token, for example `§ 906 BGB`.
     * @param list<string>|null $citedStatutes Any of StGB, StPO, InsO, DSGVO, GDPR, BDSG, BauGB, BBauG, BauNVO, StVG, StVO.
     * @param int|null $limit Results per section, at most 10.
     * @param bool|null $bridge Whether the server may add curated legal-register terms to the query.
     */
    public function search(
        string $query,
        ?string $jurisdiction = null,
        ?string $court = null,
        ?string $branch = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $mode = null,
        ?string $documentType = null,
        ?string $citedNorm = null,
        ?array $citedStatutes = null,
        ?int $limit = null,
        ?bool $bridge = null,
    ): SearchResult {
        [$body, $meta] = $this->transport->send('GET', '/search', [
            'query' => $query,
            'jurisdiction' => $jurisdiction,
            'court' => $court,
            'branch' => $branch,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'document_type' => $documentType,
            'cited_norm' => $citedNorm,
            'cited_statutes' => $citedStatutes,
            'limit' => $limit,
            'bridge' => $bridge,
        ]);

        return new SearchResult($body, $meta);
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
