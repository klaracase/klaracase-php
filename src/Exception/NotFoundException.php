<?php

declare(strict_types=1);

namespace Klaracase\Exception;

/**
 * HTTP 404. Read `$errorCode` for the reason: `not_found`, `statute_superseded` (see `$extra['successor']`), and so on.
 */
class NotFoundException extends KlaracaseException
{
}
