<?php

declare(strict_types=1);

namespace Klaracase\Tests\Support;

use Http\Mock\Client as MockClient;
use Klaracase\Factory;
use Klaracase\Klaracase;
use Klaracase\Testing\FakeClock;
use Klaracase\Testing\RecordingSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A Klaracase client over an HTTP mock, a test clock and a sleeper that does not wait.
 *
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait UsesMockClient
{
    private Psr17Factory $psr17;

    private MockClient $mock;

    private FakeClock $clock;

    private RecordingSleeper $sleeper;

    protected function setUpMockClient(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->mock = new MockClient($this->psr17);
        $this->clock = new FakeClock();
        $this->sleeper = new RecordingSleeper($this->clock);
        putenv(Klaracase::TOKEN_ENV_VAR);
        Klaracase::resetDeprecationWarnings();
    }

    protected function factoryOverMock(): Factory
    {
        return Klaracase::factory()
            ->withToken('klc_test_token')
            ->withBaseUrl('https://example.invalid/api/v1')
            ->withHttpClient($this->mock)
            ->withRequestFactory($this->psr17)
            ->withStreamFactory($this->psr17)
            ->withSleeper($this->sleeper)
            ->withClock($this->clock);
    }

    protected function mockedClient(): Klaracase
    {
        return $this->factoryOverMock()->make();
    }

    /**
     * @param array<string, string> $headers
     */
    protected function queueJson(int $status, string $body, array $headers = []): void
    {
        $response = $this->psr17->createResponse($status)
            ->withHeader('Content-Type', $status >= 400 ? 'application/problem+json' : 'application/json')
            ->withHeader('X-Klaracase-Api-Release', '0.95.0');
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        $this->mock->addResponse($response->withBody($this->psr17->createStream($body)));
    }

    protected function queueExample(string $name, int $status = 200): void
    {
        $this->queueJson($status, $this->exampleBody($name));
    }

    protected function exampleBody(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/examples/' . $name . '.json');
        self::assertIsString($contents);

        return $contents;
    }

    protected function lastRequest(): RequestInterface
    {
        $request = $this->mock->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    protected function respond(ResponseInterface $response): void
    {
        $this->mock->addResponse($response);
    }
}
