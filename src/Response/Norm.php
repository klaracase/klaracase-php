<?php

declare(strict_types=1);

namespace Klaracase\Response;

/**
 * Answer of GET /norms/{reference}: the current consolidated text of one statute norm.
 */
final readonly class Norm extends Response
{
    protected const KEYS = [
        'reference',
        'requested_reference',
        'law',
        'norm',
        'requested_qualifiers',
        'version',
        'neighbours',
        'links',
    ];

    /**
     * The reference as resolved, for example `§ 535 BGB`.
     */
    public ?string $reference;

    /**
     * The reference as you sent it.
     */
    public ?string $requested_reference;

    /**
     * @var array{id?: string, abbreviation?: string, source_abbreviation?: string, official_name?: string, law_level?: string, jurisdiction?: string, source_url?: string|null, upstream_status?: string, upstream_last_seen_at?: string|null, upstream_missing_since?: string|null}|null
     */
    public ?array $law;

    /**
     * @var array{number?: string, title?: string|null, lifecycle?: string, text?: string, absatz_identifiers?: list<string>, chars?: int}|null
     */
    public ?array $norm;

    /**
     * @var list<array{type?: string, value?: string, checked?: bool, present?: bool}>|null
     */
    public ?array $requested_qualifiers;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $version;

    /**
     * @var array{previous?: string|null, next?: string|null}|null
     */
    public ?array $neighbours;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $links;

    /**
     * @param array<array-key, mixed> $raw The decoded JSON object.
     */
    public function __construct(array $raw, ResponseMeta $meta)
    {
        parent::__construct($raw, $meta);

        $this->reference = Fields::string($raw, 'reference');
        $this->requested_reference = Fields::string($raw, 'requested_reference');
        /** @var array{id?: string, abbreviation?: string, source_abbreviation?: string, official_name?: string, law_level?: string, jurisdiction?: string, source_url?: string|null, upstream_status?: string, upstream_last_seen_at?: string|null, upstream_missing_since?: string|null}|null $value */
        $value = Fields::array($raw, 'law');
        $this->law = $value;
        /** @var array{number?: string, title?: string|null, lifecycle?: string, text?: string, absatz_identifiers?: list<string>, chars?: int}|null $value */
        $value = Fields::array($raw, 'norm');
        $this->norm = $value;
        /** @var list<array{type?: string, value?: string, checked?: bool, present?: bool}>|null $value */
        $value = Fields::array($raw, 'requested_qualifiers');
        $this->requested_qualifiers = $value;
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'version');
        $this->version = $value;
        /** @var array{previous?: string|null, next?: string|null}|null $value */
        $value = Fields::array($raw, 'neighbours');
        $this->neighbours = $value;
        /** @var array<string, mixed>|null $value */
        $value = Fields::array($raw, 'links');
        $this->links = $value;
    }
}
