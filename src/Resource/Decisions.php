<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Http\Path;
use Klaracase\Response\Decision;
use Klaracase\Response\DecisionCitations;
use Klaracase\Response\DecisionSearchResult;

/**
 * Court decisions: search, read, citation graph.
 */
final class Decisions extends AbstractResource
{
    /**
     * Search court decisions. Costs 1 credit. German legal terms work best.
     *
     * @param string $query 2 to 500 characters, German legal-concept phrasing.
     * @param string|null $court Court name, for example `BGH`.
     * @param string|null $branch ordentlich, verwaltung, sozial, arbeit, finanz, verfassung, patent or unbekannt.
     * @param string|null $documentType Urteil, Beschluss, Verfügung, Sonstige, Gerichtsbescheid or Schlussanträge.
     * @param string|null $citedNorm One canonical statute token, for example `§ 906 BGB`.
     * @param list<string>|null $citedStatutes Any of StGB, StPO, InsO, DSGVO, GDPR, BDSG, BauGB, BBauG, BauNVO, StVG, StVO.
     * @param string|null $dateFrom Earliest decision date, `YYYY-MM-DD`.
     * @param string|null $dateTo Latest decision date, `YYYY-MM-DD`.
     * @param string|null $mode keyword, semantic or hybrid (the server default).
     * @param int|null $limit Results per page, at most 20.
     * @param int|null $page Page number, 1 to 10.
     * @param bool|null $bridge Whether the server may add curated legal-register terms to the query.
     */
    public function search(
        string $query,
        ?string $court = null,
        ?string $branch = null,
        ?string $documentType = null,
        ?string $citedNorm = null,
        ?array $citedStatutes = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $mode = null,
        ?int $limit = null,
        ?int $page = null,
        ?bool $bridge = null,
    ): DecisionSearchResult {
        [$body, $meta] = $this->transport->send('GET', '/decisions/search', [
            'query' => $query,
            'court' => $court,
            'branch' => $branch,
            'document_type' => $documentType,
            'cited_norm' => $citedNorm,
            'cited_statutes' => $citedStatutes,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'limit' => $limit,
            'page' => $page,
            'bridge' => $bridge,
        ]);

        return new DecisionSearchResult($body, $meta);
    }

    /**
     * Read one decision. Costs 1 credit.
     *
     * @param string $id A UUID, an Aktenzeichen or an ECLI.
     * @param int|null $maxChars Characters of text to return, at least 500.
     * @param int|null $rnFrom First Randnummer to return.
     * @param int|null $rnTo Last Randnummer to return, inclusive.
     * @param string|null $section Tenor, Leitsatz, Tatbestand or Gründe.
     * @param string|null $court Disambiguates an Aktenzeichen, for example `BGH`.
     * @param string|null $date Disambiguates an Aktenzeichen, `YYYY-MM-DD`.
     */
    public function get(
        string $id,
        ?int $maxChars = null,
        ?int $rnFrom = null,
        ?int $rnTo = null,
        ?string $section = null,
        ?string $court = null,
        ?string $date = null,
    ): Decision {
        [$body, $meta] = $this->transport->send('GET', '/decisions/' . Path::segment($id), [
            'max_chars' => $maxChars,
            'rn_from' => $rnFrom,
            'rn_to' => $rnTo,
            'section' => $section,
            'court' => $court,
            'date' => $date,
        ]);

        return new Decision($body, $meta);
    }

    /**
     * Follow the citations of one decision. Costs 2 credits.
     *
     * @param string $id A UUID, an Aktenzeichen or an ECLI.
     * @param string|null $direction outgoing, incoming or both (the server default).
     * @param int|null $depth 1 (the server default) or 2.
     * @param string|null $court Disambiguates an Aktenzeichen.
     * @param string|null $date Disambiguates an Aktenzeichen, `YYYY-MM-DD`.
     * @param int|null $page Page of the neighbour lists, 1 to 20. Depth 1 only.
     */
    public function citations(
        string $id,
        ?string $direction = null,
        ?int $depth = null,
        ?string $court = null,
        ?string $date = null,
        ?int $page = null,
    ): DecisionCitations {
        [$body, $meta] = $this->transport->send('GET', '/decisions/' . Path::segment($id) . '/citations', [
            'direction' => $direction,
            'depth' => $depth,
            'court' => $court,
            'date' => $date,
            'page' => $page,
        ]);

        return new DecisionCitations($body, $meta);
    }
}
