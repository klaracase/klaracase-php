<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Status of an uploaded document.
 */
enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case OcrPending = 'ocr_pending';
    case Ready = 'ready';
    case Failed = 'failed';
    /** A value this SDK version does not know. Never sent to the server. */
    case Unknown = '__unknown__';

    /**
     * Null when the server sent no value; Unknown for a value this SDK version does not know.
     */
    public static function fromWire(?string $value): ?self
    {
        return $value === null ? null : (self::tryFrom($value) ?? self::Unknown);
    }
}
