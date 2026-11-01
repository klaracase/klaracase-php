<?php

declare(strict_types=1);

namespace Klaracase\Exception;

/**
 * HTTP 503. Nothing ran and nothing was charged. The client retries it.
 */
class ServiceUnavailableException extends KlaracaseException
{
}
