<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * A PSR-18 client refusing to send a request at all.
 */
final class RequestRefused extends RuntimeException implements RequestExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
