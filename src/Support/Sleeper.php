<?php

declare(strict_types=1);

namespace Klaracase\Support;

/**
 * Waits between retries and between document polls. Tests replace it.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
