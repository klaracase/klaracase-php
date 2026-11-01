<?php

declare(strict_types=1);

namespace Klaracase\Response;

use ArrayAccess;
use BadMethodCallException;
use JsonSerializable;

/**
 * Base of every response. Property names are the API's key names, unchanged.
 *
 * - `$extra` holds every top-level key the SDK does not model, so a new API key is never lost.
 * - `toArray()` returns the decoded body exactly as it arrived. Nothing is renamed, reordered or cleaned.
 * - `$response['key']` reads the same body. Responses are immutable.
 * - A typed property reads null when the key is missing or holds an unexpected JSON type.
 *
 * @implements ArrayAccess<string, mixed>
 */
abstract readonly class Response implements ArrayAccess, JsonSerializable
{
    /** Keys every served body carries; they are modelled on this base class. */
    private const BASE_KEYS = ['api_version', 'changelog_url', 'credits', 'ignored_parameters'];

    /** @var list<string> The top-level keys the subclass models. */
    protected const KEYS = [];

    public ?string $api_version;

    public ?string $changelog_url;

    /** Credits charged for this call and credits left. Null when the body carries none. */
    public ?Credits $credits;

    /** @var array<array-key, mixed>|null Set when the request carried arguments the API does not know. */
    public ?array $ignored_parameters;

    /** @var array<string, mixed> Top-level keys the SDK does not model. */
    public array $extra;

    /** @var array<array-key, mixed> */
    private array $raw;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, public ResponseMeta $meta)
    {
        $this->raw = $raw;
        $this->api_version = Fields::string($raw, 'api_version');
        $this->changelog_url = Fields::string($raw, 'changelog_url');
        $credits = Fields::array($raw, 'credits');
        $this->credits = $credits === null ? null : Credits::fromArray($credits);
        $this->ignored_parameters = Fields::array($raw, 'ignored_parameters');

        $known = array_flip([...self::BASE_KEYS, ...static::KEYS]);
        $extra = [];
        foreach ($raw as $key => $value) {
            if (! isset($known[$key])) {
                $extra[(string) $key] = $value;
            }
        }
        $this->extra = $extra;
    }

    /**
     * The decoded body, without `meta`.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('A Klaracase response is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('A Klaracase response is immutable.');
    }
}
