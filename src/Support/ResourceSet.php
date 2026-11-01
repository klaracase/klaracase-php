<?php

declare(strict_types=1);

namespace Klaracase\Support;

use Klaracase\Http\Transport;
use Klaracase\Resource\CitationCheckDocuments;
use Klaracase\Resource\CitationChecks;
use Klaracase\Resource\Corpus;
use Klaracase\Resource\Datapackages;
use Klaracase\Resource\Decisions;
use Klaracase\Resource\Norms;

/**
 * The six resources of one client, built over one transport.
 *
 * @internal
 */
final readonly class ResourceSet
{
    public Decisions $decisions;

    public Norms $norms;

    public Datapackages $datapackages;

    public CitationChecks $citationChecks;

    public CitationCheckDocuments $citationCheckDocuments;

    public Corpus $corpus;

    public function __construct(public Transport $transport, Sleeper $sleeper, Clock $clock)
    {
        $this->decisions = new Decisions($transport);
        $this->norms = new Norms($transport);
        $this->datapackages = new Datapackages($transport);
        $this->citationCheckDocuments = new CitationCheckDocuments($transport, $sleeper, $clock);
        $this->citationChecks = new CitationChecks($transport, $this->citationCheckDocuments);
        $this->corpus = new Corpus($transport);
    }
}
