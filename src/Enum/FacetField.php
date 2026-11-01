<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Field of the corpus facets endpoint.
 */
enum FacetField: string
{
    case CourtName = 'court_name';
    case CourtBranch = 'court_branch';
    case DocumentType = 'document_type';
    case Jurisdiction = 'jurisdiction';
    case LawType = 'law_type';
    case CitedNorms = 'cited_norms';
}
