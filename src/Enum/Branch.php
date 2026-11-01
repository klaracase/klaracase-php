<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Court branch.
 */
enum Branch: string
{
    case Ordentlich = 'ordentlich';
    case Verwaltung = 'verwaltung';
    case Sozial = 'sozial';
    case Arbeit = 'arbeit';
    case Finanz = 'finanz';
    case Verfassung = 'verfassung';
    case Patent = 'patent';
    case Unbekannt = 'unbekannt';

    /**
     * Null when absent or not known to this SDK version. The raw string stays on the property.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
