<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Whether a citation edge resolved to a corpus decision.
 */
enum ResolutionStatus: string
{
    case Resolved = 'resolved';
    case Unresolved = 'unresolved';
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
