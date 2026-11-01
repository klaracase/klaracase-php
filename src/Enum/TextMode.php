<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * How much text a data package carries.
 */
enum TextMode: string
{
    case Excerpt = 'excerpt';
    case FullText = 'full_text';
    /** A value this SDK version does not know. Never sent to the server. */
    case Unknown = '__unknown__';

    /**
     * Null when the server sent no value; Unknown for a value this SDK version does not know.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : (self::tryFrom($value) ?? self::Unknown);
    }
}
