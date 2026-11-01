<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use DateTimeInterface;
use Generator;
use Klaracase\Enum\Branch;
use Klaracase\Enum\Direction;
use Klaracase\Enum\DocumentType;
use Klaracase\Enum\Mode;
use Klaracase\Enum\Section;
use Klaracase\Enum\Statute;
use Klaracase\Exception\ValidationException;
use Klaracase\Http\Path;
use Klaracase\Http\Request;
use Klaracase\RequestOptions;
use Klaracase\Response\CitationEdge;
use Klaracase\Response\Decision;
use Klaracase\Response\DecisionCitations;
use Klaracase\Response\DecisionHit;
use Klaracase\Response\DecisionSearchResult;

/**
 * Court decisions: search, read, citation graph.
 */
final class Decisions extends AbstractResource
{
    /** The server serves search pages 1 to 10. */
    public const MAX_SEARCH_PAGES = 10;

    /** The server serves citation pages 1 to 20. */
    public const MAX_CITATION_PAGES = 20;

    /**
     * Search court decisions. Costs 1 credit. German legal terms work best.
     *
     * @param non-empty-string $query 2 to 500 characters, German legal-concept phrasing. Longer input is rejected, not cut.
     * @param string|null $court Court name, for example `BGH`. The values are in `corpus->facets('court_name')`.
     * @param Branch|string|null $branch Court branch: ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt. It names the court jurisdiction, not the legal subject area.
     * @param DocumentType|string|null $documentType Urteil, Beschluss, Verfügung, Sonstige, Gerichtsbescheid or Schlussanträge.
     * @param string|null $citedNorm Decisions whose Normenkette cites this statute. One canonical token, for example `§ 906 BGB`; subdivisions nest.
     * @param list<Statute|string>|null $citedStatutes Any section of these statutes, for example `[Statute::StGB, 'StPO']`.
     * @param DateTimeInterface|string|null $dateFrom Earliest decision date, inclusive, `YYYY-MM-DD`.
     * @param DateTimeInterface|string|null $dateTo Latest decision date, inclusive, `YYYY-MM-DD`, not earlier than `$dateFrom`.
     * @param Mode|string|null $mode keyword, semantic or hybrid. Hybrid is the server default.
     * @param int<1, 20>|null $limit Results per page. 20 is the default and the cap.
     * @param int<1, 10>|null $page 1-based page number, at most 10.
     * @param bool|null $bridge Whether the server may add curated legal-register terms to the query (the default is true).
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, for a malformed date or `$dateFrom` after `$dateTo`.
     */
    public function search(
        string $query,
        ?string $court = null,
        Branch|string|null $branch = null,
        DocumentType|string|null $documentType = null,
        ?string $citedNorm = null,
        ?array $citedStatutes = null,
        DateTimeInterface|string|null $dateFrom = null,
        DateTimeInterface|string|null $dateTo = null,
        Mode|string|null $mode = null,
        ?int $limit = null,
        ?int $page = null,
        ?bool $bridge = null,
        ?RequestOptions $options = null,
    ): DecisionSearchResult {
        $from = Params::date($dateFrom, 'dateFrom');
        $to = Params::date($dateTo, 'dateTo');
        Params::dateOrder($from, $to);

        $response = $this->call(new Request('GET', '/decisions/search', [
            'query' => $query,
            'court' => $court,
            'branch' => Params::enum($branch),
            'document_type' => Params::enum($documentType),
            'cited_norm' => $citedNorm,
            'cited_statutes' => Params::enums($citedStatutes),
            'date_from' => $from,
            'date_to' => $to,
            'mode' => Params::enum($mode),
            'limit' => $limit,
            'page' => $page,
            'bridge' => $bridge,
        ]), $options);

        return DecisionSearchResult::fromArray($response->body, $response->meta);
    }

