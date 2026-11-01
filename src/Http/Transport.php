<?php

declare(strict_types=1);

namespace Klaracase\Http;

use Klaracase\Exception\KlaracaseException;
use Klaracase\RequestOptions;

/**
 * Sends one API call and returns the decoded answer. `HttpTransport` talks HTTP; the test double in
 * `Klaracase\Testing` serves queued answers.
 *
 * @internal
 */
interface Transport
{
    /**
     * @throws KlaracaseException On any error status, on a network failure or on an unreadable success body.
     */
    public function send(Request $request, RequestOptions $options): Response;
}
