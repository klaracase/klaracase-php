<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class of every error that comes from talking to the API.
 *
 * It carries the RFC 9457 problem members. `$errorCode` is the problem `code`
 * (a PHP exception already owns `$code` as an int). `$extra` holds every
 * problem member that is not `type`, `title`, `status`, `detail` or `code`,
 * typed members included.
 *
 * The API token never appears in a message.
 */
class KlaracaseException extends RuntimeException
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $type = null,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $apiRelease = null,
        public readonly array $extra = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
