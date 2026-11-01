<?php

declare(strict_types=1);

namespace Klaracase\Exception;

/**
 * Any other error status (400, 405, 413, 415, 500, 502 and so on), or an answer that is not a problem body.
 * For a body that is not a problem, `errorCode` is `unknown` and `detail` holds up to 1,000 characters of it.
 */
class ApiException extends KlaracaseException
{
}
