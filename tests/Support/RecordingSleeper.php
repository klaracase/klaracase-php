<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use Klaracase\Support\Sleeper;

/**
 * Records every wait, returns at once, and moves the fake clock forward by the wait.
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
