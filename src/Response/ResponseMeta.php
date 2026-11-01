<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * What the transport knows about one answer. It is not part of the body.
 */
final readonly class ResponseMeta
{
    /**
     * @param int $status HTTP status of the answer (200 or 202).
     * @param string|null $apiRelease Value of `X-Klaracase-Api-Release`, null when the header is absent.
     * @param float $requestDuration Seconds the final request took, 0 or more.
     * @param array<string, list<string>> $headers Every response header. Names are lower-case. The token is never in here.
     * @param int $attempts How many HTTP requests this call took, 1 when nothing was retried.
     * @param float $retriedFor Seconds the client waited between attempts, 0 when nothing was retried.
     */
    public function __construct(
        public int $status,
        public ?string $apiRelease,
        public float $requestDuration,
        public array $headers = [],
        public int $attempts = 1,
        public float $retriedFor = 0.0,
    ) {
    }

    /**
     * The first value of one response header, or null. The name is case-insensitive.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}
