<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Exception\DocumentFailedException;
use Klaracase\Exception\DocumentTimeoutException;
use Klaracase\Exception\ValidationException;
use Klaracase\Http\Multipart;
use Klaracase\Http\Path;
use Klaracase\Http\Transport;
use Klaracase\Response\CitationCheckDocument;
use Klaracase\Support\Clock;
use Klaracase\Support\Sleeper;
use Psr\Http\Message\StreamInterface;

/**
 * Uploaded documents that wait for a citation check.
 */
final class CitationCheckDocuments extends AbstractResource
{
    public function __construct(Transport $transport, private readonly Sleeper $sleeper, private readonly Clock $clock)
    {
        parent::__construct($transport);
    }

    /**
     * Upload a PDF, DOCX, Markdown or plain text file (up to 25 MB). Free. The answer is a receipt with status
     * `uploaded`; poll with `get()` or call `waitUntilReady()`.
     *
     * @param mixed $file The file BYTES as a string, an open stream resource, or a PSR-7 `StreamInterface`. A string is never read as a path.
     * @param string $filename For example `schriftsatz.pdf`.
     * @param string $contentType For example `application/pdf`.
     */
    public function upload(mixed $file, string $filename, string $contentType): CitationCheckDocument
    {
        $contents = match (true) {
            is_string($file) => $file,
            $file instanceof StreamInterface => (string) $file,
            is_resource($file) => self::readResource($file),
            default => throw ValidationException::client('`file` must be the file bytes as a string, a stream resource or a PSR-7 stream.'),
        };

        [$body, $meta] = $this->transport->send(
            'POST',
            '/citation-check-documents',
            multipart: new Multipart('file', $filename, $contentType, $contents),
        );

        return CitationCheckDocument::fromArray($body, $meta);
    }

    /**
     * Read the receipt of an upload. Free.
     */
    public function get(string $id): CitationCheckDocument
    {
        [$body, $meta] = $this->transport->send('GET', '/citation-check-documents/' . Path::segment($id));

        return CitationCheckDocument::fromArray($body, $meta);
    }

    /**
     * Poll `get()` until the upload is `ready`. The statuses `uploaded`, `extracting` and `ocr_pending` are not final.
     *
     * @param float $pollInterval Seconds between polls.
     * @param float $timeout Seconds to wait in total.
     *
     * @throws DocumentFailedException When the upload ends in status `failed`; read `$document->error_code`.
     * @throws DocumentTimeoutException When the timeout passes first.
     */
    public function waitUntilReady(string $id, float $pollInterval = 2.0, float $timeout = 120.0): CitationCheckDocument
    {
        if ($pollInterval <= 0.0) {
            throw ValidationException::client('`pollInterval` must be greater than zero.');
        }

        $deadline = $this->clock->now() + $timeout;
        while (true) {
            $document = $this->get($id);
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

    /**
     * @param resource $stream
     */
    private static function readResource($stream): string
    {
        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw ValidationException::client('The stream resource given as `file` could not be read.');
        }

        return $contents;
    }
}
