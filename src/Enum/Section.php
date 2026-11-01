<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Named section of a decision.
 */
enum Section: string
{
    case Tenor = 'Tenor';
    case Leitsatz = 'Leitsatz';
    case Tatbestand = 'Tatbestand';
    case Gruende = 'Gründe';
}