    /**
     * Search and walk the pages, yielding one decision at a time.
     *
     * EACH PAGE IS ONE SEARCH CALL AND COSTS 1 CREDIT. That is why `$maxPages` is required: it is the most you
     * agree to pay for. It is clamped to 10, the last page the server serves. The walk stops at once when a page
     * comes back empty or when the totals say no page follows, so a short result does not pay for empty pages.
     * Stop reading the generator and no further page is fetched.
     *
     * @param non-empty-string $query 2 to 500 characters, German legal-concept phrasing.
     * @param positive-int $maxPages Most pages to fetch, at least 1. Values above 10 count as 10.
     * @param string|null $court Court name, for example `BGH`.
     * @param Branch|string|null $branch Court branch.
     * @param DocumentType|string|null $documentType Urteil, Beschluss, Verfügung, Sonstige, Gerichtsbescheid or Schlussanträge.
     * @param string|null $citedNorm One canonical statute token, for example `§ 906 BGB`.
     * @param list<Statute|string>|null $citedStatutes Any section of these statutes.
     * @param DateTimeInterface|string|null $dateFrom Earliest decision date, `YYYY-MM-DD`.
     * @param DateTimeInterface|string|null $dateTo Latest decision date, `YYYY-MM-DD`.
     * @param Mode|string|null $mode keyword, semantic or hybrid.
     * @param int<1, 20>|null $limit Results per page.
     * @param bool|null $bridge Whether the server may add curated legal-register terms to the query.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for every page.
     * @return Generator<int, DecisionHit>
     *
     * @throws ValidationException With code `client_validation` when `$maxPages` is below 1, or for the date checks of `search()`.
     */
    public function searchAll(
        string $query,
        int $maxPages,
        ?string $court = null,
        Branch|string|null $branch = null,
        DocumentType|string|null $documentType = null,
        ?string $citedNorm = null,
        ?array $citedStatutes = null,
        DateTimeInterface|string|null $dateFrom = null,
        DateTimeInterface|string|null $dateTo = null,
        Mode|string|null $mode = null,
        ?int $limit = null,
        ?bool $bridge = null,
        ?RequestOptions $options = null,
    ): Generator {
        $pages = min(self::requirePages($maxPages), self::MAX_SEARCH_PAGES);

        $index = 0;
        for ($page = 1; $page <= $pages; $page++) {
            $result = $this->search($query, $court, $branch, $documentType, $citedNorm, $citedStatutes, $dateFrom, $dateTo, $mode, $limit, $page, $bridge, $options);
            $hits = $result->results ?? [];
            foreach ($hits as $hit) {
                yield $index++ => $hit;
            }
            if ($hits === [] || ($result->total_available?->value !== null && ! $result->hasMore())) {
                return;
            }
        }
    }

    /**
     * Read one decision. Costs 1 credit.
     *
     * @param string $id A UUID, an Aktenzeichen or an ECLI.
     * @param int<500, max>|null $maxChars Characters of text to return. The default is 40,000, the minimum 500.
     * @param int<1, max>|null $rnFrom First Randnummer to return. Whole chunks come back, so the served span is in `returned_range`.
     * @param int<1, max>|null $rnTo Last Randnummer to return, inclusive, not below `$rnFrom`.
     * @param Section|string|null $section One named section instead of the whole text: Tenor, Leitsatz, Tatbestand or Gründe. `available_sections` lists what a decision has.
     * @param string|null $court Disambiguates an Aktenzeichen: the court short form, for example `BGH`. Ignored for a UUID.
     * @param DateTimeInterface|string|null $date Disambiguates an Aktenzeichen: the decision date, `YYYY-MM-DD`.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, for a malformed `$date` or `$rnFrom` above `$rnTo`.
     */
    public function get(
        string $id,
        ?int $maxChars = null,
        ?int $rnFrom = null,
        ?int $rnTo = null,
        Section|string|null $section = null,
        ?string $court = null,
        DateTimeInterface|string|null $date = null,
        ?RequestOptions $options = null,
    ): Decision {
        Params::rnOrder($rnFrom, $rnTo);

        $response = $this->call(new Request('GET', '/decisions/' . Path::segment($id), [
            'max_chars' => $maxChars,
            'rn_from' => $rnFrom,
            'rn_to' => $rnTo,
            'section' => Params::enum($section),
            'court' => $court,
            'date' => Params::date($date, 'date'),
        ]), $options);

        return Decision::fromArray($response->body, $response->meta);
    }

