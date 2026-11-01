<?php

declare(strict_types=1);

namespace Klaracase\Support;

/**
 * A monotonic clock. Used for the request duration and the wait timeout. Tests replace it.
 *
 * @internal An extension point for tests; implement it only to control time.
 */
interface Clock
{
    /**
     * Seconds from an arbitrary fixed start. Only differences are meaningful.
     */
    public function now(): float;
}
