<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use InvalidArgumentException;
use Klaracase\Response\CitationCheck;
use Klaracase\Response\CitationCheckDocument;
use Klaracase\Response\CorpusCoverage;
use Klaracase\Response\CorpusFacets;
use Klaracase\Response\Datapackage;
use Klaracase\Response\Decision;
use Klaracase\Response\DecisionCitations;
use Klaracase\Response\DecisionSearchResult;
use Klaracase\Response\Norm;
use Klaracase\Response\SearchResult;

/**
 * Ready-made answers for `FakeClient`: the example bodies of the API documentation, as response objects, and a
 * builder for problem answers. The data is example data, not a statement about German law.
 */
final class Fixtures
{
    public static function decisionSearch(): DecisionSearchResult
    {
        return DecisionSearchResult::fromArray(self::body('decisions.search'));
    }

    public static function search(): SearchResult
    {
        return SearchResult::fromArray(self::body('search'));
    }

    public static function decision(): Decision
    {
        return Decision::fromArray(self::body('decisions.show'));
    }

    public static function decisionCitations(): DecisionCitations
    {
        return DecisionCitations::fromArray(self::body('decisions.citations'));
    }

    public static function norm(): Norm
    {
        return Norm::fromArray(self::body('norms.show'));
    }

    public static function datapackage(): Datapackage
    {
        return Datapackage::fromArray(self::body('datapackages.store'));
    }

    /**
     * A finished citation check run with one verified and one unverified citation.
     */
    public static function citationCheck(): CitationCheck
    {
        return CitationCheck::fromArray(self::body('citation-checks.show'));
    }

    /**
     * The receipt of a document that is ready to check.
     */
    public static function citationCheckDocument(): CitationCheckDocument
    {
        return CitationCheckDocument::fromArray(self::body('citation-check-documents.show'));
    }

    /**
     * The receipt `upload()` answers with (status `uploaded`). Queue it with status 202.
     */
    public static function citationCheckDocumentUploaded(): CitationCheckDocument
    {
        return CitationCheckDocument::fromArray(self::body('citation-check-documents.store'));
    }

    public static function corpusCoverage(): CorpusCoverage
    {
        return CorpusCoverage::fromArray(self::body('corpus.coverage'));
    }

    public static function corpusFacets(): CorpusFacets
    {
        return CorpusFacets::fromArray(self::body('corpus.facets'));
    }

    /**
     * An RFC 9457 problem answer. `FakeClient` throws the exception class for its status.
     *
     * @param array<string, mixed> $extra Further members, for example `['retry_after_seconds' => 3]` or `['cost' => 5, 'balance' => 2]`.
     * @return array<string, mixed>
     */
    public static function problem(int $status, string $code, array $extra = [], ?string $detail = null): array
    {
        return [
            'type' => 'https://klaracase.de/docs/api/errors#' . $code,
            'title' => 'Error',
            'status' => $status,
            'detail' => $detail ?? 'Test problem ' . $code . '.',
            'code' => $code,
            ...$extra,
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function body(string $name): array
    {
        $json = file_get_contents(__DIR__ . '/examples/' . $name . '.json');
        if ($json === false) {
            throw new InvalidArgumentException('Unknown fixture ' . $name);
        }
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Fixture ' . $name . ' is not a JSON object.');
        }

        return $decoded;
    }
}
