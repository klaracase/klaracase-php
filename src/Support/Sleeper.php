<?php

declare(strict_types=1);

namespace Klaracase\Support;

/**
 * Waits between retries and between document polls. Tests replace it.
 *
 * @internal An extension point for tests; implement it only to control waiting.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
