<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Reads typed values out of a decoded object and remembers what it consumed. A key with the wrong JSON type is NOT
 * consumed: the property reads null and the raw value stays in `rest()`, so nothing is ever dropped.
 *
 * @internal
 */
final class Reader
{
    /** @var array<string, true> */
    private array $used = [];

    /** @var list<string> */
    private array $nulls = [];

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function string(string $key): ?string
    {
        $value = $this->raw($key);

        return is_string($value) ? $this->use($key, $value) : null;
    }

    public function int(string $key): ?int
    {
        $value = $this->raw($key);

        return is_int($value) ? $this->use($key, $value) : null;
    }

    public function bool(string $key): ?bool
    {
        $value = $this->raw($key);

        return is_bool($value) ? $this->use($key, $value) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function map(string $key): ?array
    {
        $value = $this->raw($key);
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $this->use($key, $value);
    }

    /**
     * A JSON array or object whose inner shape the SDK does not model.
     *
     * @return array<array-key, mixed>|null
     */
    public function anyArray(string $key): ?array
    {
        $value = $this->raw($key);

        return is_array($value) ? $this->use($key, $value) : null;
    }

    /**
     * @return list<mixed>|null
     */
    public function list(string $key): ?array
    {
        $value = $this->raw($key);

        return is_array($value) && array_is_list($value) ? $this->use($key, $value) : null;
    }

    /**
     * @return list<string>|null
     */
    public function strings(string $key): ?array
    {
        $value = $this->raw($key);
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }
        foreach ($value as $item) {
            if (! is_string($item)) {
                return null;
            }
        }

        /** @var list<string> $value */
        return $this->use($key, $value);
    }

    /**
     * @return array<string, int>|null
     */
    public function ints(string $key): ?array
    {
        $value = $this->raw($key);
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }
        foreach ($value as $item) {
            if (! is_int($item)) {
                return null;
            }
        }

        /** @var array<string, int> $value */
        return $this->use($key, $value);
    }

    /**
     * @template T of Model
     * @param class-string<T> $class
     * @return T|null
     */
    public function object(string $key, string $class): ?Model
    {
        $value = $this->raw($key);
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }

        return $this->use($key, $class::fromArray($value));
    }

    /**
     * @template T of Model
     * @param class-string<T> $class
     * @return list<T>|null
     */
    public function listOf(string $key, string $class): ?array
    {
        $value = $this->raw($key);
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }
        $items = [];
        foreach ($value as $item) {
            if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                return null;
            }
            $items[] = $class::fromArray($item);
        }

        return $this->use($key, $items);
    }

    /**
     * @template T of Model
     * @param class-string<T> $class
     * @return array<string, T>|null
     */
    public function mapOf(string $key, string $class): ?array
    {
        $value = $this->raw($key);
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }
        $items = [];
        foreach ($value as $name => $item) {
            if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                return null;
            }
            $items[(string) $name] = $class::fromArray($item);
        }

        return $this->use($key, $items);
    }

    /**
     * @return array<string, mixed>
     */
    public function rest(): array
    {
        $rest = [];
        foreach ($this->data as $key => $value) {
            if (! isset($this->used[(string) $key])) {
                $rest[(string) $key] = $value;
            }
        }

        return $rest;
    }

    /**
     * @return list<string>
     */
    public function nulls(): array
    {
        return $this->nulls;
    }

    private function raw(string $key): mixed
    {
        $value = $this->data[$key] ?? null;
        if ($value === null && array_key_exists($key, $this->data)) {
            $this->nulls[] = $key;
            $this->used[$key] = true;
        }

        return $value;
    }

    /**
     * @template V
     * @param V $value
     * @return V
     */
    private function use(string $key, mixed $value): mixed
    {
        $this->used[$key] = true;

        return $value;
    }
}
