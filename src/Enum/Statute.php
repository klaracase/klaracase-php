<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Statute filter for `cited_statutes`.
 */
enum Statute: string
{
    case StGB = 'StGB';
    case StPO = 'StPO';
    case InsO = 'InsO';
    case DSGVO = 'DSGVO';
    case GDPR = 'GDPR';
    case BDSG = 'BDSG';
    case BauGB = 'BauGB';
    case BBauG = 'BBauG';
    case BauNVO = 'BauNVO';
    case StVG = 'StVG';
    case StVO = 'StVO';
}
