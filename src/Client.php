<?php

declare(strict_types=1);

namespace Klaracase;

use DateTimeInterface;
use Klaracase\Enum\Branch;
use Klaracase\Enum\DocumentType;
use Klaracase\Enum\Mode;
use Klaracase\Enum\Statute;
use Klaracase\Resource\CitationCheckDocuments;
use Klaracase\Resource\CitationChecks;
use Klaracase\Resource\Corpus;
use Klaracase\Resource\Datapackages;
use Klaracase\Resource\Decisions;
use Klaracase\Resource\Norms;
use Klaracase\Response\SearchResult;

/**
 * What an application needs from Klaracase. `Klaracase` implements it over HTTP and `Testing\FakeClient` over a
 * queue of answers, so code that asks for a `Client` can be tested without a network.
 *
 * Both implementations also expose the resources as public read-only properties
 * (`$client->decisions`), the form the README uses.
 */
interface Client
{
    public function decisions(): Decisions;

    public function norms(): Norms;

    public function datapackages(): Datapackages;

    public function citationChecks(): CitationChecks;

    public function citationCheckDocuments(): CitationCheckDocuments;

    public function corpus(): Corpus;

    /**
     * Search statutes and decisions together. Costs 1 credit.
     *
     * @param non-empty-string $query 2 to 500 characters, German legal-concept phrasing. Longer input is rejected, not cut.
     * @param string|null $jurisdiction DE, EU (EU law and the Treaties) or XI (international). Narrows the laws section only.
     * @param string|null $court Court name filter on the decisions section, canonical short form, for example `BGH`.
     * @param Branch|string|null $branch Court branch: ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt. It names the court jurisdiction, not the legal subject area.
     * @param DateTimeInterface|string|null $dateFrom Earliest decision date, inclusive, `YYYY-MM-DD`.
     * @param DateTimeInterface|string|null $dateTo Latest decision date, inclusive, `YYYY-MM-DD`, not earlier than `$dateFrom`.
     * @param Mode|string|null $mode keyword, semantic or hybrid. Hybrid is the server default.
     * @param DocumentType|string|null $documentType Restrict the decisions section to one document type: Urteil, Beschluss, Verfügung, Sonstige, Gerichtsbescheid or Schlussanträge.
     * @param string|null $citedNorm Decisions whose Normenkette cites this statute. One canonical token, for example `§ 906 BGB`.
     * @param list<Statute|string>|null $citedStatutes Any section of these statutes, for example `[Statute::StGB, 'StPO']`.
     * @param int<1, 10>|null $limit Results per section. The default is 5 and the cap is 10.
     * @param bool|null $bridge Whether the server may add curated legal-register terms to the query (the default is true).
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws Exception\ValidationException With code `client_validation`, before any request, for a malformed date or `$dateFrom` after `$dateTo`.
     */
    public function search(
        string $query,
        ?string $jurisdiction = null,
        ?string $court = null,
        Branch|string|null $branch = null,
        DateTimeInterface|string|null $dateFrom = null,
        DateTimeInterface|string|null $dateTo = null,
        Mode|string|null $mode = null,
        DocumentType|string|null $documentType = null,
        ?string $citedNorm = null,
        ?array $citedStatutes = null,
        ?int $limit = null,
        ?bool $bridge = null,
        ?RequestOptions $options = null,
    ): SearchResult;
}
