<?php

declare(strict_types=1);

namespace Klaracase\Http;

use Klaracase\Response\ResponseMeta;

/**
 * A decoded success answer: the JSON object and what the transport knows about it.
 *
 * @internal
 */
final readonly class Response
{
    /**
     * @param array<array-key, mixed> $body The decoded JSON object.
     */
    public function __construct(
        public array $body,
        public ResponseMeta $meta,
    ) {
    }
}
