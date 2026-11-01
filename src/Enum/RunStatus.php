<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Status of a citation check run.
 */
enum RunStatus: string
{
    case Pending = 'pending';
    case Gating = 'gating';
    case Extracting = 'extracting';
    case Resolving = 'resolving';
    case CheckingExternal = 'checking_external';
    case Complete = 'complete';
    case Rejected = 'rejected';
    case Failed = 'failed';
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
