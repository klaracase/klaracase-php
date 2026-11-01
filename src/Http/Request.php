<?php

declare(strict_types=1);

namespace Klaracase\Http;

/**
 * One API call, before it is turned into an HTTP request.
 *
 * @internal
 */
final readonly class Request
{
    /**
     * @param string $method GET or POST.
     * @param string $path Path below the base URL, already encoded, starting with a slash.
     * @param array<string, scalar|list<scalar>|null> $query Null values and empty lists are not sent.
     * @param array<string, mixed>|null $json JSON request body.
     * @param Multipart|null $multipart Multipart request body.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public ?array $json = null,
        public ?Multipart $multipart = null,
    ) {
    }
}
