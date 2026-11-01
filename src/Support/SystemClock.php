<?php

declare(strict_types=1);

namespace Klaracase\Support;

final class SystemClock implements Clock
{
    public function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
