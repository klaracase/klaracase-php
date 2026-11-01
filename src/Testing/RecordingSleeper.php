<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use Klaracase\Support\Sleeper;

/**
 * Records every wait, returns at once, and moves the test clock forward by the wait. A polling test then runs in
 * no time and can still read how long the code would have waited.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(private readonly ?FakeClock $clock = null)
    {
    }

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->clock?->advance($seconds);
    }
}
