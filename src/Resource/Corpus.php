<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Http\Path;
use Klaracase\Response\CorpusCoverage;
use Klaracase\Response\CorpusFacets;

/**
 * What the corpus holds. Both calls are free.
 */
final class Corpus extends AbstractResource
{
    /**
     * Coverage per branch and per court.
     *
     * @param string|null $branch ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt.
     * @param int|null $top Cap on the per-court rows, busiest first. 1 to 100, the server default is 20.
     */
    public function coverage(?string $branch = null, ?int $top = null): CorpusCoverage
    {
        [$body, $meta] = $this->transport->send('GET', '/corpus/coverage', [
            'branch' => $branch,
            'top' => $top,
        ]);

        return CorpusCoverage::fromArray($body, $meta);
    }

    /**
     * Values of one facet with counts.
     *
     * @param string $field court_name, court_branch, document_type, jurisdiction, law_type or cited_norms.
     * @param string|null $type decision or law.
     * @param string|null $norm Required for `cited_norms`: the norm whose family to list, for example `§ 543 BGB`.
     * @param string|null $prefix Case-insensitive prefix filter on the facet value.
     */
    public function facets(string $field, ?string $type = null, ?string $norm = null, ?string $prefix = null): CorpusFacets
    {
        [$body, $meta] = $this->transport->send('GET', '/corpus/facets/' . Path::segment($field), [
            'type' => $type,
            'norm' => $norm,
            'prefix' => $prefix,
        ]);

        return CorpusFacets::fromArray($body, $meta);
    }
}
