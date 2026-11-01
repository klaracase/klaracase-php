<?php

declare(strict_types=1);

namespace Klaracase\Support;

use DateTimeInterface;
use Klaracase\Enum\Branch;
use Klaracase\Enum\DocumentType;
use Klaracase\Enum\Mode;
use Klaracase\Http\Request;
use Klaracase\Http\Transport;
use Klaracase\RequestOptions;
use Klaracase\Resource\CitationCheckDocuments;
use Klaracase\Resource\CitationChecks;
use Klaracase\Resource\Corpus;
use Klaracase\Resource\Datapackages;
use Klaracase\Resource\Decisions;
use Klaracase\Resource\Norms;
use Klaracase\Resource\Params;
use Klaracase\Response\SearchResult;

/**
 * The resource properties and the cross-type search, shared by `Klaracase` and `Testing\FakeClient`.
 *
 * @internal
 */
trait ProvidesResources
{
    public readonly Decisions $decisions;

    public readonly Norms $norms;

    public readonly Datapackages $datapackages;

    public readonly CitationChecks $citationChecks;

    public readonly CitationCheckDocuments $citationCheckDocuments;

    public readonly Corpus $corpus;

    private readonly Transport $apiTransport;

    public function decisions(): Decisions
    {
        return $this->decisions;
    }

    public function norms(): Norms
    {
        return $this->norms;
    }

    public function datapackages(): Datapackages
    {
        return $this->datapackages;
    }

    public function citationChecks(): CitationChecks
    {
        return $this->citationChecks;
    }

    public function citationCheckDocuments(): CitationCheckDocuments
    {
        return $this->citationCheckDocuments;
    }

    public function corpus(): Corpus
    {
        return $this->corpus;
    }

    /**
     * {@inheritDoc}
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
    ): SearchResult {
        $from = Params::date($dateFrom, 'dateFrom');
        $to = Params::date($dateTo, 'dateTo');
        Params::dateOrder($from, $to);

        $response = $this->apiTransport->send(new Request('GET', '/search', [
            'query' => $query,
            'jurisdiction' => $jurisdiction,
            'court' => $court,
            'branch' => Params::enum($branch),
            'date_from' => $from,
            'date_to' => $to,
            'mode' => Params::enum($mode),
            'document_type' => Params::enum($documentType),
            'cited_norm' => $citedNorm,
            'cited_statutes' => Params::enums($citedStatutes),
            'limit' => $limit,
            'bridge' => $bridge,
        ]), $options ?? new RequestOptions());

        return SearchResult::fromArray($response->body, $response->meta);
    }
}
