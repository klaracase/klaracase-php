<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;

require_once __DIR__ . '/SymfonyTimeoutStub.php';

/**
 * The inner exception of a Symfony HttpClient timeout.
 */
final class SymfonyLikeTimeout extends RuntimeException implements TimeoutExceptionInterface
{
}
