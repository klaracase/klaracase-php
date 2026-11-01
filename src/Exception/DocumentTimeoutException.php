<?php

declare(strict_types=1);

namespace Klaracase\Exception;

use Klaracase\Response\CitationCheckDocument;

/**
 * `waitUntilReady()` ran out of time. `$document` is the last receipt.
 */
final class DocumentTimeoutException extends KlaracaseException
{
    public function __construct(public readonly CitationCheckDocument $document, float $timeout)
    {
        parent::__construct(
            sprintf('Document %s was not ready after %s seconds (last status: %s).', $document->id, rtrim(rtrim(sprintf('%.3F', $timeout), '0'), '.'), $document->status ?? 'unknown'),
            errorCode: 'wait_timeout',
        );
    }
}
