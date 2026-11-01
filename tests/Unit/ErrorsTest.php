<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Exception\ApiException;
use Klaracase\Exception\ConfigurationException;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\KlaracaseThrowable;
use Klaracase\Exception\NotFoundException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\ServiceUnavailableException;
use Klaracase\Exception\TransportException;
use Klaracase\Tests\Support\NetworkFailure;
use Klaracase\Tests\Support\UsesMockClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ErrorsTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    private function problem(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/conformance/problems/' . $name . '.json');
        self::assertIsString($contents);

        return $contents;
    }

    private function failure(int $status, string $body, ?string $header = null): KlaracaseException
    {
        $this->queueJson($status, $body, $header === null ? [] : ['Retry-After' => $header]);
        try {
            $this->factoryOverMock()->withMaxRetries(0)->make()->decisions->get('x');
        } catch (KlaracaseException $e) {
            return $e;
        }
        self::fail('expected an exception');
    }

    public function testAnAmbiguousAktenzeichenListsItsCandidates(): void
    {
        $error = $this->failure(422, $this->problem('ambiguous_aktenzeichen'));

        $this->assertNotNull($error->candidates);
        $this->assertCount(2, $error->candidates);
        $this->assertSame('OLG Hamm', $error->candidates[0]['court']);
        $this->assertFalse($error->isRetryable());
    }

    public function testASupersededStatuteNamesItsSuccessor(): void
    {
        $error = $this->failure(404, $this->problem('statute_superseded'));

        $this->assertInstanceOf(NotFoundException::class, $error);
        $this->assertSame('BauGB', $error->successor);
        $this->assertNull($error->candidates);
    }

    public function testAVolumeCapCarriesUnitCapAndResetAndIsNotRetryable(): void
    {
        $error = $this->failure(429, $this->problem('volume_cap'));

        $this->assertInstanceOf(RateLimitException::class, $error);
        $this->assertSame('rows', $error->unit);
        $this->assertSame(1000, $error->cap);
        $this->assertSame('2026-11-02T00:00:00+01:00', $error->resetsAt);
        $this->assertSame(41230, $error->retryAfterSeconds);
        $this->assertFalse($error->isRetryable(), 'a daily cap does not clear in a retry window');
    }

    public function testARateLimitIsRetryableAndKeepsTheHeadersAndTheWholeProblem(): void
    {
        $error = $this->failure(429, $this->problem('rate_limited'), '42');

        $this->assertTrue($error->isRetryable());
        $this->assertSame(['42'], $error->headers['retry-after']);
        $this->assertSame(['0.95.0'], $error->headers['x-klaracase-api-release']);
        $this->assertSame('rate_limited', $error->body['code']);
        $this->assertSame(42, $error->body['retry_after_seconds']);
        $this->assertArrayNotHasKey('authorization', $error->headers);
    }

    /**
     * @return array<string, array{int, string, bool}>
     */
    public static function retryability(): array
    {
        return [
            'service unavailable' => [503, 'backend_unavailable', true],
            'payment required' => [402, 'insufficient_credits', false],
            'not found' => [404, 'not_found', false],
            'server error' => [500, 'internal_error', false],
            'unauthenticated' => [401, 'unauthenticated', false],
        ];
    }

    #[DataProvider('retryability')]
    public function testIsRetryableFollowsTheStatus(int $status, string $code, bool $expected): void
    {
        $error = $this->failure($status, '{"type":"t","title":"T","status":' . $status . ',"detail":"d","code":"' . $code . '"}');

        $this->assertSame($expected, $error->isRetryable());
    }

    public function testToArrayIsASmallLoggableSummary(): void
    {
        $limited = $this->failure(429, $this->problem('rate_limited'));
        $credits = $this->failure(402, $this->problem('insufficient_credits'));

        $this->assertSame(
            ['code' => 'rate_limited', 'status' => 429, 'detail' => $limited->detail, 'retry_after_seconds' => 42],
            $limited->toArray(),
        );
        $this->assertInstanceOf(InsufficientCreditsException::class, $credits);
        $this->assertNull($credits->toArray()['retry_after_seconds']);
        $this->assertSame(['code', 'status', 'detail', 'retry_after_seconds'], array_keys($credits->toArray()));
    }

    public function testANonProblemBodyKeepsNoBodyButTheHeaders(): void
    {
        $error = $this->failure(502, '<html>Bad gateway</html>');

        $this->assertInstanceOf(ApiException::class, $error);
        $this->assertSame([], $error->body);
        $this->assertSame('unknown', $error->errorCode);
        $this->assertArrayHasKey('content-type', $error->headers);
    }

    public function testNetworkFailuresAreRetryableAndServiceUnavailableToo(): void
    {
        $this->mock->addException(new NetworkFailure($this->psr17->createRequest('POST', 'https://example.invalid/'), 'dropped'));
        try {
            $this->factoryOverMock()->make()->citationChecks->create(text: str_repeat('a', 60));
            $this->fail('expected an exception');
        } catch (TransportException $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertNull($e->status);
        }
        $unavailable = $this->failure(503, $this->problem('backend_unavailable'));
        $this->assertInstanceOf(ServiceUnavailableException::class, $unavailable);
        $this->assertTrue($unavailable->isRetryable());
    }

    public function testOneCatchCoversTheApiErrorsAndTheConfigurationError(): void
    {
        $this->assertTrue((new ReflectionClass(ConfigurationException::class))->implementsInterface(KlaracaseThrowable::class));
        $this->assertTrue((new ReflectionClass(KlaracaseException::class))->implementsInterface(KlaracaseThrowable::class));
        $caught = null;
        try {
            \Klaracase\Klaracase::client('   ');
        } catch (KlaracaseThrowable $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(ConfigurationException::class, $caught);
    }
}
