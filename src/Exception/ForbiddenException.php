<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * HTTP 403: the token lacks an ability, lost its organization, or two-factor sign-in is required.
 */
class ForbiddenException extends KlaracaseException
{
    /** The ability the endpoint needs, when the problem names one (`token_ability_missing`). */
    public readonly ?string $requiredAbility;

    /**
     * @param array<string, mixed> $extra
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
    ) {
        parent::__construct($message, $status, $type, $title, $detail, $errorCode, $apiRelease, $extra, $previous);

        $ability = $extra['required_ability'] ?? null;
        $this->requiredAbility = is_string($ability) ? $ability : null;
    }
}
