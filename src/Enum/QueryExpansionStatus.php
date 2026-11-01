<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Whether query expansion changed the query.
 */
enum QueryExpansionStatus: string
{
    case Abstained = 'abstained';
    case Applied = 'applied';
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
