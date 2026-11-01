<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\TimeoutException;
use Klaracase\Exception\TransportException;
use Klaracase\RequestOptions;
use Klaracase\Tests\Support\NetworkFailure;
use Klaracase\Tests\Support\RequestRefused;
use Klaracase\Tests\Support\SymfonyLikeTimeout;
use Klaracase\Tests\Support\UsesMockClient;
use Nyholm\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class TransportTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    /**
     * @return array<string, array{callable(RequestInterface): (Exception&ClientExceptionInterface), bool}>
     */
    public static function failures(): array
    {
        $request = new Psr7Request('GET', 'https://example.invalid/');

        return [
            'symfony timeout further down the chain' => [static fn (): Exception&ClientExceptionInterface => new NetworkFailure($request, 'Transport failed', new SymfonyLikeTimeout('idle')), true],
            'curl errno 28 in the guzzle handler context' => [static fn (): Exception&ClientExceptionInterface => new ConnectException('Something odd', $request, null, ['errno' => 28]), true],
            'curl error 28 in a previous message' => [static fn (): Exception&ClientExceptionInterface => new NetworkFailure($request, 'wrapped', new RuntimeException('cURL error 28: reading')), true],
            'message fallback' => [static fn (): Exception&ClientExceptionInterface => new NetworkFailure($request, 'Connection timed out after 30 seconds'), true],
            'connection refused' => [static fn (): Exception&ClientExceptionInterface => new NetworkFailure($request, 'cURL error 7: Failed to connect'), false],
            'a refused request is never a timeout' => [static fn (): Exception&ClientExceptionInterface => new RequestRefused($request, 'timeout header is invalid'), false],
        ];
    }

    /**
     * @param callable(RequestInterface): (Exception&ClientExceptionInterface) $make
     */
    #[DataProvider('failures')]
    public function testTimeoutsAreToldApartFromOtherNetworkFailures(callable $make, bool $isTimeout): void
    {
        $this->mock->addException($make($this->psr17->createRequest('GET', 'https://example.invalid/')));

        try {
            $this->factoryOverMock()->withMaxRetries(0)->make()->corpus->coverage();
            $this->fail('expected an exception');
        } catch (TransportException $e) {
            $this->assertSame($isTimeout, $e instanceof TimeoutException, $e::class . ': ' . $e->getMessage());
            $this->assertTrue($e->isRetryable());
        }
    }

    public function testAPerCallTimeoutReachesAGuzzleClient(): void
    {
        $seen = [];
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->exampleBody('corpus.coverage')),
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->exampleBody('corpus.coverage')),
        ]));
        $stack->push(static function (callable $handler) use (&$seen): callable {
            return static function (RequestInterface $request, array $options) use ($handler, &$seen) {
                $seen[] = $options;

                return $handler($request, $options);
            };
        });
        $client = $this->factoryOverMock()->withHttpClient(new GuzzleClient(['handler' => $stack]))->make();

        $client->corpus->coverage(options: RequestOptions::with(timeout: 4.5));
        $client->corpus->coverage();

        $this->assertCount(2, $seen);
        $first = $seen[0];
        $this->assertSame(4.5, $first['timeout']);
        $this->assertSame(4.5, $first['connect_timeout']);
        $this->assertFalse($first['http_errors'], 'an error status is read by the SDK, not thrown by Guzzle');
        $this->assertFalse($first['allow_redirects']);
        $this->assertArrayNotHasKey('timeout', $seen[1], 'without a per-call timeout the client keeps its own');
    }

    public function testAPerCallTimeoutIsIgnoredByAClientWithoutAHook(): void
    {
        $this->queueExample('corpus.coverage');

        $this->mockedClient()->corpus->coverage(options: RequestOptions::with(timeout: 1.0));

        $this->assertCount(1, $this->mock->getRequests());
    }

    public function testMiddlewareWrapsEveryAttemptFirstEntryOutermost(): void
    {
        $this->queueJson(503, '{"type":"t","title":"T","status":503,"detail":"d","code":"backend_unavailable"}');
        $this->queueExample('corpus.coverage');
        $trace = [];
        $client = $this->factoryOverMock()
            ->withMiddleware(static function (RequestInterface $request, callable $next) use (&$trace): ResponseInterface {
                $trace[] = 'outer in';
                $response = $next($request->withHeader('X-Outer', '1'));
                $trace[] = 'outer out ' . $response->getStatusCode();

                return $response;
            })
            ->withMiddleware(static function (RequestInterface $request, callable $next) use (&$trace): ResponseInterface {
                $trace[] = 'inner in ' . $request->getHeaderLine('X-Outer');

                return $next($request);
            })
            ->make();

        $client->corpus->coverage();

        $this->assertSame(['outer in', 'inner in 1', 'outer out 503', 'outer in', 'inner in 1', 'outer out 200'], $trace);
    }

    public function testTheMetaCarriesHeadersAttemptsAndTheTimeWaited(): void
    {
        $this->queueJson(429, '{"type":"t","title":"T","status":429,"detail":"d","code":"rate_limited","retry_after_seconds":3}');
        $this->queueJson(200, $this->exampleBody('corpus.coverage'), ['X-Request-Id' => 'r-1', 'Set-Cookie' => 'a=b']);

        $coverage = $this->mockedClient()->corpus->coverage();

        $this->assertSame(2, $coverage->meta->attempts);
        $this->assertSame(3.0, $coverage->meta->retriedFor);
        $this->assertSame('0.95.0', $coverage->meta->apiRelease);
        $this->assertSame(['r-1'], $coverage->meta->headers['x-request-id']);
        $this->assertSame('r-1', $coverage->meta->header('X-REQUEST-ID'));
        $this->assertArrayNotHasKey('authorization', $coverage->meta->headers);
    }

    public function testAnUntouchedCallHasOneAttemptAndNoWait(): void
    {
        $this->queueExample('corpus.coverage');

        $meta = $this->mockedClient()->corpus->coverage()->meta;

        $this->assertSame(1, $meta->attempts);
        $this->assertSame(0.0, $meta->retriedFor);
    }

    public function testTheLoggerSeesRetriesAndDeprecationsButNeverTheToken(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{string, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [is_string($level) ? $level : '', (string) $message, $context];
            }
        };
        $this->queueJson(429, '{"type":"t","title":"T","status":429,"detail":"d","code":"rate_limited","retry_after_seconds":2}');
        $this->respond(
            $this->psr17->createResponse(200)
                ->withHeader('Sunset', 'Sat, 01 Aug 2026 00:00:00 GMT')
                ->withBody($this->psr17->createStream($this->exampleBody('corpus.coverage'))),
        );
        set_error_handler(static fn (): bool => true);
        try {
            $this->factoryOverMock()->withLogger($logger)->make()->corpus->coverage();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(2, $logger->records);
        [$level, , $context] = $logger->records[0];
        $this->assertSame('debug', $level);
        $this->assertSame(['method' => 'GET', 'path' => '/corpus/coverage', 'status' => 429, 'attempt' => 1, 'wait' => 2.0], $context);
        $this->assertSame('warning', $logger->records[1][0]);
        $this->assertStringContainsString('Sunset', $logger->records[1][1]);
        $this->assertStringNotContainsString('klc_test_token', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    public function testARetriedNetworkFailureIsLoggedWithoutAStatus(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array<array-key, mixed>> */
            public array $contexts = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->contexts[] = $context;
            }
        };
        $this->mock->addException(new NetworkFailure($this->psr17->createRequest('GET', 'https://example.invalid/'), 'cURL error 52'));
        $this->queueExample('corpus.coverage');

        $this->factoryOverMock()->withLogger($logger)->make()->corpus->coverage();

        $this->assertNull($logger->contexts[0]['status']);
        $this->assertSame(1, $logger->contexts[0]['attempt']);
    }

    public function testASleepAboveThirtySecondsIsRaisedWithoutALogLine(): void
    {
        $this->queueJson(429, '{"type":"t","title":"T","status":429,"detail":"d","code":"rate_limited","retry_after_seconds":31}');

        try {
            $this->mockedClient()->corpus->coverage();
            $this->fail('expected an exception');
        } catch (RateLimitException $e) {
            $this->assertSame([], $this->sleeper->sleeps);
            $this->assertSame(31, $e->retryAfterSeconds);
        }
    }
}
