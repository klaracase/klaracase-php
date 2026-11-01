<?php

declare(strict_types=1);

namespace Klaracase\Support;

/**
 * A monotonic clock. Used for the request duration and the wait timeout.
 */
interface Clock
{
    /**
     * Seconds from an arbitrary fixed start. Only differences are meaningful.
     */
    public function now(): float;
}
