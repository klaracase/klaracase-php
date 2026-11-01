<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Klaracase\Response\CitationCheckDocument;

/**
 * `waitUntilReady()` saw the upload end in status `failed`. `errorCode` mirrors the receipt's `error_code`.
 */
final class DocumentFailedException extends KlaracaseException
{
    public function __construct(public readonly CitationCheckDocument $document)
    {
        $reason = $document->error_code ?? 'unknown';
        parent::__construct(
            sprintf('Document %s failed (%s).', $document->id, $reason),
            errorCode: $document->error_code,
        );
    }
}
