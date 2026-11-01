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
     */
    public function __construct(
        public int $status,
        public ?string $apiRelease,
        public float $requestDuration,
    ) {
    }
}
