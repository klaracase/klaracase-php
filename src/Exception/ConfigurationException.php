<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use InvalidArgumentException;

/**
 * The client cannot be built: no token, a bad base URL, or no HTTP client found.
 * It is not a KlaracaseException, because no problem answer is involved.
 */
class ConfigurationException extends InvalidArgumentException implements KlaracaseThrowable
{
}
