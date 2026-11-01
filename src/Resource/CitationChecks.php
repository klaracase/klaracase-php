<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use DateTimeInterface;
use Klaracase\Exception\DocumentFailedException;
use Klaracase\Exception\DocumentTimeoutException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\ValidationException;
use Klaracase\Http\Path;
use Klaracase\Http\Request;
use Klaracase\Http\Transport;
use Klaracase\RequestOptions;
use Klaracase\Response\CitationCheck;

/**
 * Citation check runs.
 */
final class CitationChecks extends AbstractResource
{
    public function __construct(Transport $transport, private readonly CitationCheckDocuments $documents)
    {
        parent::__construct($transport);
    }

    /**
     * Check every citation in a German legal text. Costs 2 to 4 credits, by length. The same text again in the
     * same month costs nothing more. The answer is the finished run.
     *
     * Send exactly one of `$text` and `$documentId`.
     *
     * @param string|null $text 50 to 20,000 characters.
     * @param string|null $documentId Id of an upload whose status is `ready`. The check consumes the upload.
     * @param DateTimeInterface|string|null $referenceDate Document date `YYYY-MM-DD`, applied to statute citations that name no date.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, when not exactly one of `$text` and `$documentId` is given or the date is malformed.
     * @throws KlaracaseException On an API error or when no answer arrives.
     */
    public function create(
        ?string $text = null,
        ?string $documentId = null,
        DateTimeInterface|string|null $referenceDate = null,
        ?RequestOptions $options = null,
    ): CitationCheck {
        if ($text !== null && $documentId !== null) {
            throw ValidationException::client('Send either `text` or `documentId`, not both.');
        }
        if ($text === null && $documentId === null) {
            throw ValidationException::client('Send `text` or `documentId`.');
        }
        $date = Params::date($referenceDate, 'referenceDate');

        $json = [];
        if ($text !== null) {
            $json['text'] = $text;
        }
        if ($documentId !== null) {
            $json['document_id'] = $documentId;
        }
        if ($date !== null) {
            $json['reference_date'] = $date;
        }

        $response = $this->call(new Request('POST', '/citation-checks', json: $json), $options);

        return CitationCheck::fromArray($response->body, $response->meta);
    }

    /**
     * Upload a file, wait until it is ready, then check it. The upload is free; the check costs 2 to 4 credits.
     *
     * @param string $path A readable PDF, DOCX, Markdown or plain text file.
     * @param DateTimeInterface|string|null $referenceDate Document date `YYYY-MM-DD`.
     * @param float $waitTimeout Seconds to wait for the upload to become ready.
     * @param float $pollInterval Seconds between polls.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for every call.
     *
     * @throws ValidationException With code `client_validation` when the file cannot be read.
     * @throws DocumentFailedException When the upload ends in status `failed`.
     * @throws DocumentTimeoutException When the upload is not ready within `$waitTimeout`.
     * @throws KlaracaseException On an API error or when no answer arrives.
     */
    public function createFromFile(
        string $path,
        DateTimeInterface|string|null $referenceDate = null,
        float $waitTimeout = 120.0,
        float $pollInterval = 1.0,
        ?RequestOptions $options = null,
    ): CitationCheck {
        $date = Params::date($referenceDate, 'referenceDate');
        $receipt = $this->documents->upload($path, options: $options);
        $ready = $this->documents->waitUntilReady($receipt, $pollInterval, $waitTimeout, $options);

        return $this->create(documentId: $ready->id, referenceDate: $date, options: $options);
    }

    /**
     * Read back a run made through the API by your organization. Free.
     *
     * @param string $runId The `id` of a run.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws KlaracaseException On an API error or when no answer arrives.
     */
    public function get(string $runId, ?RequestOptions $options = null): CitationCheck
    {
        $response = $this->call(new Request('GET', '/citation-checks/' . Path::segment($runId)), $options);

        return CitationCheck::fromArray($response->body, $response->meta);
    }
}
