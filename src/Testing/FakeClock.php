<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use Klaracase\Support\Clock;

/**
 * A clock that moves only when a test moves it. `RecordingSleeper` moves it for you.
 */
final class FakeClock implements Clock
{
    private float $now = 1000.0;

    public function now(): float
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
