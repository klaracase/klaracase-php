<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Search mode.
 */
enum Mode: string
{
    case Keyword = 'keyword';
    case Semantic = 'semantic';
    case Hybrid = 'hybrid';

    /**
     * Null when absent or not known to this SDK version. The raw string stays on the property.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
