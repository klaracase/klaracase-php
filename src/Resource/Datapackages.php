<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Response\Datapackage;

/**
 * Bundles of decisions.
 */
final class Datapackages extends AbstractResource
{
    /**
     * Bundle 1 to 10 decisions with their citation edges. Costs 5 credits.
     *
     * @param array<array-key, string> $ids Decision UUIDs or Aktenzeichen. Order matters: the size budget drops from the end.
     * @param bool|null $includeCitations Include the outgoing citation edges (the server default is true).
     * @param bool|null $fullText Serve whole judgments instead of excerpts (the server default is false).
     * @param int|null $maxChars Per-decision cap when `$fullText` is true, 500 to 200,000.
     */
    public function create(
        array $ids,
        ?bool $includeCitations = null,
        ?bool $fullText = null,
        ?int $maxChars = null,
    ): Datapackage {
        $json = ['ids' => array_values($ids)];
        if ($includeCitations !== null) {
            $json['include_citations'] = $includeCitations;
        }
        if ($fullText !== null) {
            $json['full_text'] = $fullText;
        }
        if ($maxChars !== null) {
            $json['max_chars'] = $maxChars;
        }

        [$body, $meta] = $this->transport->send('POST', '/datapackages', json: $json);

        return Datapackage::fromArray($body, $meta);
    }
}
