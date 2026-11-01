<?php

declare(strict_types=1);

namespace Klaracase\Exception;

/**
 * HTTP 401: the token is missing, expired, revoked or not a personal access token.
 */
final class AuthenticationException extends KlaracaseException
{
}
