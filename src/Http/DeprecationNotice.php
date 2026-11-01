<?php

declare(strict_types=1);

namespace Klaracase\Http;

/**
 * What the `onDeprecation` hook receives: the raw `Deprecation`, `Sunset` and `Link` header values
 * of an answer, and the request that got them. A header that was not sent is null.
 */
final readonly class DeprecationNotice
{
    public function __construct(
        public ?string $deprecation,
        public ?string $sunset,
        public ?string $link,
        public string $method,
        public string $path,
    ) {
    }

    public function message(): string
    {
        $parts = [];
        foreach (['Deprecation' => $this->deprecation, 'Sunset' => $this->sunset, 'Link' => $this->link] as $name => $value) {
            if ($value !== null) {
                $parts[] = $name . ': ' . $value;
            }
        }

        return sprintf('Klaracase API: %s %s is deprecated (%s).', $this->method, $this->path, implode('; ', $parts));
    }
}
