<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Enum\Branch;
use Klaracase\Enum\FacetField;
use Klaracase\Http\Path;
use Klaracase\Http\Request;
use Klaracase\RequestOptions;
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
     * @param Branch|string|null $branch Scope the report to one court branch: ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt. A branch is a court jurisdiction, not a legal subject area.
     * @param int<1, 100>|null $top Cap on the per-court rows, busiest first. The server default is 20. The branch rollup is never capped.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     */
    public function coverage(Branch|string|null $branch = null, ?int $top = null, ?RequestOptions $options = null): CorpusCoverage
    {
        $response = $this->call(new Request('GET', '/corpus/coverage', [
            'branch' => Params::enum($branch),
            'top' => $top,
        ]), $options);

        return CorpusCoverage::fromArray($response->body, $response->meta);
    }

    /**
     * Values of one facet with counts.
     *
     * @param FacetField|string $field court_name, court_branch, document_type, jurisdiction, law_type or cited_norms.
     * @param string|null $type decision or law. Ignored for `cited_norms`.
     * @param string|null $norm Required for `cited_norms`: the norm whose family to list, for example `§ 543 BGB`.
     * @param string|null $prefix Case-insensitive prefix filter on the facet value. A trailing space counts.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     */
    public function facets(FacetField|string $field, ?string $type = null, ?string $norm = null, ?string $prefix = null, ?RequestOptions $options = null): CorpusFacets
    {
        $response = $this->call(new Request('GET', '/corpus/facets/' . Path::segment((string) Params::enum($field)), [
            'type' => $type,
            'norm' => $norm,
            'prefix' => $prefix,
        ]), $options);

        return CorpusFacets::fromArray($response->body, $response->meta);
    }
}
