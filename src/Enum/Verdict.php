<?php

declare(strict_types=1);

namespace Klaracase\Enum;

/**
 * Verdict of one citation.
 */
enum Verdict: string
{
    case VerifiedCorpus = 'verified_corpus';
    case VerifiedExternal = 'verified_external';
    case NotFound = 'not_found';
    case OutOfCoverage = 'out_of_coverage';
    case Ambiguous = 'ambiguous';
    case Unverifiable = 'unverifiable';
    case Discrepancy = 'discrepancy';
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
