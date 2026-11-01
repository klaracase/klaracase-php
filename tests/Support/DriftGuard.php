<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use Klaracase\Response\Model;
use ReflectionMethod;
use ReflectionObject;
use UnitEnum;

/**
 * The checks behind the drift tests. They live here so a test can run them on a deliberately broken
 * model and see them fail.
 */
final class DriftGuard
{
    /**
     * Paths of every `extra` that is not empty, at any depth. A golden body must produce none.
     *
     * @return list<string>
     */
    public static function extras(Model $model, string $path = '$'): array
    {
        $found = [];
        foreach (get_object_vars($model) as $name => $value) {
            if ($name === 'extra') {
                if (is_array($value) && $value !== []) {
                    $found[] = $path . ' has extra keys: ' . implode(', ', array_map('strval', array_keys($value)));
                }

                continue;
            }
            foreach (self::children($value, $path . '.' . $name) as [$child, $childPath]) {
                $found = [...$found, ...self::extras($child, $childPath)];
            }
        }

        return $found;
    }

    /**
     * Every enum accessor (`xEnum()`) of every model in the tree whose raw value is set, and the enum it gave.
     * A value the enum does not know comes back as `Unknown`, or as null for the enums without an Unknown case.
     *
     * @return list<string> paths where the raw value is set but the enum is unknown
     */
    public static function unknownEnumValues(Model $model, string $path = '$'): array
    {
        $found = [];
        foreach ((new ReflectionObject($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (! str_ends_with($name, 'Enum') || $method->getNumberOfParameters() !== 0) {
                continue;
            }
            $property = lcfirst(substr($name, 0, -4));
            $raw = get_object_vars($model)[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property))] ?? null;
            $enum = $method->invoke($model);
            if ($raw !== null && (! $enum instanceof UnitEnum || $enum->name === 'Unknown')) {
                $found[] = $path . '.' . $property . ' = ' . json_encode($raw);
            }
        }
        foreach (get_object_vars($model) as $name => $value) {
            foreach (self::children($value, $path . '.' . $name) as [$child, $childPath]) {
                $found = [...$found, ...self::unknownEnumValues($child, $childPath)];
            }
        }

        return $found;
    }

    /**
     * True when the JSON form of the model is the body, ignoring key order but not `{}` against `[]`.
     */
    public static function jsonRoundTrips(Model $model, string $body): bool
    {
        return Canonical::json($body) === Canonical::json(json_encode($model, JSON_THROW_ON_ERROR));
    }

    /**
     * True when `toArray()` is the body decoded to arrays, ignoring key order.
     */
    public static function arrayRoundTrips(Model $model, string $body): bool
    {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return Canonical::sortKeys($decoded) === Canonical::sortKeys($model->toArray());
    }

    /**
     * @return iterable<array{Model, string}>
     */
    private static function children(mixed $value, string $path): iterable
    {
        if ($value instanceof Model) {
            yield [$value, $path];

            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($item instanceof Model) {
                    yield [$item, $path . '[' . $key . ']'];
                }
            }
        }
    }
}
