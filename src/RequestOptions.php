<?php

declare(strict_types=1);

namespace Klaracase;

use Klaracase\Exception\ConfigurationException;

/**
 * Settings for one call, or the defaults of a client. A null field means "use the client's setting".
 *
 * ```php
 * $client->decisions->search('Eigenbedarf', options: RequestOptions::with(timeout: 5.0, maxRetries: 0));
 * ```
 *
 * The timeout reaches the HTTP client only when it is a Guzzle client (the bundled default is one). PSR-18 has no
 * way to pass a timeout per request, so with any other client the setting of that client stays in force.
 */
final readonly class RequestOptions
{
    /**
     * @param float|null $timeout Seconds for this call. Guzzle clients only.
     * @param int|null $maxRetries Retries on HTTP 429 and 503 for this call, 0 or more.
     * @param array<string, string> $extraHeaders Headers added to the request. `Authorization` cannot be set here.
     * @param array<string, scalar|list<scalar>> $extraQuery Query parameters added to the request. They win over a parameter of the same name.
     *
     * @throws ConfigurationException When a value is out of range or a header is not allowed.
     */
    public function __construct(
        public ?float $timeout = null,
        public ?int $maxRetries = null,
        public array $extraHeaders = [],
        public array $extraQuery = [],
    ) {
        if ($timeout !== null && $timeout <= 0.0) {
            throw new ConfigurationException('`timeout` must be greater than zero.');
        }
        if ($maxRetries !== null && $maxRetries < 0) {
            throw new ConfigurationException('`maxRetries` must be 0 or more.');
        }
        foreach (array_keys($extraHeaders) as $name) {
            if (strtolower((string) $name) === 'authorization') {
                throw new ConfigurationException('The Authorization header comes from the token. Do not set it in `extraHeaders`.');
            }
        }
    }

    /**
     * @param float|null $timeout Seconds for this call. Guzzle clients only.
     * @param int|null $maxRetries Retries on HTTP 429 and 503 for this call, 0 or more.
     * @param array<string, string> $extraHeaders Headers added to the request.
     * @param array<string, scalar|list<scalar>> $extraQuery Query parameters added to the request.
     */
    public static function with(
        ?float $timeout = null,
        ?int $maxRetries = null,
        array $extraHeaders = [],
        array $extraQuery = [],
    ): self {
        return new self($timeout, $maxRetries, $extraHeaders, $extraQuery);
    }

    /**
     * These options with the set fields of `$override` on top. Headers and query parameters are merged by name.
     */
    public function merge(?self $override): self
    {
        if ($override === null) {
            return $this;
        }

        return new self(
            $override->timeout ?? $this->timeout,
            $override->maxRetries ?? $this->maxRetries,
            self::mergeHeaders($this->extraHeaders, $override->extraHeaders),
            [...$this->extraQuery, ...$override->extraQuery],
        );
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $over
     * @return array<string, string>
     */
    private static function mergeHeaders(array $base, array $over): array
    {
        $merged = [];
        foreach ([$base, $over] as $set) {
            foreach ($set as $name => $value) {
                foreach (array_keys($merged) as $existing) {
                    if (strcasecmp($existing, (string) $name) === 0) {
                        unset($merged[$existing]);
                    }
                }
                $merged[(string) $name] = $value;
            }
        }

        return $merged;
    }
}
