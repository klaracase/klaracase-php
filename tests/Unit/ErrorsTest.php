<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Enum\InsufficientCreditsReason;
use Klaracase\Exception\ApiException;
use Klaracase\Exception\ConfigurationException;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\KlaracaseThrowable;
use Klaracase\Exception\NotFoundException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\ServiceUnavailableException;
use Klaracase\Exception\TokenLimitReachedException;
use Klaracase\Exception\TransportException;
use Klaracase\Http\ErrorFactory;
use Klaracase\Testing\Fixtures;
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

    public function testAnEmptyApiBudgetNamesTheReasonAndTheTopUpPageForAHumanAdmin(): void
    {
        $error = $this->failure(402, $this->problem('insufficient_credits.api_budget_empty'));

        $this->assertInstanceOf(InsufficientCreditsException::class, $error);
        $this->assertSame('insufficient_credits', $error->errorCode);
        $this->assertSame(1, $error->cost);
        $this->assertSame(0, $error->balance);
        $this->assertSame('api_budget_empty', $error->reason);
        $this->assertSame(InsufficientCreditsReason::ApiBudgetEmpty, $error->reasonEnum());
        $this->assertSame('https://klaracase.de/org/billing/api-budget', $error->topUpUrl);
        $this->assertSame('api_budget_empty', $error->extra['reason'], 'the raw members stay in extra');
        $this->assertFalse($error->isRetryable());
    }

    public function testAnApiBudgetThatIsNotAvailableHasNoTopUpPage(): void
    {
        $error = $this->failure(402, $this->problem('insufficient_credits.api_budget_not_available'));

        $this->assertInstanceOf(InsufficientCreditsException::class, $error);
        $this->assertSame(InsufficientCreditsReason::ApiBudgetNotAvailable, $error->reasonEnum());
        $this->assertNull($error->topUpUrl);
    }

    public function testAnInsufficientCreditsProblemFromBeforeTheBudgetHasNeitherReasonNorTopUpPage(): void
    {
        $error = $this->failure(402, $this->problem('insufficient_credits'));

        $this->assertInstanceOf(InsufficientCreditsException::class, $error);
        $this->assertNull($error->reason);
        $this->assertNull($error->reasonEnum());
        $this->assertNull($error->topUpUrl);
        $this->assertNotNull($error->cost);
    }

    public function testAReasonThisVersionDoesNotKnowIsKeptAsWrittenAndReadsAsUnknown(): void
    {
        $error = $this->failure(402, (string) json_encode(Fixtures::problem(402, 'insufficient_credits', ['cost' => 2, 'balance' => 1, 'reason' => 'something_new'])));

        $this->assertInstanceOf(InsufficientCreditsException::class, $error);
        $this->assertSame('something_new', $error->reason);
        $this->assertSame(InsufficientCreditsReason::Unknown, $error->reasonEnum());
        $this->assertSame(InsufficientCreditsReason::PlanCreditsExhausted, InsufficientCreditsReason::fromWire('plan_credits_exhausted'));
    }

    public function testAKeyLimitIsAnInsufficientCreditsExceptionWithoutCostAndBalance(): void
    {
        $error = $this->failure(402, $this->problem('token_limit_reached'));

        $this->assertInstanceOf(TokenLimitReachedException::class, $error);
        $this->assertContains(InsufficientCreditsException::class, class_parents($error), 'a catch for the 402 still sees it');
        $this->assertSame('token_limit_reached', $error->errorCode);
        $this->assertSame(402, $error->status);
        $this->assertNull($error->cost);
        $this->assertNull($error->balance);
        $this->assertNull($error->reason);
        $this->assertNull($error->topUpUrl);
        $this->assertSame('credits', $error->unit);
        $this->assertSame(500, $error->cap);
        $this->assertSame(500, $error->spent);
        $this->assertSame('2026-12-01T00:00:00+00:00', $error->resetsAt);
        $this->assertSame('2026-12-01T00:00:00+00:00', $error->resetsAtDateTime?->format('c'));
        $this->assertSame('https://klaracase.de/org/api-keys', $error->settingsUrl);
        $this->assertFalse($error->isRetryable(), 'waiting a few seconds cannot lift a key limit');
    }

    public function testAKeyLimitToleratesMissingAndOddMembers(): void
    {
        $bare = ErrorFactory::fromProblem(402, Fixtures::problem(402, 'token_limit_reached'));
        $odd = ErrorFactory::fromProblem(402, Fixtures::problem(402, 'token_limit_reached', ['cap' => '500', 'spent' => 12.5, 'resets_at' => 'soon', 'settings_url' => 7]));

        foreach ([$bare, $odd] as $error) {
            $this->assertInstanceOf(TokenLimitReachedException::class, $error);
            $this->assertNull($error->unit);
            $this->assertNull($error->cap);
            $this->assertNull($error->spent);
            $this->assertNull($error->resetsAtDateTime);
            $this->assertNull($error->settingsUrl);
            $this->assertNull($error->cost);
        }
        $this->assertSame('soon', $odd->resetsAt);
        $this->assertSame('500', $odd->extra['cap'], 'the raw value stays in extra');
    }

    public function testOnlyTheKeyLimitCodeMakesATokenLimitException(): void
    {
        $plain = $this->failure(402, $this->problem('insufficient_credits'));

        $this->assertNotInstanceOf(TokenLimitReachedException::class, $plain);
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
