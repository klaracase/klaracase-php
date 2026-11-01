<?php

declare(strict_types=1);

namespace Klaracase\Exception;

/**
 * HTTP 409: for example `document_unavailable` or `token_org_missing`.
 */
class ConflictException extends KlaracaseException
{
}
