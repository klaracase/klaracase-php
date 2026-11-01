<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Exception\ValidationException;
use Klaracase\Http\Path;
use Klaracase\Response\CitationCheck;

/**
 * Citation check runs.
 */
final class CitationChecks extends AbstractResource
{
    /**
     * Check every citation in a German legal text. Costs 2 to 4 credits, by length. The same text again in the
     * same month costs nothing more. The answer is the finished run.
     *
     * Send exactly one of `$text` and `$documentId`.
     *
     * @param string|null $text 50 to 20,000 characters.
     * @param string|null $documentId Id of an upload whose status is `ready`. The check consumes the upload.
     * @param string|null $referenceDate Document date `YYYY-MM-DD`, applied to statute citations that name no date.
     *
     * @throws ValidationException With code `client_validation`, before any request, when not exactly one of `$text` and `$documentId` is given.
     */
    public function create(?string $text = null, ?string $documentId = null, ?string $referenceDate = null): CitationCheck
    {
        if ($text !== null && $documentId !== null) {
            throw ValidationException::client('Send either `text` or `documentId`, not both.');
        }
        if ($text === null && $documentId === null) {
            throw ValidationException::client('Send `text` or `documentId`.');
        }

        $json = [];
        if ($text !== null) {
            $json['text'] = $text;
        }
        if ($documentId !== null) {
            $json['document_id'] = $documentId;
        }
        if ($referenceDate !== null) {
            $json['reference_date'] = $referenceDate;
        }

        [$body, $meta] = $this->transport->send('POST', '/citation-checks', json: $json);

        return new CitationCheck($body, $meta);
    }

    /**
     * Read back a run made through the API by your organization. Free.
     */
    public function get(string $runId): CitationCheck
    {
        [$body, $meta] = $this->transport->send('GET', '/citation-checks/' . Path::segment($runId));

        return new CitationCheck($body, $meta);
    }
}
