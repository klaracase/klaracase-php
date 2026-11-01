<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Why an upload failed.
 */
enum DocumentErrorCode: string
{
    case DocumentTooLong = 'document_too_long';
    case UnsupportedFormatPending = 'unsupported_format_pending';
    case CorruptedDocument = 'corrupted_document';
    case ZipBombRejected = 'zip_bomb_rejected';
    case EncryptedPdf = 'encrypted_pdf';
    case ScannedPdf = 'scanned_pdf';
    case PdfTextGarbled = 'pdf_text_garbled';
    case ExtractionFailed = 'extraction_failed';
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
