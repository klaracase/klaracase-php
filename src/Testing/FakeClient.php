<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use AssertionError;
use Klaracase\Client;
use Klaracase\Response\Model;
use Klaracase\Support\Clock;
use Klaracase\Support\ProvidesResources;
use Klaracase\Support\ResourceSet;
use Klaracase\Support\Sleeper;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * A `Client` that answers from a queue instead of the network. Use it in your own tests.
 *
 * ```php
 * $client = new FakeClient();
 * $client->queue(Fixtures::decisionSearch());
 * $client->queue(Fixtures::problem(429, 'rate_limited', ['retry_after_seconds' => 3]));
 *
 * $hits = $client->decisions->search('Eigenbedarf', court: 'BGH');
 *
 * $client->assertSent(fn (RecordedRequest $r) => $r->path === '/decisions/search' && $r->query['court'] === 'BGH');
 * ```
 *
 * Answers are served in the order they were queued. Retries do not happen: an error answer is thrown at once.
 * The sleeper and the clock are test doubles that do not wait, so `waitUntilReady()` runs in no time.
 */
final class FakeClient implements Client
{
    use ProvidesResources;

    public readonly Sleeper $sleeper;

    public readonly Clock $clock;

    private readonly FakeTransport $fakeTransport;

    public function __construct(?Sleeper $sleeper = null, ?Clock $clock = null)
    {
        $testClock = $clock ?? new FakeClock();
        $this->clock = $testClock;
        $this->sleeper = $sleeper ?? new RecordingSleeper($testClock instanceof FakeClock ? $testClock : null);
        $this->fakeTransport = new FakeTransport();
        $resources = new ResourceSet($this->fakeTransport, $this->sleeper, $this->clock);
        $this->apiTransport = $resources->transport;
        $this->decisions = $resources->decisions;
        $this->norms = $resources->norms;
        $this->datapackages = $resources->datapackages;
        $this->citationChecks = $resources->citationChecks;
        $this->citationCheckDocuments = $resources->citationCheckDocuments;
        $this->corpus = $resources->corpus;
    }

    /**
     * Queue the next answer.
     *
     * @param Model|array<array-key, mixed>|Throwable $answer A response object such as `Fixtures::decisionSearch()`,
     *        a problem array such as `Fixtures::problem(402, 'insufficient_credits')`, a plain body array or an exception.
     * @param int $status HTTP status of a success answer. Use 202 for an upload receipt.
     */
    public function queue(Model|array|Throwable $answer, int $status = 200): self
    {
        $this->fakeTransport->queue($answer, $status);

        return $this;
    }

    /**
     * Every call received so far, oldest first.
     *
     * @return list<RecordedRequest>
     */
    public function requests(): array
    {
        return $this->fakeTransport->requests();
    }

    /**
     * Passes when at least one recorded call satisfies the callback.
     *
     * @param callable(RecordedRequest): bool $matcher
     */
    public function assertSent(callable $matcher, string $message = 'No recorded request matched.'): void
    {
        $matched = false;
        foreach ($this->requests() as $request) {
            if ($matcher($request)) {
                $matched = true;

                break;
            }
        }
        self::check($matched, $message . ' Recorded: ' . self::describe($this->requests()));
    }

    public function assertSentCount(int $expected): void
    {
        $actual = count($this->requests());
        self::check($actual === $expected, sprintf('Expected %d request(s), %d sent. Recorded: %s', $expected, $actual, self::describe($this->requests())));
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    private static function check(bool $condition, string $message): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue($condition, $message);

            return;
        }
        if (! $condition) {
            throw new AssertionError($message);
        }
    }

    /**
     * @param list<RecordedRequest> $requests
     */
    private static function describe(array $requests): string
    {
        return $requests === [] ? 'none' : implode(', ', array_map(static fn (RecordedRequest $r): string => $r->method . ' ' . $r->path, $requests));
    }
}
