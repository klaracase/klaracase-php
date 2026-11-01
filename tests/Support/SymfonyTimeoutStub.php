<?php

declare(strict_types=1);

namespace Symfony\Contracts\HttpClient\Exception;

use Throwable;

if (! interface_exists(TimeoutExceptionInterface::class)) {
    /**
     * Stands in for the Symfony interface, so the test runs without symfony/http-client. The SDK matches it by name.
     */
    interface TimeoutExceptionInterface extends Throwable
    {
    }
}
