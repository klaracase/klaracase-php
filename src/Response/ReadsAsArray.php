<?php

declare(strict_types=1);

namespace Klaracase\Response;

use BadMethodCallException;

/**
 * Array access over `toArray()`, kept so code written for the first array-shaped responses still runs.
 *
 * @internal
 */
trait ReadsAsArray
{
    /**
     * @deprecated Read the typed properties instead; array access is kept for old code and will go in 1.0.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->toArray()[$offset]);
    }

    /**
     * @deprecated Read the typed properties instead; array access is kept for old code and will go in 1.0.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    /**
     * @deprecated A response is immutable.
     */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('A Klaracase response is immutable.');
    }

    /**
     * @deprecated A response is immutable.
     */
    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('A Klaracase response is immutable.');
    }
}
