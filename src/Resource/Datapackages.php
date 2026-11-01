<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Exception\ValidationException;
use Klaracase\Http\Request;
use Klaracase\RequestOptions;
use Klaracase\Response\Datapackage;

/**
 * Bundles of decisions.
 */
final class Datapackages extends AbstractResource
{
    public const MAX_IDS = 10;

    /**
     * Bundle 1 to 10 decisions with their citation edges. Costs 5 credits.
     *
     * @param non-empty-list<non-empty-string> $ids Decision UUIDs or Aktenzeichen, 1 to 10. Order matters: the size budget drops from the end. Duplicates collapse into one.
     * @param bool|null $includeCitations Include the outgoing citation edges (the server default is true).
     * @param bool|null $fullText Serve whole judgments instead of excerpts of about 700 characters (the server default is false).
     * @param int<500, max>|null $maxChars Per-decision cap when `$fullText` is true, 500 to 200,000.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, when `$ids` is empty, holds more than 10 entries or holds an empty id.
     */
    public function create(
        array $ids,
        ?bool $includeCitations = null,
        ?bool $fullText = null,
        ?int $maxChars = null,
        ?RequestOptions $options = null,
    ): Datapackage {
        if ($ids === [] || count($ids) > self::MAX_IDS) {
            throw ValidationException::client(sprintf('`ids` must hold 1 to %d decision ids, got %d.', self::MAX_IDS, count($ids)));
        }
        foreach ($ids as $id) {
            if (trim($id) === '') {
                throw ValidationException::client('`ids` must not hold an empty id.');
            }
        }

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

        $response = $this->call(new Request('POST', '/datapackages', json: $json), $options);

        return Datapackage::fromArray($response->body, $response->meta);
    }
}