    /**
     * Follow the citations of one decision. Costs 2 credits.
     *
     * @param string $id A UUID, an Aktenzeichen or an ECLI.
     * @param Direction|string|null $direction outgoing (decisions this one cites), incoming (decisions citing this one) or both (the server default).
     * @param int<1, 2>|null $depth 1 (the server default) for direct neighbours, 2 for neighbours of neighbours, a bounded sample.
     * @param string|null $court Disambiguates an Aktenzeichen: the court short form, for example `OLG Hamm`.
     * @param DateTimeInterface|string|null $date Disambiguates an Aktenzeichen: the decision date, `YYYY-MM-DD`.
     * @param int<1, 20>|null $page 1-based page over the neighbour lists, 25 decisions per direction, up to page 20. Depth 1 only.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, for a malformed `$date`.
     */
    public function citations(
        string $id,
        Direction|string|null $direction = null,
        ?int $depth = null,
        ?string $court = null,
        DateTimeInterface|string|null $date = null,
        ?int $page = null,
        ?RequestOptions $options = null,
    ): DecisionCitations {
        $response = $this->call(new Request('GET', '/decisions/' . Path::segment($id) . '/citations', [
            'direction' => Params::enum($direction),
            'depth' => $depth,
            'court' => $court,
            'date' => Params::date($date, 'date'),
            'page' => $page,
        ]), $options);

        return DecisionCitations::fromArray($response->body, $response->meta);
    }

    /**
     * Walk the citation pages of one decision (depth 1), yielding one edge at a time: the outgoing edges of a
     * page, then the incoming ones.
     *
     * EACH PAGE IS ONE CALL AND COSTS 2 CREDITS. `$maxPages` is required for that reason. It is clamped to 20, the
     * last page the server serves. The walk stops when a page has no edges or when `pagination` says no list has
     * more.
     *
     * @param string $id A UUID, an Aktenzeichen or an ECLI.
     * @param positive-int $maxPages Most pages to fetch, at least 1. Values above 20 count as 20.
     * @param Direction|string|null $direction outgoing, incoming or both.
     * @param string|null $court Disambiguates an Aktenzeichen.
     * @param DateTimeInterface|string|null $date Disambiguates an Aktenzeichen, `YYYY-MM-DD`.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for every page.
     * @return Generator<int, CitationEdge>
     *
     * @throws ValidationException With code `client_validation` when `$maxPages` is below 1.
     */
    public function citationsAll(
        string $id,
        int $maxPages,
        Direction|string|null $direction = null,
        ?string $court = null,
        DateTimeInterface|string|null $date = null,
        ?RequestOptions $options = null,
    ): Generator {
        $pages = min(self::requirePages($maxPages), self::MAX_CITATION_PAGES);

        $index = 0;
        for ($page = 1; $page <= $pages; $page++) {
            $result = $this->citations($id, $direction, 1, $court, $date, $page, $options);
            $edges = [...($result->references ?? []), ...($result->referenced_by ?? [])];
            foreach ($edges as $edge) {
                yield $index++ => $edge;
            }
            $pagination = $result->pagination;
            $more = $pagination !== null && ($pagination->has_more_references === true || $pagination->has_more_referenced_by === true);
            if ($edges === [] || ! $more) {
                return;
            }
        }
    }

    private static function requirePages(int $maxPages): int
    {
        if ($maxPages < 1) {
            throw ValidationException::client('`maxPages` must be 1 or more.');
        }

        return $maxPages;
    }
}
