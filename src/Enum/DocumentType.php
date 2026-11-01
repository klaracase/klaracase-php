<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Type of a decision document.
 */
enum DocumentType: string
{
    case Urteil = 'Urteil';
    case Beschluss = 'Beschluss';
    case Verfuegung = 'Verfügung';
    case Sonstige = 'Sonstige';
    case Gerichtsbescheid = 'Gerichtsbescheid';
    case Schlussantraege = 'Schlussanträge';

    /**
     * Null when absent or not known to this SDK version. The raw string stays on the property.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
