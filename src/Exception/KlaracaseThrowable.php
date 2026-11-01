<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Throwable;

/**
 * Marker for every exception this package throws on purpose, the API errors and the configuration error alike.
 * Catch it when one handler should cover both.
 */
interface KlaracaseThrowable extends Throwable
{
}
