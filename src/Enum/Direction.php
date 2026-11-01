<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Direction of a citation graph.
 */
enum Direction: string
{
    case Outgoing = 'outgoing';
    case Incoming = 'incoming';
    case Both = 'both';

    /**
     * Null when absent or not known to this SDK version. The raw string stays on the property.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
