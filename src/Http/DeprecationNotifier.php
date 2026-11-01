<?php

declare(strict_types=1);

namespace Klaracase\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Calls the deprecation hook once per process, when an answer carries a `Deprecation` or `Sunset` header.
 * The default hook raises an E_USER_DEPRECATED notice. Tests call `reset()` between cases.
 *
 * @internal
 */
final class DeprecationNotifier
{
    private static bool $notified = false;

    /** @var Closure(DeprecationNotice): void */
    private readonly Closure $hook;

    /**
     * @param (callable(DeprecationNotice): void)|null $hook
     */
    public function __construct(?callable $hook = null, private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->hook = $hook === null
            ? static function (DeprecationNotice $notice): void {
                trigger_error($notice->message(), E_USER_DEPRECATED);
            }
        : Closure::fromCallable($hook);
    }

    /**
     * Forget that the hook ran, so the next deprecation fires it again.
     */
    public static function reset(): void
    {
        self::$notified = false;
    }

    public function inspect(ResponseInterface $response, string $method, string $path): void
    {
        $deprecation = self::header($response, 'Deprecation');
        $sunset = self::header($response, 'Sunset');
        if ($deprecation === null && $sunset === null) {
            return;
        }
        if (self::$notified) {
            return;
        }

        self::$notified = true;
        $notice = new DeprecationNotice($deprecation, $sunset, self::header($response, 'Link'), $method, $path);
        $this->logger->warning($notice->message(), ['method' => $method, 'path' => $path, 'deprecation' => $deprecation, 'sunset' => $sunset]);
        ($this->hook)($notice);
    }

    private static function header(ResponseInterface $response, string $name): ?string
    {
        $line = $response->getHeaderLine($name);

        return $line === '' ? null : $line;
    }
}
