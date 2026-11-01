<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * HTTP 422, or a client-side refusal before any request (`errorCode` is `client_validation`, `status` is null).
 */
final class ValidationException extends KlaracaseException
{
    /** @var array<string, list<string>> Field name to messages, from the `errors` member. */
    public readonly array $errors;

    /**
     * @param array<string, mixed> $extra
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $type = null,
        ?string $title = null,
        ?string $detail = null,
        ?string $errorCode = null,
        ?string $apiRelease = null,
        array $extra = [],
        ?Throwable $previous = null,
        array $headers = [],
        array $body = [],
    ) {
        parent::__construct($message, $status, $type, $title, $detail, $errorCode, $apiRelease, $extra, $previous, $headers, $body);

        $errors = [];
        $raw = $extra['errors'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $field => $messages) {
                $errors[(string) $field] = is_array($messages)
                    ? array_values(array_map(static fn (mixed $m): string => is_scalar($m) ? (string) $m : '', $messages))
                    : [];
            }
        }
        $this->errors = $errors;
    }

    /**
     * The error raised when the arguments are wrong, before any request is sent.
     */
    public static function client(string $message): self
    {
        return new self(message: $message, errorCode: 'client_validation');
    }
}
