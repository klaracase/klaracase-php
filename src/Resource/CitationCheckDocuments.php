<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Exception\DocumentFailedException;
use Klaracase\Exception\DocumentTimeoutException;
use Klaracase\Exception\ValidationException;
use Klaracase\Http\Multipart;
use Klaracase\Http\Path;
use Klaracase\Http\Request;
use Klaracase\Http\Transport;
use Klaracase\RequestOptions;
use Klaracase\Response\CitationCheckDocument;
use Klaracase\Support\Clock;
use Klaracase\Support\Sleeper;
use SplFileInfo;

/**
 * Uploaded documents that wait for a citation check.
 */
final class CitationCheckDocuments extends AbstractResource
{
    private const CONTENT_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'md' => 'text/markdown',
        'markdown' => 'text/markdown',
        'txt' => 'text/plain',
    ];

    public function __construct(Transport $transport, private readonly Sleeper $sleeper, private readonly Clock $clock)
    {
        parent::__construct($transport);
    }

    /**
     * Upload a PDF, DOCX, Markdown or plain text file (up to 25 MB) from disk. Free. The answer is a receipt with
     * status `uploaded`; poll with `get()` or call `waitUntilReady()`.
     *
     * A string is a PATH, not file content. To send bytes you already hold, call `uploadBytes()`.
     * The file name and the content type come from the path unless you give them.
     *
     * @param SplFileInfo|string $path A readable file.
     * @param string|null $filename Name to send. Defaults to the name of the file, for example `schriftsatz.pdf`.
     * @param string|null $contentType Defaults from the extension (pdf, docx, md, txt), then from the file content.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws ValidationException With code `client_validation`, before any request, when the path does not exist or cannot be read.
     */
    public function upload(SplFileInfo|string $path, ?string $filename = null, ?string $contentType = null, ?RequestOptions $options = null): CitationCheckDocument
    {
        $location = $path instanceof SplFileInfo ? $path->getPathname() : $path;
        if ($location === '' || ! is_file($location) || ! is_readable($location)) {
            throw ValidationException::client(sprintf(
                'The file "%s" does not exist or cannot be read. `upload()` takes a path; to send bytes use `uploadBytes()`.',
                mb_substr($location, 0, 200, 'UTF-8'),
            ));
        }
        $contents = file_get_contents($location);
        if ($contents === false) {
            throw ValidationException::client(sprintf('The file "%s" could not be read.', mb_substr($location, 0, 200, 'UTF-8')));
        }
        $name = $filename ?? basename($location);

        return $this->uploadBytes(
            $contents,
            $name,
            $contentType ?? self::contentTypeFor($name) ?? self::sniff($location, null) ?? 'application/octet-stream',
            $options,
        );
    }

    /**
     * Upload file bytes you already hold. Free. Same answer as `upload()`.
     *
     * @param string $bytes The file content.
     * @param string $filename For example `schriftsatz.pdf`.
     * @param string|null $contentType Defaults from the extension (pdf, docx, md, txt), then from the bytes.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     */
    public function uploadBytes(string $bytes, string $filename, ?string $contentType = null, ?RequestOptions $options = null): CitationCheckDocument
    {
        $type = $contentType ?? self::contentTypeFor($filename) ?? self::sniff(null, $bytes) ?? 'application/octet-stream';
        $response = $this->call(
            new Request('POST', '/citation-check-documents', multipart: new Multipart('file', $filename, $type, $bytes)),
            $options,
        );

        return CitationCheckDocument::fromArray($response->body, $response->meta);
    }

    /**
     * Read the receipt of an upload. Free.
     *
     * @param string $id The `id` of the receipt.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     */
    public function get(string $id, ?RequestOptions $options = null): CitationCheckDocument
    {
        $response = $this->call(new Request('GET', '/citation-check-documents/' . Path::segment($id)), $options);

        return CitationCheckDocument::fromArray($response->body, $response->meta);
    }

    /**
     * Poll `get()` until the upload is `ready`. The statuses `uploaded`, `extracting` and `ocr_pending` are not final.
     *
     * @param CitationCheckDocument|string $documentOrId The receipt `upload()` gave back, or its id.
     * @param float $pollInterval Seconds between polls.
     * @param float $timeout Seconds to wait in total.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for every poll.
     *
     * @throws DocumentFailedException When the upload ends in status `failed`; read `$e->document->error_code`.
     * @throws DocumentTimeoutException When the timeout passes first.
     */
    public function waitUntilReady(CitationCheckDocument|string $documentOrId, float $pollInterval = 2.0, float $timeout = 120.0, ?RequestOptions $options = null): CitationCheckDocument
    {
        if ($pollInterval <= 0.0) {
            throw ValidationException::client('`pollInterval` must be greater than zero.');
        }
        $id = $documentOrId instanceof CitationCheckDocument ? $documentOrId->id : $documentOrId;

        $deadline = $this->clock->now() + $timeout;
        while (true) {
            $document = $this->get($id, $options);
            if ($document->status === 'ready') {
                return $document;
            }
            if ($document->status === 'failed') {
                throw new DocumentFailedException($document);
            }

            $remaining = $deadline - $this->clock->now();
            if ($remaining <= 0.0) {
                throw new DocumentTimeoutException($document, $timeout);
            }
            $this->sleeper->sleep(min($pollInterval, $remaining));
        }
    }

    private static function contentTypeFor(string $filename): ?string
    {
        return self::CONTENT_TYPES[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? null;
    }

    private static function sniff(?string $path, ?string $bytes): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $type = $path !== null ? finfo_file($finfo, $path) : finfo_buffer($finfo, (string) $bytes);

        return is_string($type) && $type !== '' ? $type : null;
    }
}
