<?php

declare(strict_types=1);

namespace Klaracase\Support;

final class NativeSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0.0) {
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }
}
